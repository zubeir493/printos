# PrintOS

A production-ready ERP system built for printing companies. Manages the full lifecycle from job order intake through production, materials, dispatch, and invoicing — across multiple role-based panels.

## Tech Stack

- **PHP 8.3** / **Laravel 12**
- **Filament 5** — admin UI framework
- **Livewire 4** / **Alpine.js**
- **MySQL 8** — primary database
- **Redis 7** — cache, sessions, queues
- **Tailwind CSS v4**

## Panels & Roles

| Panel | Role | Access |
|---|---|---|
| `/` | Admin | Full access to all modules |
| `/design` | Design | Artworks, job order tasks, design workflow |
| `/production` | Production | Tasks in production status, machines, production plans |
| `/operations` | Operations | Job orders, dispatches, purchase orders, material management |
| `/warehouse` | Warehouse | Inventory, stock movements, goods receipts, dispatches |
| `/finance` | Finance | Payments, invoices, journal entries, financial reports |
| `/sales` | Sales | Sales orders, customer management |
| `/retail` | Retail | Point-of-sale sales orders |
| `/hr` | HR | Employee management, salary tracking |

All roles log in from the main `/login` page and are redirected to their panel automatically.

## Core Modules

**Job Orders** — Client and internal jobs with task breakdown, artwork approval workflow, material requirements, and state machine enforcement (Draft → Active → Completed/Cancelled).

**Inventory & Warehouse** — Raw materials with purchase/base unit conversion (e.g. reams → sheets), stock movements, warehouse transfers, goods receipts, stock adjustments.

**Procurement** — Purchase orders with one-click generation from job order material shortages, unit-aware pricing, goods receipt workflow.

**Finance** — Payments, payment allocations (polymorphic across job orders and sales orders), invoices with PDF generation, journal entries, bank transfers, financial reports (P&L, balance sheet, general ledger, aging).

**Production** — Production plans, machine efficiency tracking, production reports.

**Dispatch** — Outbound dispatch management linked to job orders.

**HR** — Employee records, salary history, overtime rates.

## Getting Started

### Local (Laravel Herd / Valet)

```bash
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
```

### Docker (VPS / Production)

```bash
cp .env.docker .env
# Edit .env — set APP_KEY, DB_PASSWORD, REDIS_PASSWORD, APP_URL
php artisan key:generate --show   # paste output into APP_KEY
docker compose up -d
```

The entrypoint automatically runs migrations, links storage, and warms caches on startup.

### First Login

After seeding, log in with the credentials from `database/seeders/UserSeeder.php`. The admin user has full access to all panels.

## Environment Variables

Key variables to configure for production:

| Variable | Description |
|---|---|
| `APP_KEY` | Laravel encryption key — generate with `php artisan key:generate --show` |
| `APP_URL` | Full URL including scheme, e.g. `https://printos.example.com` |
| `DB_*` | MySQL connection details |
| `REDIS_PASSWORD` | Redis auth password |
| `SESSION_SECURE_COOKIE` | Set to `true` when running behind HTTPS |
| `FILESYSTEM_DISK` | `local` for VPS storage, `s3` for object storage |
| `PRIVATE_FILESYSTEM_DISK` | Disk for private files (artworks, invoices, text files) |
| `AWS_*` | S3-compatible storage credentials (Backblaze B2, AWS, etc.) |
| `MAIL_*` | SMTP credentials for invoice and artwork emails |

## License

Proprietary. All rights reserved.
