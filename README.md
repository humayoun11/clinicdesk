# ClinicDesk

A complete Laravel MVC appointment desk for a fictional clinic. Patients find doctors and available times, book appointments and manage changes. Doctors see their assigned appointments and schedules. Administrators manage the clinic directory, services, availability and appointment statuses.

The application uses a real MySQL-compatible database, server-side authorization and a versioned REST API. It deliberately excludes medical records and payments.

## Features

- Patient-only registration, session login/logout, profile editing, password changes and Laravel password reset notifications.
- Separate role dashboards with database totals, upcoming appointments and assigned doctor schedules.
- Search, status filters, pagination, validation feedback and empty states.
- Doctor/service administration: create, edit and deactivate while preserving historical references.
- Schedule administration: create future availability blocks and remove blocks without active bookings. Replace a block by removing and adding it; overlapping blocks are rejected.
- Booking, cancellation and rescheduling through the same transactional service for web and API.
- Bootstrap interface with local compiled assets and vanilla JavaScript availability loading.
- Sanctum bearer tokens with explicit 120-minute default expiry and current-token logout.
- OpenAPI specification, Postman collection, PHPUnit tests, browser smoke script, GitHub Actions and deployment Dockerfile.

## Versions and architecture

Verified on 9 October 2026:

| Component | Version |
| --- | --- |
| PHP / Composer | 8.2.12 / 2.8.6 |
| Laravel / Sanctum | 12.69.3 / 4.3.3 |
| XAMPP database | MariaDB 10.4.32 through Laravel's mysql driver |
| Node / npm | 22.14.0 / 10.9.2 |
| Bootstrap / Vite | 5.3.8 / 6.4.4 |
| Playwright | 1.64.0, using installed Chrome |

