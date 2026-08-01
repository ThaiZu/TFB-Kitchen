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

Les trois durées — `prep_minutes`, `cook_minutes`, `finish_minutes` — ne se
saisissent pas ici : elles sont **calculées depuis la recette** au moment où la
fournée est programmée. Voir §2.6, qui donne la table des étapes et la règle de
cumul.

### 2.6 Nouvelle table — les étapes de préparation

**C'est une table de back-office : la PWA ne la lit jamais.** Elle sert à
construire `pro_baking_batch` au moment où une fournée est programmée. Le front
ne connaît que **trois** étapes — préparation, cuisson, finition — parce que sa
frise a trois segments et son vocabulaire trois couleurs. La recette, elle, en
a autant qu'il faut.

```sql
CREATE TABLE pro_product_step (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_product   INT UNSIGNED NOT NULL,
    id_shop      INT UNSIGNED NULL COMMENT 'NULL = recette réseau ; renseigné = variante d''un magasin',
    position     SMALLINT     NOT NULL COMMENT 'ordre d''exécution : 1, 2, 3…',
    stage        VARCHAR(8)   NOT NULL COMMENT 'prep | cook | finish — le regroupement vu par la PWA',
    label        VARCHAR(64)  NOT NULL COMMENT 'Pétrissage, Pointage, Façonnage, Nappage…',
    minutes      DECIMAL(6,2) NOT NULL,
    is_per_piece TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'minutes × quantité au lieu de minutes',
    is_waiting   TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'pointage, apprêt : ça dure sans occuper personne',
    temperature  SMALLINT     NULL COMMENT 'quand stage = cook',
    UNIQUE KEY uq_step (id_product, id_shop, position),
    KEY ix_product (id_product)
);
```

`id_shop` est optionnel : si toutes les boutiques du réseau suivent la même
recette, laissez-le à `NULL` partout — ou retirez la colonne.

#### Les minutes sont-elles cumulables ? Oui, avec deux réserves

**Elles s'additionnent dans leur étape.** Pétrissage 15 + Pointage 60 +
Façonnage 20 = 95 minutes de `prep`. C'est cette somme qui part dans
`pro_baking_batch.prep_minutes`.

```
minutes(étape)  = is_per_piece ? minutes × quantité : minutes

prep_minutes    = Σ minutes(étape) où stage = 'prep'
cook_minutes    = Σ minutes(étape) où stage = 'cook'
finish_minutes  = Σ minutes(étape) où stage = 'finish'
```

**Réserve 1 — `is_per_piece` multiplie avant d'additionner.** Un nappage d'une
minute la pièce sur 36 éclairs fait 36 minutes, pas 1. C'est exactement ce que
le front appelle déjà `finish_type: PIECE` (§3.11) ; si **toutes** les étapes
de finition d'un produit sont à la pièce, servez `finish_type: "PIECE"` et
`finish_per_piece_minutes` = la somme des minutes unitaires. Si elles sont
mélangées, servez `finish_type: "LOT"` avec le total déjà multiplié — le front
n'a pas besoin de connaître le détail, il a besoin d'une durée juste.

**Réserve 2 — `is_waiting` ne se soustrait pas, mais il ne se cumule pas non
plus côté ressources.** Un pointage de 60 minutes allonge le délai comme les
autres : il compte dans `prep_minutes`. En revanche il n'occupe ni un opérateur
ni un four, et c'est ce qui permet au back-office de placer une seconde fournée
sur le même four pendant ce temps-là. *La PWA ne lit pas ce champ* — c'est
votre ordonnanceur qui en a besoin, pas l'écran.

#### Conséquence : `production_lead_minutes` est un champ **calculé**

Le lead du produit (§3.2) n'est pas une saisie : c'est la somme de toute la
chaîne, attentes comprises.

```
production_lead_minutes = prep_minutes + cook_minutes + finish_minutes + shelf_delay_minutes
                          ↑ calculées sur une quantité de référence = batch_size
```

