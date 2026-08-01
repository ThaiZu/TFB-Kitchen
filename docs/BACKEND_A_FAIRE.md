# Ce qu'il reste à faire côté back-office

Document de passation. Il liste **tout** ce que la PWA Cuisine appelle
aujourd'hui, dit ce qui existe déjà et ce qui manque, donne les migrations et
la forme exacte de chaque échange.

La PWA est **entièrement écrite et testée** contre un serveur bouchon
(`tools/mock-api/`). Chaque endpoint ci-dessous y est déjà implémenté : en cas
de doute sur une réponse, `php -S 127.0.0.1:8081 tools/mock-api/index.php` la
sert, et `docs/mocks/*.json` la montre figée.

---

## 0. Trois conventions à lire avant d'écrire une ligne

**Le corps est NU.** `ApiClient::get()` construit lui-même l'enveloppe
`{"success": <2xx>, "data": <corps décodé>}`. Renvoyer `{"success":…,"data":…}`
côté serveur ferait donc arriver la charge utile sous `data.data`, et **tous**
les dépôts liraient à côté. On renvoie l'objet ou le tableau, point.

```
✅  {"items": [...]}          ✅  [ {...}, {...} ]
❌  {"success": true, "data": {"items": [...]}}
```

**`POST` et `PATCH` ne remontent que trois champs.** `ApiClient::post()`
écarte le corps et ne garde que `message`, `inserted_id` (plus `success` et
`description` qu'il fabrique). Tout ce dont l'écran a besoin après une écriture
doit donc tenir dans **`inserted_id`**. C'est notamment vital pour
`POST /shops/{id}/baking` : sans lui, la PWA ne sait pas quelle fournée mettre
en avant à l'arrivée sur le planning.

**L'échec se dit par le code HTTP**, et le détail va dans `description` :

```json
HTTP 409
{"description": "shelf_exceeds_produced"}
```

**`404` ≠ liste vide, et c'est structurant.** Toutes les lectures renvoient
`null` quand l'API ne répond pas, jamais un tableau vide : l'écran distingue
« rien à produire » de « pas encore servi » et **écrit** la différence. Un
tableau vide servi à la place d'un endpoint muet ferait ouvrir le magasin sans
rien en vitrine. Tant qu'un endpoint n'existe pas, un `404` est donc la bonne
réponse — la PWA l'affiche proprement et continue de fonctionner.

---

## 1. Où on en est

| # | Endpoint | Méthode | Statut | Priorité |
|---|---|---|---|---|
| 1 | `/shops/{id}/production/config` | GET | à faire | **1** |
| 2 | `/shops/{id}/production/products` | GET | **à étendre** (4 champs) | **1** |
| 3 | `/shops/{id}/mep` | GET | à faire | **1** |
| 4 | `/shops/{id}/mep` | POST | à faire | 2 |
| 5 | `/shops/{id}/mep/validate` | POST | à faire | **1** |
| 6 | `/shops/{id}/stock` | GET | à faire | **1** |
| 7 | `/shops/{id}/sales/profile` | GET | à faire | **1** |
| 8 | `/shops/{id}/production/batches` | POST | à faire | **1** |
| 9 | `/shops/{id}/orders` | GET | à faire | 2 |
| 10 | `/shops/{id}/production/pending-count` | GET | à faire | 3 |
| 11 | `/shops/{id}/ovens` | GET | à faire | 2 |
| 12 | `/shops/{id}/baking` | GET | à faire | 2 |
| 13 | `/shops/{id}/baking` | POST | à faire | 2 |
| 14 | `/baking/{batchId}` | PATCH | à faire | 2 |
| 15 | `/shops/{id}/baking/pending-count` | GET | à faire | 3 |
| 16 | `/shops/{id}/employees` | GET | **existe déjà** | — |

**Priorité 1** = l'écran « Ce qui manque » fonctionne de bout en bout (voir,
décider, mettre en vente). **Priorité 2** = l'atelier et les commandes.
**Priorité 3** = les pastilles de compteur, purement cosmétiques.

