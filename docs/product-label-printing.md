# Kitchen product labels: API queue, one selected POS, Panel settings

## Delivered scope

- Kitchen `/product-labels`: searchable shop product list, optional existing portion,
  integer label count, preparation datetime, editable batch, submit, last 100 jobs
  and explicit reprint confirmation. The selected Kitchen employee signs the request.
- Panel `/shops/product-label-printer`: select one POS or pause printing, independently
  from webshop order printing. Optional automatic labels after fresh Kitchen production
  receipt. No POS is silently selected on initial installation.
- POS: a background worker polls every 15 seconds and reuses the existing ZPL builder
  and Windows RAW spool transport. It does not require an open cashier shift. No new
  POS screen, physical-device driver or production dependency was added.
- API: durable immutable snapshots, scoped jobs, audited request IDs, selected-device
  arbitration, leases, start-before-spool acknowledgement and explicit reprints.
- UI and labels support PL/EN/FR/NL/IT. Automatic labels use the selected employee's
  language, falling back to French. Small-label captions stay compact.

## Model and endpoints

Migration `TFB-Api/database/2026_11_16_product_label_queue.sql` creates:

- `product_label_printer`: selected device and automatic-receipt setting per shop;
- `product_label_request`: unique `(shop_id,request_key)`, input hash, source and actor;
- `product_label_job`: immutable JSON, state, attempts, lease owner/token and history.

All endpoints are under `/api/v1/shops/{shop}/product-label-printing/`:

- GET `catalog`, `jobs`, `settings`; PUT `settings`;
- POST `create`, `claim`, `start`, `finish`;
- POST `jobs/{job}/reprint`.

Authenticated matching shop scope is required. Device identity is checked against
its database shop, and claim/start must match the single selected POS. No new `app`
or permissions gate was added. Existing webshop printing authorization is unchanged.
Settings expose only device ID/name/type, never PINs, passwords or tokens.

Manual request: `request_id`, `employee_id`, `prepared_at` (RFC3339 seconds with offset),
`lang`, and `lines: [{product_id,portion_id:null|id,count}]`. The Kitchen proxy derives
employee and language from the signed staff session instead of accepting client values.
Limit: 100 lines, 300 labels per line, 1,000 labels per request. Missing product,
invalid/inactive portion, missing portion price, invalid format or shelf life is rejected.
A repeated request ID returns its original result; changed data with the same ID gives 409.

The snapshot stores product/portion name, real shop/portion price, recipe ingredients,
translated recipe allergens, storage/reheating values, employee/shop data, label format
and preparation/expiry dates. Empty allergen data does not assert absence of allergens.
Dates use the configured shop timezone; shelf-life minutes represent elapsed minutes,
including DST changes. No artificial age limit is imposed on a preparation date.
Printing/reprinting never substitutes current POS product data, employee or print time.
The existing technical QR is retained; it is not a loyalty QR.

## Printing lifecycle and failure handling

`pending → leased → submitting → submitted`.

- One in-flight job per shop. Each job is one existing physical template batch: up to
  three small labels or one large label. Windows spool submission is not physical proof
  of paper output; user-facing status says submitted to printer.
- SQL configuration upsert takes an exclusive duplicate-key lock before claim, avoiding
  shared-to-exclusive lock upgrade deadlocks between simultaneous workers.
- Before start/spool, POS durably records an uncertain intent outside the repository,
  under the existing local data directory in `ProductLabelPrinting/` (no SQLite change).
- Failed HTTP acknowledgement retries only acknowledgement, never the physical print.
- A proven pre-submission failure retries after 30 seconds, maximum three attempts.
  Exhausted jobs are `failed`; users can explicitly reprint from Kitchen.
- Partial or unknown submission becomes `uncertain` and is never automatically reprinted.
  An expired `submitting` lease also becomes uncertain. History reconciles expiry even
  while printing is paused. Expired leases that never started may return to pending.
- Changing the selected POS does not erase another POS's active lease. The old device
  cannot start new work; an old valid token may still acknowledge its already-submitted
  job. Reprints create a new audit request and retain original dates/content.
- Rendering that uses WPF resources runs on its dispatcher. RAW spool I/O runs in the
  background so the cashier UI is not held during a printer-driver call.

## Automatic receipts

Disabled by default. Enabling in Panel creates labels in the same database transaction
as fresh Kitchen receipt, not as a browser follow-up. Hooks cover confirmation of the
Kitchen production-plan cycle, legacy Kitchen receipt, explicit fresh production at
opening and positive production additions in the Kitchen day view.

One label per received physical piece/portion, using source portion count instead of
scaled whole-product inventory units. Weighted products, fractional label counts and
old overnight carryover are not automatically labelled; use the manual form with the
correct preparation date. No labels are created for waste, stock counts or corrections.

A receipt exceeding 1,000 labels produces one visible `blocked` job for manual split
requests; it does not expand into an unbounded queue inside the stock transaction.
Missing label metadata also creates a blocked job instead of undoing received stock.
Printer/network availability is not consulted by the receipt transaction. Other database
failures remain real transaction failures, with existing receipt idempotency protecting
retries. No historical receipt backfill or physical reprint is performed at deployment.

## Deployment and rollback

1. Run `2026_11_16_product_label_queue.sql` once. IF NOT EXISTS permits re-execution and
   preserves existing queue/history (verified on isolated MySQL).
2. Deploy API, update the selected Windows POS, then deploy Panel and Kitchen.
   Use normal Composer deployment/install/autoload generation for new PHP classes;
   Panel's existing GitHub deployment regenerates its classmap. Do not copy stale vendor.
