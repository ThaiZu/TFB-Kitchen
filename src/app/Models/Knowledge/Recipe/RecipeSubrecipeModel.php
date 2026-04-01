<?php

namespace App\Kitchen\app\Models\Knowledge\Recipe;

use JsonSerializable;

class RecipeSubrecipeModel implements JsonSerializable
{
    private $recipe_id;
    private $name;
    private $type;
    private $is_part_of_package;
    private $recipe_quantity;
    private $yield_quantity;
    private $id_unit;
    private $unit_name;
    private $elements; // Array of RecipeIngredientModel or RecipeSubrecipeModel

    public function __construct($data)
    {
        $this->recipe_id = $data['recipe_id'] ?? null;
        $this->name = $data['name'] ?? null;
        $this->type = $data['type'] ?? 'sub-recipe';
        $this->is_part_of_package = $data['is_part_of_package'] ?? 0;
        $this->recipe_quantity = $data['recipe_quantity'] ?? null;
        $this->yield_quantity = $data['yield_quantity'] ?? null;
        $this->id_unit = $data['id_unit'] ?? null;
        $this->unit_name = $data['unit_name'] ?? null;

        // Parse nested elements
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
            'recipe_id' => $this->recipe_id,
            'name' => $this->name,
            'type' => $this->type,
            'is_part_of_package' => $this->is_part_of_package,
            'recipe_quantity' => $this->recipe_quantity,
            'yield_quantity' => $this->yield_quantity,
            'id_unit' => $this->id_unit,
            'unit_name' => $this->unit_name,
            'elements' => $this->elements
        ];
    }

    public function getRecipeId() { return $this->recipe_id; }
    public function getName() { return $this->name; }
    public function getType() { return $this->type; }
    public function getIsPartOfPackage() { return $this->is_part_of_package; }
    public function getRecipeQuantity() { return $this->recipe_quantity; }
    public function getYieldQuantity() { return $this->yield_quantity; }
    public function getIdUnit() { return $this->id_unit; }
    public function getUnitName() { return $this->unit_name; }
    public function getElements() { return $this->elements; }
    public function hasElements(): bool { return !empty($this->elements); }
}