---

## 2. Les migrations

Les tables du module portent le préfixe **`pro_`**, comme `mac_` côté panel
consultant. Là où l'ancien nom répétait déjà le module — « production_batch » —
le mot redondant saute : `pro_batch`. Seule `product` échappe au préfixe : ce
n'est pas une table du module, c'est le catalogue partagé, et on ne fait que
lui ajouter des colonnes.

### 2.1 Table produit — quatre colonnes

```sql
ALTER TABLE product
    ADD COLUMN sector      VARCHAR(32)  NULL     COMMENT 'atelier : bakery, catering, …',
    ADD COLUMN sector_name VARCHAR(64)  NULL     COMMENT 'libellé affiché du secteur',
    ADD COLUMN is_pdb      TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'se prépare la veille',
    ADD COLUMN is_pdm      TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'tenu à un minimum en magasin';
```

- **`sector`** — la clé est **libre**, la PWA n'a pas de liste fermée : elle
  affiche ce que le catalogue porte. Ajouter « Boucherie » plus tard ne demande
  aucune modification du front. `NULL` partout ⇒ le magasin n'a qu'un atelier
  et le sélecteur ne s'affiche pas du tout.
- **`is_pdb`** — « prep day before ». Seuls ces produits sont proposés à
  l'encodage de la MEP du lendemain. *Le champ `is_prepared_before_sales` de la
  fiche technique dit déjà la même chose : si vous préférez le réutiliser, le
  front l'accepte en repli et il n'y a rien à migrer.*
- **`is_pdm`** — le produit se pilote à un **plancher de vitrine** plutôt qu'à
  la prévision de ventes.

### 2.2 Nouvelle table — les minimums de vitrine

```sql
CREATE TABLE pro_shop_minimum (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_shop     INT UNSIGNED NOT NULL,
    id_product  INT UNSIGNED NOT NULL,
    period      VARCHAR(16)  NOT NULL COMMENT 'morning | noon | afternoon',
    quantity    DECIMAL(10,2) NOT NULL,
    UNIQUE KEY uq_min (id_shop, id_product, period),
    KEY ix_shop (id_shop)
);
```

**Une ligne absente n'est pas un zéro.** Zéro veut dire « on n'en tient pas sur
cette période » — un sandwich le matin — et se lit comme une décision. Absente
veut dire qu'on ne sait pas, et l'écran l'écrit au lieu de proposer une relance
inventée. **Ne créez donc pas de lignes à zéro par défaut.**

Le minimum dépend du magasin : deux boutiques du même réseau n'ont pas la même
vitrine. D'où `id_shop` dans la clé.

### 2.3 Nouvelle table — le carnet de commandes

```sql
CREATE TABLE pro_order_line (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_shop     INT UNSIGNED NOT NULL,
    id_order    INT UNSIGNED NULL     COMMENT 'commande client d''origine',
    id_product  INT UNSIGNED NOT NULL,
    quantity    DECIMAL(10,2) NOT NULL,
    channel     VARCHAR(16)  NOT NULL COMMENT 'shop | click | delivery',
    due_date    DATE         NOT NULL,
    due_time    TIME         NULL     COMMENT 'NULL = « pour midi », sans plus',
    period      VARCHAR(16)  NULL     COMMENT 'repli quand due_time est NULL',
    reference   VARCHAR(64)  NULL     COMMENT 'le numéro que le client a sous les yeux',
    KEY ix_shop_date (id_shop, due_date)
);
```

**Une ligne par produit et par commande**, pas une commande imbriquée : la
production raisonne par produit et par four, et un client qui commande trois
choses fait trois fournées différentes.

