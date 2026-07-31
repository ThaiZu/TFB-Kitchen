# Module Production — contrat d'API

État au 31/07/2026. **Aucun de ces endpoints n'existe encore.** Le front est
écrit contre ce contrat : le jour où l'API répond, l'écran fonctionne sans
modification.

## Ce que l'écran doit montrer

D'après `.tfb/module.json` : « Ce qu'il y a à faire aujourd'hui, avec
l'avancement par tâche : à faire, en cours, terminé. »

Le plan vient du serveur — il n'est pas déduit des commandes du jour. Et
l'avancement vit côté serveur, pas dans la tablette : deux personnes doivent
travailler sur la même liste, et le magasin compte souvent plusieurs
appareils.

---

## 1. Le plan du jour

```
GET /shops/{shopId}/production?date=YYYY-MM-DD
```

`date` optionnelle, défaut : aujourd'hui. La cuisine consulte aussi la veille
et le lendemain — le plan doit donc être interrogeable sur une date, pas
seulement « maintenant ».

```json
{
  "success": true,
  "data": {
    "date": "2026-07-31",
    "items": [
      {
        "id": 8801,
        "id_product": 6700106,
        "name": "Croissant pur beurre",
        "category_name": "Viennoiserie",
        "quantity_planned": 120,
        "quantity_done": 45,
        "unit_name": "pc",
        "status": "IN_PROGRESS",
        "slot": "06:00",
        "priority": 1,
        "note": "Série du matin, avant l'ouverture",
        "main_photo_path": "r2://products/6700106/main.jpg",
        "started_at": "2026-07-31 05:40:00",
        "completed_at": null,
        "completed_by": "Nathan C."
      }
    ]
  }
}
```

### Champs

| champ | rôle | obligatoire |
|---|---|---|
| `id` | identifiant de la ligne de production (pas du produit) | oui |
| `id_product` | pour ouvrir la fiche technique | recommandé |
| `name` | ce qu'on produit | oui |
| `quantity_planned` | combien il faut en faire | oui |
| `quantity_done` | combien sont faits | oui |
| `unit_name` | pièce, kg, plaque… | recommandé |
| `status` | `TODO`, `IN_PROGRESS`, `DONE`, `CANCELLED` | oui |
| `slot` | heure ou créneau visé | recommandé |
| `priority` | ordre d'exécution, 1 = d'abord | recommandé |
| `note` | consigne libre | facultatif |
| `main_photo_path` | même convention `r2://` que les produits | facultatif |
| `started_at`, `completed_at`, `completed_by` | traçabilité | facultatif |

**`quantity_done` compte.** Une production se fait rarement d'un bloc : on
enfourne une partie, on reprend plus tard. Un simple booléen fait/pas fait
obligerait à mentir jusqu'à la fin de la série.

**Le statut et les quantités doivent être cohérents côté serveur** : c'est lui
qui décide que `quantity_done == quantity_planned` vaut `DONE`. Deux tablettes
peuvent écrire en même temps ; l'arbitrage ne peut pas vivre dans le
navigateur.

---

## 2. Mettre à jour l'avancement

```
PATCH /production/{itemId}
```

```json
{ "status": "IN_PROGRESS", "quantity_done": 45, "id_employee": 12 }
```

Les trois champs sont facultatifs et indépendants : on peut incrémenter la
quantité sans toucher au statut, ou marquer terminé sans compter.

Réponse : la ligne mise à jour, dans la forme ci-dessus. Le front la réaffiche
telle quelle plutôt que de deviner le nouvel état.

### Question ouverte : faut-il un PIN ?

Les checklists exigent un PIN employé pour valider une tâche. Faut-il la même
exigence en production ? Deux lectures :

- **Oui**, si l'avancement est une preuve — qui a produit quoi, à quelle heure.
- **Non**, si c'est un tableau de bord d'atelier qu'on manipule à quatre mains
  pendant le service, où chaque validation coûterait quatre chiffres.

Le front est écrit sans PIN. L'ajouter plus tard est simple ; le retirer après
l'avoir imposé aux équipes l'est moins.

---

## 3. Compteur, pour le badge de l'onglet

```
GET /shops/{shopId}/production/pending-count?date=YYYY-MM-DD
```

```json
{ "success": true, "data": { "todo": 6, "in_progress": 2, "done": 14 } }
```

Même logique que `/ajax/orders/pending-count` : sondé toutes les dix secondes,
il ne renvoie que des nombres.

Optionnel — sans lui, l'onglet Production n'affichera simplement pas de
pastille.

---

## Comportement du front en attendant

`ProductionRepository` appelle déjà ces routes. Tant qu'elles renvoient 404 ou
une réponse vide, l'écran affiche un état explicite : « le plan de production
n'est pas encore servi par l'API ». Ni page blanche, ni erreur, ni fausse liste
vide qui laisserait croire qu'il n'y a rien à produire aujourd'hui — la
confusion serait pire que l'absence.

L'entrée du menu reste marquée « en construction » jusqu'à ce que l'API
réponde.
