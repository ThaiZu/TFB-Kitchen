# Kitchen checklist PIN compatibility (temporary)

Deploy API before Kitchen. No new migration: existing employee credential schema (20261112_employee_credentials.sql) and attendance attempts schema (20261111_employee_attendance.sql) are prerequisites. Do not run production migrations automatically.

POST /api/v1/kitchen/employees/verify-pin accepts employee_id and four-digit string pin; a verified kitchen_tablet access token determines the device/shop. Persisted device/type/shop and employee shop/schedule eligibility are checked. Uses existing device/employee attempt counters: five failures lock verification for five minutes. Does not clock in/out or complete tasks. Does not return or log PIN/hash.

Temporary compatibility is restricted to this Kitchen endpoint. If pin_hash exists, only password_verify with that hash is accepted, and pin_requires_reset blocks it. Never fall back to the previous plaintext PIN when a hash exists, even if the supplied old PIN matches. If no hash exists, compare four-digit legacy pin using hash_equals; retain leading zeros. Legacy rows may have migration-default pin_requires_reset=1: temporary legacy compatibility accepts those rows until a new hash is set. Removed employees are rejected. This does not restore plaintext credentials erased by migration/backfill; those employees require a new individual PIN through the existing product workflow.

Kitchen calls this endpoint for both opening a shift and completing a task without a verified shift. API outages are reported as verification_unavailable rather than employee_not_found. Existing photo and task submission remain unchanged. No plaintext/hash is added to employee lists. The ordinary POS/Employee hash-only policy is unchanged.

Tests: API KitchenPinTest plus Unit/Employee regression; Kitchen bin/checklist-pin-test.php, bin/checklist-test.php, bin/worker-business-date-test.php. No production requests or physical devices used. Native deployment/photo upload with real test-shop credentials remains a staging verification: select scheduled employee, enter correct old-only or new PIN, attach photo, complete task, check stored employee/task/photo once; wrong PIN/foreign shop must fail without marking task complete.

Remove legacy branch once all employee PINs are hashed. Avoid switching back to the old Kitchen verifier after API credential migration: it expects plaintext employee lists which intentionally no longer exist.
