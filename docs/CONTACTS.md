# Contacts Module Documentation

## 1. Overview

The **Contacts** module manages business relationships, pricing tiers, contact persons, and user assignments for each tenant shop in the multi-tenant enPOS platform.

It is divided into four main sections under **Contacts**:
1. **Suppliers** (`/app/contacts?type=supplier` & `/api/v1/shop/contacts`) — Manage vendors, purchase dues, and supplier details.
2. **Customers** (`/app/contacts/customers` & `/api/v1/shop/customers`) — Manage individual and business customers, credit limits, pay terms, assigned staff, and multiple contact persons.
3. **Customer Groups** (`/app/contacts/customer-groups` & `/api/v1/shop/customer-groups`) — Manage customer pricing tiers based on either a percentage adjustment or a Selling Price Group.
4. **Import Contacts** (`/app/contacts/import` & `/api/v1/shop/import-contacts`) — Bulk import contacts via `.xls` or `.csv` using the downloadable `.xls` template and strict 27-column specification.

---

## 2. Architecture & Tenancy

- Every model in the module (`Contact`, `ContactPerson`, `CustomerGroup`, `SellingPriceGroup`, `ImportContact`) uses the `App\Traits\BelongsToBusiness` trait.
- All records are strictly scoped by `business_id` via `CurrentBusiness` and the global `business` Eloquent scope.
- Attempting to read, update, delete, restore, or assign records belonging to another business returns HTTP `404 Not Found` or `422 Unprocessable Entity` (for cross-tenant foreign IDs in validation).
- **Models**:
  - `App\Models\Contact` — Core contact entity (`type`: `customer`, `supplier`, `both`; `entity_type`: `individual`, `business`). Includes `customerGroup()`, `contactPersons()`, `assignedUsers()`, `creator()`, `scopeCustomers()`, `scopeSuppliers()`, and collision-safe `generateContactId()`.
  - `App\Models\ContactPerson` — Sub-contacts linked to a `Contact` (up to 10 per request, 3 collapsible panels in the UI), with commission percentage and `allow_login` support.
  - `App\Models\CustomerGroup` — Pricing group linked to `Contact` and optionally `SellingPriceGroup`.
  - `App\Models\SellingPriceGroup` — Shop-scoped selling price group (`selling_price_groups` table) used when a customer group uses `selling_price_group` calculation.
  - `App\Models\ImportContact` — Holds the 27-column import template schema (`TEMPLATE_COLUMNS`) and stores imported contact rows linked to `Contact` (`contact_record_id`) and the importing `User` (`imported_by`).

---

## 3. Database Schema & Migrations

Migrations for the Contacts module:
1. `2026_11_08_200003_create_contacts_tables.php` — Base `customer_groups` and `contacts` tables.
2. `2026_11_10_140000_add_pay_term_and_address_fields_to_contacts_table.php` — Adds `pay_term_number`, `pay_term_type`, `landmark`, `street_name`, `building_number`, and `additional_number` to `contacts`.
3. `2026_11_11_100000_create_contact_persons_and_customer_fields.php` — Adds `contact_id`, `entity_type`, `created_by`, makes `credit_limit` nullable (`null` = `"No Limit"`), and creates `contact_persons` and `contact_user` tables.
4. `2026_11_11_120000_add_price_calculation_fields_to_customer_groups_table.php` — Adds `price_calculation_type` (`percentage` | `selling_price_group`), nullable `amount`, and `selling_price_group_id` FK to `customer_groups`.
5. `2026_11_11_130000_create_import_contacts_table.php` — Creates the `import_contacts` table with the 27 template columns, `imported_by`, and `contact_record_id`.

