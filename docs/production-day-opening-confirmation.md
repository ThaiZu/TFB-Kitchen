# Opening confirmation and missing production guide

Opening shortage cards use one tablet-sized confirmation button, without the
redundant disabled checkbox. Pending disposal is amber; confirmed disposal is
green and explicitly labelled. Editing allocations, reasons or photos resets the
confirmation. The modal save button remains disabled and grey until every
remaining card is confirmed; restoring stock removes a card from that requirement.
The save callback also retains its validation guard.

During today's production entry, an amber guide sits above the footer actions.
It counts mandatory products with zero total quantity and no complete individual
absence explanation, including products hidden by current filters. Clicking it
clears the filters and focuses/scrolls to the first pending explanation in table
order. The guide disappears when all such decisions are complete, and respects
reduced-motion preferences.

No API or schema change. Asset version 25, PL/EN/FR/IT/NL labels.
Validation: production-day proxy/rendering, navigation, legacy and wizard browser
suites. Browser backend responses are mocked; no live API, storage or physical
tablets were tested. Full unrelated suites and API tests were not run because
this change only affects Kitchen presentation and local validation.
