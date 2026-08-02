# Mode de la tablette — Gestion · Production · WebShop

Une même application tourne sur trois postes qui n'ont pas le même métier : le
fournil produit, le bureau contrôle, le comptoir vend. Le **mode** dit à quoi
sert un appareil, et donc ce qu'il affiche.

> Contrat d'origine : `INTEGRATION_KITCHEN_WEBSHOP.md` (dépôt
> `samsam2703MFC/WebShop`), **révision du 2 août 2026** — le mode PIN a été
> retiré, la tablette s'identifie par un jeton d'appareil. Ce document dit ce
> qui a été fait, ce qui a été fait autrement, et pourquoi.

---

## 1. Ce que chaque mode montre

| Mode | Menu | Barre du bas |
|---|---|---|
| **Gestion** | Tableau de bord, Objectifs & Primes, Checklists, Base de connaissances, Réclamations | Accueil, Checklists, Connaissances, Réclamations |
| **Production** *(défaut)* | Tableau de bord, Production, Objectifs & Primes, Checklists, Commandes, Base de connaissances, Réclamations | Accueil, Production, Checklists, Commandes |
| **WebShop** | WebShop | WebShop |

Le **Profil** est présent dans les trois modes, dans la barre du bas et en bas du
menu. Ce n'est pas une exception décorative : c'est par lui qu'on revient aux
réglages. Une tablette dont on ne peut plus changer le mode serait à
réinstaller.

**Production est le défaut, et il ne change rien.** Les tablettes déjà en
service tournent en production : leur navigation après ce déploiement est
exactement celle d'avant. C'est la seule garantie qui compte au moment de
livrer.

### Deux libellés du brief sans module dédié

Le brief range dans Gestion « contrôle qualité » et « KPI ». Aucun des deux
n'existe comme module :

- le **contrôle qualité** se fait aujourd'hui par les **Checklists** ;
- les **KPI** vivent dans le **Tableau de bord** (statistiques des checklists du
  jour), et l'entrée **Objectifs & Primes** — badgée « En construction »,
  déjà présente avant ce travail — est leur emplacement annoncé.

On n'a donc pas créé d'entrée de menu supplémentaire : elle n'aurait mené nulle
part, et le brief interdit les entrées mortes. Quand ces deux modules
existeront, il suffira d'ajouter leur clé dans `DeviceModeService::NAV`.

---

## 2. Où vit le réglage

Le brief demande, par ordre de préférence :

1. côté ERP, sur l'appareil (`GET /devices/me`, champ `mode`) ;
2. à défaut, en cookie persistant côté Kitchen.

**On a pris l'option 2, explicitement.** `GET /devices/me` est bien consommé
(`app/Repositories/Me/DeviceRepository`), mais sa réponse ne porte aucun champ
`mode` et Kitchen n'a pas d'écriture sur l'appareil. Inventer un troisième
mécanisme était exclu ; le cookie est celui que le brief nomme.

| Cookie | Contenu | Durée |
|---|---|---|
| `kitchen_device_mode` | `gestion` \| `production` \| `webshop` | 5 ans |
| `kitchen_webshop_url` | surcharge locale de l'URL du back-office | 5 ans |
| `kitchen_webshop_token` | **le jeton d'appareil du webshop — un secret** | 5 ans |

`Secure` · `HttpOnly` · `SameSite=Strict` · `path=/`, comme les cookies d'auth
existants.

**Les deux premiers ne sont pas une identité.** Ils n'entrent dans aucune
décision d'autorisation : les forger ne donne accès à rien. Chaque module reste
protégé par le jeton de session, et le back-office WebShop par son jeton
d'appareil, que le serveur du webshop vérifie lui-même. Ils ne font que choisir
des entrées de menu.

**Le troisième, si.** `HttpOnly` le met hors de portée du JavaScript de la page,
`Secure` hors de portée d'un réseau en clair, et il ne part jamais vers une
autre origine que Kitchen. Voir §3 pour le reste du traitement.

### Pour passer à l'option 1 (ERP)

