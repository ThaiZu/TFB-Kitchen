# Employés de service — comment le front s'y prend

État au 12/08/2026. Ce document décrivait une demande faite au back ; il décrit
maintenant ce qui est branché.

## La demande, rappelée

Quand on marque une tâche faite, la liste ne doit proposer que les personnes
**de service ce jour-là**. Proposer toute l'équipe invite à sélectionner
quelqu'un qui n'était pas là — et le relevé de checklist perd sa valeur de
preuve.

## Ce qui est en place côté front

**Mis à jour le 12/08/2026.** Le front croise désormais deux endpoints, sur
demande explicite :

| Endpoint | Ce qu'il répond | Ce qu'on en fait |
|---|---|---|
| `GET /franchisee-employees` | les fiches des employés du franchisé | la liste de référence, filtrée sur l'état d'activité |
| `GET /shops/{shopId}/schedule?date=YYYY-MM-DD` | le planning d'un jour | qui, parmi eux, est de service ce jour-là |

Le croisement se fait sur les identifiants — le planning ne porte pas de noms —
dans `StaffService`, vérifié sans réseau par `bin/staff-test.php`
(25 assertions).

### Ce que le front encaisse sans broncher

Ces tolérances ne sont pas de la coquetterie : chacune correspond à une façon
dont la liste pourrait se vider en silence, et une liste vide rend la checklist
inachevable.

- **L'identifiant en nombre ou en chaîne.** `41` et `"41"` sont la même
  personne. La comparaison se fait en chaînes ; typée, elle ferait disparaître
  la moitié de l'équipe sans rien signaler.
- **Le nom du champ d'identifiant** dans le planning : `employee_id`,
  `franchisee_employee_id`, `id_employee`, `id_franchisee_employee`,
  `user_id`, ou une fiche imbriquée sous `employee`.
- **L'emballage de la réponse** : la liste directement, ou portée par `items`,
  `employees`, `schedule`, `rows`, `data`.
- **L'état d'activité** : `is_active`, `active`, `enabled`, `deleted_at`,
  `archived_at`, ou `status` valant `INACTIVE`/`DISABLED`/`ARCHIVED`/
  `DELETED`/`LEFT`. **Une fiche sans aucun de ces champs est CONSERVÉE** — on
  n'écarte personne sur une absence d'information.
- **Une ligne de planning datée d'un autre jour** est écartée même si
  l'endpoint est censé filtrer. Ce n'est pas de la défiance : une ligne de la
  veille laissée passer ferait signer quelqu'un qui n'était pas là.

### Les trois états, et pourquoi ils restent distincts

| Situation | Ce que l'écran montre |
|---|---|
| planning servi, des gens dedans | ces personnes-là, et elles seules |
| planning servi, vide | « Personne n'est au planning ce jour-là. » |
| planning non servi | toute l'équipe active, **et on l'écrit** |

La distinction entre `null` (« on ne sait pas ») et `false` (« pas de
service ») est portée jusqu'à la vue. Sans elle, une indisponibilité du
planning viderait la liste et rendrait les tâches invalidables ; un filtre
annoncé mais inopérant, à l'inverse, tromperait.

### La date compte

C'est celle de la checklist consultée, pas celle du jour : une checklist se
relit pour hier, et il faut alors savoir qui était de service **ce jour-là**.
`ChecklistController` passe donc `$date`, pas `date('Y-m-d')`.

### Repli d'adresse

Si `/franchisee-employees` ne répond pas, le front retombe sur
`GET /shops/{shopId}/employees`, qui répondait déjà. C'est un repli
d'**adresse**, pas de données : on ne fabrique rien, et si les deux se taisent
l'écran dit qu'il ne sait pas.

`/shops/{shopId}/employees` reste par ailleurs la source du PIN, côté serveur
uniquement — voir la note de sécurité ci-dessous.

### Ce qui n'est plus utilisé

L'indicateur porté par la fiche elle-même (`on_schedule`, `is_on_schedule`,
`on_shift`, `is_working`, `is_present`, `scheduled_today`) est toujours lu,
mais seulement quand le planning n'est pas disponible. Le planning daté a
priorité : c'est la seule source qui sait répondre pour une date passée.

## Note de sécurité, hors sujet mais rencontrée

`ChecklistService::completeTask()` récupère la liste des employés **avec leur
PIN** pour comparer en PHP :

```php
if (($employee['pin'] ?? '') !== $pin) { … }
```

Les PIN de toute l'équipe transitent donc par le front-end à chaque
validation, et la comparaison n'est pas à temps constant. Une vérification
côté API — `POST /employees/{id}/verify-pin`, ou le PIN passé directement à
`mark-as-done` — éviterait de les faire circuler. À arbitrer avec vous : ce
n'est pas une régression introduite ici, mais l'écran de saisie du PIN vient
d'être retravaillé, autant le signaler.
