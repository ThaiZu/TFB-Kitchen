# Module Production — contrat d'API

État au 31/07/2026. **Aucun de ces endpoints n'existe encore.** Le front est
écrit contre ce contrat : le jour où l'API répond, l'écran fonctionne sans
modification. Tant qu'elle ne répond pas, chaque vue affiche un état explicite
plutôt qu'une liste vide — confondre « rien à produire » et « pas encore servi »
coûterait une matinée.

Rappel d'architecture : la PWA n'a **aucune base de données**. Tout ce qui est
décrit ici doit être détenu et persisté côté back-office.

---

## Forme des réponses

**Le corps est renvoyé nu, sans enveloppe.** C'est la convention de toute
l'application : `ApiClient::get()` construit lui-même
`['success' => <code HTTP 2xx>, 'data' => <corps décodé>]`. Un serveur qui
répondrait `{"success": true, "data": {…}}` ferait donc arriver la charge utile
sous `$response['data']['data']`, et tous les dépôts liraient à côté.

L'échec se dit par le **code HTTP**. Le corps ne porte que le détail, sous
`description` — la seule clé que `post()` et `patch()` savent remonter.

À noter pour l'écriture du serveur : `ApiClient::post()` et `::patch()`
**ne remontent pas le corps de la réponse**, seulement `message`,
`inserted_id`, `description` et le code. Les réponses décrites plus bas pour
les écritures restent utiles pour l'avenir, mais le front n'en dépend pas : il
relit systématiquement après une écriture.

---

## Décisions arrêtées

| Point | Décision |
|---|---|
| Calcul de la prévision | L'API sert un **profil de ventes agrégé** ; la PWA en tire la projection et l'arrondi au batch |
| Moyenne historique | **Même jour de semaine**, moyenne simple sur N semaines (6 par défaut) |
| Détenteur du stock live | **Le back-office**. La PWA n'écrit jamais dans la caisse |
| Validation de la MEP | **Ligne par ligne, quantité éditable** |

La PWA ne parle donc **jamais** au POS. Le back-office consolide production et
ventes ; la caisse s'aligne sur lui. `PosSalesProviderInterface` existe côté
front pour isoler l'origine des ventes, mais son implémentation lit le
back-office, pas la caisse.

---

## 0. Configuration du magasin

```
GET /shops/{shopId}/production/config
```

```json
{
  "periods": [
    {
      "key": "morning",
      "label": "Matin",
      "start": "05:00",
      "end": "11:00"
    },
    {
      "key": "noon",
      "label": "Midi",
      "start": "11:00",
      "end": "14:00"
    },
    {
      "key": "afternoon",
      "label": "Après-midi",
      "start": "14:00",
      "end": "19:00"
    }
  ],
  "forecast_hours": 2,
  "history_weeks": 6,
  "safety_margin": 0
}
```

Facultatif. Sans lui, la PWA retombe sur les constantes de `config/app.php`
(`PRODUCTION_PERIODS`, `PRODUCTION_FORECAST_HOURS`, `PRODUCTION_HISTORY_WEEKS`,
`PRODUCTION_SAFETY_MARGIN`). Les bornes horaires codées en dur sont un pari sur
un magasin type : dès que deux magasins ouvrent à des heures différentes, cet
endpoint devient nécessaire.

---

## 1. Les produits de production

```
GET /shops/{shopId}/production/products
```

```json
[
  {
    "id_product": 6700106,
    "name": "Croissant pur beurre",
    "id_category": 12,
    "category_name": "Viennoiserie",
    "periods": [
      "morning",
      "noon"
    ],
    "batch_size": 24,
    "unit_name": "pc",
    "production_lead_minutes": 40,
    "is_active": true,
    "main_photo_path": "r2://products/6700106/main.jpg"
  }
]
```

| champ | rôle | obligatoire |
|---|---|---|
| `id_product` | clé de jointure avec le stock, la MEP et les ventes | oui |
| `periods` | **un produit peut appartenir à plusieurs périodes** | oui |
| `batch_size` | taille de fournée ; la proposition est arrondie à son multiple supérieur | oui |
| `production_lead_minutes` | temps entre la validation et la disponibilité à la vente | recommandé |
| `is_active` | un produit inactif ne se produit pas et ne se propose pas | oui |
| `id_category` / `category_name` | regroupement dans la vue période | recommandé |

