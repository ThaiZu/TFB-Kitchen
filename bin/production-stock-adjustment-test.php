<?php

require __DIR__ . '/../vendor/autoload.php';

$checks = 0;
$check = static function (bool $condition, string $label) use (&$checks): void {
    ++$checks;
    if (!$condition) throw new RuntimeException($label);
};

$loader = new Twig\Loader\ChainLoader([
    new Twig\Loader\ArrayLoader([
        'layouts/base.twig' => '<!doctype html><html><head><meta charset="utf-8">{% block head %}{% endblock %}</head><body>{% block content %}{% endblock %}{% block scripts %}{% endblock %}</body></html>',
    ]),
    new Twig\Loader\FilesystemLoader(__DIR__ . '/../src/app/Views'),
]);
$twig = new Twig\Environment($loader, ['autoescape' => 'html']);
$screen = [
    'available' => true,
    'has_shift' => true,
    'shop_id' => 7,
    'lines' => [
        ['id_product' => 11, 'name' => 'Croissant', 'in_stock' => '7.0000', 'inventory_initialized_at' => '2026-10-13T06:00:00Z', 'last_movement_id' => '81', 'is_piece_based' => 1, 'is_divisible' => 0],
        ['id_product' => 12, 'name' => 'Flour', 'in_stock' => '2.0000', 'inventory_initialized_at' => '2026-10-13T06:00:00Z', 'last_movement_id' => '82', 'is_piece_based' => 0, 'is_divisible' => 0],
    ],
];

foreach (['pl', 'en', 'fr', 'nl', 'it'] as $language) {
    $translations = json_decode(file_get_contents(__DIR__ . '/../src/core/I18n/translations/page/' . $language . '/production_stock_adjustment.json'), true, 512, JSON_THROW_ON_ERROR);
    $html = $twig->render('production_stock_adjustment/index.twig', array_merge($screen, ['ROOT' => '/kitchen', 'translations' => $translations]));
    foreach (['title', 'subtitle', 'back_to_plan', 'unavailable', 'shift_required', 'product', 'product_search', 'product_search_hint', 'product_not_found', 'current_stock', 'stock_after', 'quantity', 'help', 'save', 'saving', 'saved', 'invalid', 'conflict', 'failed', 'network'] as $key) {
        $check(is_string($translations[$key] ?? null) && $translations[$key] !== '', "Translation $language: $key");
    }
    $check(str_contains($html, 'id="psaProductSearch"') && str_contains($html, 'id="psaQuantity"') && str_contains($html, 'id="psaAfter"'), "Add-only stock form renders in $language");
    $check(str_contains($html, '/ajax/production/stock-correction') && str_contains($html, 'idempotency_key'), "Stock correction uses the atomic endpoint in $language");
    $check(str_contains($html, 'supported=line') && str_contains($html, 'is_piece_based') && str_contains($html, 'is_divisible'), "Weighted products are excluded in $language");
    $check(str_contains($html, 'stock_after') && str_contains($html, 'Number(line.in_stock)+amount'), "Form previews the resulting stock in $language");
}

$dispatcher = FastRoute\simpleDispatcher(require __DIR__ . '/../src/core/Bootstrap/routes.php');
foreach ([['GET', '/production/stock-correction'], ['POST', '/ajax/production/stock-correction']] as [$method, $path]) {
    $check($dispatcher->dispatch($method, $path)[0] === FastRoute\Dispatcher::FOUND, "Route resolves: $method $path");
}

echo "Kitchen production stock adjustment: {$checks} checks passed\n";
