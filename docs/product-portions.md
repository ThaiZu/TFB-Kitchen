# Product portions in Kitchen

Products configured with active portions now show tablet-sized choices such as
Whole product, 1/2, 1/4 and 1/8 next to quantity controls. Selecting a portion
changes the visible input to its count, with an equivalent preview such as
`3 × 1/4 = 0.75 pcs`. +/- shortcuts increment that count. Whole product restores
the canonical quantity editor. Products without configured portions keep the
existing controls; weighted products keep kilograms.

The shared editor covers opening stock, overnight/day production, opening waste
allocations, live stock adjustments, preparation quantities and editable closing
counts. SSL full-disposal counts remain locked. Both standalone waste entry
points use the same editor. Waste reasons, photos and confirmations remain
unchanged. Stock/count/closing/preparation totals stay in whole-product equivalents;
selected adjustments, opening production and opening waste allocations also
submit source portion evidence for the movement history.

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
  separate night/day production at desktop/tablet/mobile widths.
- `bin/waste-portions-browser-test.cjs`: both standalone report forms with
  canonical native quantities and source portion counts, 390/1024px.
- Existing day/shortage/stock-filter/wizard browser tests and PHP proxy/template/
  navigation tests cover unchanged day behavior and all five template languages.

Browser API responses are mocked; the companion API also has a guarded disposable
MySQL scenario for actual writes, closing, carryover and the following opening.
No deployment or test against a live shop database was performed.
