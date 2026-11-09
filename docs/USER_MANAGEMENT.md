# User Management Module Documentation

## 1. Overview

The **User Management** section provides role-based access control, tenant staff management, and sales commission tracking for each shop in the multi-tenant enPOS platform.

It is split into three main sub-sections:
1. **Users** (`/app/users` & `/api/v1/shop/users`) — Manage all tenant users and staff members.
2. **Roles** (`/app/roles` & `/api/v1/shop/roles`) — Define fine-grained access roles and permissions.
3. **Sales Commission Agents** (`/app/sales-commission-agents` & `/api/v1/shop/sales-commission-agents`) — Manage commission-based agents.

---

## 2. Architecture & Tenancy

- Every shop user record is strictly bound to `business_id`.
- The global tenancy scope prevents cross-tenant data access; attempting to view or modify another shop's user returns HTTP `404 Not Found`.
- Roles and permissions are managed using **Spatie Laravel Permission** in team mode (`team_foreign_key => business_id`). When assigning or verifying roles, the active tenant context is injected via `setPermissionsTeamId($businessId)`.
- System roles (`owner`, `admin`, `cashier`) are pre-seeded and protected from deletion.

---

## 3. Database Schema Changes

Migration: `2026_11_09_100000_add_user_management_columns_to_users_table.php`

### Added Columns to `users`:
| Column | Type | Description |
|---|---|---|
| `username` | string, nullable | Unique username for shop staff login |
| `prefix` | string, nullable | Name prefix (Mr, Mrs, Miss, etc.) |
| `first_name` | string, nullable | First name |
| `last_name` | string, nullable | Last name |
| `is_cmmsn_agent` | boolean (default false) | Flag identifying sales commission agents |
| `access_locations` | json, nullable | Authorized location IDs or `['all']` |
| `dob` | date, nullable | Date of birth |
| `gender` | string(20), nullable | `male`, `female`, `other` |
| `marital_status` | string(30), nullable | `married`, `unmarried`, `divorced` |
| `blood_group` | string(10), nullable | Blood group |
| `alt_number` | string(30), nullable | Alternate contact number |
| `family_number` | string(30), nullable | Family emergency contact number |
| `fb_link`, `twitter_link` | string, nullable | Social profile URLs |
| `social_media_1`, `social_media_2` | string, nullable | Additional social media accounts |
| `custom_field_1` to `custom_field_4` | string, nullable | Customizable user metadata |
| `guardian_name` | string, nullable | Guardian / Father / Spouse name |
| `id_proof_name` | string, nullable | ID type (Passport, NID, etc.) |
| `id_proof_number` | string, nullable | Identification document number |
| `permanent_address` | text, nullable | Permanent residential address |
| `current_address` | text, nullable | Current residential address |
| `bank_details` | json, nullable | Bank name, account number, code, branch, tax ID |
| `hrm_details` | json, nullable | Department, designation, salary, pay cycle |

---

## 4. Permissions Registry

Defined in `config/shop_permissions.php` and seeded via `PermissionSeeder`:
- **Core Groups**:
  - **Others**: Export buttons (`csv/excel/print/pdf`), Payment reminders
  - **User**: View, Add, Edit, Delete
  - **Roles**: View, Add, Edit, Delete
  - **Supplier & Customer**: View all, View own, Filtered view by sales inactivity, Add, Edit, Delete
  - **Product & Purchase**: View, Add, Edit, Delete, Opening stock, Payment management, Status updates
  - **Stock Adjustment & Stock Transfer**: View all/own, Add, Edit, Delete
  - **POS & Sell**: POS operations, discount permissions, payment adjustments, invoice number management, returns
  - **Draft & Quotation**: View, Add, Delete
  - **Shipments & Cash Register**: Access shipments, Close cash register
  - **Catalog Master**: Brand, Tax rate, Unit, Category
  - **Reports**: Profit/loss, stock, taxes, customer/supplier, trending products, register, stock alert
  - **Settings & Expenses**: Business settings, barcodes, invoices, printers, expense CRUD
  - **Home**: View Home dashboard data
