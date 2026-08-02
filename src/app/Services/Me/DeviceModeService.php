<?php

namespace App\Kitchen\app\Services\Me;

/**
 * Le mode de la tablette : ce qu'elle sert, et donc ce qu'elle montre.
 *
 * Une même application tourne sur trois postes qui n'ont pas le même métier :
 * le fournil produit, le bureau contrôle, le comptoir vend. Leur donner à
 * tous les sept menus, c'est demander à chacun d'ignorer les cinq qui ne le
 * concernent pas, à chaque écran, toute la journée.
 *
 * Le mode est un réglage de l'APPAREIL, pas du compte : la tablette du fournil
 * reste en Production même quand le responsable s'y connecte. Deux comptes qui
 * partagent une tablette partagent son mode ; un compte qui passe d'une
 * tablette à l'autre change de mode. C'est voulu.
 *
 * Cette classe ne connaît ni cookie, ni requête, ni horloge : elle dit
 * seulement quelles règles s'appliquent, ce qui la rend vérifiable par
 * bin/mode-test.php. La persistance vit dans core/Support/DeviceMode.
 *
 * Contrat d'intégration : docs/MODE_TABLETTE.md
 */
class DeviceModeService
{
    public const MODE_GESTION    = 'gestion';
    public const MODE_PRODUCTION = 'production';
    public const MODE_WEBSHOP    = 'webshop';

    /**
     * Production par défaut, et ce défaut n'est pas neutre : les tablettes déjà
     * en service tournent en production. Tout autre défaut changerait ce
     * qu'elles affichent au premier déploiement, sans que personne l'ait
     * demandé.
     */
    public const DEFAULT_MODE = self::MODE_PRODUCTION;

    /**
     * En mode production, la navigation est celle d'aujourd'hui, à l'entrée
     * près. C'est la contrainte : le mode par défaut ne doit rien changer.
     */
    private const NAV = [
        self::MODE_PRODUCTION => ['dashboard', 'production', 'objectives', 'checklists', 'orders', 'knowledge', 'complaints'],
        self::MODE_GESTION    => ['dashboard', 'objectives', 'checklists', 'knowledge', 'complaints'],
        self::MODE_WEBSHOP    => ['webshop'],
    ];

    /**
     * La barre du bas n'est pas le menu : elle porte les gestes du quotidien,
     * pas l'inventaire des sections. Quatre au plus, le profil en cinquième
     * étant ajouté par la coque.
     */
    private const TABS = [
        self::MODE_PRODUCTION => ['dashboard', 'production', 'checklists', 'orders'],
        self::MODE_GESTION    => ['dashboard', 'checklists', 'knowledge', 'complaints'],
        self::MODE_WEBSHOP    => ['webshop'],
    ];

    /** @return string[] */
    public function modes(): array
    {
        return [self::MODE_GESTION, self::MODE_PRODUCTION, self::MODE_WEBSHOP];
    }

    /**
     * Une valeur inconnue — cookie tronqué, mode retiré d'une version
     * ultérieure, valeur forgée à la main — retombe sur le défaut plutôt que
     * de vider la navigation. Une tablette sans menu est irrécupérable au
     * doigt ; une tablette en production ne l'est jamais.
     */
    public function normalise(?string $raw): string
    {
        $raw = strtolower(trim((string)$raw));

        return in_array($raw, $this->modes(), true) ? $raw : self::DEFAULT_MODE;
    }

    /** @return string[] clés des sections visibles dans le menu */
    public function navKeys(?string $mode): array
    {
        return self::NAV[$this->normalise($mode)];
    }

    /** @return string[] clés des onglets du bas, profil non compris */
    public function tabKeys(?string $mode): array
    {
        return self::TABS[$this->normalise($mode)];
    }

    public function allows(?string $mode, string $navKey): bool
    {
        return in_array($navKey, $this->navKeys($mode), true);
    }

