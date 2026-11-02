# Production day workflow batch

Steps are: yesterday stock, overnight production, today's production, current
stock, day closing, preparation for tomorrow. New API metadata enables the new
flow; older APIs retain their previous wizard/closing presentation.

Preparation is independent of opening/closing. It stays reachable before opening,
with an overdue-day blocker, and after closing. Selecting it on a historical day
opens today's preparation tool. It uses a separate server preparation revision
and catalog, so closed-day read-only stock does not block this helper. The log
shows preparation entries since opening (OPEN) or last closing (CLOSED), with
product, author, local timestamp and quantity. Replacement totals and added
batches are labelled distinctly. The API bounds the log to its latest 200
matching commands. Existing PDM-only batch entry and step-six total review remain.

Current stock asynchronously loads configured current/upcoming bake windows.
Columns show native observed sales averages for the last four matching weekdays,
remaining-window average and projected current-window end stock. Negative
projections are highlighted. Missing configuration/data (including unsupported
weighted historical sales) is explicit and does not block stock adjustments.
Statistics do not write stock; Refresh/date change reload them.

Closing initially shows one centered start button. The modal presents progress
and one product at a time. SSL waste is fixed to full stock; MSL/LSL have editable
carryover controls, with full LSL stock prefilled. Positive waste requires a reason
badge and photo. Next saves that product's draft to the API before advancing.
Saved steps can be revisited/overwritten; Back warns before discarding an unsaved
current step. Closing/reloading the screen and worker handover resume saved counts.
The final short summary requires a separate closing confirmation; only that action
changes ready stock/waste. A stock change requires that product to be recounted.

Headers/footers stay visible. The tablet layout (768/1024 px, 1000 px viewport
height) is checked without vertical body scrolling for one product with photo;
small phones use safe body scrolling. All quantity fields keep tablet increment
controls, editable input selection and existing native-unit validation. Locks and
spinners cover save plus refresh; uncertain saves retry with their original IDs.
The global bottom bar no longer has the generic knowledge-base link; product
cards and navigation-sheet knowledge access remain.

Paired API migration: ../database/2026_11_02_kitchen_closing_draft.sql in TFB-Api.
Apply manually before rollout. Assets v29, PL/EN/FR/IT/NL. No production migrations
or deployment performed. Release with the paired API workflow changes.

Validation: PHP proxy/routes, controller navigation; dedicated new workflow
browser suite with mocked API at 1024/768/390 px, including lost draft response,
reload/resume, editing quantities without duplicate rows, LSL carryover and
preparation after closing/before opening. Separate isolated MySQL tests verify
real API persistence/stock and POS average semantics. Legacy, opening-wizard,
stock-filter, preparation-batch, recovery and count browser regressions are also
run. Physical tablets, live POS/API/storage and full unrelated suites are skipped.
