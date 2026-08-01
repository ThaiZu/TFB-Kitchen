<?php
/**
 * Jeux de données du serveur bouchon.
 *
 * Les horaires sont calculés par rapport à l'heure courante plutôt que figés :
 * un plan de cuisson daté de 06 h 00 ne montre rien d'intéressant quand on
 * teste à 15 h. Ici, il y a toujours une fournée au four.
 */

const MOCK_SHOP_ID = 1;

/**
 * ShopModel lit chacun de ces champs sans valeur de repli : en omettre un
 * suffit à couvrir les journaux d'avertissements. Ils sont tous fournis.
 */
function mock_shops(): array
{
    $shop = fn(int $id, string $name, string $city, string $zip, string $street, string $num) => [
        'id'                  => $id,
        'name'                => $name,
        'representative_name' => $name,
        'email'               => 'atelier' . $id . '@example.test',
        'street'              => $street,
        'street_num'          => $num,
        'city'                => $city,
        'zip'                 => $zip,
        'phone'               => '+32 2 000 00 0' . $id,
        'vat_number'          => 'BE0700.000.00' . $id,
        'opening_hours'       => '06:30',
        'closing_hours'       => '19:00',
    ];

    return [
        $shop(1, "L'Atelier — Bruxelles Centre", 'Bruxelles',        '1000', 'Rue du Marché aux Herbes', '42'),
        $shop(2, "L'Atelier — Louvain-la-Neuve", 'Louvain-la-Neuve', '1348', 'Grand-Place',              '7'),
        $shop(3, "L'Atelier — Namur",            'Namur',            '5000', 'Rue de Fer',               '18'),
    ];
}

/**
 * L'équipe du magasin.
 *
 * `on_schedule` est servi ici pour que le filtre « personnel actif » soit
 * réellement exerçable : un absent (Sofia) et un nom sans deuxième mot (Ali)
 * y sont volontairement, parce que ce sont les deux cas qui cassent — le
 * premier le filtre, le second le calcul d'initiales.
 *
 * Le PIN reste dans la réponse : c'est le serveur qui le vérifie, et le front
 * le retire avant d'atteindre la moindre vue (StaffService).
 */
function mock_employees(): array
{
    return [
        ['id' => 41, 'name' => 'Nathan Colin',   'pin' => '1234', 'on_schedule' => true],
        ['id' => 42, 'name' => 'Aïcha Benali',   'pin' => '2345', 'on_schedule' => true],
        ['id' => 43, 'name' => 'Marek Kowalski', 'pin' => '3456', 'on_schedule' => true],
        ['id' => 44, 'name' => 'Ali',            'pin' => '4567', 'on_schedule' => true],
        ['id' => 45, 'name' => 'Sofia Ferreira', 'pin' => '5678', 'on_schedule' => false],
    ];
}

function mock_ovens(): array
{
    return [
        ['id' => 1, 'name' => 'Four 1 — Rotatif', 'levels' => 8, 'temp_min' => 160, 'temp_max' => 250],
        ['id' => 2, 'name' => 'Four 2 — Ventilé', 'levels' => 5, 'temp_min' => 140, 'temp_max' => 220],
        ['id' => 3, 'name' => 'Four 3 — Sole',    'levels' => 4, 'temp_min' => 200, 'temp_max' => 280],
    ];
}

/**
 * Catalogue de production.
 *
 * Trois cas limites y sont volontairement représentés, parce que ce sont ceux
 * qui cassent : un produit sans taille de fournée, un produit inactif, et un
 * produit absent du profil de ventes.
 *
 * `is_pdb` — « prep day before » — dit si le produit se façonne la veille.
 * Seuls ceux-là sont proposés à l'encodage de la MEP du lendemain : la
 * pâtisserie du jour et le traiteur restent hors du sélecteur.
 */
