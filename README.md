# DART AFCS — Automated Fare Collection System
### PHP + MySQL + React (No build tools required)

---

## Project Structure

Each concern lives in its own file/folder — card management, ticketing, and
reporting are handled completely separately front and back, and the frontend
loads as plain `<script>` tags (Babel transforms each one in the browser),
so there's still no build step, no npm install, no bundler.

```
afcs/
├── index.html                          ← Thin HTML shell: loads CSS + all JS modules in order
├── assets/
│   ├── css/
│   │   └── app.css                     ← Fonts, design tokens, every component class
│   └── js/
│       ├── core/
│       │   ├── hooks.js                ← Shared React hook aliases
│       │   ├── api.js                  ← fetch() wrappers for the PHP backend
│       │   └── format.js               ← fmtMoney / fmtDate helpers
│       ├── components/
│       │   ├── shared/                 ← Toast, Spinner, Numpad, CardReader, CardProfile
│       │   ├── layout/                 ← TopBar, StatusBar
│       │   ├── screens/                ← One file per screen (Login, Dashboard, QR Sale,
│       │   │                             Card Sale, Topup, Card Details, Session Report,
│       │   │                             Card Activation/Deactivation/Penalty, Login Report,
│       │   │                             Balance Transfer, Standby, Placeholder)
│       │   ├── print/                  ← SessionReportPrint (the printable A5 layout)
│       │   └── modals/                 ← EndSessionModal
│       ├── App.jsx                     ← Root component: routing between screens/modes
│       └── main.jsx                    ← ReactDOM render — the actual entry point
└── api/
    ├── bootstrap.php                   ← Single include point for every endpoint
    ├── config/
    │   └── database.php                ← MySQL connection + schema + seed data  ← CONFIGURE THIS
    ├── helpers/
    │   ├── response.php                ← json_ok / json_err / json_body
    │   └── auth.php                    ← require_auth / get_active_session_id
    ├── auth.php                        ← Login, logout, session management
    ├── cards.php                       ← Card lookup, register, topup, activate, deactivate
    ├── qr.php                          ← QR student ticket sales + gate validation
    └── reports.php                     ← Session report, login/logout log
```

---

## Setup on XAMPP

### Step 1 — Create the database

Open **phpMyAdmin**: `http://localhost/phpmyadmin`

Click **New** on the left sidebar, name the database:
```
dart_afcs
```
Collation: `utf8mb4_general_ci` → click **Create**

Tables are created automatically when the app loads for the first time.

### Step 2 — Configure the connection

Open `api/config/database.php` and edit the top:

```php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');   // XAMPP default MySQL port — change if yours differs (e.g. Laragon often uses 3307)
define('DB_NAME', 'dart_afcs');
define('DB_USER', 'root');
define('DB_PASS', '');   // XAMPP default is empty — change if you set a password
```

### Step 3 — Copy into htdocs

```
Windows:  C:\xampp\htdocs\afcs\
Linux:    /opt/lampp/htdocs/afcs\
```

### Step 4 — Start Apache + MySQL from XAMPP Control Panel

### Step 5 — Open the app
```
http://localhost/afcs/
```

---

## Default Login Credentials

| Username    | Password   | Role    |
|-------------|------------|---------|
| `luogaw`    | `1234`     | Cashier |
| `mbangalae` | `1234`     | Cashier |
| `admin`     | `admin123` | Admin   |

Log in as `admin` to reach the **Management Console** — a completely separate
shell from the cashier UI (different top bar, different nav, no cashier
operations at all). Non-admins never see it; it's gated both in the UI and,
more importantly, with a `require_admin()` check on every admin endpoint.

---

## Session Ownership Rules

A cashier session belongs to whoever started it, and only one can be open per
terminal at a time:

- **Logging out ≠ ending your session.** A cashier can log out mid-shift
  (e.g. lunch break) without closing their session — it just stays open,
  running totals intact.
- **Only the same cashier can resume it.** Logging back in with the same
  account picks the open session back up automatically — no need to hit
  "Start Session" again, no need to visit the standby screen at all.
- **A different cashier is locked out.** If cashier B tries to log into a
  terminal that cashier A left an open session on, B can still log in, but
  the standby screen shows a locked notice and disables "Start Session" —
  they can't start their own session or touch A's.
- **Only an admin can force it closed.** The Management Console's **Session
  Management** screen lists every open session across every terminal, live,
  with a one-click "End Session" that closes it out — audit-logged as closed
  by the admin, not the cashier.

---

## Card Inventory (Admin)

The Card Sale screen no longer hands out a random card number when the cashier
hits "Scan New Card." It pulls the oldest still-unregistered card straight out
of the `cards` table (`status = 'new'`) — deterministic, never something
already registered, and it disappears from the pool the instant it's
registered so two cashiers can never be handed the same physical card.

That pool has to come from somewhere, though — that's what the **Card
Inventory** screen in the Management Console is for:
- Live counts of blank / active / inactive cards, and Adult vs Staff split.
- A form to batch-generate sequential blank cards (e.g. start at
  `994160000026`, add 50) whenever stock runs low.
- A live list of every blank card currently sitting in stock.

25 blank cards ship pre-seeded so the demo works out of the box; the cashier's
Card Sale screen shows a live "X blank cards in stock" counter and simply
won't let a scan happen once it hits zero — it tells the cashier to ask an
admin to top up inventory instead of erroring out mid-transaction.

---

## Database Schema

```sql
users              — Cashier accounts (username, password_hash, role, terminal)
cards              — Smart card registry (card_number, holder_name, phone, balance, status)
cashier_sessions   — One row per login, closed when cashier clicks End Session
transactions       — Every action: topup, qr_sale, card_sale, activate, deactivate, penalty
qr_tickets         — Issued QR tickets with unique codes and 24hr expiry
```

---

## Troubleshooting

**"Database connection failed"**
→ MySQL must be running in XAMPP Control Panel
→ Database `dart_afcs` must exist in phpMyAdmin
→ Check `DB_PASS` — XAMPP default is empty `''`

**Blank page / 404**
→ Confirm folder is at `htdocs/afcs/` not `htdocs/afcs/afcs/`

**QR codes not showing**
→ Needs internet to load QRCode.js CDN on first use, then it's cached

---

## Platform evolution (v4+)

This codebase has been hardened into an AFCS **platform foundation**:

- Financial ledger + idempotent APIs
- RBAC, device registry, signed QR tickets
- Configurable fares, settlement, reconciliation
- Optional MFA (TOTP), anomaly signals
- Offline policies + sync outbox/inbox
- Admin UI: sessions, inventory, fares, devices, settlement

See **ARCHITECTURE.md** and `STAGE*_CHANGELOG.md` for details.

### Health checks
```
GET api/health.php?action=live
GET api/health.php?action=ready
GET api/health.php?action=info
```

### Production secrets
Copy `.env.example` values into the process environment.  
**Never** deploy with default HMAC secret or empty DB password.
