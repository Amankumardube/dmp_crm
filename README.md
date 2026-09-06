# DMP AI Digital Institute CRM — Lead Management & Admission System

A complete PHP + MySQL CRM for a digital marketing training institute:
**Lead Sources → Lead Entry → Counselor Assignment → Follow-up → Conversion → Admission → Payments**

## Features
- Role-based login: **Admin**, **Manager**, **Counselor**
- Lead capture (manual, CSV bulk import, or API webhook for Facebook/Google Ads/website forms)
- Lead pipeline: New → Contacted → Follow-up → Interested → Not Interested → Converted → Junk
- Follow-up scheduling + notes/remarks timeline per lead
- Lead assignment / reassignment to counselors
- Counselor performance dashboard (leads handled, conversions, targets)
- Course & fee management
- One-click "Convert Lead to Admission"
- Admission record with document upload (ID proof, photo)
- Payment / installment tracking with auto-calculated due balance
- Printable fee receipts (browser print-to-PDF)
- Due-payments report
- Dashboard charts (Chart.js): leads by source, leads by status, revenue
- CSRF protection, prepared statements (PDO), password hashing (bcrypt), activity logs

## 1. Requirements
- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- A web server (Apache/XAMPP/WAMP) or PHP's built-in server for local testing

## 2. Setup (Local — XAMPP/WAMP)
1. Copy the whole `dmp_crm` folder into your `htdocs` (XAMPP) or `www` (WAMP) directory.
2. Open **phpMyAdmin**, create nothing manually — just import the schema:
   - Go to **Import** tab → choose `database/schema.sql` → Go.
   - This creates the `dmp_crm` database, all tables, and a default Admin user.
3. Edit `config/config.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'dmp_crm');
   define('DB_USER', 'root');
   define('DB_PASS', '');          // your MySQL password
   define('BASE_URL', 'http://localhost/dmp_crm'); // adjust if folder name differs
   ```
4. Visit `http://localhost/dmp_crm/login.php`
5. Login with the seeded admin account:
   - **Email:** `admin@dmpschool.com`
   - **Password:** `Admin@123`
   - ⚠️ Change this password immediately (create a "change password" page, or update it directly in the `users` table using `password_hash()`).

## 3. Setup (Live Server / cPanel / VPS)
1. Upload the `dmp_crm` folder via FTP or File Manager.
2. Create a MySQL database + user in your hosting control panel, then import `database/schema.sql` via phpMyAdmin.
3. Update `config/config.php` with your live DB credentials and `BASE_URL` (your domain).
4. Make sure the `uploads/` folder is writable (`chmod 755` or `775`).
5. (Recommended) Force HTTPS and set `ini_set('display_errors', 0);` in `config.php` for production.

## 3a. Deploy on Render
This repository includes a `Dockerfile` and `render.yaml` for Render's Docker web service. Create a Render Blueprint from the repository, then set these environment variables on the web service:

```text
DB_HOST=your-mysql-host
DB_PORT=3306
DB_NAME=dmp_crm
DB_USER=your-mysql-user
DB_PASS=your-mysql-password
DB_SSL_CA=/etc/secrets/aiven-ca.pem
APP_URL=https://your-render-service.onrender.com
```

For Supabase, set `DB_DRIVER=pgsql`, use the Supabase database host, port `5432`, database `postgres`, user `postgres`, and import `database/schema.supabase.sql` in the Supabase SQL Editor. The connection uses SSL automatically. The free web service filesystem is ephemeral, so configure persistent storage or external object storage if uploaded documents must survive redeploys.

## 4. First Steps After Login
1. Go to **Courses** → add your actual courses, durations, and fees.
2. Go to **Counselors** → add your team members (each gets their own login).
3. Start adding **Leads** manually, or set up the webhook below for automatic capture.
4. When a lead is ready, open it and click **Convert to Admission**.
5. On the Admission page, click **Add Payment** to record fees as they come in.
6. Print receipts anytime from Payments → Receipt.

## 5. Connecting Facebook / Google Ads / Website Forms (Auto Lead Capture)
An API endpoint is ready at:
```
POST https://yourdomain.com/dmp_crm/modules/leads/webhook.php?key=YOUR_SECRET
```
1. Open `modules/leads/webhook.php` and change `WEBHOOK_SECRET` to a strong random string.
2. Send a POST request (JSON or form-encoded) with fields: `name`, `phone`, `email`, `city`, `source`.
3. For Facebook Lead Ads, use a middleware like **Zapier**, **Make.com**, or **Facebook's Leads Sync API + a small script** to forward new leads to this endpoint.
4. For your website's own contact/enquiry form, point its form submission (AJAX or server-side) directly to this URL.

### WhatsApp Cloud API
The same endpoint accepts Meta WhatsApp Cloud API webhooks. Configure these values as environment variables on the server:

```text
DMP_WEBHOOK_SECRET=your-long-random-secret
DMP_WHATSAPP_VERIFY_TOKEN=your-whatsapp-verify-token
```

In Meta for Developers, set the callback URL to the endpoint above and use the same verify token. Subscribe to the `messages` webhook field. The endpoint creates a new lead from an incoming WhatsApp message using the sender's phone number and profile name, and ignores duplicate phone numbers. The callback URL must be publicly reachable over HTTPS; use a tunnel such as ngrok only for local testing.

### WATI
In WATI, open the Webhooks or Integrations settings and add a webhook for incoming WhatsApp messages:

```text
https://yourdomain.com/dmp_crm/modules/leads/webhook.php?key=YOUR_SECRET
```

Select the incoming message/contact event and save it. The CRM accepts WATI fields such as `waId`, `name`, and `eventType`, then creates a lead with source `whatsapp`. WATI must be able to reach the URL over HTTPS; a localhost XAMPP URL will not work without a public tunnel.

Example request body:
```json
{ "name": "Rahul Sharma", "phone": "9876543210", "email": "rahul@example.com", "city": "Ghaziabad", "source": "facebook" }
```

## 6. Roles Summary
| Role       | Can do |
|------------|--------|
| Admin      | Everything — manage users, courses, all leads, all admissions, all payments, reports |
| Manager    | Same as Admin except cannot manage system-level settings (can extend as needed) |
| Counselor  | Sees & manages only leads/admissions assigned to them; can add payments for their students |

## 7. Folder Structure
```
dmp_crm/
├── config/           → config.php (DB + app settings)
├── includes/         → auth.php, header.php, footer.php
├── modules/
│   ├── leads/        → list, add, view, import (CSV), webhook (API)
│   ├── counselors/   → list, add
│   ├── courses/      → list/add
│   ├── admissions/   → list, add (convert), view
│   └── payments/     → list, add, receipt
├── assets/css/       → style.css
├── uploads/          → student ID proofs & photos
├── database/schema.sql
├── login.php / logout.php / dashboard.php / index.php
```

## 8. Suggested Next Enhancements
- Add a "Change Password" / "My Profile" page
- WhatsApp/SMS follow-up reminders (via Gupshup/Interakt/Twilio API)
- Email notifications for new leads and payment receipts
- Export reports to Excel/PDF
- Auto round-robin lead assignment
- Multi-branch support if the institute has more than one center