function mock_products(): array
{
    return [
        ['id_product' => 6700106, 'name' => 'Croissant pur beurre',      'id_category' => 12, 'category_name' => 'Viennoiserie',
         'periods' => ['morning', 'noon'], 'batch_size' => 24, 'unit_name' => 'pc', 'production_lead_minutes' => 40, 'is_active' => true, 'is_pdb' => true],
        ['id_product' => 6700110, 'name' => 'Pain au chocolat',          'id_category' => 12, 'category_name' => 'Viennoiserie',
         'periods' => ['morning', 'noon'], 'batch_size' => 24, 'unit_name' => 'pc', 'production_lead_minutes' => 40, 'is_active' => true, 'is_pdb' => true],
        ['id_product' => 6700120, 'name' => 'Baguette tradition',        'id_category' => 14, 'category_name' => 'Boulangerie',
         'periods' => ['morning', 'noon', 'afternoon'], 'batch_size' => 12, 'unit_name' => 'pc', 'production_lead_minutes' => 20, 'is_active' => true, 'is_pdb' => true],
        ['id_product' => 6700130, 'name' => 'Tarte au citron meringuée', 'id_category' => 16, 'category_name' => 'Pâtisserie',
         'periods' => ['noon', 'afternoon'], 'batch_size' => 6, 'unit_name' => 'pc', 'production_lead_minutes' => 0, 'is_active' => true, 'is_pdb' => true],
        // Pas de batch_size : la proposition tombe à l'unité, et l'écran le dit.
        ['id_product' => 6700140, 'name' => 'Macaron pistache',          'id_category' => 16, 'category_name' => 'Pâtisserie',
         'periods' => ['afternoon'], 'unit_name' => 'pc', 'production_lead_minutes' => 0, 'is_active' => true, 'is_pdb' => false],
        // Inactif : jamais affiché, jamais proposé, même à stock zéro.
        ['id_product' => 6700150, 'name' => 'Bûche de Noël',             'id_category' => 16, 'category_name' => 'Pâtisserie',
         'periods' => ['afternoon'], 'batch_size' => 4, 'unit_name' => 'pc', 'production_lead_minutes' => 0, 'is_active' => false, 'is_pdb' => false],
        // Absent du profil de ventes : aucune recuisson proposée.
        ['id_product' => 6700160, 'name' => 'Sandwich club',             'id_category' => 18, 'category_name' => 'Traiteur',
         'periods' => ['noon'], 'batch_size' => 10, 'unit_name' => 'pc', 'production_lead_minutes' => 0, 'is_active' => true, 'is_pdb' => false],
    ];
}

function mock_initial_stock(): array
{
    return [
        6700106 => 10, 6700110 => 60, 6700120 => 30,
        6700130 => 2,  6700140 => 3,  6700150 => 0, 6700160 => 0,
    ];
}

