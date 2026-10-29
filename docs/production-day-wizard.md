# Production day: guided workflow

The existing `/production-day` now shows six compact stages when the API returns
`wizard_version: 2`. Without that capability it keeps the previous interface,
which allows API-first rollout and retries of older pending requests.

1. **Yesterday stock** — confirm the saleable quantity, explaining any reduction
   through the existing multi-reason/photo modal. This step does not write stock.
2. **Overnight production** — enter finished products not recorded before the
   previous closing. Yesterday's preparation is shown separately for checking;
   it is never added automatically. Zeros allow proceeding without night work.
3. **Today's production** — enter new production. Each unavailable mandatory
   product has its own reason badges and optional Other comment. Entering a
   positive quantity clears only that product's absence decision. Filters do not
   remove hidden rows from the command; “Show missing products” reveals unresolved
   mandatory products. Only the final action atomically opens the day.
4. **Live stock** — existing production and waste adjustments, photos and product
   picker. This is the working screen for today's open day, not an instruction to
   close immediately.
5. **Tomorrow preparation** — amounts for products flagged
   `is_prepared_before_sales`, independent of today's stock. Save uses the same
   authenticated shop/employee proxy, revision and persistent retry mechanism.
   Skip sends no preparation command. This does not redefine `is_pdm`.
6. **Closing** — existing shelf-life controls and guided waste/photo cards.
   Overdue open days go directly here and do not allow production/preparation.

Existing palette, fonts, quantity shortcuts, collapsed filters, sector/category
headers, searchable product picker and bottom navigation are retained. Wizard
navigation, validation hints, numeric input selection and footer controls support
touch use. An explicit stock refresh warns before discarding unsaved opening
entries. Intermediate opening steps are browser-local until the final opening;
there is no partial OPEN day or intermediate inventory posting. Uncertain writes
retain their original payload/request id in sessionStorage, including preparation.
All UI labels are provided in PL, EN, FR, IT and NL.

The opening receipt shows the three quantities and each product's persisted
absence reason, not a shared reason for the whole assortment. Night production
is posted at opening with a distinct movement and stored as overnight evidence;
it does not reopen or backdate the previous day.

API-first rollout is required; see the paired TFB-Api change and its
`docs/kitchen-day-wizard.md`. No migration is needed. The unused older MEP screen
is not re-enabled and its missing endpoint is not used; preparation is recorded
through the real new `production-day/prepare` command on existing day storage.

Checks: `bin/production-day-test.php`, `bin/navigation-test.php`,
`bin/production-day-browser-test.cjs` (legacy compatibility/regression) and
`bin/production-day-wizard-browser-test.cjs` (new flow on 1440, 1024, 768 and
390px). PHP suites generate browser fixtures in `KITCHEN_DAY_FIXTURE_DIR`; use
`KITCHEN_DAY_REAL_SHELL=1` for the legacy browser suite. Browser requests/storage
are mocked. Real API, POS, object storage and physical tablet testing are not
part of these browser checks.

Local fixture screenshots: [tablet opening](screenshots/production-day-wizard/tablet-opening.png),
[independent product reasons](screenshots/production-day-wizard/individual-reasons.png),
[preparation](screenshots/production-day-wizard/tablet-preparation.png).
The device-configuration API banner visible in these fixtures comes from the
existing full-shell test environment, not from the wizard implementation.
