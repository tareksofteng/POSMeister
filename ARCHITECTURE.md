# POSmeister — Architecture

This document describes the technical architecture of POSmeister: how the system is structured, how its major subsystems work, and the reasoning behind the key design decisions. It is kept up to date as new modules are implemented.

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Module Layout](#2-module-layout)
3. [Backend Architecture](#3-backend-architecture)
4. [Frontend Architecture](#4-frontend-architecture)
5. [Authentication & Session Flow](#5-authentication--session-flow)
6. [Role-Based Access Control](#6-role-based-access-control)
7. [Multi-Branch Data Isolation](#7-multi-branch-data-isolation)
8. [Internationalization](#8-internationalization)
9. [Database Design](#9-database-design)
10. [Purchase & Inventory Flow](#10-purchase--inventory-flow)
11. [Build & Deployment](#11-build--deployment)

---

## 1. System Overview

POSmeister is a **Single-Page Application (SPA)** backed by a JSON REST API.

```
┌──────────────────────────────────────────────────────┐
│                     Browser (SPA)                    │
│                                                      │
│   Vue 3 · Pinia · Vue Router · Vue I18n · Tailwind   │
│                                                      │
│  ┌────────────┐  ┌─────────────┐  ┌───────────────┐  │
│  │   Stores   │  │   Router    │  │  Composables  │  │
│  │ auth/sett. │  │(guards+meta)│  │ useAlert      │  │
│  └────────────┘  └─────────────┘  └───────────────┘  │
└─────────────────────────┬────────────────────────────┘
                          │ HTTPS  Bearer token
┌─────────────────────────▼────────────────────────────┐
│                Laravel 13 API Server                 │
│                                                      │
│  Middleware chain → Controller → Service → Resource  │
│                                                      │
│  ┌──────────┐  ┌────────────────────┐  ┌──────────┐  │
│  │ Sanctum  │  │   app/Modules/     │  │  Traits  │  │
│  │  (Auth)  │  │ Branch / Product / │  │ Audit /  │  │
│  └──────────┘  │ Purchase / etc.    │  │ Scoped   │  │
│                └────────────────────┘  └──────────┘  │
└─────────────────────────┬────────────────────────────┘
                          │
┌─────────────────────────▼────────────────────────────┐
│                      Database                        │
│              SQLite (dev) / MySQL (prod)             │
└──────────────────────────────────────────────────────┘
```

The Vue SPA is served by a single Blade view. All routes — including the `/{any}` catch-all — return the same Blade view, and client-side routing handles navigation entirely.

---

## 2. Module Layout

All domain business logic lives under `app/Modules/`. Each module is self-contained and follows the same four-layer structure:

```
app/Modules/
├── Branch/
│   ├── Controllers/BranchController.php
│   ├── Models/Branch.php
│   ├── Requests/StoreBranchRequest.php
│   │           UpdateBranchRequest.php
│   ├── Resources/BranchResource.php
│   └── Services/BranchService.php
│
├── UserManagement/
├── RolePermission/
│
├── Settings/
│   ├── Controllers/SettingsController.php
│   ├── Models/Setting.php
│   ├── Requests/UpdateSettingsRequest.php
│   ├── Resources/SettingsResource.php
│   └── Services/SettingsService.php
│
├── Product/
│   ├── Controllers/
│   │   ├── ProductController.php
│   │   ├── CategoryController.php
│   │   ├── BrandController.php
│   │   └── UnitController.php
│   ├── Models/
│   │   ├── Product.php
│   │   ├── Category.php (→ product_categories table)
│   │   ├── Brand.php
│   │   ├── Unit.php
│   │   └── Inventory.php
│   ├── Requests/   ← Store + Update variants per resource
│   ├── Resources/  ← ProductResource, CategoryResource, etc.
│   └── Services/   ← ProductService, CategoryService, etc.
│
└── Purchase/
    ├── Controllers/
    │   ├── SupplierController.php
    │   └── PurchaseController.php
    ├── Models/
    │   ├── Supplier.php
    │   ├── Purchase.php
    │   └── PurchaseItem.php
    ├── Requests/
    │   ├── StoreSupplierRequest.php
    │   └── StorePurchaseRequest.php
    ├── Resources/
    │   ├── SupplierResource.php
    │   └── PurchaseResource.php
    └── Services/
        ├── SupplierService.php
        └── PurchaseService.php
```

---

## 3. Backend Architecture

### 3.1 Four-layer pattern

Each module follows a strict four-layer pattern:

| Layer | Responsibility |
|---|---|
| **Controller** | Reads HTTP request, delegates to Service, wraps result in Resource |
| **Service** | All business logic and database queries — no HTTP or JSON concerns |
| **Resource** | Transforms Eloquent models into API-safe JSON, handles type casting |
| **Request** | Validation rules, decoupled from controller logic |

Controllers are intentionally thin. They never contain `if` statements for business rules, calculations, or database queries.

### 3.2 Middleware stack

Every protected API request passes through this chain:

```
1. SetLocaleMiddleware     reads Accept-Language → App::setLocale
2. auth:sanctum            validates Bearer token
3. BranchScopeMiddleware   sets app('pos.activeBranchId')
4. RoleMiddleware          role:admin  /  role:admin,manager  (where applicable)
```

### 3.3 Traits

**`HasAuditFields`** registers `creating` and `updating` Eloquent model events to set `created_by` and `updated_by` from `auth()->id()`. No controller code is needed — any model using this trait automatically gets audit fields.

**`BranchScoped`** registers a global Eloquent scope that adds `WHERE branch_id = :activeBranchId` to every query on the model. Controllers and services never need to add branch conditions manually.

### 3.4 Service design

Services are plain PHP classes. They do not extend any framework base class, do not use traits for business logic, and take plain arrays or Eloquent models as arguments. This makes them easy to test in isolation.

The PurchaseService demonstrates the most complex service in the system:

```php
// All three operations happen in a single DB transaction
public function store(array $data): Purchase
{
    return DB::transaction(function () use ($data) {
        $totals   = $this->calculateTotals($data['items'], $data);
        $purchase = Purchase::create([...]);
        $this->syncItems($purchase, $data['items']);

        if ($data['receive'] ?? false) {
            $this->receiveStock($purchase);   // updates inventory atomically
        }

        return $purchase->fresh(['supplier', 'branch', 'items.product']);
    });
}
```

### 3.5 Type safety in API responses

Laravel's `decimal:2` cast serializes numeric columns to strings (`"19.00"`, not `19.0`). Resources explicitly cast these to `float` before returning JSON, preventing Vue's `v-model` and comparison logic from receiving unexpected string values:

```php
// In PurchaseResource
'total_amount' => (float) $this->total_amount,
'vat_rate'     => (float) $this->vat_rate,
```

---

## 4. Frontend Architecture

### 4.1 Application entry

`resources/js/app.js` mounts the Vue application after registering plugins in the correct order. The i18n plugin must come first so translated strings are available to the router's `afterEach` title hook.

### 4.2 Layout

The authenticated layout is managed by `AppShell.vue`, which boots the application state on mount:

```js
onMounted(async () => {
    await auth.fetchMe();      // restore session from localStorage
    settingsStore.load();      // load application settings once
});
```

Settings (company name, logo, currency) are loaded once and stored in Pinia. Any component that needs the currency symbol or VAT default reads from `useSettingsStore()` reactively — if the admin changes settings, the UI updates without a page reload.

### 4.3 The Sidebar nav config

The sidebar is driven entirely by a static `NAV_GROUPS` data structure. Each item declares:

- `permKey` — which permission key to check (or `null` for always-visible items)
- `labelKey` — the i18n key for the label
- `to` — the Vue Router route object
- `implemented` — controls whether the link is active or shows a "Soon" badge

The `visibleGroups` computed filters by `auth.hasPermission(permKey)`. Adding a new module requires only adding an entry to the array.

### 4.4 UI component library

`components/ui/` is a small internal library:

| Component | Purpose |
|---|---|
| `DataTable.vue` | Paginated table with loading skeleton, empty state, column format functions, and row-action slots |
| `FormField.vue` | Label + input wrapper with error display |
| `StatusBadge.vue` | Active/inactive pill |
| `LanguageSwitcher.vue` | Locale dropdown with RTL indicator |

DataTable accepts a `columns` array with optional `format` functions, allowing currency formatting to be defined at the column level:

```js
{ key: 'total_amount', label: 'Total', format: (v) => formatCurrency(v) }
```

### 4.5 SweetAlert2 integration

All user-triggered confirmations and notifications use a shared `useAlert.js` composable rather than native `alert()` or a custom confirm component:

```js
// Confirm before a destructive action
const ok = await confirm({ title: 'Delete?', danger: true });
if (!ok) return;

// Notify on success
toast('success', 'Deleted successfully.');
```

This composable is used consistently across all views and prevents the UI from having a mix of native browser dialogs and custom modals.

### 4.6 Services

Each API resource has a thin Axios wrapper in `resources/js/services/`. Services own no state and perform no error handling — that belongs to the view. They exist only to centralise the URL construction:

```js
export const purchaseService = {
    index:   (params) => api.get('/purchases', { params }),
    store:   (data)   => api.post('/purchases', data),
    receive: (id)     => api.put(`/purchases/${id}/receive`),
};
```

---

## 5. Authentication & Session Flow

### Login

```
User enters credentials
        │
        ▼
auth.login() → POST /api/auth/login
        │
        ▼
Laravel validates, creates Sanctum token, loads permissions
        │
        ▼
{ token, user, permissions } stored in localStorage
        │
        ▼
router.push('/dashboard')
```

### Page reload

```
Token found in localStorage
        │
        ▼
auth.fetchMe() → GET /api/auth/me
        │
   ┌────┴────┐
200 OK     401 Expired
   │           │
   ▼           ▼
Rehydrate   auth:expired event
store       → clear localStorage
            → redirect to /login
```

The `auth:expired` event is dispatched by Axios's response interceptor on any 401 from the API. The auth store listens for it. This means expired-session handling works identically everywhere in the app without any per-component code.

---

## 6. Role-Based Access Control

Three roles: `admin`, `manager`, `cashier`.

Admin always has full access. This is enforced in `hasPermission()` — admins bypass the permissions array entirely and cannot be accidentally locked out by a misconfigured permission matrix.

Manager and Cashier permissions are stored as rows in `role_permissions`. The admin manages this through the Access Control screen. When a role's permissions change, the next API call to `/me` picks up the new set. Currently active users are not affected until their token is refreshed.

Permission checks run in three places:
1. **Route guard** (`meta.permission`) — prevents navigation to inaccessible pages
2. **Sidebar** — hides nav items the user cannot access
3. **API middleware** (`role:admin,manager`) — enforces access at the server, regardless of any client-side state

---

## 7. Multi-Branch Data Isolation

`BranchScopeMiddleware` resolves the active branch on every request:

- Non-admin users: always their own `branch_id`. They cannot access another branch's data even by manipulating request parameters.
- Admins: can pass `?branch_id=X` to switch context, useful for cross-branch reports.

The resolved value is bound to the IoC container (`app('pos.activeBranchId')`). The `BranchScoped` trait reads this in a global Eloquent scope. The scope is invisible to service-layer code — a service simply calls `Product::all()` and the WHERE clause is added automatically.

---

## 8. Internationalization

### Architecture split

| Half | Technology | Scope |
|---|---|---|
| Frontend | Vue I18n v9 | All UI text |
| Backend | Laravel `__()` | Validation errors, API messages |

### Frontend

`plugins/i18n.js` reads `pos_locale` from `localStorage` before the Vue app mounts. It applies `document.dir` and `document.lang` at this point so Arabic RTL layout is active before the first render.

The `useLocale.js` composable owns locale switching. It updates the Vue I18n locale ref, persists the choice, and flips `document.dir` — no page reload required.

Locale files use dot-notated keys grouped by feature section. Column definitions in DataTable use `computed()` arrays so translated headers update immediately when the locale changes.

One rule enforced by Vue I18n: the `@` character must be escaped as `{'@'}` in all locale strings, because `@` is reserved for linked messages. All email placeholder values follow this rule.

### Backend

`SetLocaleMiddleware` reads `Accept-Language` on every request and sets `App::setLocale()`. Laravel's validation error messages are returned in the user's language automatically.

---

## 9. Database Design

### Schema

```
branches
  id, code (UNIQUE), name, phone, email, address
  is_active, created_by, updated_by
  deleted_at (soft delete), timestamps

users
  id, name, email (UNIQUE), phone, password
  role ENUM(admin, manager, cashier)
  branch_id → branches.id
  is_active, timestamps

role_permissions
  id, role, module
  UNIQUE(role, module), timestamps

settings
  id (always 1 — singleton row)
  company_name, address, phone, email
  logo (storage path)
  currency_code, currency_symbol
  vat_default, invoice_prefix, invoice_footer, date_format
  timestamps

product_categories
  id, name, description
  created_by, updated_by, deleted_at, timestamps

brands
  id, name, description
  created_by, updated_by, deleted_at, timestamps

units
  id, name, symbol, description
  created_by, updated_by, deleted_at, timestamps

products
  id, sku (UNIQUE), name, description
  category_id → product_categories.id
  brand_id    → brands.id
  unit_id     → units.id
  barcode, cost_price, selling_price, wholesale_price
  min_selling_price, tax_rate (0|7|19), reorder_level
  is_service, is_active, image
  created_by, updated_by, deleted_at, timestamps

inventory
  id, product_id → products.id, branch_id → branches.id
  quantity, low_stock_alert
  UNIQUE(product_id, branch_id)
  timestamps

suppliers
  id, code (SUP-000001, auto-generated), name
  contact_person, email, phone, address, city
  country (default: Deutschland), vat_number, notes
  is_active
  created_by, updated_by, deleted_at, timestamps

purchases
  id, purchase_number (EK-YYYY-NNNNN), branch_id, supplier_id
  purchase_date, status ENUM(draft, received)
  reference, notes
  subtotal, discount_amount, vat_amount, freight_amount, total_amount
  created_by, updated_by, deleted_at, timestamps

purchase_items
  id, purchase_id → purchases.id (CASCADE DELETE)
  product_id      → products.id  (RESTRICT DELETE)
  quantity, unit_cost, vat_rate, vat_amount, line_total
  timestamps
```

### Design decisions

**`settings` as a singleton row** — `Setting::firstOrCreate(['id' => 1], $defaults)` guarantees there is always exactly one settings row. No secondary lookup is needed anywhere in the application.

**`decimal:2` cast + explicit float conversion** — Eloquent's `decimal:2` cast serializes to a string in JSON (`"19.00"`). Resources explicitly cast to `float` before returning responses to prevent Vue's type-sensitive comparisons from failing silently.

**Purchase status as a two-state enum** — `draft` and `received`. The `isDraft()` method on the model gates all mutations. Once received, a purchase cannot be edited, deleted, or re-received. This is intentional — received purchases are part of the stock history.

**Atomic inventory increment** — Receiving a purchase uses `Inventory::increment('quantity', $qty)` rather than a read-modify-write pattern. This translates to `UPDATE inventory SET quantity = quantity + ? WHERE ...`, which is safe under concurrent requests without application-level locking.

**`withTrashed()` in purchase number generation** — The sequential counter queries `Purchase::withTrashed()->whereYear()->max('purchase_number')`. This ensures deleted draft purchases don't cause number gaps or duplicates after deletion.

**`purchase_items.product_id` as RESTRICT** — Deleting a product that has been purchased should fail loudly, not silently break historical stock records. The foreign key is RESTRICT, not CASCADE.

---

## 10. Purchase & Inventory Flow

```
Create Purchase (draft)
        │
        ▼
User fills: supplier, date, line items (product / qty / cost / VAT%)
        │
        ▼
Frontend calculates totals live:
  lineVAT   = round(qty × cost × (vatRate / 100), 2)
  lineTotal = lineBase + lineVAT
  subtotal  = Σ lineBase
  vatAmount = Σ lineVAT
  grandTotal = subtotal + vatAmount − discount + freight
        │
        ▼
POST /api/purchases   (or PUT to update draft)
        │
        ▼
PurchaseService::store()
  ├── DB::transaction
  ├── calculateTotals()  ← server recalculates, never trusts client totals
  ├── Purchase::create()
  ├── syncItems()        ← deletes old items, inserts new
  └── if receive=true → receiveStock()
        │
        ▼
receiveStock():
  foreach item:
    Inventory::firstOrCreate([product_id, branch_id], [qty=0])
    Inventory::increment('quantity', item.quantity)  ← atomic
  Purchase::update(['status' => 'received'])

Received purchase: READ ONLY
  ├── Cannot be edited  (isDraft() guard in update)
  ├── Cannot be deleted (isDraft() guard in delete)
  └── Cannot be received again (isDraft() guard in receive)
```

The server always recalculates totals from the submitted line items. The client-side totals shown in the form are for display only and are never trusted by the API.

---

## 11. Build & Deployment

### Development

```bash
composer run dev
# starts: php artisan serve (port 8000)
#         npm run dev       (Vite HMR, port 5173)
```

### Production

```bash
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Vite code-splits automatically — each route component becomes its own hashed chunk. The result:

```
public/build/assets/
├── app-<hash>.css          ← full Tailwind output
├── app-<hash>.js           ← Vue app core + Pinia + Router
├── i18n-<hash>.js          ← all locale JSON merged
├── useAlert-<hash>.js      ← SweetAlert2 (largest single chunk)
├── PurchaseFormView-<hash>.js
├── SupplierListView-<hash>.js
├── ProductListView-<hash>.js
└── ...one chunk per view
```

Users loading the dashboard do not download the purchase form code until they navigate there.
