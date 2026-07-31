# Mocks — module Production

Un fichier par endpoint, avec la charge utile exacte que le front attend.
Le contrat en prose est dans `docs/ENDPOINTS_PRODUCTION.md` ; ces fichiers en
sont la forme exécutable — à servir tels quels pour développer sans back-office.

| Fichier | Endpoint |
|---|---|
| `00_production_config.json` | `GET /shops/{shopId}/production/config` |
| `01_production_products.json` | `GET /shops/{shopId}/production/products` |
| `02a_mep_today_prepared.json` | `GET /shops/{shopId}/mep?date=<aujourd'hui>` — à valider |
| `02b_mep_today_validated.json` | idem, après validation |
| `02c_mep_tomorrow_draft.json` | `GET /shops/{shopId}/mep?date=<demain>` — brouillon repris à l'ouverture |
| `03a/03b_mep_save_*` | `POST /shops/{shopId}/mep` — encodage de la MEP de demain |
| `04a/04b_mep_validate_*` | `POST /shops/{shopId}/mep/validate` |
| `05_stock.json` | `GET /shops/{shopId}/stock` |
| `06_sales_profile.json` | `GET /shops/{shopId}/sales/profile` |
| `07a/07b_batch_*` | `POST /shops/{shopId}/production/batches` |
| `08_pending_count.json` | `GET /shops/{shopId}/production/pending-count` |
| `09_error_examples.json` | formes d'erreur attendues |

Les suffixes `_REQUEST` / `_RESPONSE` distinguent ce que le front envoie de ce
que le serveur renvoie.

## Ce que les mocks encodent volontairement

Ils ne sont pas un jeu « tout va bien » : chaque cas limite y est représenté,
parce que ce sont ceux qui cassent en production.

- **`6700140` (macaron) n'a pas de `batch_size`.** Le front le traite à
  l'unité et l'écrit à l'écran — proposer « 17 pièces » à un four qui sort des
  plaques de 24 n'est pas exécutable, et le taire serait pire.
- **`6700150` (bûche) est `is_active: false`.** Stock à zéro, jamais proposée,
  jamais affichée.
- **`6700160` (sandwich) est absent de `sales/profile`.** Produit dont on ne
  sait rien : aucune proposition. Le traiter comme « zéro vente » ferait
  enfourner à l'aveugle.
- **`04b` renvoie une ligne `SKIPPED` avec `quantity_validated: 0`.** Écarter
  une ligne et en produire zéro ne racontent pas la même chose.
- **`04b` et `07b` renvoient le stock résultant.** Le front réaffiche un chiffre
  serveur au lieu de faire sa propre addition : deux tablettes peuvent valider
  à quelques secondes d'intervalle.
- **`06` a une vraie courbe de journée** — pic du matin, creux de 10 h, pic de
  midi. Un profil plat validerait la mécanique mais pas les propositions.

## Servir les mocks

```bash
cd docs/mocks && python3 -m http.server 8080
```

Puis, le temps du développement, pointer `KITCHEN_API_BASE` vers un petit
routeur qui renvoie ces fichiers. Les fixtures de test de la prévision, elles,
vivent séparément dans `tests/fixtures/` : elles sont calibrées pour que le
résultat se recalcule à la main (`php bin/forecast-test.php`), là où ces
mocks-ci visent le réalisme.
