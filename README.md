# POSmeister

> A professional, multi-branch Point-of-Sale management system built with **Laravel 13** and **Vue 3**. Designed to European commercial standards, with German retail workflows built in. SaaS-ready architecture.

![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-13.x-FF2D20?logo=laravel&logoColor=white)
![Vue](https://img.shields.io/badge/Vue-3.5-4FC08D?logo=vue.js&logoColor=white)
![Vite](https://img.shields.io/badge/Vite-8.x-646CFF?logo=vite&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind-4.x-06B6D4?logo=tailwindcss&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-green)

---

## Overview

POSmeister is a ground-up rebuild of a legacy CodeIgniter 3 point-of-sale system into a modern, production-quality web application. The project demonstrates a full product lifecycle from architecture design through to a functional, deployable system — covering authentication, multi-tenancy, a product catalogue, supplier management, and purchase order processing with automatic inventory updates.

The system is built for German-speaking markets: VAT rates follow §12 UStG (0 %, 7 %, 19 %), purchase numbers follow the Einkaufsbeleg format (`EK-YYYY-NNNNN`), and the UI ships in both English and German.

---

## Technology Stack

| Layer | Technology | Version |
|---|---|---|
| Backend framework | Laravel | ^13.0 |
| PHP | PHP | ^8.3 |
| Authentication | Laravel Sanctum | ^4.3 |
| Frontend framework | Vue 3 (Composition API) | ^3.5 |
| Build tool | Vite | ^8.0 |
| CSS framework | Tailwind CSS | ^4.0 |
| State management | Pinia | ^3.0 |
| Client-side routing | Vue Router | ^4.6 |
| Internationalization | Vue I18n | ^9.14 |
| HTTP client | Axios | ^1.8 |
| UI utilities | VueUse | ^14.2 |
| Alert/confirm dialogs | SweetAlert2 | ^11 |
| Icons | Heroicons | ^2.2 |
| Database (default) | SQLite / MySQL | — |

---

## What Is Built

### Phase 1 — Authentication & Core Infrastructure

- Token-based authentication using Laravel Sanctum. Login, logout, and a `/me` endpoint that returns the full user context and permission set.
- Session restore on page reload: if a valid token exists, the app rehydrates silently without a full login. Token expiry is handled globally — a 401 response anywhere triggers a clean logout.
- All API routes protected behind middleware. No endpoint is reachable without a valid token except `/api/auth/login`.

### Phase 2 — Branch Management & User Administration

- Full branch CRUD with soft-delete, status toggle, and search. Branches act as data tenants — non-admin users can only see and create data within their assigned branch.
- User management with role assignment (`admin`, `manager`, `cashier`), branch assignment, and activate/deactivate workflow.
- Configurable role-permission matrix: an admin can grant or revoke per-module access to Manager and Cashier roles at runtime through the Access Control screen, with no code changes required.
- Audit trail on every mutable record: `created_by` and `updated_by` are set automatically via Eloquent model events, not by controller code.

### Phase 3 — Product Catalogue & Application Settings

- Product catalogue with full CRUD, image upload (public storage with signed URL), and detail view.
- Product taxonomy: categories, brands, and units of measure each managed in their own admin screens with inline create/edit modals.
- Flexible pricing: cost price, selling price, wholesale price, minimum selling price, and profit margin (calculated field).
- German VAT integration: each product carries a VAT rate of 0 %, 7 % (reduced, e.g. food), or 19 % (standard). The default is drawn from application settings.
- Application settings module: company name, logo, address, phone, email, currency code, currency symbol, default VAT rate, invoice prefix, date format, and invoice footer. Settings are applied system-wide — the sidebar logo, currency symbols in all tables, and default VAT in the product form all respond to settings changes without a page reload.

### Phase 4 — Suppliers & Purchase Module

- Supplier directory with full CRUD, activate/deactivate, and auto-generated supplier codes (`SUP-000001`).
- Purchase order workflow: create draft → edit → receive. Only draft orders can be edited or deleted. Receiving a purchase is irreversible and updates stock atomically.
- Line items with per-line VAT calculation. Purchase totals include: subtotal (Σ qty × cost), per-line VAT, order-level discount, and freight/shipping charge. The grand total formula is `subtotal + VAT − discount + freight`.
- Automatic inventory management: receiving a purchase increments the `inventory` table for each product/branch pair using a database-level atomic increment (`UPDATE ... SET quantity = quantity + ?`), which is safe under concurrent requests.
- Purchase numbering follows the German Einkaufsbeleg convention: `EK-2026-00001`. The sequence is per-year and uses `withTrashed()` to prevent gaps when orders are deleted.
- The purchase form auto-fills unit cost and VAT rate when a product is selected, based on the product's stored values.

---

## Internationalization

The system ships in four languages:

| Code | Language | Direction |
|---|---|---|
| `en` | English | LTR |
| `de` | Deutsch | LTR |
| `bn` | বাংলা | LTR |
| `ar` | العربية | RTL |

Language selection is instant, with no page reload. For Arabic, `document.dir = 'rtl'` is applied at the document level. The selected language persists across sessions. All backend validation errors and API messages also respect the client's `Accept-Language` header.

---

## Prerequisites

- PHP 8.3+
- Composer 2.x
- Node.js 20+ and npm 10+
- SQLite (zero-config) or MySQL 8+

---

## Installation

```bash
# 1. Install PHP dependencies
composer install

# 2. Install Node.js dependencies
npm install

# 3. Configure environment
cp .env.example .env
php artisan key:generate
```

Edit `.env` for your database. SQLite works with no further configuration:

```env
DB_CONNECTION=sqlite
```

For MySQL:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=posmeister
DB_USERNAME=root
DB_PASSWORD=secret
```

```bash
# 4. Run migrations and seed
php artisan migrate --seed

# 5. Create storage symlink for uploaded files
php artisan storage:link
```

The seeder creates a default admin account. Credentials are defined in `database/seeders/DatabaseSeeder.php` — change them immediately before any production use.

---

## Running Locally

```bash
composer run dev
```

This starts the Laravel development server (`php artisan serve`) and the Vite HMR server concurrently. The application is available at `http://127.0.0.1:8000`.

---

## Production Build

```bash
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Vite outputs content-hashed assets to `public/build/`. Each route is automatically code-split into its own JavaScript chunk — users only download the code for pages they visit.

---

## Project Structure

```
app/
├── Http/Middleware/
│   ├── BranchScopeMiddleware.php   ← multi-tenant data isolation
│   ├── RoleMiddleware.php          ← role:admin, role:admin,manager
│   └── SetLocaleMiddleware.php     ← Accept-Language → App::setLocale
├── Modules/
│   ├── Branch/                     ← branch CRUD, soft-delete
│   ├── UserManagement/             ← user CRUD, roles, status
│   ├── RolePermission/             ← per-role module access matrix
│   ├── Settings/                   ← application-wide settings, logo
│   ├── Product/                    ← products, categories, brands, units
│   └── Purchase/                   ← suppliers, purchase orders, inventory
└── Traits/
    ├── HasAuditFields.php          ← auto created_by / updated_by
    └── BranchScoped.php            ← automatic branch WHERE clause

resources/js/
├── components/
│   ├── layout/                     ← AppShell, Sidebar, Topbar
│   └── ui/                         ← DataTable, FormField, StatusBadge
├── composables/
│   ├── useAlert.js                 ← SweetAlert2 toast + confirm wrappers
│   └── useLocale.js                ← locale switching
├── locales/                        ← en.json, de.json, bn.json, ar.json
├── services/                       ← Axios wrappers per resource
├── stores/                         ← Pinia: auth, settings
└── views/
    ├── auth/
    ├── branches/
    ├── dashboard/
    ├── products/
    ├── purchases/
    ├── settings/
    ├── suppliers/
    └── users/
```

---

## API Reference

All endpoints are prefixed `/api` and require `Authorization: Bearer <token>` unless marked public.

### Authentication

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| `POST` | `/auth/login` | Public | Authenticate, receive Sanctum token |
| `GET` | `/auth/me` | Any | Current user, role, and permissions |
| `POST` | `/auth/logout` | Any | Revoke current token |

### Branches & Users (Admin only)

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/branches` | List branches (paginated) |
| `GET` | `/branches/all` | All active branches (for dropdowns) |
| `POST` | `/branches` | Create branch |
| `PUT` | `/branches/{id}` | Update branch |
| `DELETE` | `/branches/{id}` | Soft-delete branch |
| `GET` | `/users` | List users (paginated) |
| `POST` | `/users` | Create user |
| `PUT` | `/users/{id}` | Update user |
| `PUT` | `/users/{id}/status` | Toggle active/inactive |
| `DELETE` | `/users/{id}` | Delete user |
| `GET` | `/role-permissions` | Get permission matrix |
| `PUT` | `/role-permissions/{role}` | Update permissions for a role |

### Settings

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| `GET` | `/settings` | Any | Get application settings |
| `PUT` | `/settings` | Admin | Update settings |
| `POST` | `/settings/logo` | Admin | Upload company logo |
| `DELETE` | `/settings/logo` | Admin | Remove company logo |

### Products (read: any auth; write: admin + manager)

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/products` | List products (paginated, filterable) |
| `GET` | `/products/all` | All active products (for dropdowns) |
| `GET` | `/products/search?q=` | Live product search |
| `GET` | `/products/{id}` | Product detail |
| `POST` | `/products` | Create product |
| `PUT` | `/products/{id}` | Update product |
| `PUT` | `/products/{id}/status` | Toggle active/inactive |
| `DELETE` | `/products/{id}` | Delete product |
| `POST` | `/products/{id}/image` | Upload product image |
| `DELETE` | `/products/{id}/image` | Remove product image |
| `GET` | `/categories` | List categories |
| `GET` | `/categories/all` | All categories (for dropdowns) |
| `POST/PUT/DELETE` | `/categories/{id}` | Category CRUD |
| `GET` | `/brands` | List brands |
| `GET` | `/brands/all` | All brands (for dropdowns) |
| `POST/PUT/DELETE` | `/brands/{id}` | Brand CRUD |
| `GET` | `/units` | List units |
| `GET` | `/units/all` | All units (for dropdowns) |
| `POST/PUT/DELETE` | `/units/{id}` | Unit CRUD |

### Suppliers & Purchases (read: any auth; write: admin + manager)

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/suppliers` | List suppliers (paginated) |
| `GET` | `/suppliers/all` | All active suppliers (for dropdowns) |
| `GET` | `/suppliers/{id}` | Supplier detail |
| `POST` | `/suppliers` | Create supplier |
| `PUT` | `/suppliers/{id}` | Update supplier |
| `PUT` | `/suppliers/{id}/status` | Toggle active/inactive |
| `DELETE` | `/suppliers/{id}` | Delete supplier |
| `GET` | `/purchases` | List purchases (paginated, filterable) |
| `GET` | `/purchases/{id}` | Purchase detail with line items |
| `POST` | `/purchases` | Create purchase (draft or receive immediately) |
| `PUT` | `/purchases/{id}` | Update purchase (draft only) |
| `PUT` | `/purchases/{id}/receive` | Mark as received, update inventory |
| `DELETE` | `/purchases/{id}` | Delete purchase (draft only) |

---

## Planned Modules

| Module | Scope |
|---|---|
| POS Terminal | Touch-optimised sales screen, receipt printing |
| Sales / Invoicing | Customer invoices, payment registration |
| Quotations | Customer quotes with status tracking |
| Inventory | Stock overview, low-stock alerts, adjustments |
| Customers | Customer records, purchase history |
| Finance | Cash flow, expense tracking |
| Reports | Sales analytics, stock reports, purchase history |

---

## License

MIT License. See [LICENSE](LICENSE) for details.
