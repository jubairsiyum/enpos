<?php

namespace App\Support;

use App\Models\BarcodeSetting;

/**
 * Pure-PHP minimal PDF 1.4 generator for printable barcode labels.
 */
class LabelPdfCodec
{
    /**
     * Build a multi-page PDF document containing formatted product labels.
     *
     * @param  array<int, array<string, mixed>>  $labels
     * @param  array<string, mixed>  $displayOptions
     */
    public function buildLabelsPdf(array $labels, array $displayOptions, BarcodeSetting $setting): string
    {
        $cols = max(1, (int) $setting->stickers_in_one_row);
        $settingsMeta = $setting->settings ?? [];
        $stickersPerSheet = max(1, (int) ($settingsMeta['stickers_per_sheet'] ?? ($cols * 10)));
        $rowsPerPage = max(1, (int) ceil($stickersPerSheet / $cols));

        $pageWidthPt = 612.0;  // 8.5in * 72pt
        $pageHeightPt = 792.0; // 11in * 72pt
        $marginX = 24.0;
        $marginY = 24.0;
        $usableW = $pageWidthPt - ($marginX * 2);
        $usableH = $pageHeightPt - ($marginY * 2);
        $cellW = $usableW / $cols;
        $cellH = $usableH / $rowsPerPage;

        $chunks = array_chunk($labels, $cols * $rowsPerPage);
        if (empty($chunks)) {
            $chunks = [[]];
        }

        $pageStreams = [];
        foreach ($chunks as $pageLabels) {
            $stream = "0.2 w\n";
            foreach ($pageLabels as $index => $label) {
                $col = $index % $cols;
                $row = intdiv($index, $cols);

                $x = $marginX + ($col * $cellW);
                $yTop = $pageHeightPt - $marginY - ($row * $cellH);
                $yBottom = $yTop - $cellH;

                $stream .= sprintf("0.85 0.85 0.85 RG %.2F %.2F %.2F %.2F re S\n", $x + 2, $yBottom + 2, $cellW - 4, $cellH - 4);
                $stream .= "0 0 0 RG 0 0 0 rg\n";

                $lines = $this->buildLabelLines($label, $displayOptions);
                $cursorY = $yTop - 14;
                foreach ($lines as $line) {
                    if ($cursorY < $yBottom + 8) {
                        break;
                    }
                    $fontSize = (int) $line['size'];
                    $escaped = $this->escapePdfText((string) $line['text']);
                    $stream .= sprintf("BT /F1 %d Tf %.2F %.2F Td (%s) Tj ET\n", $fontSize, $x + 8, $cursorY, $escaped);
                    $cursorY -= ($fontSize + 3);
                }
            }
            $pageStreams[] = $stream;
        }

        return $this->assemblePdf($pageStreams, $pageWidthPt, $pageHeightPt);
    }

    /**
     * @param  array<string, mixed>  $label
     * @param  array<string, mixed>  $displayOptions
     * @return array<int, array{text: string, size: int}>
     */
    private function buildLabelLines(array $label, array $displayOptions): array
    {
        $lines = [];

        if (! empty($displayOptions['show_business_name']) && ! empty($label['business_name'])) {
            $lines[] = [
                'text' => (string) $label['business_name'],
                'size' => max(6, min(14, (int) round(((int) ($displayOptions['business_name_size'] ?? 20)) * 0.55))),
            ];
        }

        if (! empty($displayOptions['show_product_name']) && ! empty($label['product_name'])) {
            $lines[] = [
                'text' => (string) $label['product_name'],
                'size' => max(6, min(12, (int) round(((int) ($displayOptions['product_name_size'] ?? 15)) * 0.6))),
            ];
        }

        if (! empty($displayOptions['show_variation']) && ! empty($label['variation_name'])) {
            $lines[] = [
                'text' => (string) $label['variation_name'],
                'size' => max(6, min(12, (int) round(((int) ($displayOptions['variation_size'] ?? 17)) * 0.55))),
            ];
        }

        if (! empty($displayOptions['show_price']) && isset($label['formatted_price'])) {
            $lines[] = [
                'text' => 'Price: ' . (string) $label['formatted_price'],
                'size' => max(6, min(12, (int) round(((int) ($displayOptions['price_size'] ?? 17)) * 0.55))),
            ];
        }

        if (! empty($displayOptions['show_packing_date']) && ! empty($label['packing_date'])) {
            $lines[] = [
                'text' => 'Packed: ' . (string) $label['packing_date'],
                'size' => max(6, min(10, (int) round(((int) ($displayOptions['packing_date_size'] ?? 12)) * 0.65))),
            ];
        }

        $sku = (string) ($label['sku'] ?? '');
        if ($sku !== '') {
            $lines[] = [
                'text' => '[' . ($label['barcode_type'] ?? 'C128') . '] ' . $sku,
                'size' => 7,
            ];
        }

        return $lines;
    }

    private function escapePdfText(string $text): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $clean = $ascii !== false ? $ascii : $text;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $clean);
    }

    /**
     * @param  array<int, string>  $pageStreams
     */
    private function assemblePdf(array $pageStreams, float $width, float $height): string
    {
        $objects = [];
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";

        $pageIds = [];
        $nextId = 4;
        foreach ($pageStreams as $stream) {
            $contentId = $nextId++;
            $pageId = $nextId++;
            $objects[$contentId] = "<< /Length " . strlen($stream) . " >>\nstream\n{$stream}endstream";
            $objects[$pageId] = sprintf(
                "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R >> >> /Contents %d 0 R >>",
                $width,
                $height,
                $contentId
            );
            $pageIds[] = $pageId . ' 0 R';
        }

        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $pageIds) . '] /Count ' . count($pageIds) . ' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $totalObjects = count($objects) + 1;
        $pdf .= "xref\n0 {$totalObjects}\n";
        $pdf .= "0000000000 65535 f \n";
        foreach ($objects as $id => $body) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }

        $pdf .= "trailer\n<< /Size {$totalObjects} /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }
}
