<?php

/**
 * PRZYKŁAD UŻYCIA MODELI - ProductDetailModel
 *
 * Ten plik pokazuje jak używać utworzonych modeli do parsowania
 * danych z API i dostępu do zagnieżdżonych struktur.
 */

// Przykładowe dane z API (uproszczone)
$apiResponse = [
    'object' => [
        'id' => 2300004,
        'name' => 'FlipFlap - Américain Maison',
        'id_category' => 23000,
        'category_name' => 'Flip & Flap',
        'storage_name' => 'Comptoir Frigo - 1',
        'shelf_life_minutes' => 720,
        'suggested_sale_price' => 5.90,
        'is_vegetarian' => 0,
        // ... inne pola
    ],
    'photos' => [
        'main_photo_path' => '',
        'photo_1_path' => 'r2://recipes/230/photo_1/d7ee4ffc387cdddb677027dcdd017904_print.jpg',
        'photo_2_path' => 'r2://recipes/230/photo_2/17e30e78d983a04a361f49631f1aa0a3_print.jpg',
        'photo_3_path' => ''
    ],
    'recipe' => [
        'id' => 230,
        'name' => 'FlipFlap - Américain Maison',
        'is_subrecipe' => 0,
        'yield_quantity' => 1.00,
        'id_unit' => 3,
        'elements' => [
            // Sub-recipe
            [
                'recipe_id' => 374,
                'name' => 'Americain préparé',
                'type' => 'sub-recipe',
                'recipe_quantity' => 130,
                'yield_quantity' => 1365.00,
                'unit_name' => 'g',
                'elements' => [
                    // Ingredient w sub-recipe
                    [
                        'ingredient_id' => 303,
                        'name' => 'Filet americain nature',
                        'type' => 'ingredient',
                        'recipe_quantity' => 1000,
                        'required_quantity' => 95.238,
                        'unit_name' => 'g',
                        'allergens' => []
                    ],
                    // Więcej składników...
                ]
            ],
            // Direct ingredient
            [
                'ingredient_id' => 63,
                'name' => 'Baguette Tradition 500 g.',
                'type' => 'ingredient',
                'recipe_quantity' => 0.5,
                'required_quantity' => 0.5,
                'unit_name' => 'pcs',
                'allergens' => ['Zboża zawierające gluten']
            ]
        ]
    ],
    'package' => [
        'id' => 2,
        'name' => 'Emballage pour Flip-Flap',
        'description' => 'Pour les Sandwiches et FlipFlap',
        'materials' => [
            [
                'id' => 279,
                'name' => 'Sac Baguette Blanc - 400x120x70',
                'quantity' => 1,
                'base_unit_price_net' => 0.050000,
                'base_unit_price_gross' => 0.053000,
                'unit_name' => 'pcs'
            ],
            [
                'id' => 592,
                'name' => 'Étiquettes en rouleau (100 x 50)',
                'quantity' => 1,
                'base_unit_price_net' => 0.048853,
                'base_unit_price_gross' => 0.060089,
                'unit_name' => 'pcs'
            ]
        ]
    ],
    'competences' => [
        [
            'id' => 20,
            'name' => 'Mise en Traiteur',
            'description' => 'Découper et travailler les légumes...'
        ]
    ],
    'preparation' => [
        'types' => [
            [
                'id' => 2,
                'name' => 'Mise en place',
                'duration_second' => 60
            ]
        ],
        'steps' => [
            [
                'step_number' => 1,
                'step_description' => 'Déposer la salade feuille de chêne...'
            ],
            [
                'step_number' => 2,
                'step_description' => 'Ajouter les quartiers de tomates...'
            ],
            [
                'step_number' => 3,
                'step_description' => 'Etaler l\'américain maison...'
            ]
        ]
    ]
];

// ============================================================================
// UŻYCIE W REPOSITORY
// ============================================================================

use App\Kitchen\app\Models\Knowledge\Product\ProductDetailModel;

class ProductRepository
{
    public function getProductDetail(int $productId): ProductDetailModel
    {
        // Pobierz dane z API
        $rawData = $this->apiClient->get("/products/{$productId}");

        // Utwórz model - automatycznie parsuje całą strukturę
        return new ProductDetailModel($rawData);
    }
}

// ============================================================================
// UŻYCIE W CONTROLLER
// ============================================================================

class ProductController extends Controller
{
    public function show(int $id)
    {
        // Pobierz pełny produkt
        $product = $this->productRepository->getProductDetail($id);

        // Przekaż do widoku
        $this->view('knowledge/product/detail', [
            'product' => $product
        ]);
    }
}

// ============================================================================
// UŻYCIE W WIDOKU (TWIG)
// ============================================================================

