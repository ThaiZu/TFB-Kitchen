# Kitchen tablet session

The device login uses a 14-hour access token and a rotating refresh token.
Kitchen refresh sessions now last 60 days from login or successful refresh;
POS and other device types keep their previous 30-day lifetime. This does not
extend the employee PIN/shift session or bypass logout/revocation.

Authenticated Kitchen pages call protected, read-only `GET /ajax/session` every
five minutes while visible and on focus/visibility return (25-second throttle).
The existing auth middleware renews an absent/expiring access token. A suspended
browser does not need to run background timers: its next request can refresh
using the HttpOnly refresh cookie. No credentials enter JavaScript.

Refresh errors are distinguished from invalid credentials. Network failures,
malformed upstream responses and API outages preserve device cookies; an expired
access token yields retryable JSON 503. A usable access token remains usable during
a temporary refresh outage. Definitively invalid refresh credentials yield JSON
401 for AJAX and a normal login redirect for document navigation. The browser
recognizes the device-auth response before attempting JSON parsing; employee
`KITCHEN_SHIFT_REQUIRED` responses do not trigger a device logout. Inventory writes
are never automatically replayed. Heartbeat 503 failures retry a read after 30 seconds.

The companion API change handles concurrent refreshes: for 60 seconds, a Kitchen
request may recover the same immediate, still-active successor of a rotated token.
The API stores hashes only. Expired/revoked successors and other device types are
excluded. Without this protection, a losing concurrent request could delete the
cookies just set by the winner.

Deploy the API change before Kitchen. No schema migration is needed. Existing
30-day refresh cookies receive the longer lifetime on the next successful refresh
or login; refresh tokens that have already expired/revoked require login once.
Reload existing tabs to load the new versioned browser script.

## Offline verification

- `php bin/session-test.php`: renewal, cookie lifetime, temporary/invalid failures,
  JSON/HTML responses and no-cookie-loss behavior.
- `node bin/session-browser-test.cjs`: idle/focus heartbeat with simulated time,
  auth redirect before parsing, temporary outages, employee PIN separation and
  no automatic mutation retry.
- `php bin/navigation-test.php`, `php bin/knowledge-product-data-test.php`,
  `node bin/knowledge-products-browser-test.cjs`: real templates, product images,
  inherited sector, dependent filters, and desktop/tablet/mobile layouts.
- API: `DevicePersistentSessionTest` and existing auth tests. The shared auth suite
  has JWT constant interference in one process; running each auth test file in
  its own PHP invocation passes. PHPUnit child process isolation is unsuitable
  for the temporary shared-vendor checkout bootstrap used here.

No live MySQL concurrency or real 60-day wall-clock endurance test was run.
Browser timing uses a simulated clock; API concurrency uses repository mocks.
