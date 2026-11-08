# enPOS — Team Working Guide

> Read this fully before writing your first line of code. Your AI assistant reads `AGENTS.md` automatically.

---

## 1. What we are building

**enPOS** is a multi-tenant SaaS Point-of-Sale platform (similar in scope to Ultimate POS).

- **Platform admin** (`/platform/*`) — we (the company) manage shops, plans, subscriptions, platform users.
- **Shop app** (`/app/*`) — each customer business gets its own workspace: products, purchases, sales, POS, stock, expenses, HRM, etc.
- **One codebase, one deploy.** Tenants are separated by `business_id` at the database level — never by deploying separate code.

### Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13, PHP 8.3+, Sanctum, Spatie Permissions |
| Frontend | Inertia v3 (SPA), Vue 3 `<script setup>`, Tailwind CSS 4 (`@theme` tokens) |
| Icons / UI | Heroicons Vue, Headless UI |
| Routing | Ziggy (use `route('name')` in JS, never hardcode URLs) |
| DB | SQLite locally, MySQL/Postgres in production |
| Tests | PHPUnit 12 (`php artisan test`), Pint for formatting |

### Current state (be honest with yourself about this)

- **Done:** all database migrations, Eloquent models, tenancy layer (`BelongsToBusiness` trait, `CurrentBusiness`), middleware (`business`, `module`, `platform`), permissions + roles seeders, Inertia share props, platform dashboard (working example of a full feature), login page.
- **Stubbed:** almost every `App\Http\Controllers\Shop\*` controller is an empty `//` skeleton. Shop pages are one placeholder.
- **Missing:** most platform Vue pages, shop layout route registration, tests.

---

## 2. Setup (every developer, first day)

```bash
git clone <repo-url> enpos
cd enpos
composer run setup      # installs PHP+JS deps, creates .env, generates key, migrates, builds assets
composer run dev        # runs server + queue + vite together
```

Verify you can log in and see the platform dashboard. If you can't, stop and ask — don't work around a broken environment.

**Rules baked into the setup:**
- Never commit `.env`, `storage/`, `node_modules/`, `vendor/`, or `database/database.sqlite`.
- Never edit migration files that have already been committed. Create a new migration instead.
- Local solution link / DB credentials must never be pasted into Slack or a ticket.

---

## 3. Architecture rules that MUST NOT be broken

### 3.1 Multi-tenancy — the single most important rule

Every tenant table has `business_id`. Models use `App\Traits\BelongsToBusiness`, which:

1. auto-fills `business_id` on create, and
2. adds a **global scope** filtering every query by the current `business_id`.

**Rules:**
- Any new model that holds tenant data **must** use `BelongsToBusiness`.
- Never bypass the scope with `withoutGlobalScope()` inside shop code. It is only for platform-admin code.
- **Never accept `business_id` from the request body.** Ever. Tenants must not be able to read or write each other's data.
- When a record is fetched by route-model binding, the global scope protects you — but still add a feature test proving tenant A cannot see tenant B's data for every new endpoint.
- Platform code that operates across tenants uses `Business::withoutGlobalScope('business')` — see `Platform\BusinessController` for the pattern.

### 3.2 Modules

`config/modules.php` defines `core` and `optional` modules. Optional modules (`hrm`, `essentials`, `cheque`, `woocommerce`) are gated twice: the **plan** must include the module, then the **shop** must enable it.

- Protect routes with `middleware('module:hrm')`.
- In Vue, read `page.props.enabledModules` to show/hide nav and features (see `AppLayout.vue`).

### 3.3 Auth & permissions

- Web (Inertia) uses session auth + CSRF. API uses Sanctum tokens under `/api/v1`.
- User types: `super_admin`, `admin` (platform) / `owner`, `staff` (shop).
- Shop permissions are `shop.*`, platform permissions are `platform.*` (see `PermissionSeeder`). Do not invent permission names ad-hoc — add them to the seeder.
- Platform super admin impersonation issues an owner token (API) — do not add a "login as anyone" shortcut on the web side.

### 3.4 Shared Inertia props

`HandleInertiaRequests::share()` exposes `auth.user`, `auth.business`, `enabledModules`, `flash`. Treat these as the single source of truth on the frontend. If you need more global data, add it there — not per-page.

### 3.5 Money, stock, dates