/** Profil de ventes : une vraie courbe de journée, pas un plateau. */
function mock_sales_profile(): array
{
    $slots = [];
    for ($m = 6 * 60; $m < 19 * 60; $m += 30) {
        $slots[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
    }
    // Pic du matin, creux de 10 h, pic de midi, fin d'après-midi qui retombe.
    $shape = [0.3,0.6,1.4,2.2,2.6,2.1,1.5,1.0,1.0,1.1,1.6,2.4,2.8,2.2,1.4,0.9,0.8,0.9,1.1,1.3,1.2,0.9,0.6,0.4,0.3,0.2];
    $rates = [6700106 => 2.3, 6700110 => 1.5, 6700120 => 1.15, 6700130 => 0.58, 6700140 => 0.77];

    $products = [];
    foreach ($rates as $id => $r) {
        $products[] = [
            'id_product' => $id,
            'expected'   => array_map(fn($s) => round($r * $s, 2), $shape),
        ];
    }

    return [
        'granularity_minutes' => 30,
        'weeks'               => 6,
        'weekday_only'        => true,
        'samples'             => 6,
        'slots'               => $slots,
        'products'            => $products,
    ];
}

/** MEP du jour : préparée hier, en attente de validation. */
function mock_mep_today(string $date): array
{
    return [
        'date'        => $date,
        'prepared_at' => date('Y-m-d 17:40:00', strtotime($date . ' -1 day')),
        'status'      => 'PREPARED',
        'lines'       => [
            ['id' => 4401, 'id_product' => 6700106, 'name' => 'Croissant pur beurre', 'category_name' => 'Viennoiserie',
             'period' => 'morning', 'quantity_planned' => 120, 'quantity_validated' => null, 'unit_name' => 'pc', 'status' => 'PREPARED'],
            ['id' => 4402, 'id_product' => 6700110, 'name' => 'Pain au chocolat', 'category_name' => 'Viennoiserie',
             'period' => 'morning', 'quantity_planned' => 80, 'quantity_validated' => null, 'unit_name' => 'pc', 'status' => 'PREPARED'],
            ['id' => 4403, 'id_product' => 6700120, 'name' => 'Baguette tradition', 'category_name' => 'Boulangerie',
             'period' => 'morning', 'quantity_planned' => 60, 'quantity_validated' => null, 'unit_name' => 'pc', 'status' => 'PREPARED'],
            ['id' => 4404, 'id_product' => 6700130, 'name' => 'Tarte au citron meringuée', 'category_name' => 'Pâtisserie',
             'period' => 'noon', 'quantity_planned' => 12, 'quantity_validated' => null, 'unit_name' => 'pc', 'status' => 'PREPARED'],
        ],
    ];
}

/** MEP du lendemain : brouillon partiel, pour tester la reprise de saisie. */
function mock_mep_tomorrow(string $date): array
{
    return [
        'date'        => $date,
        'prepared_at' => date('Y-m-d H:i:s'),
        'status'      => 'PREPARED',
        'lines'       => [
            ['id' => 4501, 'id_product' => 6700106, 'name' => 'Croissant pur beurre', 'category_name' => 'Viennoiserie',
             'period' => 'morning', 'quantity_planned' => 144, 'quantity_validated' => null, 'unit_name' => 'pc', 'status' => 'PREPARED'],
            ['id' => 4502, 'id_product' => 6700120, 'name' => 'Baguette tradition', 'category_name' => 'Boulangerie',
             'period' => 'morning', 'quantity_planned' => 72, 'quantity_validated' => null, 'unit_name' => 'pc', 'status' => 'PREPARED'],
        ],
    ];
}

/**
 * Plan de cuisson calé sur l'instant présent.
 *
 * Les décalages sont donnés en minutes autour de « maintenant » : à toute
 * heure de la journée, l'écran montre une fournée au four, une en finition,
 * une qui attend. Sinon il faudrait tester à 06 h 40 pour voir quelque chose.
 */
function mock_baking_batches(int $nowMinutes): array
{
    $clock = fn(int $m) => sprintf('%02d:%02d', intdiv(max(0, $m), 60) % 24, max(0, $m) % 60);

    // [décalage du début de préparation, produit, four, °C, prép, cuisson, finition, rayon]
    $plan = [
        [-80, 6700210, 'Éclair chocolat',      'Pâtisserie',   36, 2, 'Four 2 — Ventilé', 180, 30, 20, ['PIECE', 'Nappage', 1],           10],
        [-40, 6700220, 'Chouquette glacée',    'Pâtisserie',   30, 1, 'Four 1 — Rotatif', 175, 15, 15, ['PIECE', 'Glaçage', 0.5],          5],
        [-20, 6700120, 'Baguette tradition',   'Boulangerie',  48, 3, 'Four 3 — Sole',    240, 20, 22, ['LOT', 'Ressuage', 20],            5],
        [-50, 6700106, 'Croissant pur beurre', 'Viennoiserie', 48, 1, 'Four 1 — Rotatif', 175, 20, 18, ['LOT', 'Refroidissement', 15],     5],
        [-35, 6700130, 'Chausson aux pommes',  'Viennoiserie', 36, 1, 'Four 1 — Rotatif', 180, 15, 20, ['LOT', 'Refroidissement', 10],     5],
        [-20, 6700170, 'Pain céréales',        'Boulangerie',  20, 3, 'Four 3 — Sole',    230, 20, 30, ['LOT', 'Ressuage', 25],            5],
        [ 10, 6700106, 'Croissant pur beurre', 'Viennoiserie', 48, 1, 'Four 1 — Rotatif', 175, 20, 18, ['LOT', 'Refroidissement', 15],     5],
        [ 40, 6700180, 'Cookie chocolat',      'Pâtisserie',   40, 2, 'Four 2 — Ventilé', 165, 10, 12, ['LOT', 'Refroidissement', 10],     0],
    ];

    $batches = [];
    foreach ($plan as $i => $p) {
        [$off, $idProduct, $name, $cat, $qty, $oven, $ovenName, $temp, $prep, $cook, $fin, $shelf] = $p;

        $prepStart = $nowMinutes + $off;
        $cookStart = $prepStart + $prep + 5;   // cinq minutes entre la fin de la prép et l'enfournement
        $finStart  = $cookStart + $cook;
        $finDur    = $fin[0] === 'PIECE' ? $qty * $fin[2] : $fin[2];

        // Le statut découle des horaires au moment de la génération ; il est
        // ensuite persisté dans l'état, et ce sont les boutons qui le font
        // avancer — pas l'horloge.
        if ($nowMinutes < $prepStart)                    { $status = 'PLANNED'; }
        elseif ($nowMinutes < $prepStart + $prep)        { $status = 'PREPARING'; }
        elseif ($nowMinutes < $cookStart)                { $status = 'READY_TO_BAKE'; }
        elseif ($nowMinutes < $finStart)                 { $status = 'BAKING'; }
        elseif ($nowMinutes < $finStart + $finDur)       { $status = 'FINISHING'; }
        else                                             { $status = 'DONE'; }

        $batch = [
            'id'                  => 5501 + $i,
            'id_product'          => $idProduct,
            'name'                => $name,
            'category_name'       => $cat,
            'quantity'            => $qty,
            'unit_name'           => 'pc',
            'id_oven'             => $oven,
            'oven_name'           => $ovenName,
            'temperature'         => $temp,
            'prep_start'          => $clock($prepStart),
            'prep_minutes'        => $prep,
            'cook_start'          => $clock($cookStart),
            'cook_minutes'        => $cook,
            'finish_type'         => $fin[0],
            'finish_label'        => $fin[1],
            'shelf_delay_minutes' => $shelf,
            'status'              => $status,
            'prep_started_at'     => null,
            'cook_started_at'     => null,
            'finish_started_at'   => null,
        ];
        if ($fin[0] === 'PIECE') {
            $batch['finish_per_piece_minutes'] = $fin[2];
        } else {
            $batch['finish_minutes'] = $fin[2];
        }
        $batches[] = $batch;
    }

    return $batches;
}