La quantité de référence compte : avec des étapes à la pièce, le lead dépend de
la taille de fournée. Prendre `batch_size` donne le délai d'**une** fournée,
qui est l'unité dans laquelle l'atelier décide.

Un lead sous-estimé fait décider trop tard : la fenêtre de projection
(`forecast_hours + lead`) regarde alors moins loin que la réalité, et le manque
se découvre vitrine vide. C'est le seul champ dérivé du module, et c'est celui
qu'il ne faut pas laisser à 0 « en attendant ».

#### La fournée garde sa propre copie, et c'est voulu

`pro_baking_batch` ne pointe pas vers `pro_product_step` : il **recopie** les
minutes au moment de la programmation. Une recette corrigée à 14 h ne doit pas
déplacer une fournée déjà au four, et le `allotted_minutes` du
`PATCH /baking/{id}` (§3.13) corrige la fournée — **jamais la recette**.

Autrement dit : `pro_product_step` fait foi pour *planifier*,
`pro_baking_batch` fait foi pour *ce qui est en cours*.

`finish_label` de la fournée reprend le libellé de l'étape de finition — celui
de la première quand il y en a plusieurs. C'est le mot qui apparaît sur le
bouton : « Ressuage terminé », « Glaçage terminé ».

### 2.7 Table de MEP

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

Chaque endpoint est suivi d'un tableau **champ par champ** : qui le lit,
ce qu'il décide à l'écran, et ce qui se passe s'il manque. C'est la colonne
« si absent » qui compte le plus — elle dit ce qui est vraiment obligatoire et
ce qui peut attendre une seconde passe. Quelques champs y sont marqués *pas lu
aujourd'hui* : ne perdez pas de temps à les remplir soigneusement.

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

| champ | ce qu'il décide | si absent |
|---|---|---|
| `periods[].key` | **la clé pivot** : elle relie `periods` du catalogue, `period` des lignes de MEP et des minimums de vitrine. Les trois doivent employer le même vocabulaire | repli sur `PRODUCTION_PERIODS` de `config/app.php` |
| `periods[].label` | le libellé des pastilles « Matin / Midi / Après-midi » | la clé s'affiche telle quelle |
| `periods[].start` / `end` | découpe la journée : quel onglet s'ouvre par défaut, quelles périodes sont passées (elles quittent le sélecteur), et les deux horizons de l'écran Stock | repli config |
| `forecast_hours` | la profondeur de projection : `maintenant → + forecast_hours + lead du produit`. Plus large = on enfourne plus tôt et plus gros | 2 h |
| `history_weeks` | combien de semaines sont demandées au profil de ventes, **et** le seuil sous lequel l'écran avertit « moyenne sur N journées seulement » | 6 |
| `safety_margin` | unités gardées en réserve avant de conclure au manque | 0 |

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

| champ | ce qu'il décide | si absent |
|---|---|---|
| `id_product` | **la clé de jointure de tout le module** : stock, MEP, ventes, commandes et fournées se recoupent par elle | ligne ignorée |
| `name` | le nom sur les tuiles, les tableaux et la feuille de validation | « — » |
| `id_category` / `category_name` | le regroupement des tableaux **et** la rangée de badges de filtre (Besoins, Stock, Minimums) | « — », un seul badge |
| `periods` | dans quelle pastille de période le produit apparaît, et s'il compte dans le badge « N produits » de l'onglet | le produit n'apparaît dans aucune période |
| `batch_size` | **l'arrondi de toutes les quantités proposées** — à produire, à recuire, à remonter au plancher — et le pas des boutons − / + | lot de 1, signalé à l'écran |
| `unit_name` | le suffixe « 48 pc » | rien n'est suffixé |
| `production_lead_minutes` | élargit **deux** fenêtres : celle des ventes projetées et celle des commandes prises en compte. Un pain à 30 min de cuisson se décide 30 min plus tôt. **Champ calculé** — somme de toute la chaîne, voir §2.6 | 0 : le manque se découvre vitrine vide |
| `is_active` | un produit inactif ne s'affiche, ne se propose et ne se compte nulle part | actif |
| **`is_pdb`** | la liste du sélecteur « Ajouter » de la MEP du lendemain, et les catégories qui y apparaissent | `false` ⇒ sélecteur vide |
| **`is_pdm`** | l'appartenance à l'écran Minimums : lui seul y entre | `false` ⇒ absent du tableau |
| **`pdm_minimums`** | les colonnes par période du tableau Minimums, et le calcul `à produire = plancher + commandes − stock` | ligne « inconnu », aucune relance proposée |
| **`sector`** / **`sector_name`** | la barre de secteur et le filtrage de **tous** les écrans, compteurs compris | pas de barre : le magasin n'a qu'un atelier |
| `main_photo_path` | la vignette de la tuile | pictogramme générique |

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