- All money columns are `decimal(15,4)`. Send numbers as strings/decimals from PHP, format in Vue with the shop's `currency_symbol` and `currency_position`.
- Stock lives in `variation_location_details` per location. Never compute stock on the fly "somewhere else" — read from there.
- All timestamps are UTC; localise at render.

---

## 4. The contract between Backend and Frontend

This is what prevents the two teams from blocking each other. **We build backend + API contract first.**

### 4.1 Standard Inertia page response (web)

Every Inertia page must receive exactly the props the Vue page needs:

```php
return Inertia::render('Shop/Products/Index', [
    'products' => $products->through(fn ($p) => [
        'id' => $p->id,
        'name' => $p->name,
        'sku' => $p->sku,
        'sell_price' => $p->default_sell_price,
        'stock' => $p->stock_qty,
    ]),
    'filters' => $request->only(['search', 'category_id']),
    'can' => [
        'create' => $request->user()->can('shop.products.create'),
    ],
]);
```

- **Backend owns the prop names.** Frontend does not ask for a different shape at the end. Agree once, in the sprint ticket, before coding.
- Send camelCase or snake_case? We use **snake_case in props** (matches DB & Laravel). Ziggy converts route params anyway.
- Never dump raw Eloquent models if they contain hidden-sensitive relations — use `->through()` / resources.

### 4.2 Mutation responses

- `Inertia::render` after `store/update/destroy` via `to_route()->with('success', '...')`.
- Validation errors come through `$page.props.errors` automatically — frontend must display them.
- API: JSON with a stable envelope (`message`, `data`) and proper status codes. A full list of API routes exists in `routes/api.php`; every endpoint already has its controller method named — implement those signatures, don't invent new route names without a task.

### 4.3 Frontend page contract

- Page components live at `resources/js/Pages/<Area>/<Module>/<Action>.vue` — the path must exactly match the string in `Inertia::render()`.
- `defineOptions({ layout: AppLayout })` for shop pages, `PlatformLayout` for platform pages.
- `defineProps` for every prop the server sends.
- Use `useForm` for forms, `<Head title="...">` for page title, `route('...')` for links.
- Every page: loading/empty/error states. Tables use `v-if` for empty state (see `Platform/Dashboard.vue`).
- No raw `fetch`/`axios` to the API from Inertia pages. Use Inertia visits.

---

## 5. Definition of Done

A ticket is complete only when **all** of these are true:

- [ ] Backend: controller implemented, validated, tenancy-safe, permission-checked, optional-module-gated if applicable.
- [ ] Backend: feature test covering happy path + validation failure + cross-tenant denial (404/403).
- [ ] Frontend: page renders real props, handles loading/empty/error, mobile width ≥ 1024 (POS screens ≥ 768).
- [ ] Frontend: permissions hide/disable actions (`can` prop), errors display.
- [ ] Both: `php artisan test` green, `npm run build` green.
- [ ] README / docs updated if behaviour or setup changed.
- [ ] Reviewed and merged via Pull Request by the other side's reviewer where the change crosses layers.

---

## 6. Git workflow (we are setting up GitHub/GitLab)

> Do this on day one, before anyone writes code.

1. One person creates the remote repo (private).
2. `git init`, commit the existing app **without** `.env`, `vendor/`, `node_modules/`, `storage/`, `database/database.sqlite` (`.gitignore` already covers most — verify with `git status`).
3. Push `main`. Protect `main`: no direct pushes, require PR + 1 approval and passing CI.
4. Create the branches below.

### Branches

| Branch | Purpose |
|---|---|
| `main` | Always deployable. Only through PR. |
| `develop` | Integration branch; feature branches merge here. |
| `feature/<ticket>-<short>` | e.g. `feature/ENP-42-products-crud` |
| `fix/<ticket>-<short>` | bug fixes |
| `hotfix/<ticket>` | production emergencies, from `main` |

### Commit messages

`type(scope): message` — `feat(products): add product list endpoint`, `fix(pos): prevent negative cart total`, `chore(deps): bump vite`. Reference ticket IDs.

### Pull requests

- One ticket, one PR. No mixing frontend and backend features in the same PR unless it's a single vertical slice.
- PR description: what, why, screenshots (frontend) or endpoint table (backend).
- **Never merge with failing tests or build.**
- Squash merge into `develop`.