Si vous avez déjà `client_orders` (la PWA l'utilise ailleurs), cette table peut
être une **vue** dessus plutôt qu'un doublon — c'est même préférable, à
condition qu'elle expose une ligne par produit.

### 2.4 Table des lots produits

Elle existe peut-être déjà sous un autre nom ; il faut au minimum :

```sql
CREATE TABLE pro_batch (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_shop     INT UNSIGNED NOT NULL,
    id_product  INT UNSIGNED NOT NULL,
    quantity    DECIMAL(10,2) NOT NULL,
    source      VARCHAR(16)  NOT NULL COMMENT 'SHELF | REBAKE',
    id_mep_line INT UNSIGNED NULL,
    id_employee INT UNSIGNED NULL,
    created_at  DATETIME     NOT NULL,
    KEY ix_shop_day (id_shop, created_at)
);
```

**`source` n'est pas décoratif** : voir §4. Les deux valeurs créditent le stock
vendable, mais elles ne racontent pas la même chose — `SHELF` est un plateau
porté en rayon, `REBAKE` une relance décidée depuis l'écran Stock. Les
distinguer permettra plus tard de savoir d'où vient ce qui s'est vendu.

### 2.5 Table du plan de cuisson

Distincte de la précédente, et c'est volontaire : `pro_batch` enregistre
ce qui **est entré en magasin**, `pro_baking_batch` planifie ce qui **passe au
four**. Une fournée peut être annulée sans jamais rien créditer.

```sql
CREATE TABLE pro_baking_batch (
    id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_shop                  INT UNSIGNED NOT NULL,
    id_product               INT UNSIGNED NOT NULL,
    bake_date                DATE          NOT NULL,
    quantity                 DECIMAL(10,2) NOT NULL,
    id_oven                  INT UNSIGNED  NULL,
    temperature              SMALLINT      NULL,
    prep_start               TIME          NULL,
    prep_minutes             SMALLINT      NOT NULL DEFAULT 0,
    cook_start               TIME          NULL,
    cook_minutes             SMALLINT      NOT NULL DEFAULT 0,
    finish_type              VARCHAR(8)    NOT NULL DEFAULT 'LOT' COMMENT 'LOT | PIECE',
    finish_label             VARCHAR(64)   NULL COMMENT 'Ressuage, Glaçage, Nappage…',
    finish_minutes           SMALLINT      NULL COMMENT 'si finish_type = LOT',
    finish_per_piece_minutes DECIMAL(5,2)  NULL COMMENT 'si finish_type = PIECE',
    shelf_delay_minutes      SMALLINT      NOT NULL DEFAULT 0,
    status                   VARCHAR(16)   NOT NULL DEFAULT 'PLANNED',
    source                   VARCHAR(16)   NULL COMMENT 'SHORTFALL | MEP | MANUAL',
    id_employee              INT UNSIGNED  NULL,
    prep_started_at          DATETIME      NULL,
    cook_started_at          DATETIME      NULL,
    finish_started_at        DATETIME      NULL,
    KEY ix_shop_date (id_shop, bake_date)
);
```

`status` suit la chaîne `PLANNED → PREPARING → READY_TO_BAKE → BAKING →
FINISHING → DONE`, et **seul le pas suivant est accepté** (§3.13).

### 2.6 Table de MEP

```sql
CREATE TABLE pro_mep_line (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_shop            INT UNSIGNED NOT NULL,
    mep_date           DATE         NOT NULL,
    id_product         INT UNSIGNED NOT NULL,
    period             VARCHAR(16)  NULL,
    quantity_planned   DECIMAL(10,2) NOT NULL,
    quantity_validated DECIMAL(10,2) NULL COMMENT 'NULL tant que non validée',
    quantity_shelved   DECIMAL(10,2) NOT NULL DEFAULT 0,
    status             VARCHAR(16)  NOT NULL DEFAULT 'PREPARED' COMMENT 'PREPARED | VALIDATED | SKIPPED',
    prepared_at        DATETIME     NULL,
    UNIQUE KEY uq_line (id_shop, mep_date, id_product),
    KEY ix_shop_date (id_shop, mep_date)
);
```

**`quantity_shelved` est la colonne qui fait tourner tout l'écran** : c'est
elle qui dit ce qui reste à porter en rayon. Voir §4.

---

## 3. Les endpoints, un par un

Dans tout ce qui suit, `{id}` est l'`id_shop` et les clés de période sont
celles servies par `production/config` (`morning`, `noon`, `afternoon` par
défaut).

### 3.1 `GET /shops/{id}/production/config`

Bornes des périodes et paramètres de prévision. **Écrase** les constantes de
`config/app.php` dès qu'il répond : deux magasins n'ouvrent pas aux mêmes
heures, une constante partagée serait fausse pour l'un des deux.

```json
{
  "periods": [
    {"key": "morning",   "label": "Matin",      "start": "05:00", "end": "11:00"},
    {"key": "noon",      "label": "Midi",       "start": "11:00", "end": "14:00"},
    {"key": "afternoon", "label": "Après-midi", "start": "14:00", "end": "19:00"}
  ],
  "forecast_hours": 2,
  "history_weeks": 6,
  "safety_margin": 0
}
```

### 3.2 `GET /shops/{id}/production/products`

Le catalogue. **C'est l'endpoint à étendre en priorité** — les quatre nouveaux
champs sont en gras.

```json
[
  {
    "id_product": 6700106,
    "name": "Croissant pur beurre",
    "id_category": 12,
    "category_name": "Viennoiserie",
    "periods": ["morning", "noon"],
    "batch_size": 24,
    "unit_name": "pc",
    "production_lead_minutes": 40,
    "is_active": true,
    "is_pdb": true,
    "is_pdm": true,
    "pdm_minimums": {"morning": 48, "noon": 24, "afternoon": 12},
    "sector": "bakery",
    "sector_name": "Boulangerie",
    "main_photo_path": "r2://products/6700106/main.jpg"
  }
]
```

| champ | rôle | obligatoire |
|---|---|---|
| `id_product` | clé de jointure avec stock, MEP, ventes, commandes | oui |
| `periods` | un produit peut appartenir à plusieurs périodes | oui |
| `batch_size` | taille de fournée ; **tout est arrondi à son multiple** | oui |
| `production_lead_minutes` | délai entre le lancement et la disponibilité | recommandé |
| `is_active` | un produit inactif ne se produit ni ne se propose | oui |
| **`is_pdb`** | se prépare la veille | oui |
| **`is_pdm`** | tenu à un plancher de vitrine | oui |
| **`pdm_minimums`** | ce plancher, période par période | si `is_pdm` |
| **`sector`** / **`sector_name`** | l'atelier | recommandé |

`pdm_minimums` se construit depuis `pro_shop_minimum` (§2.2). Si le
plancher est le même toute la journée, un scalaire `pdm_min: 48` suffit — le
front l'applique à toutes les périodes.

**`batch_size` est structurant.** Sans lui, la seule proposition honnête serait
« il manque 17 croissants » devant un four qui sort des plaques de 24. À
défaut, la PWA traite le produit comme un lot de 1 et le signale à l'écran.

### 3.3 `GET /shops/{id}/mep?date=YYYY-MM-DD`

```json
{
  "date": "2026-08-01",
  "status": "PREPARED",
  "prepared_at": "2026-07-31 17:40:00",
  "lines": [
    {
      "id": 4401,
      "id_product": 6700106,
      "name": "Croissant pur beurre",
      "category_name": "Viennoiserie",
      "period": "morning",
      "quantity_planned": 120,
      "quantity_validated": null,
      "quantity_shelved": 0,
      "unit_name": "pc",
      "status": "PREPARED"
    }
  ]
}
```

Appelé pour **aujourd'hui** (ce qu'on valide) et pour **demain** (ce qu'on
encode). Une date sans MEP renvoie `lines: []` avec un `200` — ce n'est pas une
erreur, c'est une journée où personne n'a encore rien noté.

