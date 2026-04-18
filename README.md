# PharmaCare Pro — Pharmacy Management System

A full-featured, multi-branch pharmacy management system built with PHP and MySQL. Designed for Ethiopian pharmacies with local payment integrations (Chapa & Telebirr).

---

## Screenshots

> Place your screenshots in `assets/img/screenshots/` and update the paths below.

| Dashboard | POS / New Sale |
|-----------|---------------|
| ![Dashboard](assets/img/screenshots/dashboard.png) | ![POS](assets/img/screenshots/pos.png) |

| Stock Management | Reports |
|-----------------|---------|
| ![Stock](assets/img/screenshots/stock.png) | ![Reports](assets/img/screenshots/reports.png) |

| Admin Panel | Chat |
|------------|------|
| ![Admin](assets/img/screenshots/admin.png) | ![Chat](assets/img/screenshots/chat.png) |

---

## Features

### Core Modules
- **Dashboard** — Real-time stats, hourly/monthly sales charts, expiry alerts, low stock warnings, branch performance overview
- **Point of Sale (POS)** — Fast checkout with cash or online payment, expiry badge warnings on medicines
- **Medicines** — Add, edit, view medicine catalog with categories
- **Stock Management** — Per-branch stock tracking, batch numbers, expiry dates, low stock thresholds
- **Purchases** — Record supplier purchases and update stock
- **Sales** — Full sales history, invoice generation, per-sale item breakdown
- **Suppliers** — Manage supplier contacts and purchase history
- **Branches** — Multi-branch support with per-branch stock and sales isolation
- **Shifts** — Staff shift tracking per branch
- **Reports** — Sales, stock, expiry, daily, and staff reports
- **Notifications** — Real-time alerts for low stock and expiring medicines
- **Chat** — Internal staff messaging with file uploads
- **Admin Panel** — User management, categories, shop settings, payment configuration

### Payments
- **Chapa** — Ethiopian payment gateway (card, mobile money)
- **Telebirr** — Ethio Telecom mobile payment integration
- **Cash** — Standard cash checkout

### Roles & Access
| Role | Access |
|------|--------|
| Super Admin | All branches, all settings, all reports |
| Branch Manager | Own branch data, staff management |
| Pharmacist | POS, stock view, own branch only |

---

## Tech Stack

- **Backend:** PHP 8+ (PDO, no framework)
- **Database:** MySQL 5.7+ / MariaDB
- **Frontend:** HTML5, CSS3, JavaScript, Chart.js, Font Awesome
- **Payments:** Chapa API, Telebirr Direct API

---

## Project Structure

```
pharmacy/
├── admin/          # Admin panel (users, categories, settings, payments)
├── assets/         # CSS, images, screenshots
├── auth/           # Login, logout, profile
├── branches/       # Branch listing and detail view
├── chat/           # Internal messaging + file uploads
├── config/         # db.php — database connection + helper functions
├── db/             # SQL schema files
├── includes/       # Shared header/footer
├── medicines/      # Medicine catalog CRUD
├── payments/       # Chapa & Telebirr payment handlers
├── purchases/      # Purchase recording
├── reports/        # Sales, stock, expiry, daily, staff reports
├── sales/          # POS, sale processing, sale history
├── shifts/         # Shift management
├── stock/          # Stock CRUD per branch
├── suppliers/      # Supplier management
├── dashboard.php   # Main dashboard
├── index.php       # Entry point (redirects to dashboard or login)
├── closed.php      # Shop closed page
└── notifications.php
```

---

## Installation

### Requirements
- PHP 8.0+
- MySQL 5.7+ or MariaDB 10.4+
- Apache/Nginx with `mod_rewrite`
- cURL extension enabled

### Steps

1. Clone or upload the `pharmacy/` folder to your web root (e.g. `public_html/` or `htdocs/`)

2. Create a MySQL database:
   ```sql
   CREATE DATABASE pharmacy_mgmt CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. Import the SQL files in order:
   ```
   db/pharmacy.sql
   db/shop_settings.sql
   db/chat.sql
   db/updates.sql   ← run last (contains schema updates)
   ```

4. Update `config/db.php` with your credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_PORT', '3306');
   define('DB_USER', 'your_db_user');
   define('DB_PASS', 'your_db_password');
   define('DB_NAME', 'pharmacy_mgmt');
   ```

5. Make the uploads folder writable:
   ```bash
   chmod 755 chat/uploads/
   ```

6. Open in browser: `http://localhost/pharmacy/`

---

## Default Login

Default credentials are listed in `QUICKSTART.md` (not tracked in version control).

> Change all passwords immediately after first login.

---

## Payment Setup

### Chapa
1. Sign up at [dashboard.chapa.co](https://dashboard.chapa.co)
2. Get your Secret Key (`CHASECK-...`)
3. Go to Admin → Payment Settings → Chapa → paste key and enable

### Telebirr
1. Contact telebirr@ethiotelecom.et for merchant credentials
2. Go to Admin → Payment Settings → Telebirr → fill in App ID, App Key, Short Code, RSA Public Key

---

## Milestones

### v1.0 — Core System
- [x] Authentication (login/logout/roles)
- [x] Medicine catalog management
- [x] Multi-branch stock tracking
- [x] Point of Sale with cash checkout
- [x] Basic sales history and invoices
- [x] Dashboard with key stats

### v1.1 — Operations
- [x] Supplier management
- [x] Purchase recording
- [x] Shift tracking
- [x] Expiry date tracking and alerts
- [x] Low stock threshold alerts
- [x] Notification system

### v1.2 — Payments & Reports
- [x] Chapa payment integration
- [x] Telebirr payment integration
- [x] Sales reports
- [x] Stock reports
- [x] Expiry reports
- [x] Daily breakdown report
- [x] Staff activity reports

### v1.3 — Multi-Branch & Admin
- [x] Branch performance dashboard
- [x] Per-branch stock isolation
- [x] Admin panel (users, categories, shop settings)
- [x] Shop open/close scheduling per branch
- [x] Internal chat with file uploads

### v2.0 — Planned
- [ ] Mobile-responsive PWA
- [ ] Barcode scanner support
- [ ] Prescription management
- [ ] Customer loyalty / repeat prescription tracking
- [ ] SMS/email notifications
- [ ] API for external integrations
- [ ] Automated backup scheduler

---

## Free Deployment Options

| Platform | PHP | MySQL | Free Tier |
|----------|-----|-------|-----------|
| [InfinityFree](https://infinityfree.net) | ✅ | ✅ | Fully free |
| [000webhost](https://000webhost.com) | ✅ | ✅ | Fully free |
| [Railway](https://railway.app) | ✅ | ✅ | Starter free tier |

See `QUICKSTART.md` for local setup instructions.

---

## License

MIT — free to use and modify.
