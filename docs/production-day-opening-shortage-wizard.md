# Opening shortage wizard and PDM-only night production

Opening shortages are reviewed one product at a time with progress, Previous,
Next, and a final review confirmation. Existing allocation reasons, multiple
reason quantities, required photos, pending/confirmed button colours and the
restore-stock action are retained. Next stays disabled until the current product
is resolved; final confirmation requires every difference to be resolved.

Each hidden product page is a disabled fieldset, so invalid hidden controls do
not prevent navigation back to a product that needs correction. Restoring stock
removes that shortage and advances to the next remaining product. Confirmed
allocations survive closing/reopening the modal in the same opening session.
The wizard starts at the first unresolved product, or the summary if all are
already confirmed. Decisions remain part of the pending opening; reviewing them
does not create inventory movements. Refreshing/reloading the whole page still
uses the existing unsaved-opening policy; this change adds no server draft table.

Overnight production displays only products where Number(is_pdm) === 1. This
applies to rows, the additional-product selector, category filter choices and
yesterday's preparation reference. Other products stay available in the carryover
and today's-production steps and are not dropped from opening payloads. An empty
PDM catalog has an explicit message. The night step starts with cleared carryover
filters. No API rule/schema change is needed for this presentation restriction.

Current stock keeps combined badge filters and a fixed sector/category/name order;
manual sorting controls/state and their labels are removed. Other stock features,
bake-window statistics and adjustments remain unchanged. Asset version 30; labels
are present in PL/EN/FR/IT/NL.

Checks: dedicated opening-shortage browser suite on 1024/768/390px, plus opening,
stock-filter and full production-day browser regressions; PHP rendering/navigation,
JavaScript syntax, translation-key parity and diff checks. Browser responses are
mocked. API suites, live API/POS/storage, physical tablets and full unrelated suites
are not run for this Kitchen-only change. No migration or deployment.