### 3.4 `POST /shops/{id}/mep` — encoder la MEP du lendemain

L'appel **remplace** ce qui existait pour cette date.

```json
{"date": "2026-08-02", "lines": [{"id_product": 6700106, "quantity": 144}]}
```

Les lignes portent `id_product` (pas `id`) : elles n'existent pas encore. La
PWA n'envoie jamais de ligne à zéro. **La `period` de chaque ligne est déduite
côté serveur depuis la fiche produit** — l'écran de saisie ne la connaît pas.

Réponse : `{"message": "ok"}` suffit.

### 3.5 `POST /shops/{id}/mep/validate` — valider la MEP du jour

```json
{
  "date": "2026-08-01",
  "lines": [
    {"id": 4401, "quantity": 118},
    {"id": 4404, "quantity": 0, "skipped": true}
  ]
}
```

Effet attendu, ligne par ligne :

- `skipped: true` → `status = SKIPPED`, `quantity_validated = 0`
- sinon → `status = VALIDATED`, `quantity_validated = <quantity>`
- l'en-tête passe à `VALIDATED` quand plus aucune ligne n'est `PREPARED`

**Le stock vendable N'EST PAS crédité ici.** Voir §4 — c'est la seule règle
qu'il ne faut pas se tromper.

### 3.6 `GET /shops/{id}/stock`

