# Products Module Documentation

## 1. Overview

The **Products** module manages the multi-tenant product catalog, pricing (tax-exclusive & tax-inclusive), variations, combo bundles, business location assignments, opening stock, stock movement history, and stock valuation reports for each shop in enPOS.

It is organized into six main sections under **Products**:
1. **List Products** (`/app/products` & `/api/v1/shop/products`) — Manage all products (`Single`, `Variable`, `Combo`), multi-criteria filters, `Stock Report` tab, row actions (`Labels`, `View`, `Edit`, `Delete`, `Add or edit opening stock`, `Product stock history`, `Duplicate Product`), and bulk operations (`Delete Selected`, `Add to location`, `Remove from location`, `Deactivate Selected`, `WooCommerce Sync`).
2. **Add Product** (`/app/products/create` & `/api/v1/shop/products/add`) — Dedicated `AddProductController` and form (`Shop/Products/Create.vue`) supporting Single, Variable, and Combo products, inline quick-create modals for Units & Brands, image & brochure uploads, automatic tax/margin calculation, and `Save & Add Opening Stock` / `Save And Add Another` / `Save` actions. Clicking **+ Add** on **List Products** opens this same **Add Product** form.
3. **Update Price** (`/app/products/update-price` & `/api/v1/shop/products/update-price`) — `UpdatePriceController` & `ProductPriceUpdate` model for exporting all product variation prices and Selling Price Group prices (`.xls` / `.csv`), and importing updated prices with strict header/product/SKU validation and audit logging.
4. **Print Labels** (`/app/products/print-labels` & `/api/v1/shop/products/print-labels`) — `PrintLabelController`, `BarcodeSetting`, `PrintLabelJob`, and `LabelPdfCodec` supporting product/variation search, configurable label fields & font sizes, Selling Price Group / `Inc. tax` vs `Exc. tax` pricing, 6 default barcode sheet/roll presets (plus custom barcode settings CRUD), HTML preview, and PDF download.
5. **Import Products** (`/app/products/import` & `/api/v1/shop/products/import`) — `ImportProductController` & `ImportProduct` model for bulk importing `single` and `variable` products via `.xls`, `.xlsx`, or `.csv` using the downloadable 37-column `.xls` template, auto-creating missing Brands/Categories/Sub-categories/Variations, and applying initial opening stock.
6. **Variations** (`/app/variations` & `/api/v1/shop/variations`) — `VariationController` managing `VariationTemplate` & `VariationValueTemplate` (`Size`, `Color`, etc.), search, sorting, CSV/Excel export, and in-use deletion protection.

---

## 2. Architecture & Tenancy

