<?php

namespace App\Kitchen\app\Models\Knowledge\Recipe;

use JsonSerializable;

class RecipeIngredientModel implements JsonSerializable
{
    private $ingredient_id;
    private $name;
    private $type;
    private $is_part_of_package;
    private $recipe_quantity;
    private $required_quantity;
    private $calculated_req_price_net;
    private $calculated_req_price_gross;
    private $id_unit;
    private $unit_name;
    private $allergens;

    public function __construct($data)
    {
        $this->ingredient_id = $data['ingredient_id'] ?? null;
        $this->name = $data['name'] ?? null;
        $this->type = $data['type'] ?? 'ingredient';
        $this->is_part_of_package = $data['is_part_of_package'] ?? 0;
        $this->recipe_quantity = $data['recipe_quantity'] ?? null;
        $this->required_quantity = $data['required_quantity'] ?? null;
        $this->calculated_req_price_net = $data['calculated_req_price_net'] ?? 0;
        $this->calculated_req_price_gross = $data['calculated_req_price_gross'] ?? 0;
        $this->id_unit = $data['id_unit'] ?? null;
        $this->unit_name = $data['unit_name'] ?? null;
        $this->allergens = $data['allergens'] ?? [];
    }

    public function jsonSerialize(): mixed
    {
        return [
            'ingredient_id' => $this->ingredient_id,
            'name' => $this->name,
            'type' => $this->type,
            'is_part_of_package' => $this->is_part_of_package,
            'recipe_quantity' => $this->recipe_quantity,
            'required_quantity' => $this->required_quantity,
            'calculated_req_price_net' => $this->calculated_req_price_net,
            'calculated_req_price_gross' => $this->calculated_req_price_gross,
            'id_unit' => $this->id_unit,
            'unit_name' => $this->unit_name,
            'allergens' => $this->allergens
        ];
    }

    public function getIngredientId() { return $this->ingredient_id; }
    public function getName() { return $this->name; }
    public function getType() { return $this->type; }
    public function getIsPartOfPackage() { return $this->is_part_of_package; }
    public function getRecipeQuantity() { return $this->recipe_quantity; }
    public function getRequiredQuantity() { return $this->required_quantity; }
    public function getCalculatedReqPriceNet() { return $this->calculated_req_price_net; }
    public function getCalculatedReqPriceGross() { return $this->calculated_req_price_gross; }
    public function getIdUnit() { return $this->id_unit; }
    public function getUnitName() { return $this->unit_name; }
    public function getAllergens() { return $this->allergens; }
    public function hasAllergens(): bool { return !empty($this->allergens); }
}