- **Extended & Modular Groups (Image 1 & 2 additions)**:
  - **Account**: Access Accounts, Edit account transaction, Delete account transaction
  - **Access selling price groups**: Default Selling Price
  - **Accounting**: Module access, Accounts, Journal CRUD, Map Transactions, Transfer CRUD, Budget, Reports
  - **AIAssistance**: Access to omni-channel or AI assistance module
  - **AssetManagement**: Asset CRUD, Maintenance views
  - **Cheque**: View Received Cheques, View Issued Cheques
  - **Crm**: Follow-ups, Leads, Campaigns, Contact login, Sources, Life stage, Proposal, Requests
  - **CustomDashboard**: Manage Dashboard
  - **Essentials**: Leave types, Attendance (web/API), Pay components, Department/Designation, Payroll CRUD, To Do's assignment, Messages, Sales Targets, Knowledge Base
  - **Gym**: Member, Subscription, Attendance, Packages, Health, Setting, Payments
  - **Hms (Hotel Management)**: Rooms, Price, Extra, Coupon, Booking CRUD, Amenities, Booking payments
  - **Manufacturing**: Recipe CRUD, Access Production
  - **Project**: Create, Edit, Delete Project
  - **Repair**: Invoices, Statuses, Job sheet CRUD, Assigned vs All sheets
  - **Spreadsheet**: Access and Create spreadsheet
  - **Superadmin**: Access package subscriptions
  - **Woocommerce**: Sync Categories, Products, Orders, Tax mapping, API settings
  - **ZatcaIntegrationKsa**: Zatca module access, Onboarding system, Sales, Sales return

---

## 5. API & Web Endpoints

### 5.1 Users API (`/api/v1/shop/users`)
| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/shop/users` | List shop users with search and pagination |
| `POST` | `/api/v1/shop/users` | Create new shop user with role assignment |
| `GET` | `/api/v1/shop/users/{id}` | Retrieve specific user details |
| `PUT/PATCH` | `/api/v1/shop/users/{id}` | Update user details, password, and roles |
| `DELETE` | `/api/v1/shop/users/{id}` | Soft-delete user (prevents deleting self / owner) |

### 5.2 Roles API (`/api/v1/shop/roles`)
| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/shop/roles` | List all roles available for this business |
| `GET` | `/api/v1/shop/roles/permissions` | Get grouped permissions registry for role builder |
| `POST` | `/api/v1/shop/roles` | Create new custom business role with synced permissions |
| `GET` | `/api/v1/shop/roles/{id}` | Get role with associated permissions |
| `PUT/PATCH` | `/api/v1/shop/roles/{id}` | Update role name and permission list |
| `DELETE` | `/api/v1/shop/roles/{id}` | Delete custom role (system roles protected) |

### 5.3 Sales Commission Agents API (`/api/v1/shop/sales-commission-agents`)
| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/shop/sales-commission-agents` | List sales commission agents |
| `POST` | `/api/v1/shop/sales-commission-agents` | Register sales commission agent |
| `PUT/PATCH` | `/api/v1/shop/sales-commission-agents/{id}` | Update agent profile and commission rate |
| `DELETE` | `/api/v1/shop/sales-commission-agents/{id}` | Soft-delete commission agent |

---

## 6. Frontend Pages & Components

Located at `resources/js/Pages/Shop/`:
- **Users**:
  - `Users/Index.vue`: Matches Image 1 layout with search, pagination, export buttons (CSV, Excel, Print, PDF), column visibility toggles, and action buttons.
  - `Users/Create.vue`: Matches Image 2 form with all sections (Roles & Permissions, Sales, More Info, Bank Details, HRM/Payroll).
  - `Users/Edit.vue`: Edit existing user details.
  - `Users/Show.vue`: Detailed view card.
- **Roles**:
  - `Roles/Index.vue`: Matches Image 3 layout with roles table and action controls.
  - `Roles/Create.vue`: Matches Images 4, 5 and extended sets with 41 grouped permission categories and "Select all" toggle per group.
  - `Roles/Edit.vue`: Manage and update existing roles.
- **Sales Commission Agents**:
  - `SalesCommissionAgents/Index.vue`: Matches Image 3 table (Name, Email, Contact Number, Address, Sales Commission Percentage %, Action) with Export CSV, Excel, Print, Column visibility, Export PDF buttons and pagination.
  - `Add/Edit Modal`: Matches Image 4 modal (`Add sales commission agent`) with fields:
    - Prefix (`Mr / M`)
    - First Name* (`First Name`)
    - Last Name (`Last Name`)
    - Email (`Email`)
    - Contact Number (`Contact Number`)
    - Address (`Address` textarea)
    - Sales Commission Percentage (%) (`Sales Commission Percentage (%)`)
    - Save (purple) & Close (dark slate) buttons.
- **Shared Components**:
  - `Components/DataTableActions.vue`: Reusable action bar for client-side CSV/Excel/PDF export, print, and column visibility.
