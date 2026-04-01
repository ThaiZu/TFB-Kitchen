<?php

namespace App\Kitchen\app\Models\Knowledge\Product;

use JsonSerializable;

class PackageMaterialModel implements JsonSerializable
{
    private $id_brand;
    private $id;
    private $name;
    private $id_category;
    private $id_unit;
    private $is_part_of_package;
    private $waste_amount_perc;
    private $source_type;
    private $kind;
    private $quantity;
    private $base_unit_price_net;
    private $base_unit_price_gross;
    private $unit_name;

    public function __construct($data)
    {
        $this->id_brand = $data['id_brand'] ?? null;
        $this->id = $data['id'] ?? null;
        $this->name = $data['name'] ?? null;
        $this->id_category = $data['id_category'] ?? null;
        $this->id_unit = $data['id_unit'] ?? null;
        $this->is_part_of_package = $data['is_part_of_package'] ?? 0;
        $this->waste_amount_perc = $data['waste_amount_perc'] ?? 0;
        $this->source_type = $data['source_type'] ?? null;
        $this->kind = $data['kind'] ?? null;
        $this->quantity = $data['quantity'] ?? null;
        $this->base_unit_price_net = $data['base_unit_price_net'] ?? 0;
        $this->base_unit_price_gross = $data['base_unit_price_gross'] ?? 0;
        $this->unit_name = $data['unit_name'] ?? null;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'id_brand' => $this->id_brand,
            'id' => $this->id,
            'name' => $this->name,
            'id_category' => $this->id_category,
            'id_unit' => $this->id_unit,
            'is_part_of_package' => $this->is_part_of_package,
            'waste_amount_perc' => $this->waste_amount_perc,
            'source_type' => $this->source_type,
            'kind' => $this->kind,
            'quantity' => $this->quantity,
            'base_unit_price_net' => $this->base_unit_price_net,
            'base_unit_price_gross' => $this->base_unit_price_gross,
            'unit_name' => $this->unit_name
        ];
    }

    public function getIdBrand() { return $this->id_brand; }
    public function getId() { return $this->id; }
    public function getName() { return $this->name; }
    public function getIdCategory() { return $this->id_category; }
    public function getIdUnit() { return $this->id_unit; }
    public function getIsPartOfPackage() { return $this->is_part_of_package; }
    public function getWasteAmountPerc() { return $this->waste_amount_perc; }
    public function getSourceType() { return $this->source_type; }
    public function getKind() { return $this->kind; }
    public function getQuantity() { return $this->quantity; }
    public function getBaseUnitPriceNet() { return $this->base_unit_price_net; }
    public function getBaseUnitPriceGross() { return $this->base_unit_price_gross; }
    public function getUnitName() { return $this->unit_name; }
}