### 3.1 `contacts` Additions
| Column | Type | Description |
|---|---|---|
| `contact_id` | string(50), nullable | Custom or auto-generated code (e.g. `CO0001`) |
| `entity_type` | enum(`individual`, `business`) | Contact classification |
| `created_by` | unsignedBigInteger, nullable | User ID of the creator |
| `pay_term_number` | unsignedSmallInteger, nullable | Numeric pay term duration |
| `pay_term_type` | enum(`days`, `months`), nullable | Pay term unit |
| `credit_limit` | decimal(15,2), nullable | `null` represents `"No Limit"` |
| `landmark`, `street_name`, `building_number`, `additional_number` | string, nullable | Extended address metadata |

### 3.2 `contact_persons` Table
| Column | Type | Description |
|---|---|---|
| `business_id` | unsignedBigInteger | Tenant business ID |
| `contact_id` | unsignedBigInteger | Parent contact ID (cascade delete) |
| `prefix`, `first_name`, `last_name` | string | Person name (`first_name` required) |
| `email`, `mobile`, `alternate_number`, `family_number` | string, nullable | Contact channels |
| `department`, `designation` | string, nullable | Organizational role |
| `sales_commission_percentage` | decimal(5,2), nullable | Commission percentage (`0`–`100`) |
| `allow_login` | boolean (default false) | Login permission flag |
| `user_id` | unsignedBigInteger, nullable | Optional linked user record |

### 3.3 `contact_user` Pivot Table
| Column | Type | Description |
|---|---|---|
| `contact_id` | unsignedBigInteger | Contact ID (cascade delete) |
| `user_id` | unsignedBigInteger | Assigned shop user ID (cascade delete) |

### 3.4 `customer_groups` Table
| Column | Type | Description |
|---|---|---|
| `business_id` | unsignedBigInteger | Tenant business ID |
| `name` | string | Customer group name |
| `price_calculation_type` | enum(`percentage`, `selling_price_group`) | Pricing calculation mode |
| `amount` | decimal(5,2), nullable | Calculation percentage (`-100` to `999.99`); `null` when using Selling Price Group |
| `selling_price_group_id` | unsignedBigInteger, nullable | Linked Selling Price Group ID; `null` when using Percentage |
| `is_default` | boolean (default false) | Default group flag |

### 3.5 `import_contacts` Table
| Column | Type | Description |
|---|---|---|
| `business_id` | unsignedBigInteger | Tenant business ID |
| `imported_by` | unsignedBigInteger, nullable | Authenticated user ID who triggered the import |
| `contact_record_id` | unsignedBigInteger, nullable | Linked `contacts.id` record created by the import |
| `contact_type` | tinyInteger | `1` = Customer, `2` = Supplier, `3` = Both (Required) |
| `prefix` | string, nullable | Name prefix (Optional) |
| `first_name` | string | First Name (Required) |
| `middle_name` | string, nullable | Middle Name (Optional) |
| `last_name` | string, nullable | Last Name (Optional) |
| `business_name` | string, nullable | Business Name (Required if `contact_type` is `2` or `3`) |
| `contact_id` | string, nullable | Custom Contact ID (Optional; auto-generated if blank) |
| `tax_number` | string, nullable | Tax Number (Optional) |
| `opening_balance` | decimal(22,4), nullable | Opening Balance (Optional) |
| `pay_term` | integer, nullable | Pay term duration (Required if `contact_type` is `2` or `3`) |
| `pay_term_period` | string, nullable | `days` or `months` (Required if `contact_type` is `2` or `3`) |
| `credit_limit` | decimal(22,4), nullable | Credit Limit (Optional) |
| `email` | string, nullable | Email address (Optional) |
| `mobile` | string | Mobile number (Required) |
| `alternate_contact_number` | string, nullable | Alternate contact number (Optional) |
| `landline` | string, nullable | Landline number (Optional) |
| `city` | string, nullable | City (Optional) |
| `state` | string, nullable | State (Optional) |
| `country` | string, nullable | Country (Optional) |
| `address_line_1` | text, nullable | Address line 1 (Optional) |
| `address_line_2` | text, nullable | Address line 2 (Optional) |
| `zip_code` | string, nullable | Zip Code (Optional) |
| `date_of_birth` | date, nullable | Date of birth (`Y-m-d`) (Optional) |
| `custom_field_1` | string, nullable | Custom Field 1 (Optional) |
| `custom_field_2` | string, nullable | Custom Field 2 (Optional) |
| `custom_field_3` | string, nullable | Custom Field 3 (Optional) |
| `custom_field_4` | string, nullable | Custom Field 4 (Optional) |

