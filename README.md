# USM PANTAS — Library Management System

Laravel application for cataloging, circulation, patron registration, attendance scanning, room reservations, e-resources, and staff administration.

**Repository:** [github.com/borskenetic/pantas-v2.5](https://github.com/borskenetic/pantas-v2.5)

## Requirements

- PHP 8.2+
- Composer
- MySQL 8+ (or MariaDB)
- Node.js (optional, if you build front-end assets with Vite)

## Quick start (local)

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Create a MySQL database (example name `demo_2`), then set in `.env`:

```env
APP_URL=http://localhost
DB_DATABASE=demo_2
DB_USERNAME=root
DB_PASSWORD=
```

Run migrations and seed MARC catalog framework:

```bash
php artisan migrate
php artisan db:seed --class=MarcFrameworkSeeder
php artisan storage:link
```

Optional — 10 sample students for QR/attendance testing (`S-00000001` … `S-00000010`):

```bash
php artisan db:seed --class=StudentSampleSeeder
```

## Hybrid offline mode (attendance / OPAC / cataloging)

PANTAS supports **hybrid** terminals: work continues without internet, then syncs when the cloud is reachable (same idea as ACD gate-terminal).

1. Migrate: `php artisan migrate`
2. As **admin**, open **Admin → Hybrid devices**, create a device (Attendance / OPAC / Catalog capabilities), copy the token once.
3. On the kiosk/browser, open the **Pair hybrid device** panel and paste the token.

| Surface | Offline behavior | Sync |
|---------|------------------|------|
| `/attendance` | Local patron roster + IN/OUT queue | Pull roster + push scans via `/api/hybrid/attendance/*` |
| `/opac` | Browse/search local catalog cache | Pull snapshot via `/hybrid/opac/snapshot` or device API |
| `/book/create` | Queue catalog drafts in IndexedDB | Push via session `/hybrid/catalog/records` or device API |

Device APIs (Bearer / `X-Hybrid-Token`):

- `GET /api/hybrid/health`
- `GET /api/hybrid/attendance/roster?since=`
- `POST /api/hybrid/attendance/scans`
- `GET /api/hybrid/opac/snapshot?since=`
- `POST /api/hybrid/catalog/records`

Serve the app:

```bash
php artisan serve
```

Sign in with an **admin** or **staff** user created in the database or via **Create Account** in the admin UI.

## Environment notes

| Variable | Purpose |
|----------|---------|
| `BRANDING_CSS` | Per-school stylesheet under `public/branding/` |
| `SMS_MODEM_URL` / `SMS_MODEM_API_KEY` | Local Flask SMS bridge (optional) |
| `GOOGLE_BOOKS_API_KEY` | ISBN lookup quota for cataloging (optional) |

Copy `.env.example` — **never commit** your real `.env` file.

## Uploading to GitHub

From the project root:

```bash
git status
git add -A
git commit -m "Your message describing this release"
git push origin main
```

Before pushing, confirm `git status` does **not** list `.env` or files under `public/images/student_signatures/`.

## Fresh clone on another machine

```bash
git clone https://github.com/borskenetic/pantas-v2.5.git
cd pantas-v2.5
composer install
cp .env.example .env
# edit .env for DB credentials
php artisan key:generate
php artisan migrate
php artisan db:seed --class=MarcFrameworkSeeder
php artisan storage:link
```

Ensure writable directories: `storage/`, `bootstrap/cache/`, and upload folders under `public/images/` (see `.gitkeep` files).

**Videos:** MP4 files under `public/videos/` are not stored on GitHub (too large). After cloning, copy your slideshow/background videos into `public/videos/` on the server.

## Main features

- **Catalog** — MARC-based books, programs, circulation, fines, trash/archive
- **E-Resources** — `/ebooks` digital collection with program/subject filters
- **Patrons** — student registration, ID cards, pending approvals
- **Attendance** — QR scan in/out, reports, optional logout feedback
- **Rooms** — reservations, schedule, pending queue, logs
- **Admin** — user accounts (admin/staff/faculty/student roles)

## License

Application code follows your project license. Laravel framework components are [MIT](https://opensource.org/licenses/MIT).
