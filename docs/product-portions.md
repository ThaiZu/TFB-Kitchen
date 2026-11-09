# Product portions in Kitchen

Products configured with active portions now show tablet-sized choices such as
Whole product, 1/2, 1/4 and 1/8 next to quantity controls. Selecting a portion
changes the visible input to its count, with an equivalent preview such as
`3 × 1/4 = 0.75 pcs`. +/- shortcuts increment that count. Whole product restores
the canonical quantity editor. The production-day API also supplies standard
stock fractions (1/2, 1/4, 1/8) for piece products without catalogue portions;
weighted products keep kilograms. These stock choices do not create saleable portions.
Without a catalogue portion ID the request and movement use the canonical quantity
(e.g. three halves = 1.5), without fabricated source IDs. Deploy the companion API
change before Kitchen to enable these additional choices.

The shared editor covers opening stock, overnight/day production, opening waste
allocations, live stock adjustments, preparation quantities and editable closing
counts. SSL full-disposal counts remain locked. Both standalone waste entry
points use the same editor. Waste reasons, photos and confirmations remain
unchanged. Stock/count/closing/preparation totals stay in whole-product equivalents;
selected adjustments, opening production and opening waste allocations also
submit source portion evidence for the movement history.

## Adding a product to current stock

The footer first asks only for a product. Its **Set quantity** action opens the
production form, where quantity and optional portion size are entered once.
The picker contains no duplicate quantity/portion controls. New products expose
only the production operation; cancellation writes nothing. Opening-day additions
keep their existing inline quantity entry because they update an unsaved draft.

For example, Abricot → 1/4 → 3 portions writes one production operation with a
native delta of 0.75 and keeps the portion ID/count for history. Abricot appears
once in the stock table. The quantity form explains that the badges choose portion
size and that the entered number counts those portions.

The original native quantity input remains the source for existing draft and
validation handlers. Its optional count editor never sends a trusted conversion
factor: API must verify the configured active portion and matching equivalent.
The source count is preserved through the standalone waste proxy and local draft.
An existing fractional stock can still be closed/carried when its portion is later
disabled. No new product records, flags or database tables are created.

Deploy the companion API change first. The API uses the existing four-decimal
stock precision. 1/2, 1/4 and 1/8 are exact; recurring fractions use the existing
rounding policy, so enter multiple thirds/sixths in one operation where possible.

Tests:

- `bin/product-portions-browser-test.cjs`: actual day templates, touch controls,
  source evidence, production, photographed waste, limits, opening shortages and
  separate night/day production and single-entry add-product flow (Abricot and
  weighted products) at desktop/tablet/mobile widths.
- `bin/waste-portions-browser-test.cjs`: both standalone report forms with
  canonical native quantities and source portion counts, 390/1024px.
- Existing day/shortage/stock-filter/wizard browser tests and PHP proxy/template/
  navigation tests cover unchanged day behavior and all five template languages.

Browser API responses are mocked; the companion API also has a guarded disposable
MySQL scenario for actual writes, closing, carryover and the following opening.
No deployment or test against a live shop database was performed.

## Tablet portion controls and contextual day filters

The shared portion editor labels its choices and uses buttons at least 72 × 56 px.
The whole-product choice occupies a full row; configured fractions use two columns.
Production and waste share this editor, including the standalone waste forms.
API portion validation and conversion require no additional schema or endpoint changes.

Day filters are populated from the products belonging to the current opening step
or positive current stock, then narrowed by shelf life and category group. Invalid
child selections reset when their parent changes. The add-product picker intentionally
retains eligible products that are not yet in the table.

## Yesterday's stock review

Opening step 1 edits the canonical carried quantity directly. It does not render
portion-selection cards inside the narrow table cell. Fractional shortcuts come
only from actual active catalogue `portions`, ignoring generic `stock_portions`;
weighted products keep their existing quantity controls. The smallest configured
fraction supplies the outer decrement/increment buttons (e.g. −0.25 / +0.25).
For those rows ±5 is omitted, leaving ±1 and ±10 between the outer buttons and
the native quantity field. Whole-only rows retain ±1, ±5 and ±10. All changes
clamp between zero and yesterday's stock, and use the normal shortage workflow.

`bin/production-day-carry-portions-browser-test.cjs` covers configured-only
fractions, integral-only/weighted rows, totals, limits, non-overlapping touch
targets and single-line button text at 1024/768/390px. Production/waste modals and
later wizard steps retain their existing portion-choice editors.
