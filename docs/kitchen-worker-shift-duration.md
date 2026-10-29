# Kitchen worker shift duration

Worker PIN sessions now allow 8 hours since the last validated operation and
12 hours total since PIN login (previously 30 minutes / 8 hours). Filling out a
production wizard locally does not refresh the server session. Kitchen validation,
cookie expiry and API shift-proof validation use matching limits.

Deploy API before Kitchen. No schema change or migration. Existing cookies keep
their original expiry until the worker signs in again. Sign in again after rollout
to obtain the 12-hour cookie. Shop binding, signature verification and the API's
shop-local work-date check still apply, so a new calendar day requires a new PIN.
Longer sessions keep actions attributed to the selected worker; change the active
worker when handing the tablet over.

Tests cover longer inactivity, idle expiry, total expiry despite activity, and
forged/wrong-shop proofs. No live login, physical tablet or unrelated full suite
was tested.