- Tenant-owned catalog models (`Product`, `Unit`, `Category`, `Brand`, `TaxRate`, `Warranty`, `VariationTemplate`, `SellingPriceGroup`, `Transaction`) use `App\Traits\BelongsToBusiness`.
- Every query is automatically scoped to the active `business_id` resolved by `CurrentBusiness` and the `business` middleware.
- Cross-tenant foreign IDs (`unit_id`, `brand_id`, `category_id`, `sub_category_id`, `tax_id`, `warranty_id`, `location_ids.*`, `combo_components.*.component_variation_id`) are strictly rejected with HTTP `422 Unprocessable Entity`, and cross-tenant resource access returns HTTP `404 Not Found`.
- **Backend Components**:
  - **Models**:
    - `App\Models\Product` — Core catalog entity (`single`, `variable`, `combo`), SoftDeletes, auto-SKU generator `Product::generateSku()` (`AS0001`, etc.).
    - `App\Models\Variation` — Variant/SKU pricing rows (`DUMMY` for single/combo, named rows for variable).
    - `App\Models\VariationLocationDetail` — Per-variation, per-location stock (`qty_available`).
    - `App\Models\VariationGroupPrice` — Selling price group overrides per variation.
    - `App\Models\ComboProductComponent` — Component variations & quantities for `combo` products.
    - `App\Models\Unit`, `App\Models\Category`, `App\Models\Brand`, `App\Models\TaxRate`, `App\Models\Warranty`, `App\Models\VariationTemplate`, `App\Models\VariationValueTemplate` — Catalog lookup models.
    - `App\Models\Transaction`, `App\Models\PurchaseLine`, `App\Models\SellLine` — Transactional ledger backing `opening_stock` and `Product stock history`.
  - **Service**: `App\Services\ProductService` — Handles atomic creation/update, tax & profit-margin computation, media storage (`public` disk), variation & location sync, product duplication, opening stock transactions, stock history aggregation, and canonical response transformation.
  - **Form Requests**: `ProductRequest`, `StoreProductRequest`, `UpdateProductRequest`, `SaveOpeningStockRequest`, `BulkProductActionRequest`, `ImportProductPriceRequest`, `PrintLabelPreviewRequest`, `BarcodeSettingRequest`, `StoreVariationTemplateRequest`, `UpdateVariationTemplateRequest`.
  - **Controllers**:
    - `App\Http\Controllers\Shop\ProductController` — List Products, Filters, Stock Report, Show, Edit, Update, Delete/Restore/ForceDelete, Duplicate, Opening Stock, Stock History, Labels, and Bulk Actions.
    - `App\Http\Controllers\Shop\AddProductController` — Add Product form page (`create`), API dropdown options (`formOptions`), product creation (`store`), and inline quick-add endpoints (`quickStoreUnit`, `quickStoreBrand`).
    - `App\Http\Controllers\Shop\UpdatePriceController` — Instructions & update history (`index`), export product prices (`.xls` / `.csv`), and import updated prices (`import`).
    - `App\Http\Controllers\Shop\PrintLabelController` — Print Labels options & default barcode presets (`index`), product search (`search`), JSON/HTML preview (`preview`), PDF download (`previewPdf`), and Barcode Settings CRUD.
    - `App\Http\Controllers\Shop\ImportProductController` — Import Products instructions/upload page & API list (`index`), `.xls` template download (`downloadTemplate`), and bulk import (`store`).
    - `App\Http\Controllers\Shop\VariationController` — Variations CRUD (`index`, `store`, `show`, `update`, `destroy`) and CSV/XLS export (`export`).
  - **Additional Models & Support**:
    - `App\Models\ProductPriceUpdate`, `App\Models\BarcodeSetting`, `App\Models\PrintLabelJob`, `App\Models\ImportProduct`, `App\Support\LabelPdfCodec`, `App\Support\ExcelWorkbookCodec`.

---

## 3. Database Schema & Migrations

1. `2026_11_08_200004_create_catalog_tables.php` — Base `units`, `categories`, `brands`, `warranties`, `selling_price_groups`, `tax_rates`, `products`, `variations`, `variation_location_details`, `variation_group_prices`, and `combo_product_components`.
2. `2026_11_11_180000_add_product_module_fields_and_tables.php` — Extends `products` and `variations`, and adds `product_locations`, `variation_templates`, and `variation_value_templates`.
3. `2026_11_12_100000_create_product_price_updates_and_print_labels_tables.php` — Adds `product_price_updates` (price import/update audit logs) and `print_label_jobs` (label print/preview batch history).
4. `2026_11_12_150000_create_import_products_table.php` — Adds `import_products` storing the 37-column import audit rows linked to `products.id` (`product_id`) and `users.id` (`imported_by`).

### 3.1 `products` Additions
| Column | Type | Description |
|---|---|---|
| `created_by` | unsignedBigInteger, nullable | User ID of creator (`users.id`) |
| `is_inactive` | boolean (default `false`) | Deactivated flag (`Deactivate Selected`) |
| `enable_sr_no` | boolean (default `false`) | Enable Product description, IMEI or Serial Number |
| `weight` | string(100), nullable | Product weight |
| `preparation_time_in_minutes` | unsignedInteger, nullable | Service staff timer / preparation time |
| `woocommerce_disable_sync` | boolean (default `false`) | Disable WooCommerce Sync |
| `product_brochure` | string, nullable | Stored brochure attachment path |

### 3.2 `variations` & Pivot Additions
- `variations.image` (`string, nullable`) — Variation-level image path.
- `product_locations` (`product_id`, `location_id`, unique pair) — Maps products to business locations.
- `variation_templates` & `variation_value_templates` — Reusable variable-product attribute templates.

---

## 4. API & Web Endpoints

All API routes are prefixed with `/api/v1/shop` and protected by `['auth:sanctum', 'business']`.  
All Inertia web routes are prefixed with `/app` and protected by `['auth', 'business']`.

