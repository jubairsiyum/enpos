<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ContactDocument;
use App\Models\ContactPayment;
use App\Support\CurrentBusiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ContactActionController extends Controller
{
    public function __construct(private CurrentBusiness $currentBusiness) {}

    /**
     * Record a due or advance payment against a contact (Pay action).
     */
    public function pay(Request $request, Contact $contact): JsonResponse|RedirectResponse
    {
        $this->authorizeContact($contact);

        $validated = $request->validate([
            'amount'  => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
            'method'  => ['required', 'string', Rule::in(['cash', 'card', 'bank_transfer', 'cheque', 'other'])],
            'paid_on' => ['required', 'date_format:Y-m-d'],
            'note'    => ['nullable', 'string', 'max:1000'],
        ]);

        $businessId = $this->currentBusiness->id();
        $userId = $request->user()?->id;
        $amount = round((float) $validated['amount'], 2);

        $payment = DB::transaction(function () use ($contact, $businessId, $userId, $amount, $validated): ContactPayment {
            $locked = Contact::whereKey($contact->id)->lockForUpdate()->firstOrFail();

            $isSupplierOnly = $locked->type === 'supplier';
            $dueAmount = $isSupplierOnly
                ? max(0.0, (float) $locked->total_purchase - (float) $locked->total_purchase_paid)
                : max(0.0, (float) $locked->total_sell - (float) $locked->total_sell_paid);

            $dueApplied = round(min($amount, $dueAmount), 2);
            $advanceAdded = round(max(0.0, $amount - $dueApplied), 2);

            if ($isSupplierOnly) {
                $locked->total_purchase_paid = round((float) $locked->total_purchase_paid + $dueApplied, 2);
            } else {
                $locked->total_sell_paid = round((float) $locked->total_sell_paid + $dueApplied, 2);
            }

            if ($advanceAdded > 0) {
                $locked->balance = round((float) $locked->balance + $advanceAdded, 2);
            }

            $locked->save();

            $sequence = ContactPayment::withoutGlobalScope('business')
                ->where('business_id', $businessId)
                ->count() + 1;

            return ContactPayment::create([
                'business_id'    => $businessId,
                'contact_id'     => $locked->id,
                'created_by'     => $userId,
                'payment_ref_no' => 'PAY' . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
                'amount'         => $amount,
                'due_applied'    => $dueApplied,
                'advance_added'  => $advanceAdded,
                'method'         => $validated['method'],
                'paid_on'        => $validated['paid_on'],
                'note'           => $validated['note'] ?? null,
            ]);
        });

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Payment recorded successfully.',
                'data'    => $payment,
            ], 201);
        }

        return redirect()->back()->with('success', 'Payment recorded successfully.');
    }

    /**
     * Toggle a contact's active/inactive status (Deactivate / Activate action).
     */
    public function toggleStatus(Request $request, Contact $contact): JsonResponse|RedirectResponse
    {
        $this->authorizeContact($contact);

        $current = $contact->contact_status ?: 'active';
        $next = $current === 'active' ? 'inactive' : 'active';

        $contact->update(['contact_status' => $next]);

        $message = $next === 'inactive'
            ? 'Contact deactivated successfully.'
            : 'Contact activated successfully.';

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'data'    => [
                    'id'             => $contact->id,
                    'contact_status' => $next,
                ],
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Return chronological ledger entries & financial summary for a contact.
     */
    public function ledger(Request $request, Contact $contact): JsonResponse
    {
        $this->authorizeContact($contact);

        $businessId = $this->currentBusiness->id();
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $transactions = DB::table('transactions')
            ->where('business_id', $businessId)
            ->where('contact_id', $contact->id)
            ->whereNull('deleted_at')
            ->when($startDate, fn ($q) => $q->whereDate('transaction_date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->whereDate('transaction_date', '<=', $endDate))
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get(['id', 'type', 'invoice_no', 'transaction_date', 'total_amount', 'payment_status', 'additional_notes']);

        $payments = ContactPayment::query()
            ->where('business_id', $businessId)
            ->where('contact_id', $contact->id)
            ->when($startDate, fn ($q) => $q->whereDate('paid_on', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->whereDate('paid_on', '<=', $endDate))
            ->orderBy('paid_on')
            ->orderBy('id')
            ->get();

        $entries = [];
        $openingBalance = round((float) ($contact->opening_balance ?? 0), 2);
        if ($openingBalance > 0) {
            $entries[] = [
                'date'           => $contact->created_at?->format('Y-m-d') ?? now()->format('Y-m-d'),
                'reference_no'   => '-',
                'type'           => 'Opening Balance',
                'debit'          => $openingBalance,
                'credit'         => 0.0,
                'payment_method' => '-',
                'note'           => 'Opening balance',
                'sort_ts'        => ($contact->created_at?->timestamp ?? 0) - 1,
            ];
        }

        foreach ($transactions as $tx) {
            $isCredit = in_array($tx->type, ['sell_return', 'purchase_return'], true);
            $amount = round((float) $tx->total_amount, 2);

            $entries[] = [
                'date'           => substr((string) $tx->transaction_date, 0, 10),
                'reference_no'   => $tx->invoice_no ?: ('TX#' . $tx->id),
                'type'           => ucwords(str_replace('_', ' ', (string) $tx->type)),
                'debit'          => $isCredit ? 0.0 : $amount,
                'credit'         => $isCredit ? $amount : 0.0,
                'payment_method' => ucfirst((string) ($tx->payment_status ?? '-')),
                'note'           => $tx->additional_notes ?? '',
                'sort_ts'        => strtotime((string) $tx->transaction_date) ?: 0,
            ];
        }

        foreach ($payments as $pay) {
            $entries[] = [
                'date'           => $pay->paid_on?->format('Y-m-d') ?? '',
                'reference_no'   => $pay->payment_ref_no ?: ('PAY#' . $pay->id),
                'type'           => 'Payment',
                'debit'          => 0.0,
                'credit'         => round((float) $pay->amount, 2),
                'payment_method' => ucwords(str_replace('_', ' ', (string) $pay->method)),
                'note'           => $pay->note ?? '',
                'sort_ts'        => ($pay->paid_on?->timestamp ?? 0) + 1,
            ];
        }

        usort($entries, fn (array $a, array $b): int => $a['sort_ts'] <=> $b['sort_ts']);

        $running = 0.0;
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        foreach ($entries as &$entry) {
            $totalDebit += $entry['debit'];
            $totalCredit += $entry['credit'];
            $running = round($running + $entry['debit'] - $entry['credit'], 2);
            $entry['balance'] = $running;
            unset($entry['sort_ts']);
        }
        unset($entry);

        $isSupplierOnly = $contact->type === 'supplier';
        $totalInvoice = $isSupplierOnly ? (float) $contact->total_purchase : (float) $contact->total_sell;
        $totalPaid = $isSupplierOnly ? (float) $contact->total_purchase_paid : (float) $contact->total_sell_paid;
        $balanceDue = round(max(0.0, $totalInvoice - $totalPaid), 2);

        return response()->json([
            'data' => [
                'summary' => [
                    'opening_balance' => $openingBalance,
                    'total_invoice'   => round($totalInvoice, 2),
                    'total_paid'      => round($totalPaid + (float) $payments->sum('advance_added'), 2),
                    'balance_due'     => $balanceDue,
                    'advance_balance' => round((float) ($contact->balance ?? 0), 2),
                    'net_ledger'      => round($totalDebit - $totalCredit, 2),
                ],
                'entries' => $entries,
            ],
        ]);
    }

    /**
     * List sales / purchase transactions for a contact (Sales action).
     */
    public function sales(Request $request, Contact $contact): JsonResponse
    {
        $this->authorizeContact($contact);

        $businessId = $this->currentBusiness->id();
        $defaultTypes = $contact->type === 'supplier'
            ? ['purchase', 'purchase_return']
            : ['sell', 'sell_return'];

        $transactions = DB::table('transactions')
            ->where('business_id', $businessId)
            ->where('contact_id', $contact->id)
            ->whereIn('type', $defaultTypes)
            ->whereNull('deleted_at')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'type', 'status', 'payment_status', 'invoice_no', 'transaction_date', 'total_amount', 'additional_notes']);

        $txIds = $transactions->pluck('id')->all();
        $paidByTx = empty($txIds)
            ? []
            : DB::table('transaction_payments')
                ->whereIn('transaction_id', $txIds)
                ->selectRaw('transaction_id, COALESCE(SUM(amount), 0) as paid')
                ->groupBy('transaction_id')
                ->pluck('paid', 'transaction_id')
                ->all();

        $rows = $transactions->map(function (object $tx) use ($paidByTx): array {
            $total = round((float) $tx->total_amount, 2);
            $paid = round((float) ($paidByTx[$tx->id] ?? 0), 2);

            return [
                'id'               => $tx->id,
                'date'             => substr((string) $tx->transaction_date, 0, 10),
                'invoice_no'       => $tx->invoice_no ?: ('TX#' . $tx->id),
                'type'             => ucwords(str_replace('_', ' ', (string) $tx->type)),
                'status'           => ucfirst((string) $tx->status),
                'payment_status'   => ucfirst((string) $tx->payment_status),
                'total'            => $total,
                'paid'             => $paid,
                'due'              => round(max(0.0, $total - $paid), 2),
                'additional_notes' => $tx->additional_notes,
            ];
        })->values()->all();

        return response()->json([
            'data' => $rows,
        ]);
    }

    /**
     * List Documents & Notes attached to a contact.
     */
    public function documents(Contact $contact): JsonResponse
    {
        $this->authorizeContact($contact);

        $docs = ContactDocument::query()
            ->where('business_id', $this->currentBusiness->id())
            ->where('contact_id', $contact->id)
            ->with('creator:id,name')
            ->latest('id')
            ->get()
            ->map(fn (ContactDocument $doc): array => [
                'id'            => $doc->id,
                'heading'       => $doc->heading,
                'description'   => $doc->description,
                'document_name' => $doc->document_name,
                'has_file'      => ! empty($doc->document_path),
                'is_private'    => (bool) $doc->is_private,
                'created_by'    => $doc->creator?->name,
                'created_at'    => $doc->created_at?->format('Y-m-d H:i'),
            ]);

        return response()->json(['data' => $docs]);
    }

    /**
     * Store a new Document & Note for a contact.
     */
    public function storeDocument(Request $request, Contact $contact): JsonResponse|RedirectResponse
    {
        $this->authorizeContact($contact);

        $validated = $request->validate([
            'heading'     => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_private'  => ['nullable', 'boolean'],
            'file'        => ['nullable', 'file', 'max:10240'],
        ]);

        $businessId = $this->currentBusiness->id();
        $documentPath = null;
        $documentName = null;

        if ($request->hasFile('file')) {
            $uploaded = $request->file('file');
            $documentName = $uploaded->getClientOriginalName();
            $documentPath = $uploaded->store("contact-documents/{$businessId}/{$contact->id}", 'local');
        }

        $doc = ContactDocument::create([
            'business_id'   => $businessId,
            'contact_id'    => $contact->id,
            'created_by'    => $request->user()?->id,
            'heading'       => $validated['heading'],
            'description'   => $validated['description'] ?? null,
            'document_path' => $documentPath,
            'document_name' => $documentName,
            'is_private'    => (bool) ($validated['is_private'] ?? false),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Document & note added successfully.',
                'data'    => $doc,
            ], 201);
        }

        return redirect()->back()->with('success', 'Document & note added successfully.');
    }

    /**
     * Download an attached document for a contact.
     */
    public function downloadDocument(Contact $contact, ContactDocument $document): BinaryFileResponse
    {
        $this->authorizeContact($contact);
        $this->authorizeDocument($contact, $document);

        if (empty($document->document_path) || ! Storage::disk('local')->exists($document->document_path)) {
            abort(404, 'Document file not found.');
        }

        return response()->download(
            Storage::disk('local')->path($document->document_path),
            $document->document_name ?: basename($document->document_path)
        );
    }

    /**
     * Delete a Document & Note belonging to a contact.
     */
    public function destroyDocument(Request $request, Contact $contact, ContactDocument $document): JsonResponse|RedirectResponse
    {
        $this->authorizeContact($contact);
        $this->authorizeDocument($contact, $document);

        if (! empty($document->document_path)) {
            Storage::disk('local')->delete($document->document_path);
        }

        $document->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Document & note deleted successfully.',
            ]);
        }

        return redirect()->back()->with('success', 'Document & note deleted successfully.');
    }

    private function authorizeContact(Contact $contact): void
    {
        if ((int) $contact->business_id !== (int) $this->currentBusiness->id()) {
            abort(404, 'Contact not found for this business.');
        }
    }

    private function authorizeDocument(Contact $contact, ContactDocument $document): void
    {
        $isSameBusiness = (int) $document->business_id === (int) $this->currentBusiness->id();
        $isSameContact = (int) $document->contact_id === (int) $contact->id;

        if (! $isSameBusiness || ! $isSameContact) {
            abort(404, 'Document not found for this contact.');
        }
    }
}