**`batch_size` est structurant.** Sans lui, la seule proposition honnête serait
« il manque 17 croissants », alors que le four sort des plaques de 24. À défaut,
la PWA traite le produit comme un batch de 1 et le signale.

**`production_lead_minutes` ferme un trou réel.** Une recuisson validée à 15 h 00
avec 40 minutes de cuisson ne couvre pas les ventes de 15 h 00 à 15 h 40. La
fenêtre de projection d'un produit est donc `forecast_hours + son lead`.

> **À confirmer** : ce lead est-il une donnée produit stable, ou dépend-il du
> four et de la charge du moment ? Le front le lit par produit ; s'il doit
> devenir un réglage magasin, c'est un champ de `production/config`.

---

## 2. La MEP de la veille

```
GET /shops/{shopId}/mep?date=YYYY-MM-DD
```

`date` = le jour **de consommation**. Le serveur renvoie la MEP préparée la
veille pour ce jour-là ; la PWA n'a pas à raisonner en J‑1.

L'écran l'appelle sur deux dates seulement : **aujourd'hui**, pour valider ce
qui a été préparé hier, et **demain**, pour reprendre le brouillon en cours
d'encodage. Il n'y a pas de sélecteur de date dans l'interface — une cuisine ne
produit pas pour hier, et un écran qui peut afficher une autre journée finit
par en afficher une par erreur au milieu du service.

```json
{
  "date": "2026-07-31",
  "prepared_at": "2026-07-30 18:20:00",
  "status": "PREPARED",
  "lines": [
    {
      "id": 4401,
      "id_product": 6700106,
      "name": "Croissant pur beurre",
      "category_name": "Viennoiserie",
      "period": "morning",
      "quantity_planned": 120,
      "quantity_validated": null,
      "unit_name": "pc",
      "status": "PREPARED"
    }
  ]
}
```

`status` de la MEP : `PREPARED` (à valider) ou `VALIDATED`. Idem par ligne, plus
`SKIPPED` pour une ligne écartée.

### Encoder la MEP du lendemain

```
POST /shops/{shopId}/mep
```

```json
{
  "date": "2026-08-02",
  "lines": [
    { "id_product": 6700106, "quantity": 144 },
    { "id_product": 6700120, "quantity": 72 }
  ]
}
```

C'est le geste de l'après-midi : on met en place aujourd'hui ce qui sera
produit demain. Les lignes portent `id_product` — elles n'existent pas encore
côté serveur — et **la période est déduite de la fiche produit** : demander à
quelqu'un de choisir « matin » pour chaque croissant serait une saisie de plus
sans information de plus.

L'appel **remplace** la MEP de cette date. Les lignes à zéro ne sont pas
envoyées ; celles qui disparaissent du corps sont supprimées. Rouvrir l'écran
doit reprendre le brouillon là où il en était, d'où le `GET` sur la même date.

Réponse : la MEP enregistrée, forme du `GET` ci-dessus.

### Valider la MEP du jour

```
POST /shops/{shopId}/mep/validate
```

```json
{
  "date": "2026-07-31",
  "lines": [
    { "id": 4401, "quantity": 118 },
    { "id": 4402, "quantity": 0, "skipped": true }
  ]
}
```

Une quantité par ligne, éditable : on avait prévu 120 croissants, il en sort
118. Le stock de départ doit être le réel, pas le prévu — sinon l'écart se
propage dans toutes les prévisions de la journée.

Réponse : la MEP mise à jour, dans la forme ci-dessus, **et le stock qui en
résulte**. C'est cette validation qui rend les produits vendables en caisse.

> **À confirmer** : que devient une ligne préparée mais non validée en fin de
> journée — perte tracée, report sur le lendemain, ou simplement ignorée ? Le
> front envoie `skipped: true` et n'en fait rien de plus.

> **À confirmer** : la validation exige-t-elle un PIN employé, comme les
> checklists ? Le front est écrit sans. L'ajouter est simple ; le retirer après
> l'avoir imposé aux équipes l'est moins.

---

## 3. Le stock live

```
GET /shops/{shopId}/stock
```

```json
{
  "updated_at": "2026-07-31 10:42:11",
  "items": [
    {
      "id_product": 6700106,
      "name": "Croissant pur beurre",
      "category_name": "Viennoiserie",
      "quantity": 34,
      "unit_name": "pc"
    }
  ]
}
```

