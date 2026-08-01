# Module Cuisson — contrat d'API

État au 01/08/2026. **Aucun de ces endpoints n'existe encore.** Le front est
écrit contre ce contrat : le jour où l'API répond, l'écran fonctionne sans
modification. Mocks exécutables dans `docs/mocks/cuisson/`.

Module distinct de Production. Production répond à « qu'est-ce qu'on doit
sortir aujourd'hui » ; Cuisson répond à « qu'est-ce qui est au four en ce
moment, et à quelle heure ce sera en rayon ». Le premier se consulte le matin,
le second toute la journée.

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

## Le principe

Une **fournée** traverse trois étapes, dans cet ordre, et une seule à la fois :

```
Préparation  →  Cuisson  →  Finition  →  [délai]  →  en rayon
```

Chaque étape occupe une ressource différente. Le four est libre dès la cuisson
finie ; c'est la finition qui décale la mise en rayon. **Un produit n'est
vendable qu'après la finition ET son délai de mise en rayon** — c'est le seul
horaire qui intéresse le client.

### Les deux natures de finition

| | Au lot | À la pièce |
|---|---|---|
| Exemple | ressuage d'une baguette | nappage d'un éclair |
| Durée | fixe | quantité × durée unitaire |
| Ressource | des grilles | **quelqu'un** |
| Doubler la quantité | ne change rien | double la durée |

Cette distinction n'est pas cosmétique : elle décide si une fournée mobilise un
poste de travail ou seulement du temps. Un champ unique « durée de finition »
ne saurait pas la représenter, et sous-estimerait toutes les fournées nappées.

---

## 1. Les fours

```
GET /shops/{shopId}/ovens
```

```json
[
  {
    "id": 1,
    "name": "Four 1 — Rotatif",
    "levels": 8,
    "temp_min": 160,
    "temp_max": 250
  }
]
```

Facultatif pour l'écran, qui affiche le nom porté par chaque fournée. Devient
nécessaire le jour où l'on veut vérifier la charge des fours.

---

## 2. Le plan de cuisson du jour

```
GET /shops/{shopId}/baking?date=YYYY-MM-DD
```

`date` optionnelle, défaut : aujourd'hui. L'écran ne propose pas de sélecteur
de date — une cuisine ne cuit pas pour hier.

```json
{
  "date": "2026-08-01",
  "server_time": "07:20",
  "batches": [
    {
      "id": 5501,
      "id_product": 6700210,
      "name": "Éclair chocolat",
      "category_name": "Pâtisserie",
      "quantity": 36,
      "unit_name": "pc",
      "id_oven": 2,
      "oven_name": "Four 2 — Ventilé",
      "temperature": 180,
      "prep_start": "06:00",
      "prep_minutes": 30,
      "cook_start": "06:40",
      "cook_minutes": 20,
      "finish_type": "PIECE",
      "finish_label": "Nappage",
      "finish_per_piece_minutes": 1,
      "shelf_delay_minutes": 10,
      "status": "FINISHING",
      "prep_started_at": "2026-08-01 06:02:00",
      "cook_started_at": "2026-08-01 06:41:00",
      "finish_started_at": "2026-08-01 07:01:00"
    }
  ]
}
```

### Champs

| champ | rôle | obligatoire |
|---|---|---|
| `id` | identifiant de la fournée | oui |
| `name`, `quantity` | ce qu'on cuit, combien | oui |
| `id_oven`, `oven_name`, `temperature` | où et à quelle température | recommandé |
| `prep_start`, `prep_minutes` | créneau de préparation, `HH:MM` | oui |
| `cook_start`, `cook_minutes` | créneau de cuisson | oui |
| `finish_type` | `LOT` ou `PIECE` | oui |
| `finish_minutes` | durée fixe — **si `LOT`** | conditionnel |
| `finish_per_piece_minutes` | durée unitaire — **si `PIECE`** | conditionnel |
| `finish_label` | « Ressuage », « Nappage », « Refroidissement »… | recommandé |
| `shelf_delay_minutes` | délai entre fin de finition et mise en vente | recommandé |
| `status` | voir ci-dessous | oui |
| `*_started_at` | horodatage réel de chaque étape | recommandé |
| `id_product` | pour ouvrir la fiche technique | facultatif |

**`server_time`** évite une classe entière de bugs : la tablette d'atelier est
rarement à l'heure, et un écran qui décide seul qu'une fournée est en retard
parce que son horloge dérive de six minutes fait perdre confiance en tout le
reste. Le front s'y cale, avec repli sur l'heure locale.

### Statuts

| statut | étape affichée | bouton proposé |
|---|---|---|
| `PLANNED` | préparation, à venir | *(aucun — en attente)* |
| `PREPARING` | préparation, en cours | Préparation terminée |
| `READY_TO_BAKE` | préparation, terminée | Enfourner |
| `BAKING` | cuisson | Sortir du four |
| `FINISHING` | finition | Finition terminée |
| `DONE` | — | *(retirée de l'écran)* |

Le statut fait autorité, pas l'horloge. Une fournée dont l'heure de cuisson est
passée mais qui est encore `PREPARING` reste en préparation : le planning est
une intention, le statut est un fait.

**Les horaires restent le plan, pas le réel.** Quand `*_started_at` est fourni,
le front en tire le temps restant ; sinon il retombe sur le créneau planifié et
la barre de progression devient indicative. Le contrat vaut dans les deux cas.

---

## 3. Faire avancer une fournée

```
PATCH /baking/{batchId}
```

```json
{ "status": "BAKING", "id_employee": 12 }
```

Un seul champ obligatoire : `status`. Le serveur horodate lui-même le passage —
deux tablettes peuvent appuyer à quelques secondes d'intervalle, et l'arbitrage
ne peut pas vivre dans le navigateur.

Réponse : la fournée mise à jour, dans la forme du `GET`. Le front la réaffiche
telle quelle plutôt que de deviner le nouvel état.

Les transitions admises sont celles du tableau ci-dessus, dans l'ordre. Un saut
d'étape (`PLANNED` → `BAKING`) doit être refusé côté serveur : enfourner sans
avoir préparé n'existe pas en atelier, et l'accepter fausserait les horaires de
mise en rayon de toute la journée.

> **Question ouverte** : faut-il un PIN employé, comme pour les checklists ?
> Le front est écrit sans. `id_employee` est envoyé quand il est connu.

---

## 4. Compteur, pour la pastille du menu

```
GET /shops/{shopId}/baking/pending-count
```

```json
{
  "preparing": 1,
  "baking": 2,
  "finishing": 3
}
```

Facultatif — sans lui, l'entrée de menu n'affiche pas de pastille.

---

## Comportement du front en attendant

| Endpoint muet | Ce que montre l'écran |
|---|---|
| `baking` | « le plan de cuisson n'est pas encore servi par l'API » |
| `ovens` | rien de particulier — les noms de four viennent des fournées |
| `pending-count` | pas de pastille |

Ni page blanche, ni fausse liste vide. Une liste vide affichée à la place d'un
endpoint muet ferait croire qu'il n'y a rien au four.

L'écran se relit **toutes les 30 secondes** : le plan bouge en continu, et une
fournée sortie par un collègue doit disparaître de la tablette d'à côté sans
que personne ne rafraîchisse.
