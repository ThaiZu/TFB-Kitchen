# No surprise date changes during opening

The employee [physical-count path](production-day-physical-count.md) now extends this initial empty-day recovery to the affected products in a nonempty shop.

Before beginning today's wizard, the API reports an overdue opening blocker even
when today's date was chosen explicitly. Kitchen preserves that selected date,
hides the wizard, and offers one clear action to the overdue date. Canonical-day
inconsistency without a Kitchen day is also reported before starting work.

If a blocker appears concurrently while opening is being saved, the failed
command remains on the selected date and refreshes the blocker instead of
silently navigating to another date. Uncertain writes retain their original
request id and remain retryable, including on a blocked entry screen.

On an internally blocked day with **zero stock throughout the whole shop**, the
user can select “Finish the empty day” and explicitly confirm there are no
finished products. API rechecks all balances under the shop lock. If anything
changed, closing stops and the latest state is shown. Confirmation finishes all
open Kitchen scopes for that business date, without stock movements, ledger
history edits or clearing unresolved events. The confirmation is audited in the
existing closing snapshot and command storage.

After overdue closing, including a successful replay after a lost response,
Kitchen returns to today's entry. If another overdue day remains, that entry
shows its blocker. No opening is submitted automatically.

Real nonzero inventory inconsistencies remain protected. The message directs the
user to refresh and ask an administrator to inspect inventory/integration data,
rather than to a nonexistent production module. Technical events are not declared
resolved by a physical-empty confirmation; further reconciliation may be needed.

API-first deployment; no migration or manual deletion of day records is required.
Tests: production-day PHP, full-shell navigation, legacy browser, wizard browser,
and new `production-day-recovery-browser-test.cjs` at 1024, 768 and 390px.
Browsers use local API fixtures. Live API, POS/storage and physical tablet tests
are omitted.

Fixture screenshots: [early blocker](screenshots/day-entry-recovery/early-blocker.png)
and [empty-stock confirmation](screenshots/day-entry-recovery/empty-confirmation.png).
The optional device-config API banner is part of the existing local fixture shell.
