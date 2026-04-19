# POSmeister — Architecture

This document describes the technical architecture of POSmeister: how the system is structured, how its major subsystems work, and the reasoning behind key design decisions.

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Repository Layout](#2-repository-layout)
3. [Backend Architecture](#3-backend-architecture)
4. [Frontend Architecture](#4-frontend-architecture)
5. [Authentication Flow](#5-authentication-flow)
6. [Role-Based Access Control](#6-role-based-access-control)
7. [Multi-Branch Scoping](#7-multi-branch-scoping)
8. [Internationalization](#8-internationalization)
9. [Database Design](#9-database-design)
10. [Request Lifecycle](#10-request-lifecycle)
11. [Build & Deployment](#11-build--deployment)

---

## 1. System Overview

POSmeister is a **Single-Page Application (SPA)** with a JSON REST API backend.

```
┌─────────────────────────────────────────────────┐
│                   Browser (SPA)                 │
│                                                 │
│  Vue 3 + Vite + Pinia + Vue Router + Vue I18n   │
│                                                 │
│  ┌──────────┐  ┌────────────┐  ┌─────────────┐ │
│  │  Stores  │  │   Router   │  │  Composables│ │
│  │ (Pinia)  │  │(Vue Router)│  │  (useLocale)│ │
│  └──────────┘  └────────────┘  └─────────────┘ │
└──────────────────────┬──────────────────────────┘
                       │ HTTPS (JSON / Bearer token)
┌──────────────────────▼──────────────────────────┐
│               Laravel 13 API Server             │
│                                                 │
│  Middleware → Controller → Service → Resource   │
│                                                 │
│  ┌──────────┐  ┌──────────────┐  ┌───────────┐ │
│  │ Sanctum  │  │   Modules    │  │   Traits  │ │
│  │  (Auth)  │  │(Branch/User) │  │(Scoped/   │ │
│  └──────────┘  └──────────────┘  │ Audit)    │ │
│                                  └───────────┘ │
└──────────────────────┬──────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────┐
│                   Database                      │
│           SQLite (dev) / MySQL (prod)           │
└─────────────────────────────────────────────────┘
```

The Vue SPA is served by a single Blade view (`resources/views/app.blade.php`). All other routes, including the catch-all `/{any}`, return the same view — client-side routing handles navigation entirely.

---

## 2. Repository Layout

The project follows Laravel's standard directory conventions with one extension: a `Modules/` directory inside `app/` that groups business logic by domain.

```
app/
├── Http/
│   ├── Controllers/Api/Auth/   ← Authentication only
│   └── Middleware/             ← BranchScope, Role, SetLocale
├── Models/
│   └── User.php                ← Core user model (Sanctum)
├── Modules/                    ← All domain logic lives here
│   ├── Branch/
│   ├── UserManagement/
│   └── RolePermission/
├── Providers/
└── Traits/
    ├── BranchScoped.php
    └── HasAuditFields.php

resources/js/
├── app.js                      ← Vue app entry point
├── bootstrap.js                ← Axios global setup
├── components/
│   ├── layout/                 ← AppShell, Sidebar, Topbar
│   └── ui/                     ← DataTable, Modal, FormField, etc.
├── composables/
│   └── useLocale.js
├── locales/                    ← en.json, de.json, bn.json, ar.json
├── plugins/
│   └── i18n.js                 ← Vue I18n instance
├── router/
│   └── index.js
├── services/                   ← Thin Axios wrappers per resource
├── stores/                     ← Pinia stores (auth, branch)
└── views/                      ← Page-level Vue components
```

---

## 3. Backend Architecture

### 3.1 Modular structure

Each business domain lives in `app/Modules/<Name>/` and contains four layers:

| Layer | File | Responsibility |
|---|---|---|
| Controller | `Controllers/<Name>Controller.php` | HTTP request handling, delegates to Service |
| Service | `Services/<Name>Service.php` | Business logic, database queries |
| Resource | `Resources/<Name>Resource.php` | JSON response shaping |
| Request | `Requests/<Store|Update><Name>Request.php` | Validation rules |

Controllers are intentionally thin. They validate input (via Form Requests), call the Service, and return a Resource. No business logic lives in controllers.

Example flow for `GET /api/users`:

```
UserController@index
  → UserService::paginate($filters)
    → User::query()->with('branch')->paginate()
  ← UserResource::collection($paginated)
  ← JsonResponse { data: [...], meta: {...} }
```

### 3.2 Middleware stack

Every API request passes through this middleware chain (in order):

```
1. EnsureFrontendRequestsAreStateful  (Sanctum SPA cookie support)
2. SetLocaleMiddleware                 (reads Accept-Language → App::setLocale)
3. auth:sanctum                        (token validation — on protected routes)
4. BranchScopeMiddleware              (sets app('pos.activeBranchId'))
5. RoleMiddleware                      (role:admin, role:admin,manager, etc.)
```

### 3.3 Traits

**`BranchScoped`** — Registers a global Eloquent scope that automatically filters queries to the active branch. Also auto-sets `branch_id` on `creating`. Models that need multi-tenant data isolation use this trait.

```php
// Any model using BranchScoped:
User::all(); // automatically WHERE branch_id = :active_branch
User::allBranches()->get(); // escape hatch for reports
```

**`HasAuditFields`** — Listens to `creating` and `updating` Eloquent events and sets `created_by` / `updated_by` from the authenticated user's ID, without any controller involvement.

### 3.4 Service layer pattern

Services are plain PHP classes, not Laravel Services in any specific framework sense. They take filter arrays or Eloquent models as input and return paginated results, collections, or single models.

```php
// BranchService example
public function paginate(array $filters): LengthAwarePaginator
{
    return Branch::query()
        ->when($filters['search'] ?? null, fn($q, $v) => $q->where('name', 'like', "%$v%"))
        ->when(isset($filters['is_active']) && $filters['is_active'] !== '', ...)
        ->orderBy('name')
        ->paginate($filters['per_page'] ?? 15);
}
```

---

## 4. Frontend Architecture

### 4.1 Application shell

The Vue application mounts at `<div id="app">` in `app.blade.php`. The entry point `resources/js/app.js` registers plugins in this order:

```js
createApp(App)
  .use(i18n)       // vue-i18n (locale already applied to document before mount)
  .use(pinia)      // Pinia store
  .use(router)     // Vue Router (auth guards registered)
  .mount('#app')
```

### 4.2 Layout components

The authenticated layout is managed by `AppShell.vue`:

```
AppShell.vue
├── Sidebar.vue         ← Data-driven nav, permission-filtered
│   ├── NavItem.vue     ← Individual nav link with active state
│   └── SidebarSectionLabel.vue
└── Topbar.vue          ← Breadcrumb, LanguageSwitcher, user menu
    └── LanguageSwitcher.vue
```

The `Sidebar` is driven entirely by a computed `NAV_GROUPS` data structure. Each item has `permKey`, `labelKey`, and `sectionKey` fields. The `visibleGroups` computed filters items through `auth.hasPermission(permKey)`. Adding a new module to the sidebar requires only a new object in the array — no template changes.

### 4.3 UI component library

The `components/ui/` directory is a small internal component library shared by all views:

| Component | Purpose |
|---|---|
| `DataTable.vue` | Generic table with pagination, loading skeletons, empty state, row-action slots |
| `Modal.vue` | Accessible modal with header, scrollable body, and footer slots |
| `FormField.vue` | Label + input wrapper with error message and required indicator |
| `ConfirmDialog.vue` | Destructive action confirmation modal with loading state |
| `StatusBadge.vue` | Active/inactive pill badge |
| `LanguageSwitcher.vue` | Locale dropdown with flag, label, and RTL indicator |

### 4.4 Services layer

Each API resource has a corresponding service file in `resources/js/services/`. Services are thin wrappers that call the pre-configured Axios instance in `api.js`. They own no state.

```js
// userService.js
export const userService = {
  index: (params) => api.get('/users', { params }),
  store: (data)   => api.post('/users', data),
  update: (id, data) => api.put(`/users/${id}`, data),
  destroy: (id)   => api.delete(`/users/${id}`),
  toggleStatus: (id) => api.put(`/users/${id}/status`),
};
```

`api.js` handles two cross-cutting concerns:
1. **Request interceptor** — injects `Authorization: Bearer <token>` from `localStorage` on every outgoing request.
2. **Response interceptor** — on `401`, dispatches a custom `auth:expired` DOM event. The auth store listens for this event and clears the session.

### 4.5 State management (Pinia)

Two stores manage global state:

**`auth.js`**
- Persists `token`, `user`, and `permissions` to `localStorage`.
- `hasPermission(key)` returns `true` for admins unconditionally, otherwise checks the `permissions` array.
- `fetchMe()` is called on initial load to rehydrate state from the API if a token exists.

**`branch.js`**
- Two modes: paginated list (for the Branches admin screen) and a flat `allActive` list (for dropdowns in other forms).
- `fetchAllActive()` is idempotent — it skips the API call if data is already loaded (`allActiveLoaded` flag).
- `branchOptions` is a computed getter that transforms `allActive` into `{ value, label }` pairs for `<select>` elements.

### 4.6 Route guards

`router/index.js` registers two navigation guards:

**`beforeEach`** — runs on every navigation:
1. If the route requires auth and there is no token → redirect to `/login`
2. If the route requires guest (login page) and user is authenticated → redirect to `/dashboard`
3. If `meta.adminOnly` is set and user is not admin → redirect to `/dashboard`
4. If `meta.permission` is set and user lacks that permission → redirect to `/dashboard`

**`afterEach`** — translates the route's `meta.titleKey` into the current locale and sets `document.title`.

---

## 5. Authentication Flow

### Login

```
Browser                     Vue (auth store)              Laravel API
   │                              │                            │
   │── enter credentials ────────►│                            │
   │                              │── POST /api/auth/login ───►│
   │                              │                            │── validate credentials
   │                              │                            │── Hash::check(password)
   │                              │                            │── create Sanctum token
   │                              │                            │── load permissions
   │                              │◄── { token, user, perms } ─│
   │                              │── save to localStorage      │
   │                              │── router.push('/dashboard') │
   │◄── dashboard renders ────────│                            │
```

### Session restore (page reload)

On every page load, if a token exists in `localStorage`, `auth.fetchMe()` calls `GET /api/auth/me`. This refreshes the user object and permissions without requiring a re-login. If the token is expired or revoked, the 401 response triggers `auth:expired`, which logs the user out and redirects to login.

### Logout

```
Browser         Vue (auth store)        Laravel API
   │                  │                      │
   │── click logout ─►│                      │
   │                  │── POST /auth/logout ─►│── revoke token
   │                  │── clear localStorage  │
   │                  │── router.push('/login')
```

---

## 6. Role-Based Access Control

### Data model

```
role_permissions table
┌────────────┬──────────────┐
│ role       │ module       │
├────────────┼──────────────┤
│ manager    │ pos          │
│ manager    │ sales        │
│ manager    │ products     │
│ ...        │ ...          │
│ cashier    │ pos          │
│ cashier    │ sales        │
│ cashier    │ customers    │
└────────────┴──────────────┘
```

A row existing in this table means the role has access to that module. Absence of a row means no access.

### How permissions flow

```
Login/Me API response
        │
        ▼
  permissions: ["pos", "sales", "users", ...]
        │
        ├──► auth store (in-memory + localStorage)
        │
        ├──► Sidebar: visibleGroups filters by auth.hasPermission(permKey)
        │
        ├──► Router guards: meta.permission checked in beforeEach
        │
        └──► UI elements: v-if="auth.hasPermission('users')"
```

### Admin bypass

The `isAdmin` getter checks `user.role === 'admin'`. `hasPermission()` returns `true` immediately for admins without consulting the permissions array. This means admin cannot be locked out by the permission system, and the permissions array does not need to enumerate every module for admin users.

### Permission management

`RolePermissionService` owns two methods:

- `getForRole(role)` — returns an array of module strings for a role, or `ALL_MODULES` if the role is `admin`.
- `syncRole(role, modules)` — deletes all existing rows for the role and inserts the new set. This is a deliberate replace-all strategy to avoid orphaned permissions.

---

## 7. Multi-Branch Scoping

### Concept

Each branch is an isolated tenant. A user belongs to one branch. When a non-admin user makes an API request, their data access is silently restricted to their own branch.

### Implementation

`BranchScopeMiddleware` runs before any controller action on authenticated routes. It resolves the active branch ID and binds it to the service container:

```php
// Non-admin: always their own branch
app()->instance('pos.activeBranchId', $user->branch_id);

// Admin: can pass ?branch_id=X to switch context
app()->instance('pos.activeBranchId', $request->query('branch_id', null));
```

The `BranchScoped` trait then reads this value in its global Eloquent scope:

```php
$query->where('branch_id', app('pos.activeBranchId'));
```

This design means branch filtering is invisible to controllers and services. A controller fetching users never needs to add `->where('branch_id', ...)` — it happens automatically.

---

## 8. Internationalization

### Architecture split

The i18n system has two independent halves that share the same locale codes:

| Half | Technology | Scope |
|---|---|---|
| Frontend | Vue I18n v9 | All UI text, labels, validation messages |
| Backend | Laravel `__()` helper | API error messages, validation responses |

### Frontend i18n

**Initialization** — `plugins/i18n.js` reads `pos_locale` from `localStorage` before the Vue app mounts. It applies `document.documentElement.lang` and `document.documentElement.dir` at this point so the page never renders in the wrong direction.

**Locale structure** — Each locale file is a flat-ish JSON with dot-notated keys grouped by feature:

```
en.json
├── app.*         ← App name, tagline, feature list
├── menu.*        ← Sidebar labels and section headers
├── common.*      ← Shared words: save, cancel, delete, active, etc.
├── auth.*        ← Login form, field labels, error messages
├── dashboard.*   ← KPI labels, section titles, module names
├── branches.*    ← Branch CRUD form labels and messages
├── users.*       ← User CRUD form labels and messages
├── permissions.* ← RBAC screen labels and module descriptions
└── language.*    ← Language switcher labels
```

**Locale switching** — `useLocale.js` composable owns the `setLocale()` function. It updates the Vue I18n `locale` ref, persists to `localStorage`, and updates `document.dir` and `document.lang`. No page reload required.

**Reactive column headers** — DataTable column definitions that display translated headers use `computed()` to wrap the `columns` array. This ensures column labels update when the locale changes without remounting the component.

### Backend i18n

`SetLocaleMiddleware` reads the `Accept-Language` header from every API request and calls `App::setLocale()`. Laravel's `__()` and `trans()` helpers then automatically return strings from the correct `resources/lang/<code>/` directory.

Resolution priority:
1. `?lang=<code>` query parameter (explicit override)
2. `Accept-Language` header (first matching primary tag)
3. Default: `en`

### Adding a new language

1. Create `resources/js/locales/<code>.json` with all keys from `en.json`
2. Add to `SUPPORTED_LOCALES` in `resources/js/plugins/i18n.js` with `dir`, `label`, `flag`, `intl` fields
3. Create `resources/lang/<code>/auth.php` and `validation.php`

---

## 9. Database Design

### Schema overview

```
branches
┌──────────────┬──────────────────────┐
│ id           │ bigint PK            │
│ code         │ varchar(20) UNIQUE   │
│ name         │ varchar(100)         │
│ phone        │ varchar(30) NULL     │
│ email        │ varchar(150) NULL    │
│ address      │ text NULL            │
│ is_active    │ boolean default true │
│ created_by   │ bigint FK NULL       │
│ updated_by   │ bigint FK NULL       │
│ deleted_at   │ timestamp NULL       │ ← soft delete
│ created_at   │ timestamp            │
│ updated_at   │ timestamp            │
└──────────────┴──────────────────────┘

users
┌──────────────┬──────────────────────┐
│ id           │ bigint PK            │
│ name         │ varchar(255)         │
│ email        │ varchar(255) UNIQUE  │
│ phone        │ varchar(30) NULL     │
│ password     │ varchar(255)         │
│ role         │ enum(admin, manager, cashier) │
│ branch_id    │ bigint FK NULL       │ → branches.id
│ is_active    │ boolean default true │
│ created_at   │ timestamp            │
│ updated_at   │ timestamp            │
└──────────────┴──────────────────────┘

role_permissions
┌──────────────┬──────────────────────┐
│ id           │ bigint PK            │
│ role         │ varchar(20)          │
│ module       │ varchar(50)          │
│ created_at   │ timestamp            │
│ updated_at   │ timestamp            │
│ UNIQUE       │ (role, module)       │
└──────────────┴──────────────────────┘

personal_access_tokens         ← Sanctum
cache                          ← Laravel cache driver
jobs                           ← Laravel queue driver
```

### Design decisions

**Soft deletes on Branch** — A branch may have historical transactions. Hard-deleting a branch would orphan that data. `deleted_at` allows the branch to be hidden from the UI while retaining referential integrity.

**No soft deletes on User** — Users can be deactivated (`is_active = false`). This is the preferred workflow. Hard delete is available for GDPR compliance.

**Role as enum string** — Role is stored as a plain string (`admin`, `manager`, `cashier`) rather than a foreign key to a roles table. The role set is small, well-defined, and not expected to expand dynamically. A string is simpler and avoids a join on every permission check.

**`role_permissions` as a flat list** — Rather than a JSON column or a bitmask, each permission is an individual row. This makes it straightforward to query, index, and modify without deserialization.

---

## 10. Request Lifecycle

A full request trace for `GET /api/users?search=john&role=cashier`:

```
1. HTTP request arrives at Laravel
   │
2. SetLocaleMiddleware
   ├── reads Accept-Language: de
   └── App::setLocale('de')
   │
3. auth:sanctum
   ├── reads Authorization: Bearer <token>
   ├── validates token against personal_access_tokens
   └── sets auth()->user()
   │
4. BranchScopeMiddleware
   ├── admin? → activeBranchId = null (no scope)
   └── non-admin? → activeBranchId = user.branch_id
   │
5. RoleMiddleware (role:admin)
   └── user.role === 'admin' → passes
   │
6. UserController@index
   └── delegates to UserService::paginate($request->validated())
   │
7. UserService::paginate
   ├── User::query()->with('branch')
   ├── ->where('name', 'like', '%john%')
   ├── ->where('role', 'cashier')
   └── ->paginate(20)
   │
8. UserResource::collection($paginated)
   ├── formats each user: id, name, email, phone, role, branch_name, is_active
   └── appends pagination meta
   │
9. JsonResponse 200
   └── { data: [...], meta: { total, per_page, current_page, last_page } }
```

---

## 11. Build & Deployment

### Development

```
npm run dev     → Vite dev server with HMR on port 5173
php artisan serve → Laravel on port 8000
```

Vite proxies are not configured — the SPA calls the Laravel server directly. Both must be running during development.

### Production build

```
npm run build
```

Vite outputs to `public/build/` with content-hashed filenames. Laravel's `vite()` Blade helper reads `public/build/manifest.json` to inject the correct asset URLs.

```
public/build/
├── manifest.json
└── assets/
    ├── app-<hash>.js      ← vendor + app bundle
    ├── app-<hash>.css     ← Tailwind output
    ├── i18n-<hash>.js     ← vue-i18n (large, separate chunk)
    ├── vue-i18n-<hash>.js ← vue-i18n runtime
    └── <view>-<hash>.js   ← one chunk per view (code splitting)
```

Vite automatically code-splits each route into its own chunk. Users only download JS for the pages they visit.

### Environment variables

Vite exposes variables prefixed with `VITE_` to the frontend. Currently only `VITE_APP_NAME` is used. All other configuration (API base URL, etc.) is inferred from the same origin.

Laravel environment variables follow standard `.env` conventions. For production, set:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
DB_CONNECTION=mysql
```
