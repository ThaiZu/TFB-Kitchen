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
overnight production retains its existing portion-choice editor. Daytime production
(step 3) now uses the same compact native field and configured fractional shortcuts
as step 1. Its limit remains the normal production maximum, not yesterday’s stock.
The browser scenario also verifies step 3 layouts, fraction changes and production
above the carried quantity.

Tomorrow preparation (step 6) also uses the compact native quantity field and
configured fractional shortcuts. Its quantities remain independent of current stock
and are sent as canonical equivalents through the existing preparation command.
The focused browser scenario verifies mobile/tablet layout, fractional changes,
save/reload and unchanged current-stock quantities.

## Guided carryover review

Opening step 1 now shows one positive-stock product at a time, ordered using the
existing product order. A progress bar tracks explicit confirmations. Each card
shows the previous quantity, editable native carry quantity and the same configured
fraction shortcuts as the production/preparation controls. Validation prevents
negative quantities and quantities above previous stock. Whole-only and weighted
products do not gain catalogue-fraction buttons.

Previous-product navigation and edit actions on the final summary retain the
in-memory quantities. Changing a quantity invalidates that product's confirmation
and waste evidence. Every product must be confirmed before continuing. The summary
always includes the entire carryover scope; table filters cannot skip review items.
An empty carryover proceeds directly from its empty summary to overnight production.

Shortages still use the existing reason/photo wizard before overnight production.
Restoring a shortage updates the carry summary immediately. Reviewing/confirming
cards causes no stock/API writes; the normal final opening command persists the
checked quantities and applies inventory movements atomically. Reloading resets an
unfinished review, consistently with the existing opening draft behavior.

The browser helper `bin/helpers/carry-review.cjs` follows all user-visible card
confirmations. Tests cover back/edit retention, invalid quantities, zero stock,
summary progress, configured fractions, mobile touch layout, shortages/restoration,
opening writes and existing preparation/closing flows in all template languages.

## Physical stock reconciliation modal

The separate modal titled “Vérifiez le stock réel des produits” (`beginStockCheck`)
now presents one physical count at a time, then an editable final summary. It uses
the compact native-quantity control with configured-only fraction shortcuts, not
the catalogue portion selector. Actual stock can exceed recorded stock; no carryover
cap applies. Weighted products retain kilogram inputs. Navigation does not focus a
number field automatically, avoiding unnecessary tablet keyboard activation.

Each product requires confirmation, but per-product confirmation does not send a
command. Only the final count confirmation submits the entire original product
scope, token and stock/movement preconditions via the existing atomic `count`
endpoint. Final submission retains the spinner, double-submit lock and retry/read
handling. Counts remain inventory corrections, not production or waste; reasons and
photos are not introduced. Back/edit retains quantities while the modal stays open;
cancelling/reopening starts a new local review, consistent with the previous modal.

`bin/production-day-physical-count-wizard-browser-test.cjs` checks the actual count
modal at 1024/768/390px, configured-only fractional controls, limits, back/summary
editing, zero early writes and the final full-scope native-quantity payload.

## Fraction labels and storefront images

Configured fractional shortcuts now display rational labels such as −1/4 / +1/4,
−1/2 / +1/2 and −1/8 / +1/8. Their numeric data attributes and exact scaled arithmetic
remain unchanged; native quantity fields and API payloads still use decimals.

Production-day product rows and product cards in carry review, physical count,
stock adjustment, opening shortage, preparation batch and closing use the API's
`webshop_image.url`. Images are the same public R2 storefront images as product
knowledge cards. Thumbnails are bounded, lazy-loaded, and disappear on load failure;
missing images do not block editing. Count summaries include compact thumbnails.
Quantity buttons reserve scroll margins for the fixed header and action dock.
Deploy the companion API enrichment before Kitchen. No migration is required.
