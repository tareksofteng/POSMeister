# POSmeister

> A professional, multi-branch Point-of-Sale management system built with **Laravel 13** and **Vue 3**. Designed to European commercial standards. SaaS-ready architecture.

![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-13.x-FF2D20?logo=laravel&logoColor=white)
![Vue](https://img.shields.io/badge/Vue-3.5-4FC08D?logo=vue.js&logoColor=white)
![Vite](https://img.shields.io/badge/Vite-8.x-646CFF?logo=vite&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind-4.x-06B6D4?logo=tailwindcss&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-green)

---

## Overview

POSmeister is a ground-up rebuild of a legacy CodeIgniter 3 point-of-sale system. The goal is a modern, maintainable, and internationally deployable product suitable for small-to-medium retail businesses.

**Current phase:** Phase 1 — Core Infrastructure (complete)
- Authentication & session management
- Multi-branch administration
- User management with role-based access control
- Full internationalization (English, German, Bengali, Arabic)

---

## Technology Stack

| Layer | Technology | Version |
|---|---|---|
| Backend framework | Laravel | ^13.0 |
| PHP | PHP | ^8.3 |
| Authentication | Laravel Sanctum | ^4.3 |
| Frontend framework | Vue 3 (Composition API) | ^3.5.32 |
| Build tool | Vite | ^8.0.0 |
| CSS framework | Tailwind CSS | ^4.0.0 |
| State management | Pinia | ^3.0.4 |
| Client-side routing | Vue Router | ^4.6.4 |
| Internationalization | Vue I18n | ^9.14.5 |
| Icons | Heroicons (Vue) | ^2.2.0 |
| HTTP client | Axios | ^1.8.0 |
| Utilities | VueUse | ^14.2.1 |
| Database (default) | SQLite / MySQL | — |

---

## Features

### Phase 1 — Complete

- **Token-based authentication** via Laravel Sanctum. Login, logout, and `/me` endpoint with full user context.
- **Branch management** — full CRUD with soft-delete, status toggle, and search/filter.
- **User management** — full CRUD with role assignment, branch assignment, and activate/deactivate actions.
- **Role-based access control (RBAC)** — admin-configurable permission matrix. Manager and Cashier roles can have per-module access granted or revoked at runtime without code changes.
- **Multi-branch scoping** — non-admin users are automatically scoped to their branch. Audit fields (`created_by`, `updated_by`) are set automatically via model events.
- **Internationalization** — UI fully translated in English, German, Bengali, and Arabic. RTL layout support for Arabic. Language persists across sessions via `localStorage`. Backend error messages also respect the client's `Accept-Language` header.
- **Reactive page titles** — browser tab title updates on each navigation using translated route keys.
- **Responsive layout** — works on desktop and mobile. Sidebar collapses on small screens.

### Planned Phases

| Phase | Scope | Status |
|---|---|---|
| Phase 2 | Products & inventory management | Planned |
| Phase 3 | POS terminal (sales, transactions) | Planned |
| Phase 4 | Reports & analytics dashboard | Planned |
| Phase 5 | Accounting & finance module | Planned |

---

## Prerequisites

- PHP 8.3+
- Composer 2.x
- Node.js 20+ and npm 10+
- SQLite (default) or a running MySQL/PostgreSQL instance

---

## Installation

### 1. Clone the repository

```bash
git clone <repository-url>
cd posmeister_Laravel13_vue_3
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Install Node.js dependencies

```bash
npm install
```

### 4. Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` to set your database connection. The default is SQLite:

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

### 5. Run migrations and seed initial data

```bash
php artisan migrate --seed
```

This creates the database schema and seeds:
- A default **admin** user
- Default **role permission** matrix (Manager: 9 modules, Cashier: 3 modules)

> **Default admin credentials** are set in `database/seeders/DatabaseSeeder.php`. Change them immediately in production.

---

## Development

Run the Laravel development server and Vite dev server concurrently:

```bash
composer run dev
```

This is equivalent to running both:

```bash
php artisan serve          # http://127.0.0.1:8000
npm run dev                # Vite HMR server
```

---

## Production Build

```bash
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

The compiled frontend assets are output to `public/build/`.

---

## Project Structure

```
posmeister_Laravel13_vue_3/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/Auth/AuthController.php
│   │   └── Middleware/
│   │       ├── BranchScopeMiddleware.php
│   │       ├── RoleMiddleware.php
│   │       └── SetLocaleMiddleware.php
│   ├── Models/
│   │   └── User.php
│   ├── Modules/
│   │   ├── Branch/
│   │   │   ├── Controllers/BranchController.php
│   │   │   ├── Models/Branch.php
│   │   │   ├── Requests/
│   │   │   ├── Resources/BranchResource.php
│   │   │   └── Services/BranchService.php
│   │   ├── UserManagement/
│   │   │   ├── Controllers/UserController.php
│   │   │   ├── Requests/
│   │   │   ├── Resources/UserResource.php
│   │   │   └── Services/UserService.php
│   │   └── RolePermission/
│   │       ├── Controllers/RolePermissionController.php
│   │       ├── Models/RolePermission.php
│   │       └── Services/RolePermissionService.php
│   ├── Providers/AppServiceProvider.php
│   └── Traits/
│       ├── BranchScoped.php
│       └── HasAuditFields.php
├── bootstrap/app.php
├── database/
│   ├── migrations/
│   └── seeders/
├── resources/
│   ├── css/app.css
│   ├── js/
│   │   ├── app.js
│   │   ├── components/
│   │   │   ├── layout/
│   │   │   └── ui/
│   │   ├── composables/
│   │   ├── locales/          ← en.json, de.json, bn.json, ar.json
│   │   ├── plugins/i18n.js
│   │   ├── router/index.js
│   │   ├── services/
│   │   ├── stores/
│   │   └── views/
│   ├── lang/                 ← Backend translations (en, de, bn, ar)
│   └── views/app.blade.php
└── routes/
    ├── api.php
    └── web.php
```

---

## API Reference

All API endpoints are prefixed with `/api`.

### Public

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/auth/login` | Authenticate and receive a Sanctum token |

### Authenticated (`Authorization: Bearer <token>`)

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/auth/me` | Get the current user with permissions |
| `POST` | `/api/auth/logout` | Revoke the current token |

### Admin only

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/branches` | List branches (paginated, filterable) |
| `POST` | `/api/branches` | Create a branch |
| `GET` | `/api/branches/all` | All active branches (for dropdowns) |
| `GET` | `/api/branches/{id}` | Get a single branch |
| `PUT` | `/api/branches/{id}` | Update a branch |
| `DELETE` | `/api/branches/{id}` | Soft-delete a branch |
| `GET` | `/api/users` | List users (paginated, filterable) |
| `POST` | `/api/users` | Create a user |
| `GET` | `/api/users/{id}` | Get a single user |
| `PUT` | `/api/users/{id}` | Update a user |
| `DELETE` | `/api/users/{id}` | Delete a user |
| `PUT` | `/api/users/{id}/status` | Toggle active/inactive status |
| `GET` | `/api/role-permissions` | Get permission matrix for all roles |
| `PUT` | `/api/role-permissions/{role}` | Update module access for a role |

### Login response shape

```json
{
  "token": "1|abc...",
  "user": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@example.com",
    "role": "admin",
    "branch_id": null,
    "is_active": true
  },
  "permissions": ["pos", "sales", "branches", "users", "..."]
}
```

---

## Internationalization

The system supports four languages out of the box:

| Code | Language | Direction | Intl Locale |
|---|---|---|---|
| `en` | English | LTR | en-US |
| `de` | Deutsch | LTR | de-DE |
| `bn` | বাংলা | LTR | bn-BD |
| `ar` | العربية | RTL | ar-SA |

**Frontend:** The active locale is stored in `localStorage` under `pos_locale`. Changing language is instant with no page reload. For Arabic, `document.documentElement.dir` is set to `rtl`, applying browser-native RTL layout.

**Backend:** All validation errors and auth messages are served in the client's language. The `SetLocaleMiddleware` reads the `Accept-Language` header (or `?lang=` query parameter) on every API request.

To add a new language, create the locale file in `resources/js/locales/<code>.json`, add the entry to `SUPPORTED_LOCALES` in `resources/js/plugins/i18n.js`, and create `resources/lang/<code>/auth.php` and `validation.php`.

---

## Role & Permission System

Three roles are defined: `admin`, `manager`, `cashier`.

- **Admin** always has full access to all modules. This is enforced in code and cannot be changed via the UI.
- **Manager** and **Cashier** permissions are stored in the `role_permissions` table and managed by the admin through the Role Permissions settings screen.
- Permissions are included in every API token response and stored in `localStorage`. The frontend sidebar, route guards, and UI elements all check permissions without additional API calls.

Available modules that can be toggled per role:

`pos` · `sales` · `purchases` · `quotations` · `products` · `inventory` · `customers` · `suppliers` · `finance` · `employees` · `reports` · `branches` · `users`

---

## License

MIT License. See [LICENSE](LICENSE) for details.
