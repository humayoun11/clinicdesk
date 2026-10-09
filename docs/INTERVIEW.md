# Five-minute interview walkthrough

1. Open the landing page and explain the scope: fictional appointments, no medical records or payments.
2. Sign in as a patient. Find a doctor, inspect real availability, book a slot, reschedule it, and cancel it. Show validation by trying to book the same time twice.
3. Explain MVC: routes → Form Requests → controllers → BookingService → Eloquent models → Blade or API Resources.
4. Show AppointmentPolicy and the visibleTo query. A patient cannot read or alter another patient's records; a doctor only sees assigned appointments. Registration rejects submitted roles.
5. Show BookingService: UTC storage, clinic timezone display, transaction retries, doctor/patient row locks, current locking overlap read, unique active reservations. Cancelled appointments remain as history. The separate-process race test verifies the concurrency path.
6. Show the API login response: Sanctum opaque bearer token, explicit expiry, hashed storage and current-token revocation. Password changes/reset revoke all tokens.
7. Explain the tradeoff: a single clinic, 30-minute appointments, concrete schedule blocks, and final statuses. Admins deactivate doctors/services to preserve historical foreign keys.
8. Run the tests and explain why CSRF tests deliberately disable Laravel's testing bypass. Show the isolated database guard.
9. Describe deployment: compiled local assets, Apache public document root, runtime secrets, persistent MySQL, database sessions, HTTPS and scheduled expired-token pruning.

## Screenshots

Use only fictional demo accounts. Run the app and production asset build first. Capture at 1440×1000 and 390×844:
- Landing page and doctor directory.
- Patient dashboard and booking form with loaded availability.
- Appointment details before and after cancellation.
- Doctor dashboard with assigned schedule.
- Admin doctor/service/schedule management.

Save to docs/screenshots; inspect before sharing to ensure no passwords, tokens, reset links or environment secrets appear. Automated browser verification can also generate these screenshots; see scripts/browser-smoke.mjs. Screenshots do not establish a security guarantee.