    /**
     * Où atterrit la tablette : après connexion, après un changement de mode,
     * et derrière le logo. Un mode qui laisse l'utilisateur sur un écran qu'il
     * ne peut plus atteindre par le menu serait un cul-de-sac.
     */
    public function home(?string $mode): string
    {
        return $this->normalise($mode) === self::MODE_WEBSHOP ? '/webshop' : '/dashboard';
    }

    /**
     * Pourquoi le mode WebShop ne peut pas s'ouvrir, ou null s'il le peut.
     *
     * On distingue les causes parce qu'elles n'appellent pas la même
     * réparation : une URL absente et un jeton absent se règlent tous deux dans
     * les paramètres, mais pas au même champ, et un jeton mal collé — l'URL
     * dans le mauvais champ, un espace au bout — ne se voit pas si on répond
     * « non configuré ».
     *
     * @return string|null 'no_url' | 'bad_url' | 'no_token' | 'bad_token'
     */
    public function webshopBlocker(?string $base, ?string $token): ?string
    {
        $base  = trim((string)$base);
        $token = trim((string)$token);

        if ($base === '') {
            return 'no_url';
        }
        // Le champ est saisi à la main sur la tablette et finit en src d'une
        // iframe : tout ce qui n'est pas http(s) — javascript:, data: — est
        // refusé ici, à l'écriture comme à la lecture.
        if (!preg_match('#^https?://#i', $base)) {
            return 'bad_url';
        }
        if ($token === '') {
            return 'no_token';
        }
        // On ne vérifie pas que le jeton est LE bon — seul le serveur le sait,
        // et c'est son 403 qui fait foi. On vérifie qu'il a la forme d'un
        // jeton : la faute qu'on attrape ici, c'est l'URL collée dans le champ
        // du jeton, ou un espace ramassé au passage. Volontairement large : un
        // format plus strict enfermerait la tablette le jour où le webshop
        // change la longueur de ses jetons.
        if (!preg_match('#^[A-Za-z0-9_.:-]{16,256}$#', $token)) {
            return 'bad_token';
        }

        return null;
    }

    /**
     * L'URL du back-office, ou null si un réglage manque.
     *
     * Elle est prise telle qu'elle a été configurée : depuis la révision du
     * 2 août 2026, c'est le jeton qui impose la boutique, et le serveur
     * **ignore** `?shop=`. Kitchen n'a donc plus rien à y ajouter — l'ajouter
     * quand même laisserait croire que la tablette choisit son magasin.
     */
    public function webshopUrl(?string $base, ?string $token): ?string
    {
        if ($this->webshopBlocker($base, $token) !== null) {
            return null;
        }

        return trim((string)$base);
    }

    /**
     * La base d'API du webshop, déduite de l'URL du back-office : le brief la
     * définit comme « l'origine du webshop » + /webshop/api. On la déduit
     * plutôt que de la faire saisir — deux champs pour une seule information,
     * c'est un champ de trop et une occasion de les rendre incohérents.
     *
     * Sert au seul appel que Kitchen passe lui-même : la vérification du jeton
     * au démarrage (GET /franchisee/me).
     */
    public function webshopApiBase(?string $base): ?string
    {
        $base = trim((string)$base);
        if ($base === '' || !preg_match('#^https?://#i', $base)) {
            return null;
        }

        $parts = parse_url($base);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $origin = $parts['scheme'] . '://' . $parts['host']
                . (isset($parts['port']) ? ':' . $parts['port'] : '');

        return $origin . '/webshop/api';
    }

    /**
     * Les quatre derniers caractères d'un jeton, pour dire « c'est bien
     * celui-là » sans l'écrire. Le jeton est un secret : il ne doit apparaître
     * ni dans un log, ni dans une URL, ni dans le HTML d'une page laissée
     * ouverte sur un comptoir.
     */
    public function tokenHint(?string $token): ?string
    {
        $token = trim((string)$token);

        return $token === '' ? null : '…' . substr($token, -4);
    }
}
