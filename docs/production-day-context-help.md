# Context help, warm preparation accent and footer actions

The page title includes a native details/summary info control. Descriptive copy
is collected there for the current view instead of being repeated above tables.
It supports touch, Enter/Space, outside-click dismissal and Escape with focus
returned to the summary. Text is inserted with textContent; the help panel is
bounded to the viewport. View changes replace its content, and bake-window
explanations are included when available. Blocking errors, actionable empty states,
required modal instructions and asynchronous loading/failure statuses stay visible.
Unrelated operational-stock warnings are omitted from independent preparation.

Tomorrow preparation uses the site's cream surfaces and a restrained warm
left border on its heading, date marker and footer accent. The full blue page
background is removed; preparation batch cards use the same warm palette. Control
sizes/shapes, native units and preparation/stock semantics are unchanged.

Current-stock footer actions are Add product, Add preparation for tomorrow,
Go to closing. There is no duplicate preparation button above the table.
Preparation itself has batch entry beside Save in its footer; legacy open-day
preparation retains a footer batch action as well. These use the existing guarded,
authenticated modal and write/replay logic. Dock sizing continues to account for
wrapped labels on small screens.

Assets v31, PL/EN/FR/IT/NL. No API or database changes, no additional migration.
Checks: dedicated help/style browser suite at 1024/768/390 px (title alignment,
collapsed copy, help content, keyboard/touch dismissal, bounded tip, footer order,
button visibility and modal action, warm styling and context replacement),
production-day PHP rendering/routes, controller navigation, stock-filter,
preparation-batch, opening-shortage and full workflow browser regressions. Browser
API responses are mocked. Live services/storage, physical tablets, API tests and
full unrelated suites were not exercised. No deployment.