3. Configure the Zebra-compatible label printer in POS as for existing manual labels.
4. In Panel, choose the one POS under `/shops/product-label-printer`. Automatic receipts
   are optional. Kitchen manual jobs can be queued while no printing POS is selected.
5. Verify one small/large physical label before enabling automatic receipts for a shop.

Rollback: pause the label printer and disable automatic receipts in Panel, stop the new
POS worker by rolling back its client, then roll back web/API code as needed. Retain the
three queue tables and local acknowledgement journal. Do not delete pending/uncertain
jobs or replay them wholesale; inspect uncertain physical outcomes explicitly.
No deployment, production migration, production API request or physical print was run.

## Verification and remaining limits

Automated verification:

- API queue MySQL: 17 tests / 77 assertions; actual two-process competing claims,
  selected/foreign devices, handover, expiry while paused, idempotency, portions,
  immutable dates/DST, compact captions, retry limits, blocked metadata, automatic
  receipt rollback/bounds/language and safe migration re-execution.
- Kitchen-day MySQL inventory regression: 250 checks, including physical-unit and
  portion-count arguments sent to the label queue. Standalone fixture supports an
  explicitly enabled disposable MySQL sandbox; no existing shop database is used.
- Broad production regression: 682 tests / 20,622 assertions, with one pre-existing
  error in `ProductionDirectInventoryGuardTest::testPriceOnlyUpdateDoesNotGateOrChangeInventory`
  (`ShopProductService::$promotionPriceGuard` not initialized). The same error reproduces
  on unchanged baseline `dbc3303`; it is outside this change.
- POS: solution build, 0 errors, 702 existing warnings (framework/package/nullable and
  existing platform warnings). No new production warning was identified in changed
  label services. 13 headless tests pass: worker/journal, retry after restart/network,
  context isolation, all spool outcomes, actual ZPL builder dates/QR/escaping and the
  existing manual current-time fallback.
- Kitchen/Panel: strict Twig and five-language labels, 160 + 60 checks. Isolated browser
  tests: 33 checks at 1366×768, tablet 768px and mobile 390px; portions, safe product text,
  lost-response replay, reprints, device selection/pause and errors. Browser tests use
  mocked API routes and generated fixtures, not a live authenticated shop.
- PHP/JS syntax, JSON validity, OpenAPI validation and complete route coverage passed.
- Independent read-only POS/API audit returned PASS after its findings were repaired.

Useful commands (PHP 8.2 / .NET SDK / Node environments required):

```
# Disposable MySQL must be explicitly provisioned at loopback port 13316:
TFB_RUN_MYSQL_TESTS=1 TFB_CORRECTION_DISPOSABLE_MYSQL=1 php vendor/bin/phpunit tests/Integration/ProductLabels/ProductLabelQueueMySqlTest.php --do-not-cache-result
TFB_RUN_MYSQL_TESTS=1 TFB_CORRECTION_DISPOSABLE_MYSQL=1 TFB_KITCHEN_DAY_ISOLATED_TEST=1 php tests/Production/kitchen-day-mysql-test.php
# POS:
dotnet build RefreshedPOS.sln --no-restore -p:EnableWindowsTargeting=true -c Release
dotnet test tests/ProductLabels.Core.Tests/ProductLabels.Core.Tests.csproj --no-restore
# Kitchen and Panel fixture renderers, using a shared output directory:
php bin/product-labels-render-test.php /tmp
php tests/Shop/LabelPrinterRenderTest.php /tmp
node bin/product-labels-browser-test.cjs
```

Browser test is run from Kitchen with Panel checked out as its sibling. Override
`LABEL_FIXTURE_DIR`, `LABEL_PANEL_ROOT` and `PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH` as needed.
New POS test dependencies are test-only and reuse existing Newtonsoft/xUnit versions.
There are no production dependency upgrades or local SQLite migrations.

Not verified: native WPF execution, a physical Windows spooler/Zebra and actual print
legibility/media alignment. Windows-only existing WPF tests and persistent production
plan integration tests requiring a separately provisioned full schema were not run;
the POS worker suite and disposable Kitchen inventory suite cover this task's paths.

Deferred as agreed: customer-order label redesign/allergens, loyalty redemption QR,
category packing aggregation, dedicated two-up media layout, and other email changes.

## Manual acceptance after deployment

1. Two POS devices A/B in the same shop, both updated and online; choose A in Panel.
   Queue two products/portions from an employee session in Kitchen. Only A may print.
2. Retry the same submit after a lost response; there must be one request/batch only.
3. Stop A, queue more labels, choose B. Unstarted expired work can move; uncertain work
   must not silently reprint. Verify late acknowledgements and history.
4. Remove paper/disconnect printer. Verify limited automatic retry only if no bytes
   were submitted, otherwise manual review. Restore printer and use explicit reprint.
5. Wait before printing/reprinting: preparation and use-by dates, portion price, employee
   and allergens must match the original snapshot, not the later POS session.
6. Enable automatic receipts: receive three fresh pieces / two half portions; expect
   three / two labels, not scaled inventory quantities. Repeating receipt must not add
   stock or labels. Weighted products and overnight carryover must not auto-print.
7. Receive >1,000 units or a product with missing label metadata: stock succeeds, one
   blocked job is visible. Printing can be requested manually after checking data.
8. Repeat a single small/large print on real media, including a long name, all allergens
   and FR/NL date captions. Confirm QR remains the existing technical product code.
