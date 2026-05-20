# POSmeister — cPanel Deployment Guide

This zip is **deploy-ready**: `vendor/` and `public/build/` are bundled, so
you do not need Composer or Node.js on the server.

You need:
- PHP 8.2+ on the cPanel host (select via MultiPHP Manager)
- MySQL 8 / MariaDB 10.4+ database + user (you already created this)
- The subdomain pointing at the deploy folder (you already created this)
- One of: SSH access OR cPanel "Terminal" OR cPanel cron

---

## 1. Pick the layout that matches your subdomain

There are two layouts. Use the one that fits your cPanel setup.

### Layout A — subdomain document root points at `public/` (recommended)

When you created the subdomain in cPanel, the "Document Root" field can be
set to a path. If you can edit it, change it to:

```
/home/YOURUSER/posmeister/public
```

Then upload the whole zip into `/home/YOURUSER/posmeister/` and unzip.
Nothing else to move. This is the cleanest layout — `app/`, `config/`,
`storage/`, `.env` all stay outside the web root and are unreachable from
the browser.

### Layout B — subdomain document root is fixed (e.g. `public_html/pos/`)

If you cannot change the document root, you split the project:

1. Create a folder outside `public_html/`, e.g. `/home/YOURUSER/posmeister-core/`.
2. Unzip everything into that folder.
3. **Move** the contents of `public/` (not the folder itself) into your
   subdomain root (e.g. `public_html/pos/`).
4. Open the moved `index.php` in the subdomain root and edit the two
   `require` paths so they point at the core folder:

   ```php
   require __DIR__.'/../../posmeister-core/vendor/autoload.php';
   $app = require_once __DIR__.'/../../posmeister-core/bootstrap/app.php';
   ```

5. Open the `.htaccess` that was inside `public/` and leave it as-is in the
   subdomain root.
6. In the core folder, delete the now-empty `public/` directory.

---

## 2. Database

You already imported the schema and seed data. If you need to re-import or
seed a fresh DB:

- Schema → import via phpMyAdmin from the dump you uploaded.
- Seed data → import `database/cpanel-seeds.sql` (included in this zip).
  This adds the German chart of accounts (Kontenplan), role permissions
  and the default admin (email `admin@posmeister.local`, password `Admin@1234`).

**Change the default admin password right after first login.**

---

## 3. Environment file

In the **deploy root** (`Layout A` = same folder as `artisan`,
`Layout B` = the core folder), copy `.env.cpanel.example` to `.env`:

```
cp .env.cpanel.example .env
```

Edit `.env` and fill in:

- `APP_URL` — your subdomain, e.g. `https://pos.yourdomain.com`
- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` — the cPanel database credentials
- `MAIL_*` — your cPanel SMTP account (or leave default for now)

**APP_KEY** — generate one:

- If you have SSH/Terminal:
  ```
  php artisan key:generate
  ```
- If no SSH: open https://generate-random.org/laravel-key-generator and
  paste the result into `.env` as `APP_KEY=base64:...`.

---

## 4. Permissions

These folders must be writable by the web server (cPanel usually runs PHP
as your user, so 755/775 is enough):

```
chmod -R 775 storage bootstrap/cache
```

Via cPanel File Manager: select `storage/` and `bootstrap/cache/`, right-click → Permissions → 775, apply recursively.

---

## 5. Storage symlink

The app stores uploads (logos, expense attachments) in `storage/app/public/`.
A public symlink exposes them. If you have SSH:

```
php artisan storage:link
```

If no SSH — in the subdomain root, create a folder called `storage` that
symlinks (or just copies) to `storage/app/public/`. The simplest workaround
is to run a one-off PHP script: drop a file `link.php` in the deploy root with:

```php
<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('storage:link');
echo "done\n";
```

Open it once in the browser, then delete it.

---

## 6. Cache for production speed

If SSH is available, run once after deploy:

```
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Skip if no SSH — the app will run uncached.

---

## 7. Cron (optional but recommended)

Recurring expenses, scheduled cleanups and queue retries depend on Laravel's
scheduler. In cPanel → Cron Jobs, add a "once every minute" job:

```
* * * * * cd /home/YOURUSER/posmeister && php artisan schedule:run >> /dev/null 2>&1
```

(Adjust the path to your core folder.)

---

## 8. SSL

You already have SSL on the subdomain. Force HTTPS by editing the
`.htaccess` in the subdomain root (or in `public/` if Layout A). Below
the `RewriteEngine On` line, add:

```
RewriteCond %{HTTPS} !=on
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

---

## 9. First-run check

Visit `https://pos.yourdomain.com` — you should see the login screen.
Log in with `admin@posmeister.local` / `Admin@1234`, then change the
password from the user menu.

If you see a blank page or "500" error:

- Open `storage/logs/laravel.log` (via File Manager) and check the latest entry.
- Common causes:
  - `.env` missing or `APP_KEY` empty → set the key.
  - `storage/` not writable → `chmod -R 775 storage`.
  - DB credentials wrong → re-check the `.env` values against cPanel.
  - `bootstrap/cache/` not writable → fix permissions.

---

## 10. Updating in the future

When you build a new version:

1. Locally: `npm run build` then `composer install --no-dev --optimize-autoloader`.
2. Zip only the changed folders (most often `app/`, `resources/`, `routes/`,
   `database/migrations/`, `config/`, `public/build/`).
3. Upload + unzip over the existing deployment.
4. If migrations changed: run `php artisan migrate --force` via SSH, or
   import the new migration SQL via phpMyAdmin.
5. If you cached config/routes/views: re-run the three cache commands.

---

That's it. The whole app — POS, sales, purchases, HRM, payroll, expenses,
finance, accounting — runs on standard cPanel hosting alongside your
existing CodeIgniter 3 POS.
