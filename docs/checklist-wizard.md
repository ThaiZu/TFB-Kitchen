# Checklist execution wizard

Each selected `/checklists?date=...&checklist_id=...` page offers its own
Start/Resume button. The all-checklists overview remains a set of separate links;
the wizard never concatenates task lists. Existing employee-position filtering,
PIN/shift verification and date rules stay authoritative on the server.

Starting pins the selected date/checklist ID and `wizard=1` in the URL. One task
card is visible, with previous-task navigation and an answered-task progress bar.
Pending tasks are handled with the existing Done or Failed actions. Done still
requires a photograph where configured; Failed still requires a reason. Existing
Skipped results are readable. The final summary shows each saved status and can
reopen an individual card for inspection/correction. Failed responses count as
answered, not as successful task completion; checklist completion statistics retain
the existing API semantics.

Each action posts separately through the existing multipart completion endpoint.
Only an acknowledged successful response reloads the page. The next authoritative
server render automatically resumes at the first pending task of the same selected
checklist. Reloading also refreshes the verified employee context, including a
newly opened shift and the employee's allowed task scope. There is no client-side
completion cache or batch save. Errors keep the current task available. Busy state
locks task/wizard/modal actions, shows a spinner, and prevents duplicate submissions
or closing the modal during a write/refresh transition.

The existing API upserts `todo_task_completion` occurrences and stores notes,
worker attribution and attachments. Its occurrence identity is unchanged: a task
shared by multiple checklists keeps the existing shared shop/day completion state.
No API, Panel or Admin source change or migration is needed for this UI workflow.
No deployment was performed.

Validation:
- `bin/checklist-wizard-test.php`: real controller/layout rendering in five
  languages with shift/PIN modes, separate-checklist and empty states; emits
  runtime fixtures into `CHECKLIST_FIXTURE_DIR`.
- `bin/checklist-wizard-browser-test.cjs`: Chromium 1024/768/390px, individual
  saves/reloads, failures, non-JSON session response, double-submit locking,
  mandatory photos, failed-task reason, PIN/stay-logged, summary and isolation.
- Existing `bin/checklist-test.php` and `bin/checklist-pin-test.php`: focus rule
  and server-side PIN/multipart completion contract.

Browser API responses are mocked; no live shop data is read or changed.
