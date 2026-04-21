<?php

namespace App\Kitchen\app\Models\Order;

class OrderProductModel
{
    private ?int $id;
    private ?int $idOrder;
    private ?int $idProduct;
    private ?string $name;
    private ?float $quantity;
    private ?float $weight;
    private ?float $unitGrossPrice;
    private ?float $itemTaxPerc;
    private ?float $itemDiscountValue;
    private ?float $totalGrossValueAfterDiscount;
    private ?float $totalTaxValueAfterDiscount;
    private ?int $quantityPerLabel;
    private ?bool $isPieceBased;

    public function __construct(array $data)
    {
        $this->id = isset($data['id']) ? (int)$data['id'] : null;
        $this->idOrder = isset($data['id_order']) ? (int)$data['id_order'] : null;
        $this->idProduct = isset($data['id_product']) ? (int)$data['id_product'] : null;
        $this->name = $data['name'] ?? null;
        $this->quantity = isset($data['quantity']) ? (float)$data['quantity'] : null;
        $this->weight = isset($data['weight']) ? (float)$data['weight'] : null;
        $this->unitGrossPrice = isset($data['unit_gross_price']) ? (float)$data['unit_gross_price'] : null;
        $this->itemTaxPerc = isset($data['item_tax_perc']) ? (float)$data['item_tax_perc'] : null;
        $this->itemDiscountValue = isset($data['item_discount_value']) ? (float)$data['item_discount_value'] : null;
        $this->totalGrossValueAfterDiscount = isset($data['total_gross_value_after_discount']) ? (float)$data['total_gross_value_after_discount'] : null;
        $this->totalTaxValueAfterDiscount = isset($data['total_tax_value_after_discount']) ? (float)$data['total_tax_value_after_discount'] : null;
        $this->quantityPerLabel = isset($data['quantity_per_label']) ? (int)$data['quantity_per_label'] : null;
        $this->isPieceBased = isset($data['is_piece_based']) ? (bool)$data['is_piece_based'] : null;
    }

    public function getId(): ?int { return $this->id; }
    public function getIdOrder(): ?int { return $this->idOrder; }
    public function getIdProduct(): ?int { return $this->idProduct; }
    public function getName(): ?string { return $this->name; }
    public function getQuantity(): ?float { return $this->quantity; }
    public function getWeight(): ?float { return $this->weight; }
    public function getUnitGrossPrice(): ?float { return $this->unitGrossPrice; }
    public function getItemTaxPerc(): ?float { return $this->itemTaxPerc; }
    public function getItemDiscountValue(): ?float { return $this->itemDiscountValue; }
    public function getTotalGrossValueAfterDiscount(): ?float { return $this->totalGrossValueAfterDiscount; }
    public function getTotalTaxValueAfterDiscount(): ?float { return $this->totalTaxValueAfterDiscount; }
    public function getQuantityPerLabel(): ?int { return $this->quantityPerLabel; }
    public function isPieceBased(): ?bool { return $this->isPieceBased; }
}