---

## 7. How the two teams work together day to day

```text
Ticket (with agreed prop/endpoint contract)
        │
        ▼
Backend implements controller + tests  ──► API/page contract frozen & merged to develop
        │
        ▼
Frontend builds Vue page against real props  ──► PR, screenshots, both review merge
        │
        ▼
QA / you verify on develop → merge develop → main → deploy
```

### Weekly rhythm

- **Ask me first — this guide says "you coordinate directly", so every sprint starts with a written task board:**
  - One board (GitHub Projects / Trello / Jira). Columns: Backlog → Ready → In Progress → In Review → QA → Done.
  - Every ticket has: **Module, Layer (BE/FE/both), Props/endpoint contract, Acceptance criteria, Screens (frontend), Test cases (backend).**
- **Daily 15-min standup per team.** Blockers are raised there, not in a chat at 6 pm.
- **Contract review** before a backend module starts: FE lead + BE dev sit for 20 min and agree the prop names. If a prop changes later, the backend updates the ticket and the frontend dev is tagged in the PR.
- **Frontend never waits idle.** If backend is late, the FE builds against a mock page (real props hardcoded in `defineProps` default) so UI work continues — but must switch to real props before review.

### What NOT to do

- Don't "fix" the other team's layer in your PR — open a ticket.
- Don't add new npm/composer packages without notifying both leads and you (one-line ticket with justification).
- Don't touch `config/modules.php`, migrations, or seeders without the other side knowing (it changes contracts).
- Don't make design-system decisions alone: primary colours, spacing, component patterns. You own the design system; frontend implements it.
- Don't bypass tenancy, permissions, or module gating "to make it work".

---

## 8. Build order (recommended, given stubbed controllers)

The scaffolding is done, so work proceeds **module by module, backend first, vertical slices**:

1. **Shop foundation** — shop routes in `routes/web.php` (currently every `/app/*` falls through to `Shop/Dashboard`), `ShopLayout` nav wiring, settings page, users page.
2. **Products** — units/categories/brands/tax/price groups + products & variations CRUD + stock. This unblocks POS.
3. **Contacts** (customers/suppliers).
4. **Purchases → stock in → POS sell → Sell list** (POS depends on products & contacts; sell depends on stock).
5. **Expenses, accounts, stock transfers/adjustments.**
6. **Reports.**
7. **Optional modules:** HRM → Essentials → Cheque → WooCommerce (each behind `module:` middleware and plan gating).

**Each step:** backend contract + tests merged → frontend pages PR → QA on `develop`.

---

## 9. Environment & secrets

- Staging and production credentials are given by you, not shared in git.
- `.env` changes go in `.env.example` (keys without values if secret).
- Use `php artisan migrate` locally; never run it against staging/prod by hand — CI/CD does.
- Ask before running any `artisan db:*` or `migrate:fresh` on shared databases.

---

## 10. First-week checklist for each developer

**Everyone:** clone, `composer run setup`, log in as platform admin, read this guide + `AGENTS.md`, run `php artisan test`, run `npm run build`.
**Backend:** read `routes/api.php` + one reference controller (`Platform\BusinessController`), implement a "warm-up" ticket (e.g. `/api/v1/auth/me` hardening test or `LocationController` CRUD) and get it reviewed.
**Frontend:** read `Platform/Dashboard.vue` + `PlatformLayout.vue` + `AppLayout.vue`, build a small page from a real prop (e.g. Platform Businesses list) and get it reviewed.

---

## 11. Quick reference

```bash
composer run setup      # first-time
composer run dev        # dev servers
php artisan test        # tests
vendor/bin/pint         # format PHP
npm run build           # production frontend build
php artisan migrate:fresh --seed   # local reset ONLY
```

**Key files**
- `routes/web.php` — Inertia routes (`/platform`, `/app`)
- `routes/api.php` — API (`/api/v1`)
- `app/Traits/BelongsToBusiness.php` — tenancy
- `app/Http/Middleware/ResolveBusiness.php` — which shop is this request?
- `config/modules.php` — module registry (also mirror in `AppLayout.vue` nav)
- `app/Http/Middleware/HandleInertiaRequests.php` — shared props
- `database/seeders/PermissionSeeder.php` — permissions & roles
