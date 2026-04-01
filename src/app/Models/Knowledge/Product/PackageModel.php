<?php

namespace App\Kitchen\app\Models\Knowledge\Product;

use JsonSerializable;

class PackageModel implements JsonSerializable
{
    private $id_brand;
    private $id;
    private $name;
    private $description;
    private $materials; // Array of PackageMaterialModel

    public function __construct($data)
    {
        $this->id_brand = $data['id_brand'] ?? null;
        $this->id = $data['id'] ?? null;
        $this->name = $data['name'] ?? null;
        $this->description = $data['description'] ?? null;

        $this->materials = [];
        if (isset($data['materials']) && is_array($data['materials'])) {
            foreach ($data['materials'] as $material) {
                $this->materials[] = new PackageMaterialModel($material);
            }
        }
    }

    public function jsonSerialize(): mixed
    {
        return [
            'id_brand' => $this->id_brand,
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'materials' => $this->materials
        ];
    }

    public function getIdBrand() { return $this->id_brand; }
    public function getId() { return $this->id; }
    public function getName() { return $this->name; }
    public function getDescription() { return $this->description; }
    public function getMaterials() { return $this->materials; }
    public function hasMaterials(): bool { return !empty($this->materials); }

    public function getTotalPriceNet(): float
    {
        $total = 0;
        foreach ($this->materials as $material) {
            $total += $material->getBaseUnitPriceNet() * $material->getQuantity();
        }
        return round($total, 2);
    }

    public function getTotalPriceGross(): float
    {
        $total = 0;
        foreach ($this->materials as $material) {
            $total += $material->getBaseUnitPriceGross() * $material->getQuantity();
        }
        return round($total, 2);
    }
}