Laravel 12 supports the installed PHP 8.2 and receives security fixes through 24 February 2027. Plan a PHP/framework upgrade before that date. Use a patched PHP and supported MySQL 8.0.16+ or MariaDB 10.6+ for deployment; the old local XAMPP stack is a development environment. See [Laravel release policy](https://laravel.com/docs/12.x/releases).

Routes call Form Requests and focused controllers. Eloquent models describe relationships; AppointmentPolicy handles ownership and role permissions, and role middleware protects administration. BookingService owns appointment and schedule rules. Blade renders browser responses; API Resources serialize JSON.

The schema includes users, doctors, services, doctor_service, schedules, appointments, reservations and Laravel's sessions/cache/reset/token tables. Foreign keys preserve historical records. Check constraints restrict roles, statuses, positive schedules and 30-minute appointments. Reservation uniqueness covers doctor/time and patient/time.

### Booking rules

- Registration always creates a Patient; submitted roles are rejected. Booking always uses the authenticated patient, never a supplied patient ID.
- Appointments last 30 minutes and begin at local half-hour boundaries. Times must be in the future and contained in a doctor's availability block.
- Doctor and service must be active and the doctor must offer that service.
- Neither a doctor nor a patient may have overlapping booked appointments.
- Patients and Admins can cancel or reschedule future booked appointments. Rescheduling keeps the same doctor; changing doctors requires cancelling and booking again.
- Assigned Doctors and Admins can mark completed/no-show only after the appointment ends.
- Cancelled, completed and no-show statuses are final. Cancellation removes the active reservation but preserves the appointment.
- Availability browsing covers today through 90 days ahead. Seeding provides 14 days; Admins can add further concrete blocks.
- Database timestamps and application clock use UTC. Browser display uses CLINIC_TIMEZONE, default Asia/Karachi. API timestamps are ISO 8601 UTC; timestamps without an offset are interpreted in the clinic timezone.
- Transactions lock doctor/patient rows, check schedule/overlap with current locking reads, and create reservations with database uniqueness. Deadlocks retry up to three times. Availability is advisory until the booking transaction succeeds.

Do not write appointments or reservations directly from new application features; use BookingService. Constraints are a backstop, while authorization and overlap rules belong to the service.

## Windows / XAMPP setup

Enable PHP extensions: pdo_mysql, mbstring, openssl, fileinfo, dom, tokenizer and XML. Add C:\\xampp\\php and Composer to PATH. Install Node 22 and start XAMPP MySQL. The Apache service is optional when using artisan serve.

From this project directory in PowerShell:

~~~powershell
composer install
npm.cmd ci
Copy-Item .env.example .env
php artisan key:generate
~~~

For the workspace built during implementation, .env, dependencies, database and compiled assets already exist; keep your existing .env and application key.

Create only the dedicated databases, using your local database credentials:

~~~powershell
& 'C:\\xampp\\mysql\\bin\\mysql.exe' -u root -p -e "CREATE DATABASE IF NOT EXISTS clinicdesk_demo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS clinicdesk_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
~~~

Edit .env: DB_CONNECTION=mysql, DB_DATABASE=clinicdesk_demo, DB_HOST, DB_PORT, DB_USERNAME and DB_PASSWORD. The supplied defaults assume XAMPP root with an empty password; change them if your installation differs. Use no other project's database.

~~~powershell
php artisan config:clear
php artisan migrate
php artisan db:seed
npm.cmd run build
php artisan serve --host=127.0.0.1 --port=8000
~~~

Open [http://127.0.0.1:8000](http://127.0.0.1:8000). For XAMPP Apache instead, create a virtual host whose DocumentRoot is this project's public directory, enable mod_rewrite and AllowOverride All. Do not expose the project root.

### Fictional demo accounts

| Role | Email |
| --- | --- |
| Admin | admin@clinicdesk.example |
| Doctor | doctor@clinicdesk.example |
| Patient | patient@clinicdesk.example |

The seeder generates and displays a random local password, shared only by fictional demo users. You can set CLINICDESK_DEMO_PASSWORD temporarily in your local environment before the first seed instead. No password is embedded in source. Rerunning the seeder leaves existing demo data unchanged.

If the password is unknown, run:

~~~powershell
php artisan clinicdesk:demo-password
~~~

Enter your own password at the hidden prompt (10+ characters, uppercase, lowercase and a number). This resets only the six seeded fictional accounts and revokes their tokens. Or use --random to generate/display a fresh password. This command refuses production and databases other than clinicdesk_demo.

All demo names and clinic biographies are fictional. Avoid entering real patient information.

### Local mail and SMTP

MAIL_MAILER=log writes reset notifications to storage/logs/laravel.log. Request a reset from /forgot-password, locate the local reset link in that private log, and open it. Never commit or share the log or reset link.

For real SMTP, set MAIL_MAILER=smtp, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_SCHEME (smtp or smtps as appropriate), MAIL_FROM_ADDRESS and MAIL_FROM_NAME through environment secrets. Use a provider appropriate for your host and verify delivery. Local mail is synchronous; a queue worker is unnecessary for this implementation.

## API

Specification: [docs/openapi.json](docs/openapi.json). Import [ClinicDesk.postman_collection.json](docs/ClinicDesk.postman_collection.json) into Postman. Set its empty password variable locally; do not export saved tokens/passwords. Login automatically captures the token, booking captures appointment_id, and logout clears the token variable.

Base URL: /api/v1. Send Accept: application/json and Content-Type: application/json for JSON bodies. The API uses Sanctum opaque bearer tokens, **not JWT**. Login returns a token once, its expiry and the user. Laravel stores the token hash. Use Authorization: Bearer TOKEN on protected requests. Website authentication uses encrypted server sessions and CSRF, without storing API tokens in browser storage.

| Method | Endpoint | Access |
| --- | --- | --- |
| POST | /register | Public; creates Patient, no token |
| POST | /login | Public; issues expiring token |
| GET | /user | Authenticated |
| POST | /logout | Authenticated; revokes current token, 204 |
| GET | /doctors, /services | Public, paginated |
| GET | /doctors/{doctor}/availability?date=YYYY-MM-DD | Public |
| GET | /appointments | Role/ownership scoped, paginated |
| POST | /appointments | Patient; 201 |
| GET | /appointments/{appointment} | Owner Patient, assigned Doctor or Admin |
| PATCH | /appointments/{appointment}/reschedule | Owner Patient or Admin |
| POST | /appointments/{appointment}/cancel | Owner Patient or Admin |

PowerShell example (read the password without embedding it in source):

~~~powershell
$credential = Get-Credential -UserName 'patient@clinicdesk.example'
$loginBody = @{
    email = $credential.UserName
    password = $credential.GetNetworkCredential().Password
} | ConvertTo-Json
$login = Invoke-RestMethod 'http://127.0.0.1:8000/api/v1/login' -Method Post -ContentType 'application/json' -Body $loginBody -Headers @{Accept='application/json'}
$headers = @{Authorization="Bearer $($login.data.token)"; Accept='application/json'}
Invoke-RestMethod 'http://127.0.0.1:8000/api/v1/user' -Headers $headers
~~~

List doctors/services, then choose an actual returned slot:

~~~powershell
$doctors = Invoke-RestMethod 'http://127.0.0.1:8000/api/v1/doctors'
$doctor = $doctors.data[0]
$day = (Get-Date).AddDays(1).ToString('yyyy-MM-dd')
$availability = Invoke-RestMethod "http://127.0.0.1:8000/api/v1/doctors/$($doctor.id)/availability?date=$day"
$bookingBody = @{
    doctor_id = $doctor.id
    service_id = $doctor.services[0].id
    starts_at = $availability.data.slots[0]
} | ConvertTo-Json
$appointment = Invoke-RestMethod 'http://127.0.0.1:8000/api/v1/appointments' -Method Post -Headers $headers -ContentType 'application/json' -Body $bookingBody
Invoke-RestMethod "http://127.0.0.1:8000/api/v1/appointments/$($appointment.data.id)/cancel" -Method Post -Headers $headers
Invoke-RestMethod 'http://127.0.0.1:8000/api/v1/logout' -Method Post -Headers $headers
~~~

Reschedule with PATCH and the same booking body shape but a different available starts_at. Search uses q, appointment filters use status, and per_page is bounded from 1 to 50 (default 12). Responses use data; paginated responses also contain links/meta. Errors consistently use message and errors. Invalid booking states, occupied slots and validation use 422; unauthenticated 401, unauthorized 403, missing 404 and throttled 429.

Login limits: 5/minute per email+IP and 30/minute per IP. Registration: 5/minute per IP. API: 60/minute per IP for stateless bearer requests. Password reset browser endpoints: 3/minute per IP. Password change/reset revokes all that user's API tokens. Browser logout only ends the browser session.

## Tests and actual verification

Tests refuse to run unless the configured database is clinicdesk_test. Laravel's RefreshDatabase/DatabaseMigrations rebuild that isolated test database; never point it at existing application data. Keep the test database disposable. Credentials are read from your local .env or CI environment; phpunit.xml forces the mysql connection and clinicdesk_test name. Clear cached configuration first if you have used config:cache.

~~~powershell
php artisan config:clear
php artisan test --compact
php vendor/bin/pint --test
composer audit
npm.cmd audit
npm.cmd run check:api
npm.cmd run build
~~~

Verified locally: 24 tests, 213 assertions passed, including authentication/reset, privilege escalation, cross-user/doctor authorization, booking/cancellation/rescheduling, rollback, invalid states, past/off-grid dates, overlapping schedules, duplicate reservations, inactive services/doctors, API validation, expired/revoked tokens, bounded pagination, throttling, explicitly enabled CSRF rejection, fresh isolated migrations and repeatable demo seeding. A separate-process concurrency test releases two booking workers through a barrier and verifies exactly one success.

Pint passed. Composer audit and npm audit reported zero known advisories at verification. The Vite production build passed. OpenAPI schema/reference validation and Postman checks passed. These results do not establish that the application is vulnerability-free.

Chrome browser smoke verification passed for desktop/mobile pages, patient booking/rescheduling/cancellation, doctor dashboard, and Admin service creation/edit/deactivation; no page JavaScript errors. Twelve local screenshots were captured in docs/screenshots, excluded from Git. Desktop and mobile landing screenshots were visually inspected.

To reproduce browser checks, start the app, set CLINICDESK_BROWSER_PASSWORD to your local demo password, and optionally CLINICDESK_BROWSER_PATH to a Chrome/Edge executable; then npm.cmd run test:browser. Defaults use Windows Chrome. The script exercises fictional demo data in clinicdesk_demo and leaves a cancelled appointment and inactive verification service. For Linux/CI, set a browser path or adapt the launch to Playwright's installed Chromium.

Docker build/run could not be tested because Docker is not installed. GitHub Actions has been prepared but cannot be claimed to have passed remotely before a repository destination is supplied. Production SMTP and HTTPS require a deployment environment and were not live-tested.

## Production deployment

1. Use patched supported PHP, MySQL/MariaDB, Composer and Node versions. Install with composer install --no-dev --optimize-autoloader and npm ci; run npm run build. Point the web root to public/.
2. Provide a stable APP_KEY through runtime secrets, APP_ENV=production, APP_DEBUG=false, APP_URL=https://your-domain, secure database credentials, SESSION_SECURE_COOKIE=true, SESSION_HTTP_ONLY=true, SESSION_SAME_SITE=lax and SESSION_ENCRYPT=true. Do not bake .env or an application key into an image.
3. Terminate HTTPS at the host/proxy and redirect HTTP to HTTPS. Set TRUSTED_PROXIES to a comma-separated list of your actual proxy addresses if forwarding TLS headers; leave it unset for direct hosting and do not trust arbitrary proxy headers. The app adds CSP, frame denial, nosniff, referrer policy and production HSTS.
4. Keep CORS_ALLOWED_ORIGINS empty unless a specific cross-origin API client is required; supply exact origins separated by commas. Credentials are disabled for API CORS.
5. Back up the persistent database, run php artisan migrate --force as a release step, and review migration impact. Never seed fictional accounts in production. Create an initial administrator from a trusted console with php artisan clinicdesk:create-admin admin@your-domain.example "Administrator Name"; the password is entered privately and must be confirmed. Public registration cannot create Admins.
6. Run php artisan config:cache, route:cache and view:cache only after runtime environment variables are available. storage and bootstrap/cache must be writable by the web worker. Keep logs private and rotate them.
7. Schedule php artisan schedule:run every minute; the scheduler prunes expired Sanctum tokens daily. Token validation enforces expiry even before pruning.
8. Monitor errors, maintain backups, test restores and keep dependencies current.

Docker:

~~~sh
docker build -t clinicdesk .
docker run --name clinicdesk --env-file /secure/path/clinicdesk.env -p 8080:80 clinicdesk
docker exec clinicdesk php artisan migrate --force
~~~

The multi-stage Dockerfile compiles frontend assets, installs production Composer dependencies and serves public/ through Apache. Apache workers run as www-data. Supply secrets at runtime; the image excludes .env, logs, local databases and source-control metadata. The app container does not start a database and does not automatically migrate or seed it.

Use an external managed MySQL database or a separately operated database container with a persistent volume, backups and restore procedures. DB_HOST must be reachable from the app container (127.0.0.1 refers to that container). Database sessions/cache are persistent in that database. Keep the same APP_KEY across replicas. Mount private log storage or integrate host log collection. Never rely on an ephemeral SQLite file for hosted data.

### Known limitations

Single clinic; fixed 30-minute visits; concrete schedule blocks; no recurring-schedule UI, email verification, MFA, reminder delivery, medical records, payments or time-off workflow. Admins deactivate rather than delete doctors/services; schedule changes use remove/recreate. API profile/reset and terminal-status management are intentionally browser workflows. The API does not issue refresh tokens: log in again after expiry. Availability does not reserve slots or filter a patient's conflicting appointments; booking rejects conflicts. Direct out-of-service database writes can bypass service rules. Browser smoke coverage is selected end-to-end verification, not an exhaustive accessibility or penetration audit.

## Portfolio presentation and GitHub

See [docs/INTERVIEW.md](docs/INTERVIEW.md) for screenshot instructions and a five-minute interview walkthrough. Check [PROGRESS.md](PROGRESS.md) for completed stages.

GitHub Actions runs MySQL 8 tests, Pint, Composer audit, npm audit, OpenAPI validation and asset compilation. Composer and frontend lockfiles are included. Review git status before committing; .env, credentials, logs, local database files, screenshots and vendor/node_modules are ignored. No repository has been pushed and no public deployment has been performed.

Third-party notices are preserved in installed packages and documented in [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).