---

## 4. API & Web Endpoints

All API routes are prefixed with `/api/v1/shop` and protected by `['auth:sanctum', 'business']`.  
All Inertia web routes are prefixed with `/app` and protected by `['auth', 'business']`.

### 4.1 Customers API (`/api/v1/shop/customers`)
| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/shop/customers` | List customers with search, filters, pagination, and due totals |
| `POST` | `/api/v1/shop/customers` | Create customer with assigned users and contact persons |
| `GET` | `/api/v1/shop/customers/{customer}` | Retrieve a single customer with relations |
| `PUT/PATCH` | `/api/v1/shop/customers/{customer}` | Update customer, sync assigned users and contact persons |
| `DELETE` | `/api/v1/shop/customers/{customer}` | Soft-delete customer (default Walk-In customer protected) |
| `POST` | `/api/v1/shop/customers/{id}/restore` | Restore a soft-deleted customer |
| `DELETE` | `/api/v1/shop/customers/{id}/force-delete` | Permanently delete a customer |

**Supported `GET /customers` Query Filters:**
- `search` — Matches `name`, `contact_id`, `supplier_business_name`, `mobile`, `email`, `tax_number`
- `customer_group_id` — Filter by Customer Group ID
- `assigned_to` — Filter by assigned shop User ID
- `has_sell_due=1` — Customers with `(total_sell - total_sell_paid) > 0`
- `has_sell_return_due=1` — Customers with `(total_sell_return - total_sell_return_paid) > 0`
- `has_advance_balance=1` — Customers with `balance > 0`
- `has_opening_balance=1` — Customers with `opening_balance > 0`
- `no_sell_since` — `1_month`, `3_months`, `6_months`, or `1_year`
- `only_trashed=1` / `with_trashed=1` — Soft-deleted record filtering
- `per_page` — Items per page (`1`–`200`, default `25`)

**Validation & Normalization (`StoreCustomerRequest` / `UpdateCustomerRequest`):**
- `mobile` is required on creation (`sometimes|required` on update).
- `assigned_to` validates each user ID exists within the active `business_id` (empty/`null` entries stripped).
- `contact_persons` strips empty UI slots before validation; when `contact_persons` is included on update, existing records are updated by `id`, new rows created, and omitted rows removed.
- If `contact_id` is omitted/empty on creation, `Contact::generateContactId()` generates the next sequential ID (e.g. `CO0001`).

### 4.2 Customer Groups API (`/api/v1/shop/customer-groups`)
| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/shop/customer-groups` | List customer groups with search and pagination |
| `POST` | `/api/v1/shop/customer-groups` | Create customer group (`percentage` or `selling_price_group`) |
| `GET` | `/api/v1/shop/customer-groups/{customerGroup}` | Retrieve customer group with Selling Price Group name |
| `PUT/PATCH` | `/api/v1/shop/customer-groups/{customerGroup}` | Update customer group and normalize calculation fields |
| `DELETE` | `/api/v1/shop/customer-groups/{customerGroup}` | Delete customer group |

**Calculation Normalization Rules:**
- `price_calculation_type = "percentage"`: accepts `amount` (`-100` to `999.99`, negative for discount, positive for markup) and sets `selling_price_group_id = null`.
- `price_calculation_type = "selling_price_group"`: accepts `selling_price_group_id` (scoped to `business_id`) and sets `amount = null`.

