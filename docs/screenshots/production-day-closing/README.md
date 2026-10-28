# Guided production-day closing

Playwright captures using local mocked shop data (no production data), 1000px viewport height:

- [Phone, 390px](mobile.png)
- [Tablet, 768px](tablet.png)
- [Desktop, 1440px](desktop.png)
- [Phone: all products confirmed](ready-mobile.png)

The body scrolls independently while the modal header and final actions remain visible. Each product is confirmed locally; only the final closing command changes stock.

Run `bin/production-day-test.php`, `bin/navigation-test.php`, then `KITCHEN_DAY_REAL_SHELL=1 node bin/production-day-browser-test.cjs` with the same `KITCHEN_DAY_FIXTURE_DIR` to regenerate fixtures and screenshots. Browser tests use intercepted requests, not a live API or attachment storage.