Le stock **vendable** du magasin, tel que consolidé par le back-office. La PWA
n'écrit jamais dans la caisse.

```json
{
  "updated_at": "2026-08-01 10:37:00",
  "items": [
    {"id_product": 6700106, "name": "Croissant pur beurre",
     "category_name": "Viennoiserie", "quantity": 10, "unit_name": "pc"}
  ]
}
```

### 3.7 `GET /shops/{id}/sales/profile?date=…&weeks=6&weekday_only=1&granularity=30`

**Le seul endpoint de prévision — et il ne prévoit rien** : il renvoie ce qui
s'est vendu, agrégé par créneau. La projection, l'arrondi au lot et la décision
sont faits par la PWA (`ForecastService`, service pur, testable :
`php bin/forecast-test.php`).

```json
{
  "granularity_minutes": 30,
  "weeks": 6,
  "weekday_only": true,
  "samples": 6,
  "slots": ["06:00", "06:30", "07:00", "…"],
  "products": [
    {"id_product": 6700106, "expected": [0.7, 1.4, 3.2, "…"]}
  ]
}
```

- `expected` a **exactement autant d'éléments que `slots`**.
- `samples` = le nombre réel de journées agrégées. S'il est inférieur à
  `weeks`, l'écran affiche « moyenne sur 2 journées seulement » : une moyenne
  sur deux mardis n'est pas une tendance, et le dire vaut mieux que la
  présenter comme telle.
- Un produit **absent** du profil n'est pas un produit qui ne se vend pas :
  c'en est un dont on ne sait rien, et la PWA ne propose alors rien pour lui.
  Ne le remplissez pas de zéros.

### 3.8 `POST /shops/{id}/production/batches` — **l'endpoint qui met en vente**

```json
{"id_product": 6700106, "quantity": 46, "source": "SHELF", "id_mep_line": 4401, "id_employee": 42}
```

| `source` | déclenché par | effet |
|---|---|---|
| `SHELF` | le bouton « En rayon » / « Mettre en magasin » | **stock vendable +quantity**, et `pro_mep_line.quantity_shelved += quantity` si `id_mep_line` est fourni |
| `REBAKE` | une proposition de recuisson validée | stock vendable +quantity |

`id_mep_line` et `id_employee` sont optionnels.

**Refus attendu, pas d'écrêtage silencieux :**

```
HTTP 409  {"description": "shelf_exceeds_produced"}
```

quand `quantity > quantity_validated − quantity_shelved`. Deux tablettes
peuvent porter le même plateau à quelques secondes d'écart ; rogner en silence
ferait croire aux deux qu'elles ont réussi.

### 3.9 `GET /shops/{id}/orders?date=YYYY-MM-DD` — le carnet

```json
{
  "date": "2026-08-01",
  "items": [
    {
      "id_order": 9101,
      "id_product": 6700300,
      "name": "Sandwich jambon-beurre",
      "category_name": "Sandwichs",
      "quantity": 15,
      "channel": "click",
      "due_time": "11:30",
      "period": null,
      "reference": "WEB-8817",
      "unit_name": "pc"
    }
  ]
}
```