Le back-office est la source de vérité : `quantity` = productions validées −
ventes, consolidé côté serveur. La PWA ne recalcule rien et ne trie que pour
l'affichage (croissant : ce qui est en tension d'abord).

Sondé toutes les dix secondes par l'écran, comme le compteur de commandes.

---

## 4. Le profil de ventes (base de la prévision)

```
GET /shops/{shopId}/sales/profile?date=YYYY-MM-DD&weeks=6&weekday_only=1&granularity=30
```

C'est **le seul endpoint de prévision**, et il ne prévoit rien : il renvoie ce
qui s'est vendu, agrégé. La projection est faite par la PWA.

```json
{
  "granularity_minutes": 30,
  "weeks": 6,
  "weekday_only": true,
  "samples": 6,
  "slots": [
    "06:00",
    "06:30",
    "07:00",
    "…"
  ],
  "products": [
    {
      "id_product": 6700106,
      "expected": [
        0.4,
        1.2,
        3.8,
        5.1,
        "…"
      ]
    }
  ]
}
```

`expected[i]` = **moyenne des quantités vendues** sur le créneau `slots[i]`, aux
`weeks` mêmes jours de semaine précédents (6 derniers mardis pour un mardi).
Décimal assumé : 3,8 croissants par demi-heure est une information, 4 est un
arrondi prématuré.

`samples` = nombre de journées réellement agrégées. S'il est inférieur à
`weeks` (magasin ouvert récemment, jours de fermeture), l'écran le dit plutôt
que de présenter une moyenne de deux mardis comme une tendance.

**Pourquoi par créneau et non par fenêtre :** la fenêtre de projection diffère
d'un produit à l'autre — elle inclut son temps de cuisson. Un endpoint qui
renverrait « les ventes des 2 prochaines heures » obligerait à un appel par
durée distincte. Des créneaux fixes se somment côté front, une fois.

**Exclure les jours de fermeture** de la moyenne, ou les compter comme zéro ?
Les compter comme zéro divise la prévision par deux après un jour férié. À
exclure, donc — et c'est au serveur de le savoir.

---

## 5. Valider une production (MEP ou recuisson)

```
POST /shops/{shopId}/production/batches
```

```json
{
  "id_product": 6700106,
  "quantity": 48,
  "source": "REBAKE",
  "id_employee": 12
}
```

`source` : `MEP` ou `REBAKE`. Réponse : le lot créé **et le stock du produit
après application**, pour que l'écran réaffiche un chiffre serveur plutôt que
sa propre addition.

Le serveur arbitre : deux tablettes peuvent valider la même recuisson à
quelques secondes d'intervalle.

---

## 6. Compteur, pour la pastille de l'onglet

```
GET /shops/{shopId}/production/pending-count?date=YYYY-MM-DD
```

```json
{
  "mep_pending": 12,
  "rebakes_suggested": 3
}
```

Facultatif — sans lui, l'onglet Production n'affiche simplement pas de pastille.

---

## Ce que la PWA calcule elle-même

Rien qui touche à la vérité des stocks. Uniquement l'arithmétique de
proposition, dans `ForecastService` — une fonction pure, testable hors HTTP
(`php bin/forecast-test.php`) :

```
fenêtre(produit) = [maintenant ; maintenant + forecast_hours + lead(produit)]
ventes_prévues   = Σ expected[i] pour les créneaux couverts par la fenêtre
projection       = stock − ventes_prévues − marge_sécurité
si projection < 0 :
    besoin     = −projection
    proposition = ceil(besoin / batch_size) × batch_size
```

Après chaque recuisson validée, le stock est relu depuis le serveur et la
projection refaite : une proposition acceptée ne doit pas être reproposée, et
une vente survenue entre-temps doit être prise en compte.

---

## Comportement du front en attendant

| Endpoint muet | Ce que montre l'écran |
|---|---|
| `production/config` | bornes horaires par défaut, sans avertissement (ce sont des réglages, pas des données) |
| `production/products` | « le catalogue de production n'est pas encore servi par l'API » |
| `mep` | « la MEP n'est pas encore servie par l'API » — jamais « aucune MEP » |
| `stock` | « le stock live n'est pas encore servi par l'API » |
| `sales/profile` | le stock s'affiche, sans proposition de recuisson, et l'écran l'écrit |

L'entrée du menu reste marquée « en construction » jusqu'à ce que l'API réponde.
