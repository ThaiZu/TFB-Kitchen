<?php

namespace App\Kitchen\app\Models\Knowledge\Recipe;

use JsonSerializable;

class RecipeModel implements JsonSerializable
{
    private $id;
    private $name;
    private $is_subrecipe;
    private $yield_quantity;
    private $id_unit;
    private $elements; // Array of RecipeIngredientModel or RecipeSubrecipeModel

    public function __construct($data)
    {
        $this->id = $data['id'] ?? null;
        $this->name = $data['name'] ?? null;
        $this->is_subrecipe = $data['is_subrecipe'] ?? 0;
        $this->yield_quantity = $data['yield_quantity'] ?? null;
        $this->id_unit = $data['id_unit'] ?? null;

        // Parse elements (ingredients and sub-recipes)
        $this->elements = [];
        if (isset($data['elements']) && is_array($data['elements'])) {
            foreach ($data['elements'] as $element) {
                if (isset($element['type'])) {
                    if ($element['type'] === 'ingredient') {
                        $this->elements[] = new RecipeIngredientModel($element);
                    } elseif ($element['type'] === 'sub-recipe') {
                        $this->elements[] = new RecipeSubrecipeModel($element);
                    }
                }
            }
        }
    }

    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_subrecipe' => $this->is_subrecipe,
            'yield_quantity' => $this->yield_quantity,
            'id_unit' => $this->id_unit,
            'elements' => $this->elements
        ];
    }

    public function getId() { return $this->id; }
    public function getName() { return $this->name; }
    public function getIsSubrecipe() { return $this->is_subrecipe; }
    public function getYieldQuantity() { return $this->yield_quantity; }
    public function getIdUnit() { return $this->id_unit; }
    public function getElements() { return $this->elements; }
    public function hasElements(): bool { return !empty($this->elements); }

    /**
     * Get all allergens from all ingredients (recursively)
     */
    public function getAllAllergens(): array
    {
        $allergens = [];
        foreach ($this->elements as $element) {
            if ($element instanceof RecipeIngredientModel) {
                $allergens = array_merge($allergens, $element->getAllergens());
            } elseif ($element instanceof RecipeSubrecipeModel) {
                // Recursively get allergens from sub-recipes
                foreach ($element->getElements() as $subElement) {
                    if ($subElement instanceof RecipeIngredientModel) {
                        $allergens = array_merge($allergens, $subElement->getAllergens());
                    }
                }
            }
        }
        return array_unique($allergens);
    }
}