`channel` ∈ `shop` | `click` | `delivery`. Le front normalise largement (tout
ce qui contient `deliver`/`livr` devient `delivery`, `click`/`collect`/`web`
devient `click`, le reste tombe au magasin) : une commande mal étiquetée reste
une commande à honorer, la faire disparaître du calcul serait pire.

`due_time` accepte `HH:MM` comme un timestamp complet. Sans heure, `period`
prend le relais (« pour midi »).

**Ne filtrez pas les commandes échues** : une commande de 11 h lue à 11 h 20
n'a pas disparu, et c'est souvent celle qu'on a ratée qu'il faut produire en
premier. La PWA les remonte en tête, en rouge.

### 3.10 `GET /shops/{id}/ovens`

```json
[{"id": 1, "name": "Four 1 — Rotatif", "levels": 8, "temp_min": 160, "temp_max": 250}]
```

### 3.11 `GET /shops/{id}/baking?date=YYYY-MM-DD` — le plan de cuisson

```json
{
  "date": "2026-08-01",
  "server_time": "10:37",
  "batches": [
    {
      "id": 5501,
      "id_product": 6700106,
      "name": "Croissant pur beurre",
      "category_name": "Viennoiserie",
      "quantity": 48,
      "unit_name": "pc",
      "id_oven": 1,
      "oven_name": "Four 1 — Rotatif",
      "temperature": 175,
      "prep_start": "10:20",
      "prep_minutes": 20,
      "cook_start": "10:45",
      "cook_minutes": 18,
      "finish_type": "LOT",
      "finish_label": "Refroidissement",
      "finish_minutes": 15,
      "shelf_delay_minutes": 5,
      "status": "BAKING",
      "prep_started_at": null,
      "cook_started_at": "2026-08-01 10:45:00",
      "finish_started_at": null
    }
  ]
}
```

- **`server_time` fait foi.** Une tablette d'atelier dérive ; l'écran ne doit
  pas décréter un retard pour autant.
- `status` ∈ `PLANNED` → `PREPARING` → `READY_TO_BAKE` → `BAKING` →
  `FINISHING` → `DONE`.
- `finish_type` : `LOT` (durée fixe, champ `finish_minutes`) ou `PIECE`
  (durée × quantité, champ `finish_per_piece_minutes`).

### 3.12 `POST /shops/{id}/baking` — programmer une fournée

Déclenché par « Lancer la production » depuis « Ce qui manque » ou depuis les
minimums de vitrine.

```json
{"id_product": 6700106, "quantity": 72, "source": "SHORTFALL"}
```

**C'est le serveur qui place la fournée** : four libre, horaires, températures.
La PWA ne les devine pas.

**La réponse DOIT porter `inserted_id`** = l'id de la fournée créée. C'est le
seul champ qui survit à `ApiClient::post()`, et sans lui l'écran ne sait pas
quelle fournée mettre en avant à l'arrivée sur le planning.

```json
{"inserted_id": 7042, "message": "batch_planned"}
```

### 3.13 `PATCH /baking/{batchId}` — faire avancer une fournée

Noter qu'il n'y a **pas** de `/shops/{id}` ici : l'id de fournée est global.

```json
{"status": "BAKING", "allotted_minutes": 22, "id_employee": 42}
```

- `status` : l'étape **suivante**. Le saut d'étape se refuse — enfourner sans
  avoir préparé n'existe pas en atelier :
  `HTTP 409 {"description": "invalid_transition"}`.
- `allotted_minutes` : le temps corrigé à l'écran. À persister sur l'étape
  concernée — `prep_minutes`, `cook_minutes` ou `finish_minutes` selon le
  statut — pour que la frise et les échéances suivantes suivent.
- `id_employee` : qui l'a fait. Optionnel.
- Horodater `prep_started_at` / `cook_started_at` / `finish_started_at` au
  passage à `PREPARING` / `BAKING` / `FINISHING`.

