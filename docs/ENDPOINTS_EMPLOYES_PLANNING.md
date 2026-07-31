# Employés de service — ce que l'API doit fournir

État au 31/07/2026.

## La demande

Quand on marque une tâche faite, la liste ne devrait proposer que les
personnes **de service ce jour-là**. Proposer toute l'équipe invite à
sélectionner quelqu'un qui n'est pas là — et le relevé de checklist perd sa
valeur de preuve.

## Ce qui est en place côté front

`GET /shops/{shopId}/employees` alimente la modale. Le service ne gardait que
`id` et `name` ; il conserve désormais aussi les initiales (calculées) et un
indicateur de présence, lu sous plusieurs noms plausibles :

`on_schedule`, `is_on_schedule`, `on_shift`, `is_working`, `is_present`,
`scheduled_today`

Le jour où le back-end en livre un, sous l'un ou l'autre de ces noms, le
filtre s'applique sans modification du front.

**Aucun n'est renvoyé aujourd'hui.** L'indicateur vaut donc null pour tout le
monde, et la modale affiche toute l'équipe en l'écrivant explicitement — un
filtre annoncé mais inopérant tromperait l'utilisateur. La distinction est
volontaire : `null` (« information non fournie ») n'est pas `false`
(« pas de service »), sans quoi la liste serait vide et la tâche
inachevable.

## Ce qui manque

### L'indicateur de présence

`GET /shops/{shopId}/employees`

```json
{ "id": 12, "name": "Nathan Chevalier", "on_schedule": true }
```

### Ou, mieux, la question posée pour un jour donné

Le planning change chaque jour ; l'endpoint actuel n'a pas de notion de date.

`GET /shops/{shopId}/employees?date=2026-07-31&on_schedule=1`

C'est la forme à retenir si le planning existe côté back-office : la checklist
se consulte aussi pour une date passée, et il faut alors savoir qui était de
service **ce jour-là**, pas aujourd'hui.

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