**Les tables et les deux endpoints sont spécifiés dans
`docs/BACKEND_A_FAIRE.md` §8** — quatre tables préfixées `pwa_` (les modes, les
entrées de menu, leur affectation, les réglages d'appareil) et
`GET /devices/me/config` + `PATCH /devices/me/settings`.

Ce périmètre est plus large qu'un simple champ `mode` sur l'appareil, et
volontairement : tant qu'à sortir la décision du code, autant en sortir aussi
**l'affectation des menus**. Sinon il faut encore livrer une version de la PWA
pour déplacer une entrée d'un mode à l'autre, et le réglage à distance n'aurait
résolu que la moitié du problème.

Côté PWA, trois points d'accroche, déjà isolés pour ça :

| Aujourd'hui | Demain |
|---|---|
| `DeviceMode::current()` — lit le cookie | lit `config.mode`, cookie en repli hors ligne |
| `DeviceMode::remember()` — écrit le cookie | `PATCH /devices/me/settings`, cookie en cache |
| `DeviceModeService::NAV` / `TABS` — constantes | `config.menu`, constantes en repli |

Les règles restent pures et `bin/mode-test.php` ne change pas : seule la source
des données change. Le cookie ne disparaît pas — une tablette hors ligne doit
encore afficher son menu, et c'est lui qui le sait.

---

## 3. Mode WebShop

**Le back-office franchisé n'est pas réécrit.** Il existe, il est déployé, il
compte 37 sections en 6 groupes, et il gère déjà tout ce qui est délicat :
portée du jeton, cloisonnement à la boutique, révocation, liste blanche des
écrans ouverts. Kitchen lui donne un cadre.

- Route : `GET /webshop` → `app/Views/webshop/index.twig`
- Rendu : **vue intégrée** (iframe plein écran), option 1 des trois proposées —
  l'utilisateur ne quitte pas l'application et garde la barre d'onglets.
- URL ouverte : **celle qui a été configurée, telle quelle.**

Kitchen n'ajoute plus `?shop=`. Depuis la révision, c'est le jeton qui impose la
boutique et le serveur **ignore** ce paramètre : l'ajouter laisserait croire que
la tablette choisit son magasin.

### Aucune connexion personnelle

La tablette est posée dans le magasin et sert à toute l'équipe. Lui demander un
identifiant à chaque geste n'aurait pas de sens : l'identité, c'est **l'appareil**.

Deux réglages, pris dans le back-office sous **Réglages › Tablette Kitchen** :

| Réglage | Où il vit | Repli |
|---|---|---|
| **URL du back-office** | cookie `kitchen_webshop_url`, ou `SetEnv WEBSHOP_BO_URL` | serveur, puis rien |
| **Jeton d'appareil** | cookie `kitchen_webshop_token` | **aucun** |

L'URL a une valeur serveur pour ne pas la saisir sur cinquante tablettes. **Le
jeton n'en a pas, et c'est délibéré** : un jeton vaut pour une boutique, et un
même serveur Kitchen sert plusieurs magasins. Une valeur globale ouvrirait le
back-office du même magasin pour tout le monde.

### Le jeton est un secret, et il est traité comme tel

- **Jamais dans une URL** : ni dans le `src` de l'iframe, ni en paramètre de
  requête. Une URL se retrouve dans les journaux d'accès, dans le `Referer` et
  dans l'historique du navigateur. Il ne voyage qu'en en-tête `X-Device-Token`.
- **Jamais dans un log.**
- **Jamais rendu dans la page.** Une tablette de comptoir reste allumée devant
  tout le monde : le champ de réglage part vide et n'affiche que les **quatre
  derniers caractères** — assez pour reconnaître celui qui est en place, pas
  assez pour le recopier. D'où aussi la règle du formulaire : *un champ vide
  conserve le jeton*, et l'effacer demande de cocher une case.
- Cookie `HttpOnly` : illisible par le JavaScript de la page.
- **Le jeton admin ERP n'apparaît nulle part** — ni code, ni config, ni log, ni
  stockage. Il donne accès aux marges et aux paramètres réseau ; il n'a rien à
  faire sur un comptoir.

### Vérification au démarrage, et révocation

Le jeton est révocable depuis le back-office : c'est le seul recours si la
tablette disparaît. À chaque ouverture de `/webshop`, Kitchen appelle
`GET <origine>/webshop/api/franchisee/me` avec l'en-tête, et décide :

| Réponse | Ce qu'on affiche |
|---|---|
| `2xx` | le cadre |
| `401` / `403` | **écran de configuration** — « cette tablette n'est plus autorisée ». Aucun cadre monté, donc aucune donnée en cache affichée |
| réseau injoignable | le cadre **et** un bandeau « jeton non vérifié, le webshop est injoignable » |
| `5xx` | idem réseau : un incident serveur n'est pas un verdict sur le jeton |

Le cas réseau mérite d'être justifié : bloquer sur un hoquet de wifi rendrait le
comptoir inutilisable, alors que le serveur du webshop refusera de toute façon
un jeton révoqué. On ouvre, et on **écrit** qu'on n'a pas pu vérifier — un échec
réseau dit « réseau », jamais « aucune donnée ».

La base d'API est **déduite** de l'URL configurée (son origine + `/webshop/api`)
plutôt que saisie : deux champs pour une seule information, ce sont deux
occasions de les rendre incohérents.

### Quand le mode est indisponible

L'option est **grisée avec sa raison**, jamais masquée. Quatre raisons
distinctes, parce qu'elles n'appellent pas la même réparation :

| Code | Message | Où corriger |
|---|---|---|
| `no_url` | aucune adresse enregistrée | réglages, ou `WEBSHOP_BO_URL` |
| `bad_url` | l'adresse n'est pas une adresse web | réglages |
| `no_token` | aucun jeton enregistré | réglages, jeton pris dans le back-office |
| `bad_token` | le jeton n'a pas la forme attendue | réglages — c'est l'URL collée dans le mauvais champ, neuf fois sur dix |

`bad_token` ne dit **pas** que le jeton est faux : seul le serveur le sait, et
il le dit par un 403. Le contrôle local attrape la faute de saisie, rien de
plus — un format strict enfermerait la tablette le jour où le webshop change la
longueur de ses jetons.

`GET /webshop` affiche la même raison plutôt qu'un cadre vide. Aucun repli,
aucune donnée de démonstration.

### Une conséquence à connaître

Un appareil partagé sans identification personnelle signifie qu'**aucune action
n'est attribuable à quelqu'un** : changer le statut d'une commande est tracé
comme venant de la tablette, pas d'une personne. C'est le choix assumé du brief
pour ce poste. Si l'attribution devient nécessaire (litige, inventaire), il
faudra ajouter une identification — pas contourner celle-ci.

### Deux limites qui restent côté serveur

**L'iframe et l'origine.** Si Kitchen est servi depuis le même hôte que le
webshop, le cadre fonctionne tel quel. Sur une **autre origine**, le
back-office ouvert en iframe n'a pas le jeton : il faudrait alors que Kitchen
passe lui-même les appels et rende ses propres écrans (les six endpoints de la
liste blanche). C'est un autre chantier, qui n'a pas été demandé — mais c'est
la question à trancher avant de déployer sur deux hôtes distincts.

**Le cadre lui-même.** Si `/webshop/backoffice_franchisee/` répond avec
`X-Frame-Options: DENY` ou un `Content-Security-Policy: frame-ancestors`
restrictif, le cadre restera blanc. Le navigateur ne remonte rien au script : on
ne peut ni le détecter, ni le contourner d'ici — et on ne cherche pas à le
faire. La page laisse un lien « ouvrir dans un onglet » pour ne pas bloquer
l'équipe en attendant l'ajustement, qui se fait sur le serveur du webshop.

---

## 4. Où c'est écrit

| Rôle | Fichier |
|---|---|
| Les règles (pures, testées) | `app/Services/Me/DeviceModeService.php` |
| La persistance | `core/Support/DeviceMode.php` |
| Exposition à toutes les vues | `app/Http/Controllers/Controller.php` (`view()`) |
| Écran de réglage | `app/Views/me/about.twig`, `POST /me/settings` |
| Vue WebShop | `app/Http/Controllers/WebShop/`, `app/Views/webshop/index.twig` |
| Vérification du jeton | `app/Repositories/WebShop/DeviceTokenRepository.php` |
| Menu | `app/Views/components/app_nav.twig` |
| Barre du bas | `app/Views/layouts/base.twig` |
| Tests | `php bin/mode-test.php` |

Les règles ne connaissent ni cookie, ni requête, ni horloge : c'est ce qui rend
`bin/mode-test.php` possible sans navigateur, et ce qui permettra de passer à
l'ERP sans y toucher.

---

## 5. Effet de bord corrigé au passage

`GET /me` répondait **« Błąd DI »** sur toutes les tablettes : `ProfileController`
injectait `KitchenService`, une classe qui n'existe pas dans ce dépôt (nom
hérité du fork fournisseur, où elle s'appelait `SupplierService`). L'onglet
Profil ne s'ouvrait donc pas.

C'est l'écran des réglages : il est passé sur `DeviceService` (déjà présent,
qui sert `GET /devices/me`), et il s'ouvre désormais **même quand l'ERP ne
répond pas** — sinon une tablette hors ligne ne pourrait plus changer de mode.
