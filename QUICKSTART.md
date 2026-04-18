# PharmaCare Pro — Quick Start

## Access the System
Open: http://localhost/pharmacy/

## Login Credentials
| Role | Email | Password |
|------|-------|----------|
| Super Admin | admin@pharmacy.com | admin123 |
| Branch Manager | manager1@pharmacy.com | admin123 |
| Pharmacist | pharma1@pharmacy.com | admin123 |

## Database Config (config/db.php)
- Host: 127.0.0.1
- Port: 3301
- User: root
- Pass: (empty)
- DB: pharmacy_mgmt

## Payment Setup (Admin → Payment Settings)
### Chapa (Recommended for Ethiopia)
1. Sign up at https://dashboard.chapa.co
2. Get your Secret Key (CHASECK-TEST-...)
3. Go to Admin → Payment Settings → Chapa
4. Paste your keys and enable

### Telebirr Direct
1. Contact telebirr@ethiotelecom.et
2. Get App ID, App Key, Short Code, RSA Public Key
3. Go to Admin → Payment Settings → Telebirr

## Key Features
- Dashboard: Real-time stats, hourly chart, expiry alerts, low stock
- POS: Click medicine → add to cart → checkout (cash or online payment)
- Expiry Warning: Red/yellow badge on medicines expiring soon in POS
- Daily Report: Reports → Daily Report (per-day medicine breakdown)
- Notifications: Bell icon top-right shows alerts
- Branches: Super Admin sees all branches performance