### 3.14 Les deux compteurs (cosmétiques)

```
GET /shops/{id}/production/pending-count?date=…
    → {"mep_pending": 3, "rebakes_suggested": 2}

GET /shops/{id}/baking/pending-count
    → {"preparing": 2, "baking": 1, "finishing": 3}
```

Ce sont les pastilles du menu. Leur absence ne casse rien.

---

## 4. La règle à ne pas se tromper : produit ≠ vendable

C'est la seule subtilité du modèle, et elle a une conséquence en base.

```
        MEP validée                     Mise en rayon
   ────────────────────           ──────────────────────
   « c'est sorti du four »        « la caisse peut le vendre »
   quantity_validated += q        stock vendable += q
   stock vendable INCHANGÉ        quantity_shelved += q
```

Entre les deux il y a un plateau à porter, et parfois une demi-fournée qui
reste au chaud en réserve. Créditer le stock à la validation de MEP mettrait en
vente des produits encore en cuisine — la caisse les vendrait, le magasin ne
les aurait pas.

D'où :

```
reste à porter = quantity_validated − quantity_shelved
```

C'est ce calcul qui alimente la section « À mettre en rayon » de l'écran. Sans
`quantity_shelved`, l'écran reproposerait indéfiniment le même plateau.

---

## 5. Un manque connu, à arbitrer

Aujourd'hui, « reste à porter » ne se calcule **que** sur les lignes de MEP.
Une fournée lancée depuis « Ce qui manque » (donc sans ligne de MEP) et
terminée n'a nulle part où être reprise : la PWA la garde en mémoire locale, sur
la tablette qui a le plateau dans les mains.

Ça marche, mais c'est local : si le boulanger met en réserve sur la tablette du
fournil, celle du magasin ne le voit pas.

**Pour rendre la réserve partagée**, il suffit d'un champ sur la fournée :

```sql
ALTER TABLE pro_baking_batch
    ADD COLUMN quantity_shelved DECIMAL(10,2) NOT NULL DEFAULT 0;
```

exposé dans `GET /shops/{id}/baking` (§3.11) et incrémenté par
`POST /shops/{id}/production/batches` quand la charge porte `id_batch`
(champ à ajouter au corps, à côté de `id_mep_line`). La PWA
remonterait alors les fournées `DONE` non portées depuis l'API au lieu du
navigateur. **Ce n'est pas bloquant** — à faire quand le reste tourne.

---

## 6. Ordre de livraison conseillé

1. **Le socle** — 3.1 config, 3.2 catalogue étendu, 3.6 stock, 3.7 profil de
   ventes. À ce stade l'écran « Ce qui manque » affiche déjà les vrais chiffres.
2. **Le cycle du jour** — 3.3 MEP, 3.5 validation, 3.8 batches. Le magasin peut
   ouvrir, valider et mettre en vente.
3. **L'atelier** — 3.10 fours, 3.11 plan, 3.12 création, 3.13 avancement.
4. **Les commandes** — 3.9. L'écran fonctionne sans, il l'écrit quand elles
   manquent.
5. **Le reste** — 3.4 encodage MEP, 3.14 compteurs, §5 réserve partagée.

Chaque étape est livrable seule : la PWA affiche ce qu'elle a et **écrit** ce
qui lui manque, elle ne tombe jamais.

---

## 7. Comment vérifier sans la PWA

```bash
php -S 127.0.0.1:8081 tools/mock-api/index.php
curl -s 127.0.0.1:8081/shops/1/production/products | jq
curl -s 127.0.0.1:8081/shops/1/orders | jq
```

Le bouchon garde son état sur disque : valider une MEP puis porter en rayon
augmente réellement le stock qu'il sert ensuite. `POST /__reset` repart de
zéro.

Les réponses figées sont dans `docs/mocks/` ; les contrats détaillés, avec les
cas d'erreur, dans `docs/ENDPOINTS_PRODUCTION.md` et
`docs/ENDPOINTS_CUISSON.md`.
