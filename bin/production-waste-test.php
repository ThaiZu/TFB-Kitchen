<?php

require __DIR__ . '/../vendor/autoload.php';

$checks = 0;
$check = static function (bool $condition, string $label) use (&$checks): void {
    ++$checks;
    if (!$condition) throw new RuntimeException($label);
};

$loader = new Twig\Loader\ChainLoader([
    new Twig\Loader\ArrayLoader([
        'layouts/base.twig' => '<!doctype html><html><head><meta charset="utf-8">{% block vendor_head %}{% endblock %}{% block head %}{% endblock %}</head><body>{% block content %}{% endblock %}{% block scripts %}{% endblock %}</body></html>',
    ]),
    new Twig\Loader\FilesystemLoader(__DIR__ . '/../src/app/Views'),
]);
$twig = new Twig\Environment($loader, ['autoescape' => 'html']);
$screen = [
    'available' => true,
    'reasons_available' => true,
    'has_shift' => true,
    'lines' => [
        ['id_product' => 11, 'name' => 'Croissant', 'in_stock' => '7.0000', 'inventory_initialized_at' => '2026-10-13T06:00:00Z', 'last_movement_id' => '81', 'is_piece_based' => 1, 'is_divisible' => 0],
        ['id_product' => 12, 'name' => 'Flour', 'in_stock' => '2.0000', 'inventory_initialized_at' => '2026-10-13T06:00:00Z', 'last_movement_id' => '82', 'is_piece_based' => 0, 'is_divisible' => 0],
    ],
    'reasons' => [
        ['key' => 'expired', 'label' => 'Expired', 'comment_required' => false],
        ['key' => 'other_waste', 'label' => 'Other', 'comment_required' => true],
    ],
];

foreach (['pl', 'en', 'fr', 'nl', 'it'] as $language) {
    $translations = json_decode(file_get_contents(__DIR__ . '/../src/core/I18n/translations/page/' . $language . '/production_waste.json'), true, 512, JSON_THROW_ON_ERROR);
    $html = $twig->render('production_waste/index.twig', array_merge($screen, ['ROOT' => '/kitchen', 'translations' => $translations]));
    $check(str_contains($html, 'id="pwProductSearch"') && str_contains($html, 'id="pwProducts"') && str_contains($html, 'id="pwQuantity"') && str_contains($html, 'id="pwReasons"'), "Single-line waste form renders in $language");
    $check(str_contains($html, "/ajax/production/waste") && str_contains($html, 'idempotency_key'), "Production waste uses atomic API contract in $language");
    $check(str_contains($html, 'reportable') && str_contains($html, 'last_movement_id'), "Only reportable stock lines are selected with concurrency state in $language");
    $check(str_contains($html, 'term.length<2') && str_contains($html, 'renderProducts') && str_contains($html, "className='pw-reason'"), "Products are searched then selected as tablet badges in $language");
    $check(str_contains($html, "line.in_stock)>0") && str_contains($html, 'product_not_found'), "Only positive stock products are offered in $language");
    $check(strpos($html, 'const newId=') < strpos($html, 'requestId=newId()'), "Waste form initializes its idempotency key after defining its generator in $language");
}

$dispatcher = FastRoute\simpleDispatcher(require __DIR__ . '/../src/core/Bootstrap/routes.php');
foreach ([['GET', '/production/waste'], ['POST', '/ajax/production/waste']] as [$method, $path]) {
    $check($dispatcher->dispatch($method, $path)[0] === FastRoute\Dispatcher::FOUND, "Route resolves: $method $path");
}

echo "Kitchen production waste: {$checks} checks passed\n";
