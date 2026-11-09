# Shared shop date for worker sessions

The employee header and PIN login previously used PHP's default calendar date,
while production and API proof validation used the shop timezone. At a midnight
timezone boundary, a worker could be shown as selected but production rendered
`employee_active=0`. Selecting the worker again signed another wrong-date cookie.

Kitchen now obtains the shop date from authenticated, read-only
`GET /shops/{shop}/business-clock`. `ShopClock` lazily caches it for one PHP request;
a successful production read can supply its authoritative `today` without an
extra call. There is no browser-local-date or PHP-date fallback when issuing
employee sessions. Clock failure is retryable and preserves the existing cookie.

Production day shows a prominent, localized employee-selection message before
its wizard when no worker is active, with a tablet-sized button opening the
existing employee/PIN picker. After successful selection and page reload, the
message disappears and opening actions become available. Closed read-only days
do not show this prompt. Selecting a worker does not automatically open a day.

The shared header, PIN login, worker roster, default staff schedule and checklist
worker attribution use that date. Historical checklist completion cannot replace
the current employee session with a historical-date cookie. Shift signatures,
shop scope, expiry and PIN verification remain intact. The API still validates
the proof against its own shop-local date, independently of the frontend.

Deploy the API PR first, then Kitchen. No migration is required. A previously
signed wrong-date worker cookie requires selecting the employee and entering the
PIN once after deployment; it is not silently re-dated.

Verification:
- Worker/controller regression: 14 offline checks including date mismatch,
  roster/login, default staff schedule, cache, malformed/wrong-shop clock response
  and temporary clock outage without cookie loss.
- Real controller/navigation: 977 checks, including real header identity on a
  different shop/PHP day and middleware DI construction.
- Existing production day and localized guidance (85), staff (37), shift (27) checks pass.
- Dedicated Chromium worker guidance/login/retry: 45 checks at 390/768/1024px.
- Companion API tests cover midnight, summer/winter offsets and DST.
- The old broad tablet selector test fails its existing department-choice touch
  target assertion; no sector CSS or control dimensions changed in this task.
- No live-shop or fully authenticated HTTP integration test was run.
