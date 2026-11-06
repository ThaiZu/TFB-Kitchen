(() => {
    'use strict';
    const script = document.currentScript;
    if (!script || window.__kitchenSession) return;
    window.__kitchenSession = true;

    const root = script.dataset.root;
    const base = new URL(root, location.origin);
    const originalFetch = window.fetch.bind(window);
    let redirecting = false, inFlight = false, lastPing = 0, retry = null;

    function needsLogin() {
        if (!redirecting) {
            redirecting = true;
            location.assign(root + '/auth');
        }
        throw new DOMException('Authentication required', 'AbortError');
    }

    // Preserve request bodies and never retry mutations. Only the device-auth
    // response triggers login; a missing employee PIN is a separate condition.
    window.fetch = async (input, init) => {
        let url;
        try {
            url = new URL(input instanceof Request ? input.url : String(input), location.href);
        } catch (_) {
            return originalFetch(input, init);
        }
        const local = url.origin === base.origin &&
            (url.pathname === base.pathname || url.pathname.startsWith(base.pathname + '/'));
        if (local) {
            const headers = new Headers(input instanceof Request ? input.headers : undefined);
            new Headers(init?.headers).forEach((value, key) => headers.set(key, value));
            headers.set('X-Requested-With', 'XMLHttpRequest');
            if (!headers.has('Accept')) headers.set('Accept', 'application/json');
            init = {...init, headers};
        }
        const response = await originalFetch(input, init);
        if (local && ((response.status === 401 && response.headers.get('X-Kitchen-Auth') === 'required') ||
            (response.redirected && new URL(response.url).pathname === base.pathname + '/auth'))) {
            return needsLogin();
        }
        return response;
    };

    async function ping() {
        if (inFlight || redirecting || document.visibilityState === 'hidden' || Date.now() - lastPing < 25000) return;
        inFlight = true;
        lastPing = Date.now();
        try {
            const response = await fetch(root + '/ajax/session', {credentials: 'same-origin', cache: 'no-store'});
            if (response.status === 503 && !retry) {
                retry = setTimeout(() => { retry = null; ping(); }, 30000);
            }
        } catch (_) {
            // A network failure is not a logout; a later heartbeat can retry.
        } finally {
            inFlight = false;
        }
    }
    setInterval(ping, 300000);
    document.addEventListener('visibilitychange', ping);
    window.addEventListener('focus', ping);
    ping();
})();