/*
{% extends "layouts/base.twig" %}

{% block content %}
    <h1>{{ product.getName() }}</h1>
    <p>Kategoria: {{ product.getCategoryName() }}</p>
    <p>Cena: {{ product.getSuggestedSalePrice() }} zł</p>

    {# Zdjęcia #}
    {% if product.hasPhotos() %}
        <div class="photos">
            {% if product.getPhotos().hasPhoto1() %}
                <img src="{{ product.getPhotos().getPhoto1Path() }}">
            {% endif %}
            {% if product.getPhotos().hasPhoto2() %}
                <img src="{{ product.getPhotos().getPhoto2Path() }}">
            {% endif %}
        </div>
    {% endif %}

    {# Receptura #}
    {% if product.hasRecipe() %}
        <h2>Składniki:</h2>
        <ul>
        {% for element in product.getRecipe().getElements() %}
            {% if element.getType() == 'ingredient' %}
                <li>
                    {{ element.getName() }} -
                    {{ element.getRequiredQuantity() }} {{ element.getUnitName() }}
                    {% if element.hasAllergens() %}
                        <span class="text-danger">(Alergeny: {{ element.getAllergens()|join(', ') }})</span>
                    {% endif %}
                </li>
            {% elseif element.getType() == 'sub-recipe' %}
                <li>
                    <strong>{{ element.getName() }}</strong> ({{ element.getRecipeQuantity() }} {{ element.getUnitName() }})
                    <ul>
                    {% for subElement in element.getElements() %}
                        <li>{{ subElement.getName() }} - {{ subElement.getRequiredQuantity() }} {{ subElement.getUnitName() }}</li>
                    {% endfor %}
                    </ul>
                </li>
            {% endif %}
        {% endfor %}
        </ul>

        <p>Wszystkie alergeny: {{ product.getAllAllergens()|join(', ') }}</p>
    {% endif %}

    {# Opakowanie #}
    {% if product.hasPackage() %}
        <h2>Opakowanie: {{ product.getPackage().getName() }}</h2>
        <p>{{ product.getPackage().getDescription() }}</p>

        <h3>Materiały:</h3>
        <ul>
        {% for material in product.getPackage().getMaterials() %}
            <li>
                {{ material.getName() }} -
                {{ material.getQuantity() }} {{ material.getUnitName() }}
                ({{ material.getBaseUnitPriceGross() }} zł)
            </li>
        {% endfor %}
        </ul>

        <p>Koszt opakowania: {{ product.getPackage().getTotalPriceGross() }} zł</p>
    {% endif %}

    {# Kompetencje #}
    {% if product.hasCompetences() %}
        <h2>Wymagane kompetencje:</h2>
        <ul>
        {% for competence in product.getCompetences() %}
            <li>
                <strong>{{ competence.getName() }}</strong><br>
                {{ competence.getDescription() }}
            </li>
        {% endfor %}
        </ul>
    {% endif %}

    {# Przygotowanie #}
    {% if product.hasPreparation() %}
        <h2>Przygotowanie ({{ product.getTotalPreparationMinutes() }} min):</h2>

        <h3>Typy:</h3>
        <ul>
        {% for type in product.getPreparation().getTypes() %}
            <li>{{ type.getName() }} - {{ type.getDurationMinutes() }} min</li>
        {% endfor %}
        </ul>

        <h3>Kroki:</h3>
        <ol>
        {% for step in product.getPreparation().getSteps() %}
            <li>{{ step.getStepDescription() }}</li>
        {% endfor %}
        </ol>
    {% endif %}
{% endblock %}
*/

// ============================================================================
// PRZYKŁADOWE METODY W SERVICE
// ============================================================================

class ProductService
{
    /**
     * Znajdź produkty wegetariańskie
     */
    public function getVegetarianProducts(): array
    {
        $allProducts = $this->productRepository->getAll();

        return array_filter($allProducts, function($product) {
            return $product->getObject()->getIsVegetarian() === 1;
        });
    }

    /**
     * Pobierz produkty z określonym alergenem
     */
    public function getProductsWithAllergen(string $allergen): array
    {
        $products = [];
        $allProducts = $this->productRepository->getAll();

        foreach ($allProducts as $product) {
            $productDetail = $this->productRepository->getProductDetail($product->getId());

            if (in_array($allergen, $productDetail->getAllAllergens())) {
                $products[] = $productDetail;
            }
        }

        return $products;
    }

    /**
     * Oblicz całkowity koszt produktu (składniki + opakowanie)
     */
    public function calculateTotalCost(int $productId): float
    {
        $product = $this->productRepository->getProductDetail($productId);

        $ingredientsCost = 0;
        if ($product->hasRecipe()) {
            foreach ($product->getRecipe()->getElements() as $element) {
                $ingredientsCost += $element->getCalculatedReqPriceGross();
            }
        }

        $packagingCost = 0;
        if ($product->hasPackage()) {
            $packagingCost = $product->getPackage()->getTotalPriceGross();
        }

        return round($ingredientsCost + $packagingCost, 2);
    }
}

// ============================================================================
// SERIALIZACJA DO JSON (np. dla API endpoint)
// ============================================================================

$product = new ProductDetailModel($apiResponse);

// Automatyczna serializacja dzięki JsonSerializable
$json = json_encode($product, JSON_PRETTY_PRINT);

/*
Zwróci:
{
    "object": {
        "id": 2300004,
        "name": "FlipFlap - Américain Maison",
        ...
    },
    "photos": {
        "main_photo_path": "",
        "photo_1_path": "r2://...",
        ...
    },
    "recipe": {
        "id": 230,
        "elements": [...]
    },
    ...
}
*/