### 4.1 Add Product Endpoints (`AddProductController`)
| Method | API Endpoint | Web Endpoint | Description |
|---|---|---|---|
| `GET` | `/api/v1/shop/products/add-options` | `/app/products/create` | Returns dropdown metadata (`units`, `brands`, `categories`, `sub_categories`, `locations`, `tax_rates`, `warranties`, `variation_templates`, `selling_price_groups`, `combo_variations`, `barcode_types`, `default_profit_percent`) |
| `POST` | `/api/v1/shop/products/add` (or `/products`) | `POST /app/products` | Creates a `single`, `variable`, or `combo` product with optional `image` & `product_brochure` uploads |
| `POST` | `/api/v1/shop/products/quick-units` | `POST /app/products/quick-units` | Inline quick-create Unit (`actual_name`, `short_name`, `allow_decimal`) |
| `POST` | `/api/v1/shop/products/quick-brands` | `POST /app/products/quick-brands` | Inline quick-create Brand (`name`, `description`) |

### 4.2 List Products & Row Actions (`ProductController`)
| Method | Endpoint (`/api/v1/shop/...`) | Description |
|---|---|---|
| `GET` | `/products` | List products with search, filters, sorting, and pagination |
| `GET` | `/products/stock-report` | Per-variation, per-location stock valuation & potential profit report |
| `GET` | `/products/{product}` | Retrieve single product with variations, combo components & stock |
| `PUT/PATCH` | `/products/{product}` | Update product, sync locations, variations & combo components |
| `DELETE` | `/products/{product}` | Soft-delete product |
| `POST` | `/products/{id}/restore` | Restore soft-deleted product |
| `DELETE` | `/products/{id}/force-delete` | Permanently delete product & stored media files |
| `POST` | `/products/{product}/duplicate` | Duplicate product with a newly generated SKU (`ASxxxx`) |
| `GET` | `/products/{product}/opening-stock` | Retrieve variation × location opening stock matrix |
| `POST` | `/products/{product}/opening-stock` | Save/update opening stock (`opening_stock` transaction + `purchase_lines` + `variation_location_details`) |
| `GET` | `/products/{product}/stock-history` | Chronological stock ledger (`opening_stock`, `purchase`, `sell`, returns, adjustments) |
| `GET` | `/products/{product}/labels` | Printable barcode label metadata per variation |

### 4.3 Bulk Actions (`ProductController`)
| Method | Endpoint (`/api/v1/shop/...`) | Payload |
|---|---|---|
| `POST` | `/products/bulk-delete` | `{ "product_ids": [1, 2] }` |
| `POST` | `/products/bulk-location` | `{ "product_ids": [1, 2], "location_id": 1, "action": "add" \| "remove" }` |
| `POST` | `/products/bulk-status` | `{ "product_ids": [1, 2], "is_inactive": true \| false }` |
| `POST` | `/products/bulk-woocommerce-sync` | `{ "product_ids": [1, 2] }` |

### 4.4 Update Price (`UpdatePriceController`)
| Method | Endpoint (`/api/v1/shop/...`) | Description |
|---|---|---|
| `GET` | `/products/update-price` | Instructions, expected export headers, active Selling Price Groups & update history |
| `GET` | `/products/update-price/export` | Export all variation & Selling Price Group prices (`.xls`, or `?format=csv`) |
| `POST` | `/products/update-price/import` | Multipart `file` upload (`.xls`, `.xlsx`, `.csv`) validating headers, product name, SKU & prices |

### 4.5 Print Labels (`PrintLabelController`)
| Method | Endpoint (`/api/v1/shop/...`) | Description |
|---|---|---|
| `GET` | `/products/print-labels` | Barcode settings (auto-seeding 6 presets), Selling Price Groups, default display options & recent jobs |
| `GET` | `/products/print-labels/search?search=` | Search products/variations by name or SKU |
| `POST` | `/products/print-labels/preview` | Generate expanded label preview JSON & HTML (`items`, `display`, `barcode_setting_id`) |
| `POST` | `/products/print-labels/preview-pdf` | Stream `%PDF-1.4` printable labels PDF (or `?as_json=1` for base64) |
| `POST` | `/products/print-labels/barcode-settings` | Create a custom barcode setting |
| `PUT/PATCH` | `/products/print-labels/barcode-settings/{barcodeSetting}` | Update a barcode setting |
| `DELETE` | `/products/print-labels/barcode-settings/{barcodeSetting}` | Delete a barcode setting (keeps at least 1) |