| champ | ce qu'il décide | si absent |
|---|---|---|
| `lines[].id` | la clé renvoyée à `mep/validate`, et l'`id_mep_line` du portage en rayon | la ligne ne peut pas être validée |
| `lines[].id_product` | jointure catalogue et filtrage par secteur | ignorée par le filtre secteur |
| `lines[].name` / `category_name` | l'affichage et le regroupement par rayon | repli sur la fiche produit |
| `lines[].period` | dans quelle période la ligne apparaît. **Absente, la ligne est montrée partout** — mieux vaut la voir deux fois que l'oublier à la cuisson | montrée dans toutes les périodes |
| `lines[].quantity_planned` | pré-remplit le champ de validation | 0 |
| `lines[].quantity_validated` | ce qui est sorti du four — moitié gauche du « reste à porter » | ligne tenue pour non validée |
| `lines[].quantity_shelved` | ce qui est déjà en rayon — moitié droite. **C'est ce champ qui fait disparaître une tuile de « À mettre en rayon »** | 0 ⇒ le même plateau est reproposé indéfiniment |
| `lines[].status` | `PREPARED` déclenche le bandeau rouge et le compteur « à valider » | `PREPARED` |
| `prepared_at` | la méta « préparée hier à 17 h 40 » | rien n'est affiché |

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

| champ | ce qu'il décide | si absent |
|---|---|---|
| `items[].id_product` | jointure | ligne ignorée |
| `items[].quantity` | **le stock vendable** : base du manque, du plancher de vitrine, du tri par tension et du delta de l'écran Stock | 0 |
| `items[].name` / `category_name` / `unit_name` | l'affichage, avec repli sur la fiche produit | repli catalogue |
| `updated_at` | *pas lu aujourd'hui* — gardez-le pour un futur « stock à jour il y a 3 min » | — |

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

| champ | ce qu'il décide | si absent |
|---|---|---|
| `slots` | l'axe du temps : les heures de début de créneau | aucune projection possible |
| `products[].id_product` + `expected[]` | la courbe de vente du produit — c'est **la** matière première de tout l'écran | produit inconnu du profil : aucune proposition, et l'écran l'écrit |
| `granularity_minutes` | la durée d'un créneau, utilisée au prorata quand une fenêtre tombe en plein milieu de l'un d'eux | 30 min |
| `samples` | déclenche l'avertissement « moyenne sur N journées seulement » quand il est sous `history_weeks` | aucun avertissement |
| `weeks` / `weekday_only` | *renvoyés en écho, pas lus* — le seuil d'avertissement vient de `production/config` | — |

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

| champ envoyé | ce qu'il décide | si absent |
|---|---|---|
| `id_product` / `quantity` | le lot lui-même | la PWA n'envoie pas |
| `source` | **l'effet** — voir le tableau ci-dessus | traité comme `REBAKE` |
| `id_mep_line` | rattache le portage à sa ligne pour décompter le reste. Sans lui, le serveur devrait recouper sur produit + date, et deux fournées du même produit deviendraient indiscernables | le stock monte, mais le « reste à porter » ne bouge pas — la tuile revient |
| `id_employee` | qui a porté le plateau | geste non tracé |

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

