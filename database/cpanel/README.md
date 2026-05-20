# cPanel database files

You already imported your database, so you can ignore these unless you need
to re-create or re-seed on a fresh DB.

- `cpanel-schema.sql` — all 50+ tables (no data). Import this first into an
  empty database via phpMyAdmin.
- `cpanel-seeds.sql` — chart of accounts (Kontenplan, 22 accounts), role
  permissions, and the default admin user. Import this after the schema.

Default admin: `admin@posmeister.local` / `Admin@1234`. Change the password
immediately after first login.
