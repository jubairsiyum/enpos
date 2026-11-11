# Products Module Documentation

## 1. Overview

The **Products** module manages the multi-tenant product catalog, pricing (tax-exclusive & tax-inclusive), variations, combo bundles, business location assignments, opening stock, stock movement history, and stock valuation reports for each shop in enPOS.

It is organized into two main sections under **Products**:
1. **List Products** (`/app/products` & `/api/v1/shop/products`) — Manage all products (`Single`, `Variable`, `Combo`), multi-criteria filters, `Stock Report` tab, row actions (`Labels`, `View`, `Edit`, `Delete`, `Add or edit opening stock`, `Product stock history`, `Duplicate Product`), and bulk operations (`Delete Selected`, `Add to location`, `Remove from location`, `Deactivate Selected`, `WooCommerce Sync`).
2. **Add Product** (`/app/products/create` & `/api/v1/shop/products/add`) — Dedicated `AddProductController` and form (`Shop/Products/Create.vue`) supporting Single, Variable, and Combo products, inline quick-create modals for Units & Brands, image & brochure uploads, automatic tax/margin calculation, and `Save & Add Opening Stock` / `Save And Add Another` / `Save` actions. Clicking **+ Add** on **List Products** opens this same **Add Product** form.

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
  - **Form Requests**: `ProductRequest`, `StoreProductRequest`, `UpdateProductRequest`, `SaveOpeningStockRequest`, `BulkProductActionRequest`.
  - **Controllers**:
    - `App\Http\Controllers\Shop\ProductController` — List Products, Filters, Stock Report, Show, Edit, Update, Delete/Restore/ForceDelete, Duplicate, Opening Stock, Stock History, Labels, and Bulk Actions.
    - `App\Http\Controllers\Shop\AddProductController` — Add Product form page (`create`), API dropdown options (`formOptions`), product creation (`store`), and inline quick-add endpoints (`quickStoreUnit`, `quickStoreBrand`).

---

## 3. Database Schema & Migrations

1. `2026_11_08_200004_create_catalog_tables.php` — Base `units`, `categories`, `brands`, `warranties`, `selling_price_groups`, `tax_rates`, `products`, `variations`, `variation_location_details`, `variation_group_prices`, and `combo_product_components`.
2. `2026_11_11_180000_add_product_module_fields_and_tables.php` — Extends `products` and `variations`, and adds `product_locations`, `variation_templates`, and `variation_value_templates`.

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

Both new sections are registered in the master Postman collection **`enPOS — Complete API (User Management & Contacts)`**:
- `10. Add Product` — 7 requests covering `add-options`, `quick-units`, `quick-brands`, single product (JSON & multipart `formdata`), variable product, and combo product (auto-saves `product_id`, `variation_id`, `unit_id`, `brand_id`, and `location_id`).
- `11. Products (List & Actions)` — 17 requests covering list, stock report, show, update, duplicate, opening stock GET/POST, stock history, labels, soft-delete, restore, bulk actions, and force-delete.

Sync anytime via:
```bash
php artisan postman:sync
```
