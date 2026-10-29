# Physical count, automatic date changes and visible request state

When the API advertises stock_check.required and can_count, Kitchen asks the
employee to verify real quantities before continuing the selected workflow.
This is a conditional prerequisite, not another daily task when the records are
healthy. The modal includes units, sector/category labels, ±1/±5/±10 controls,
system balance, actual quantity, correction preview and an explicit confirmation.
Its header/footer stay visible while the product list scrolls.

The authenticated count command records a physical checkpoint. No waste reason
or photo is needed: a count correction is not a disposal or invented production.
The original workflow resumes after the refreshed server state arrives. Events
are retained for financial/integration resolution; confirming the physical stock
is the employee's responsibility, interpreting technical source events is not.
See the paired API's docs/kitchen-physical-checkpoint.md for ordering guarantees,
ambiguous event handling and limits around already closed financial dates.

Changing a valid date automatically requests that day's server-rendered screen
and current stock. “Show” remains a keyboard/no-JavaScript fallback, not a
required second click. Unsaved opening, preparation or closing values trigger a
confirmation; cancelling restores the original date and keeps the quantities.
Navigation is blocked during an uncertain write so the request can be resolved
using its original id.

Buttons/controls are locked immediately while a write or read is in flight. The
performing button shows a spinner and aria-busy; a visible status describes saving
or loading. The write modal stays open throughout POST and the subsequent GET,
rather than exposing a stale screen during the refresh. Upload inputs and modal
cancellation are blocked while photos are processed/uploaded, with local progress
spinners. Dialog Escape/close cannot interrupt an active write/read/upload.

On an uncertain write failure, the modal closes only after the request has
failed, and a visible retry uses the original request id/payload. After an
acknowledged write followed by a failed read, action buttons remain disabled
until Refresh/date navigation obtains definitive state; the acknowledged write
is not sent again. “Refresh” bypasses the unsaved-data warning in that case,
because the operation was already persisted.

Labels: PL/EN/FR/IT/NL. Assets v24. API-first rollout; no migration.

Checks: production-day PHP proxy/routes, full-shell navigation, legacy browser,
wizard browser, recovery browser and production-day-count-browser-test.cjs.
The new suite delays POST/GET to verify spinners, locks, double-submit protection,
count retry, failed refresh after a successful opening, date navigation and draft
preservation at 1024, 768 and 390px. Browser responses are mocked; API inventory
semantics are tested separately against isolated MySQL and ledger unit tests.
Real devices, actual POS/API/storage and the full unrelated application suite
are not exercised.

Preview: [tablet](screenshots/physical-count/tablet.png), [mobile](screenshots/physical-count/mobile.png).