### 4.6 Variations (`VariationController`)
| Method | Endpoint (`/api/v1/shop/...`) | Description |
|---|---|---|
| `GET` | `/variations` | List variation templates with values, `values_text`, `can_delete`, search & pagination |
| `GET` | `/variations/export` | Export variations table (`?format=csv` or `xls`) |
| `POST` | `/variations` | Create a variation template with `name` and `values` array |
| `GET` | `/variations/{variation}` | Retrieve single variation template with values |
| `PUT/PATCH` | `/variations/{variation}` | Update variation name and sync values (blocks removing values used by products) |
| `DELETE` | `/variations/{variation}` | Delete variation template (returns `422` if used by existing products) |

### 4.7 Import Products (`ImportProductController`)
| Method | API Endpoint | Web Endpoint | Description |
|---|---|---|---|
| `GET` | `/api/v1/shop/products/import` | `/app/products/import` | API: Paginated list of imported product audit records; Web: Instructions & upload UI |
| `GET` | `/api/v1/shop/products/import/template` | `/app/products/import/template` | Download genuine binary `.xls` template with the 37 required column headers |
| `POST` | `/api/v1/shop/products/import` | `POST /app/products/import` | Multipart `file` upload (`.xls`, `.xlsx`, `.csv`) validating all 37 columns, creating `single` or `variable` products, auto-creating Brands/Categories/Sub-categories/Variation templates, and applying Opening Stock |

**Supported `GET /products` Query Filters:**
- `search` — Matches product `name`, `sku`, or variation `sku`
- `type` — `single` | `variable` | `combo`
- `category_id`, `brand_id`, `unit_id`, `tax_id`, `location_id`
- `is_inactive` (`0` | `1`), `not_for_selling=1`, `woocommerce_enabled=1`
- `only_trashed=1` / `with_trashed=1`
- `sort_by` (`name`, `default_purchase_price`, `default_sell_price`, `current_stock`, `type`, `sku`) & `sort_dir` (`asc` | `desc`)
- `per_page` (`1`–`200`, default `25`)

---

## 5. Frontend Pages & Navigation

- **Sidebar (`resources/js/Layouts/AppLayout.vue`)**: `Products` collapsible section with **List Products** (`/app/products`) and **Add Product** (`/app/products/create`).
- **List Products (`resources/js/Pages/Shop/Products/Index.vue`)**:
  - Collapsible **Filters** panel, **All Products** & **Stock Report** tabs, `DataTableActions` export/column visibility toolbar, **+ Add** button linking to `/app/products/create`, **Download Excel**, search, sortable columns, `Actions` dropdown (`Labels`, `View`, `Edit`, `Delete`, `Add or edit opening stock`, `Product stock history`, `Duplicate Product`), bulk action bar (`Delete Selected`, `Add to location`, `Remove from location`, `Deactivate Selected`, `WooCommerce Sync`), pagination, and action modals.
- **Add / Edit Product (`resources/js/Pages/Shop/Products/Create.vue`)**:
  - 3-card layout (`Basic Information`, `IMEI/Serial/Weight/WooCommerce`, `Tax & Dynamic Pricing Matrix` with green header bar), inline `+` quick-add Unit/Brand modals, automatic Exc/Inc tax & Margin(%) calculation, and `Save & Add Opening Stock`, `Save And Add Another`, and `Save` actions.

---

## 6. Postman Testing Workflow

Both existing and new sections are registered in the master Postman collection **`enPOS — Complete API (User Management & Contacts)`**:
- `10. Add Product` — 7 requests covering `add-options`, `quick-units`, `quick-brands`, single product (JSON & multipart `formdata`), variable product, and combo product (auto-saves `product_id`, `variation_id`, `unit_id`, `brand_id`, and `location_id`).
- `11. Products (List & Actions)` — 17 requests covering list, stock report, show, update, duplicate, opening stock GET/POST, stock history, labels, soft-delete, restore, bulk actions, and force-delete.
- `12. Update Price (Products)` — 4 requests covering info/history, `.xls` export, `.csv` export, and price import upload.
- `13. Print Labels (Products)` — 7 requests covering options/presets, product search, preview JSON/HTML, preview PDF, and barcode settings CRUD.
- `14. Variations (Products)` — 6 requests covering list, CSV export, create, show, update, and delete.
- `15. Import Products (Products)` — 3 requests covering `.xls` template download, multipart file import (`.xls`, `.xlsx`, or `.csv`), and paginated imported products list.

Sync anytime via:
```bash
php artisan postman:sync
```