---

### 4.3 Import Contacts API (`/api/v1/shop/import-contacts`)
| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/import-contacts` | Paginated history of imported contacts (`import_contacts` joined with `contact` and `importer`) |
| `GET` | `/import-contacts/template` | Downloads `import_contacts_template.xls` (genuine binary Excel 97-2003 BIFF8 `.xls` format with exact 27 columns) |
| `POST` | `/import-contacts` | Multipart (`file`) upload of `.xls` (binary BIFF8, SpreadsheetML XML, or HTML table), `.xlsx`, or `.csv`/`.txt`; validates every row atomically inside `DB::transaction` and inserts into `contacts` + `import_contacts` |

**Import Validation & Conditional Rules (Exact 27 Columns):**
- Required on all rows: `Contact type` (`1 = Customer`, `2 = Supplier`, `3 = Both`), `First Name`, `Mobile`.
- Required when `Contact type` is `2` (Supplier) or `3` (Both): `Business Name`, `Pay term` (positive integer), and `Pay term period` (`days` or `months`).
- `Contact ID`: optional; leave blank to auto-generate (`CT0001`, etc.).
- `Date of birth`: optional `Y-m-d` format (e.g., `2026-11-11`).
- Supported upload formats: `.xls` (binary BIFF8 Excel generated by the template endpoint, SpreadsheetML XML, or HTML `.xls`), `.xlsx`, and `.csv` / `.txt`.

---

### 4.4 Contact Actions API (`/api/v1/shop/contacts/{contact}/...`)
Shared actions available for both **Customers** and **Suppliers**:
| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/contacts/{contact}/pay` | Records due/advance payment (`amount`, `method`, `paid_on`, `note`), reduces sell/purchase due and moves excess to advance `balance` |
| `POST` | `/contacts/{contact}/toggle-status` | Toggles `contact_status` between `active` and `inactive` (`Deactivate` / `Activate`) |
| `GET` | `/contacts/{contact}/ledger` | Returns opening balance, total invoice/paid/due summary, and chronological debit/credit ledger entries |
| `GET` | `/contacts/{contact}/sales` | Lists contact transactions (sell/sell-return for customers & both, purchase/purchase-return for suppliers) |
| `GET` | `/contacts/{contact}/documents` | Lists Notes & Documents attached to the contact |
| `POST` | `/contacts/{contact}/documents` | Adds a Note & optional Document upload (`heading`, `description`, `is_private`, `file`) |
| `GET` | `/contacts/{contact}/documents/{document}/download` | Downloads the stored document attachment |
| `DELETE` | `/contacts/{contact}/documents/{document}` | Deletes a Note & Document entry and removes its attachment |

---

## 5. Frontend Pages & Navigation

Located in `resources/js/Pages/Shop/Contacts/`, `resources/js/Components/Contacts/`, and `resources/js/Layouts/AppLayout.vue`:
- **Sidebar (`AppLayout.vue`)**: `Contact` submenu exposes `Suppliers`, `Customers`, `Customer Groups`, and `Import Contacts`.
- **Shared Action Column (`ContactActionDropdown.vue` & `ContactActionModals.vue`)** used in both **Customers (`Customers/Index.vue`)** and **Suppliers (`Suppliers/Index.vue`)**:
  - **Actions Dropdown**: `Pay`, `View`, `Edit`, `Delete`, `Deactivate` / `Activate`, divider, `Ledger`, `Sales`, `Documents & Note` (and `View` / `Restore` / `Force Delete` when viewing trashed rows).
  - **Pay Modal**: Records due/advance payment against the contact.
  - **Contact Details Modal**: Tabbed view (`View`, `Ledger`, `Sales`, `Documents & Note`) opening directly on the selected menu action.
