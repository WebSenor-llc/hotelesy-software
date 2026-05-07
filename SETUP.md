# Hotelesy / Miraj Hotel Suite — Local Setup

A complete, multi-tenant hotel ERP built on Laravel 11 + Livewire 4 + MySQL.

## Prerequisites

You need these installed on your Mac:

- PHP 8.2 or higher (you have 8.4 — perfect)
- Composer 2.x
- MySQL 8.0 (or MariaDB 10.6+)
- Node.js — only if you decide to compile assets (not needed; we use Tailwind via CDN)

Verify versions:

```bash
php -v        # should print 8.2+
composer -V
mysql --version
```

## 1. Database

Open MySQL and create the database:

```bash
mysql -u root -p
```

Inside the MySQL prompt:

```sql
CREATE DATABASE hotelesy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;
```

The default credentials baked into `.env` are `root` / `root123`. If yours differ, edit `.env` (see step 4).

## 2. Install dependencies

From the project root `/Users/shubhamsoni/Sites/hotelesy`:

```bash
cd /Users/shubhamsoni/Sites/hotelesy
composer install
```

This pulls Laravel + Livewire + Spatie multitenancy/permissions/etc.

## 3. App key

If `.env` doesn't already have an `APP_KEY`:

```bash
php artisan key:generate
```

(The current `.env` already has one — you can skip if it's there.)

## 4. Environment

Open `.env` and confirm these lines match your local setup:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hotelesy
DB_USERNAME=root
DB_PASSWORD=root123
APP_URL=http://localhost:8000
```

If your MySQL root password is different, change `DB_PASSWORD` here.

## 5. Storage symlink (for room-type photo uploads)

```bash
php artisan storage:link
```

This creates `public/storage` → `storage/app/public` so uploaded images render in the browser.

## 6. Migrate + seed

This creates ~80 tables and fills them with realistic dummy data — 60 guests, 148 reservations, 4 outlets with menus, 3 banquet halls with bookings, 19 inventory items, 6 vendors, channel mappings, vouchers, promotions, and revenue snapshots.

```bash
php artisan migrate:fresh --seed
```

If you only want to apply new migrations without wiping data:

```bash
php artisan migrate
```

## 7. Run the dev server

```bash
php artisan serve
```

That starts the app on **http://localhost:8000**. Open it in Chrome.

If port 8000 is busy, pass another:

```bash
php artisan serve --port=8001
```

## 8. Sign in

The seeder creates one owner account:

| Field    | Value                       |
|----------|-----------------------------|
| Email    | `admin@miraj-demo.test`     |
| Password | `password`                  |

You'll land on the Dashboard. Use the left sidebar to navigate any of the 18 modules.

## 9. Convenience: one-liner restart

A `start.sh` script ships with the project that:
- kills anything on port 8000
- runs `migrate:fresh --seed`
- starts `php artisan serve` on a free port

```bash
./start.sh
```

## What to try first

1. **Reservations → New booking** — create a booking, then check it in.
2. **POS / Restaurant** — pick the Lake View Restaurant, hit "+ New order (KOT)" — that pushes a ticket to the Kitchen Display.
3. **Kitchen Display** — bump the ticket through queued → preparing → ready → served. The POS Active orders list updates live.
4. **POS** — click "Generate bill" on a served order, then "Settle" → take cash payment OR charge to room folio.
5. **Banquet & events** — click Mehta Anniversary Dinner → record an advance, change status, see the function sheet with linked client.
6. **Amenities & services** — "+ Sell to guest" → pick a checked-in guest → posts to their folio.
7. **Store** — "+ Record purchase / GRN" — receive items from a vendor, stock updates immediately.
8. **Reports → GST management** — Today / This month / Custom — see GSTR-1 by tax slab, B2B vs B2C, output tax payable. Export CSV.
9. **Reports → Revenue report** — Occupancy %, ADR, RevPAR, breakdown by source and room type.
10. **Setup → Rate plans** — every plan now shows a "GST incl./excl." badge; edit one to flip the mode.

## Troubleshooting

**`SQLSTATE[HY000] [1045] Access denied`** — DB password in `.env` is wrong. Fix `DB_PASSWORD` and rerun migrate.

**`SQLSTATE[42S22] Column not found`** — A new migration hasn't been applied. Run `php artisan migrate`.

**`The "/storage/..." link does not exist`** — Run `php artisan storage:link`.

**Port 8000 already in use** — Either kill the old server (`lsof -ti :8000 | xargs kill`) or pass `--port=8001`.

**Livewire components show old data after edits** — Clear views: `php artisan view:clear` and refresh.

**Want to fully reset all dummy data?** — `php artisan migrate:fresh --seed` (destroys everything, recreates the demo).

## Project layout (orientation)

```
hotelesy/
├── app/
│   ├── Livewire/         # Every page is a Livewire component
│   │   ├── Reservations/
│   │   ├── POS/
│   │   ├── KDS/
│   │   ├── Banquet/
│   │   ├── Amenities/
│   │   ├── Store/
│   │   ├── Reports/      # ← Overview, DailyFlash, TaxReport, GstManagement, RevenueReport
│   │   └── ...
│   ├── Models/
│   └── Services/
│       ├── TenantContext.php
│       └── Billing/FolioService.php
├── database/
│   ├── migrations/       # 21+ migrations covering 80+ tables
│   └── seeders/DummyDataSeeder.php
├── resources/views/
│   ├── layouts/app-shell.blade.php   # ← sidebar + header
│   └── livewire/         # Blade for every module
├── routes/web.php        # All 50+ routes wrapped in auth+tenant+property middleware
└── .env
```

Everything is wired tenant→property→user. Login picks the user, middleware resolves their tenant + property, and global scopes filter every query.
