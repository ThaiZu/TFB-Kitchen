For independent preparation and the reordered steps, see [walk closing](production-day-walk-closing.md).

# Preparation batches from current stock

The button above step four's stock filters opens preparation in progress. The
searchable picker only offers active/sale-enabled/supported is_pdm products from
the current sheet's sector. is_prepared_before_sales alone does not qualify for
this picker. Under it, previously recorded positive totals are displayed with
additional-quantity controls (±1/5/10) and the resulting total preview. Selecting
a new product creates a draft row; selecting an existing one focuses its row.

Saving submits positive increments for changed rows to prepare_add. Zero-only,
invalid native quantities and overflowing totals disable save. Cancelling writes
nothing. Save stays visible while the product list scrolls; request controls lock
and show a spinner through mutation and subsequent refresh. An uncertain response
uses the existing persisted request-id retry mechanism. Successful save returns
to current stock. Ready stock is never increased by preparation.

Step five includes these PDM totals plus the existing advance-preparation catalog.
It remains an editable review; next morning the recorded quantities are a reference
for actual night-production confirmation. PL/EN/FR/IT/NL labels, assets v28. Requires
the paired API prepare_add implementation; no migration.

Checks: production-day PHP proxy/routes, navigation, dedicated batch browser tests
in 1024/768/390px plus wizard, stock-filter and legacy browser regression suites.
Dedicated checks cover PDM filtering, adding to an existing product, weights,
lost-response replay without duplication, spinner/disabled save, cancellation,
step-five totals, fixed save visibility and unchanged stock. Browser API responses
are mocked; real inventory semantics are checked separately in isolated API MySQL.
Physical tablets, live POS/API/storage and full unrelated suites are not tested.

Release with the paired API workflow changes. No deployment was performed.