- **Customers (`Customers/Index.vue`)**:
  - Collapsible **Filters** panel (`Sell Due`, `Sell Return`, `Advance Balance`, `Opening Balance`, `Has no sell from`, `Customer Group`, `Assigned to`, `Show Trashed`).
  - **All your Customers** DataTable (`Action`, `Contact ID`, `Business Name`, `Name`, `Email`, `Tax number`, `Credit Limit`, `Pay term`, `Opening Balance`, `Advance Balance`, `Added On`, `Customer Group`, `Address`, `Mobile`, `Total Sale Due`, `Total Sell Return Due`, `Custom Field 1–3` + toggleable `4–10`, footer `Total:` row, exports via `DataTableActions.vue`, pagination).
  - **Add / Edit Contact Modal**: Contact type, Individual/Business toggle, Contact ID, Customer Group, Mobile*, Alternate contact number, Landline, Email, multi-user Assigned to, collapsible **More Informations** (Tax, Opening Balance, Pay term, Credit Limit, full Address + Landmark/Street/Building/Additional number, Custom Fields 1–10, Shipping Address), and collapsible **Add Contact Persons** (3 accordion panels with `Allow login` and commission %).
- **Suppliers (`Suppliers/Index.vue`)**:
  - **All your Suppliers** DataTable with the same shared `Actions` dropdown (`Pay`, `View`, `Edit`, `Delete`, `Deactivate`/`Activate`, `Ledger`, `Sales`, `Documents & Note`), search, exports via `DataTableActions.vue`, pagination, and Add/Edit Supplier modal.
- **Customer Groups (`CustomerGroups/Index.vue`)**:
  - **All Customer Groups** DataTable (`Customer Group Name`, `Calculation Percentage (%)`, `Selling Price Group`, `Action`) with search, pagination, and `DataTableActions.vue`.
  - **Add / Edit Customer Group Modal**: `Customer Group Name*`, `Price calculation type` (`Percentage` / `Selling Price Group`), conditional `Calculation Percentage (%)` with `InfoTooltip.vue` or `Selling Price Group` selector, `Save` & `Close` actions.
- **Import Contacts (`ImportContacts/Index.vue`)**:
  - **Import Contacts** upload panel (`File To Import:` file chooser, `Submit` button, and green `Download template file` button targeting `/app/contacts/import/template` for `.xls` download).
  - **Instructions** table listing exact columns `1`–`27` (`Column Number`, `Column Name`, `Instruction`).

---

## 6. Single-Collection Postman Testing Workflow

All API sections are maintained inside a single master Postman collection: **`enPOS — Complete API (User Management & Contacts)`**.

- **Modular YAML source** (linked to Postman Workspace via `.postman/resources.yaml`):
  `postman/collections/enPOS — Complete API (User Management & Contacts)/`
  - `1. Auth` (auto-saves `token`, `user_id`, and `business_id` on Login)
  - `2. Users`
  - `3. Roles & Permissions`
  - `4. Sales Commission Agents`
  - `5. Contacts (Suppliers)`
  - `6. Contacts (Customers)` (auto-saves `customer_id`)
  - `7. Customer Groups` (auto-saves `customer_group_id`)
  - `8. Import Contacts` (download `.xls` template, multipart `form-data` `.xls`/`.csv` upload, list import history)
  - `9. Contact Actions` (Pay, Toggle Status, Ledger, Sales, Documents & Notes)
- **Compiled v2.1 JSON**: `postman/enPOS_Complete_API.postman_collection.json`
- **Local Environment**: `postman/environments/enPOS Local.environment.yaml`

### Artisan Commands for Future Sections
- **Add a new API section into the existing collection**:
  ```bash
  php artisan postman:add-section "Products" shop/products --variable=product_id
  ```
  Generates the next numbered folder with CRUD requests (plus `restore` / `force-delete` if registered in `routes/api.php`), registers the ID variable, and syncs the JSON file.
- **Sync YAML folders into the single JSON collection**:
  ```bash
  php artisan postman:sync
  ```
