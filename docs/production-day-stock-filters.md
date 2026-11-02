# Current stock filters, tablet selectors and tomorrow colours

Current stock uses the existing opening taxonomy: shelf life (SSL/MSL/LSL),
category group and category. Badge filters combine with AND; groups/categories
are collapsed by default, and changing group resets category. These filters are
independent of opening filters and remain selected when returning from tomorrow
preparation. The list contains positive stock only; an empty filter result is
explicit. The add-product selector still excludes all positive-stock products,
including ones hidden by a filter.

Sort badges order by category group, category, shelf life (SSL/MSL/LSL), or name,
with ascending/descending direction. Names break ties, product IDs provide stable
ordering and missing classification sorts last. Multi-group products sort by
the alphabetically ordered list of group names. Department/category headings and
product classification badges provide context. Filters/sort do not write stock.

On Production Day the worker and sector selectors, including modal choice cards,
use the production controls' 10px corner radius. Existing sizes and behaviours
remain. Tomorrow preparation uses a pale blue background, blue table headings,
notice and footer. Its geometry and inventory semantics are unchanged. The colour
scope is cleared when returning to current stock. Labels support PL/EN/FR/IT/NL;
asset version 27. No API or database changes.

Validation: production-day PHP (33), controller navigation (894), dedicated stock
filter browser suite (60, across 1024/768/390px), plus legacy/wizard regression
browser suites. Dedicated checks include combined/empty filters, category reset,
sort direction, correct product action, colour scoping, independent preparation,
selector shape and no horizontal page overflow. Browser responses are mocked.
No live API/POS/storage, physical tablets or full unrelated suites were tested.