| champ | ce qu'il décide | si absent |
|---|---|---|
| `id_product` | jointure | ligne ignorée |
| `quantity` | s'ajoute au manque, au plancher de vitrine et au delta du stock | ligne ignorée si ≤ 0 |
| `channel` | **une étiquette, rien de plus** : il ne change aucun calcul, seulement à qui on devra s'excuser | rangé en « magasin » |
| `due_time` | la fenêtre dans laquelle la commande compte, le tri du carnet, et le marquage « en retard » en rouge | due tout de suite : comptée partout et remontée en tête |
| `period` | le repli quand il n'y a pas d'heure (« pour midi ») | voir ci-dessus |
| `reference` | le numéro que le client a sous les yeux, affiché au carnet | rien |
| `name` / `category_name` | l'affichage du carnet | « — » |
| `id_order` | *pas lu aujourd'hui* — à garder pour la traçabilité vers la commande d'origine | — |

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

| champ | ce qu'il décide | si absent |
|---|---|---|
| `server_time` | **l'heure de référence de tout l'écran** : position de la barre du présent, retards, échéances. Une tablette d'atelier dérive ; elle ne doit pas décréter un retard pour autant | l'heure du navigateur, avec sa dérive |
| `batches[].id` | la clé du PATCH d'avancement, et la fournée mise en avant à l'arrivée depuis « Lancer » | fournée inutilisable |
| `batches[].id_product` | relie la fournée au produit : **c'est par lui que l'écran Besoins sait qu'un produit est en cuisson**, et que le filtre secteur s'applique | la fournée n'apparaît sur aucune tuile de produit |
| `batches[].quantity` / `unit_name` | le gros chiffre de la carte, et la quantité pré-remplie de la mise en magasin | 0 |
| `batches[].id_oven` / `oven_name` | les lignes de la frise et le filtre « Four 1 · 2 · 3 » | tout dans une ligne unique |
| `batches[].temperature` | affichée sur la carte, sous l'étape de cuisson ; vient de l'étape `cook` de la recette (§2.6) | rien |
| `batches[].prep_start` + `prep_minutes` | le segment bleu de la frise et l'échéance de l'ordre « Commencer la préparation » | segment absent |
| `batches[].cook_start` + `cook_minutes` | le segment rouge et l'heure de sortie du four | segment absent |
| `batches[].finish_type` | `LOT` (durée fixe) ou `PIECE` (durée × quantité). **Un nappage de 36 éclairs ne dure pas comme un ressuage de plaque** | `LOT` |
| `batches[].finish_minutes` / `finish_per_piece_minutes` | la longueur du segment ambre, selon `finish_type` | 0 |
| `batches[].finish_label` | le mot sur le bouton : « Ressuage terminé », « Glaçage terminé » | « Finition terminée » |
| `batches[].shelf_delay_minutes` | l'**ETA** affiché sur les tuiles de « Ce qui manque » : à quelle heure le produit sera en rayon | ETA collé à la fin de finition |
| `batches[].status` | l'étape en cours : quel bouton, quelle couleur, et si la fournée est encore active | `PLANNED` |
| `batches[].*_started_at` | la progression réelle des jauges, par rapport au prévu | jauge calée sur l'horaire prévu |

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

| champ envoyé | ce qu'il décide | si absent |
|---|---|---|
| `status` | l'étape suivante — voir ci-dessous | la PWA n'envoie pas |
| `allotted_minutes` | le temps corrigé au doigt sur la feuille de validation, à persister sur l'étape concernée pour que la frise et les échéances suivantes suivent | la durée planifiée reste |
| `id_employee` | qui a fait le geste | geste non tracé |

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
3. **L'atelier** — 3.10 fours, 3.11 plan, 3.12 création, 3.13 avancement. C'est
   là que `pro_product_step` (§2.6) devient nécessaire : sans recette, le
   serveur n'a pas de quoi calculer les horaires d'une fournée.
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
