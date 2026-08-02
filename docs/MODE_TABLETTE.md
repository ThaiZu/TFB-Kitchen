# Mode de la tablette — Gestion · Production · WebShop

Une même application tourne sur trois postes qui n'ont pas le même métier : le
fournil produit, le bureau contrôle, le comptoir vend. Le **mode** dit à quoi
sert un appareil, et donc ce qu'il affiche.

> Contrat d'origine : `INTEGRATION_KITCHEN_WEBSHOP.md` (dépôt
> `samsam2703MFC/WebShop`). Ce document dit ce qui a été fait, ce qui a été fait
> autrement, et pourquoi.

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
| `kitchen_webshop_shop` | surcharge locale de l'id de boutique | 5 ans |

`Secure` · `HttpOnly` · `SameSite=Strict` · `path=/`, comme les cookies d'auth
existants.

**Ce cookie n'est pas une identité.** Il n'entre dans aucune décision
d'autorisation : le forger ne donne accès à rien. Chaque module reste protégé
par le jeton de session, et le back-office WebShop par sa propre session PIN. Il
ne fait que choisir des entrées de menu.

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
compte 37 sections en 6 groupes, et il gère déjà tout ce qui est délicat : pavé
PIN, filtrage du menu par profil, cloisonnement à la boutique, révocation
immédiate, anti-brute-force. Kitchen lui donne un cadre et sa boutique.

- Route : `GET /webshop` → `app/Views/webshop/index.twig`
- Rendu : **vue intégrée** (iframe plein écran), option 1 des trois proposées —
  l'utilisateur ne quitte pas l'application et garde la barre d'onglets.
- URL ouverte : `<base>?shop=<id>`

### Configuration

| Réglage | Source | Priorité |
|---|---|---|
| URL du back-office | `SetEnv WEBSHOP_BO_URL` (serveur) ou champ dans `/me` (tablette) | tablette > serveur |
| Boutique (id) | session de connexion (`shop_id` du jeton) ou champ dans `/me` | tablette > session |

La configuration serveur existe pour ne pas saisir la même URL sur cinquante
tablettes ; la surcharge locale, pour en dévier une le temps d'un test.

L'id de boutique vient de la session par défaut. Le forger est sans effet côté
serveur — le back-office ignore `?shop=` pour une session PIN et retient la
boutique de la session — mais un id qui contredit la session afficherait un
back-office et des données Kitchen appartenant à deux magasins différents. D'où
le défaut.

### Quand le mode est indisponible

L'option est **grisée avec sa raison**, jamais masquée. Trois raisons
distinctes, parce qu'elles ne se corrigent pas au même endroit :

| Code | Message | Où corriger |
|---|---|---|
| `no_url` | aucune adresse enregistrée | paramètres de la tablette, ou `WEBSHOP_BO_URL` |
| `bad_url` | l'adresse n'est pas une adresse web | paramètres de la tablette |
| `no_shop` | aucune boutique dans la session | compte de connexion — **pas** ici |

`GET /webshop` affiche la même raison plutôt qu'un cadre vide. Aucun repli,
aucune donnée de démonstration.

### Authentification

**Aucun jeton n'est transmis au back-office.** Il ouvre sa propre session PIN
dans le cadre (cas B1 du brief : une saisie par service, session de 12 h). Le
jeton admin ERP n'apparaît nulle part dans Kitchen — ni code, ni config, ni log,
ni stockage — et n'a rien à faire sur une tablette de comptoir.

L'URL saisie sur la tablette finit en `src` d'une iframe : tout ce qui ne
commence pas par `http://` ou `https://` est refusé, à l'écriture comme à la
lecture. C'est ce qui ferme `javascript:` et `data:`.

### Ce qui reste côté serveur du webshop

Si `/webshop/backoffice_franchisee/` répond avec `X-Frame-Options: DENY` ou un
`Content-Security-Policy: frame-ancestors` restrictif, le cadre restera blanc.
Le navigateur ne remonte rien au script : on ne peut ni le détecter, ni le
contourner côté client — et on ne cherche pas à le faire. La page laisse un lien
« ouvrir dans un onglet » pour ne pas bloquer l'équipe en attendant
l'ajustement, qui se fait sur le serveur web du webshop.

---

## 4. Où c'est écrit

| Rôle | Fichier |
|---|---|
| Les règles (pures, testées) | `app/Services/Me/DeviceModeService.php` |
| La persistance | `core/Support/DeviceMode.php` |
| Exposition à toutes les vues | `app/Http/Controllers/Controller.php` (`view()`) |
| Écran de réglage | `app/Views/me/about.twig`, `POST /me/settings` |
| Vue WebShop | `app/Http/Controllers/WebShop/`, `app/Views/webshop/index.twig` |
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
