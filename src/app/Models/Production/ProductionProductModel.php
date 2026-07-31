<?php

namespace App\Kitchen\app\Models\Production;

use JsonSerializable;

/**
 * Un produit du catalogue de production : sa catégorie, ses périodes, sa
 * taille de fournée.
 *
 * Distinct de Knowledge\Product\ProductModel, qui décrit la fiche technique
 * (allergènes, conservation, réchauffe). Ici on ne retient que ce qui sert à
 * décider quoi enfourner.
 */
class ProductionProductModel implements JsonSerializable
{
    private ?int $idProduct;
    private ?string $name;
    private ?int $idCategory;
    private ?string $categoryName;
    /** @var string[] */
    private array $periods;
    private ?float $batchSize;
    private ?string $unitName;
    private int $leadMinutes;
    private bool $isActive;
    private ?string $mainPhotoPath;

    public function __construct(array $d)
    {
        $this->idProduct    = isset($d['id_product']) ? (int)$d['id_product'] : (isset($d['id']) ? (int)$d['id'] : null);
        $this->name         = $d['name'] ?? null;
        $this->idCategory   = isset($d['id_category']) ? (int)$d['id_category'] : null;
        $this->categoryName = $d['category_name'] ?? null;
        $this->periods      = self::readPeriods($d);
        // batch_size absent : le produit se traite à l'unité. La vue le
        // signale, parce qu'une proposition « 17 pièces » sur un four qui sort
        // des plaques de 24 n'est pas exécutable.
        $this->batchSize    = isset($d['batch_size']) && (float)$d['batch_size'] > 0 ? (float)$d['batch_size'] : null;
        $this->unitName     = $d['unit_name'] ?? null;
        $this->leadMinutes  = max(0, (int)($d['production_lead_minutes'] ?? 0));
        $this->isActive     = !isset($d['is_active']) || (bool)$d['is_active'];
        $this->mainPhotoPath = $d['main_photo_path'] ?? ($d['photos']['main_photo_path'] ?? null);
    }

    /**
     * Accepte « periods: [...] » comme « period: "morning" » : un produit
     * mono-période reste plus naturel à écrire au singulier côté serveur.
     *
     * @return string[]
     */
    private static function readPeriods(array $d): array
    {
        $raw = $d['periods'] ?? $d['period'] ?? [];
        if (is_string($raw)) {
            $raw = [$raw];
        }
        if (!is_array($raw)) {
            return [];
        }
        return array_values(array_filter(array_map(
            fn($p) => is_string($p) ? strtolower(trim($p)) : null,
            $raw
        )));
    }

    public function getIdProduct(): ?int { return $this->idProduct; }
    public function getName(): ?string { return $this->name; }
    public function getIdCategory(): ?int { return $this->idCategory; }
    public function getCategoryName(): ?string { return $this->categoryName; }
    /** @return string[] */
    public function getPeriods(): array { return $this->periods; }
    public function getBatchSize(): ?float { return $this->batchSize; }
    /** Batch utilisable pour un calcul : 1 quand le serveur ne le donne pas. */
    public function getEffectiveBatchSize(): float { return $this->batchSize ?? 1.0; }
    public function hasBatchSize(): bool { return $this->batchSize !== null; }
    public function getUnitName(): ?string { return $this->unitName; }
    public function getLeadMinutes(): int { return $this->leadMinutes; }
    public function isActive(): bool { return $this->isActive; }

    public function belongsTo(string $periodKey): bool
    {
        return in_array(strtolower($periodKey), $this->periods, true);
    }

    public function hasMainPhoto(): bool { return !empty($this->mainPhotoPath); }

    public function getMainPhotoUrl(): ?string
    {
        if (empty($this->mainPhotoPath)) {
            return null;
        }
        if (str_starts_with($this->mainPhotoPath, 'r2://')) {
            return rtrim(SHARED_FILES_URL, '/') . '/' . ltrim(substr($this->mainPhotoPath, 5), '/');
        }
        return $this->mainPhotoPath;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'id_product' => $this->idProduct,
            'name' => $this->name,
            'id_category' => $this->idCategory,
            'category_name' => $this->categoryName,
            'periods' => $this->periods,
            'batch_size' => $this->batchSize,
            'unit_name' => $this->unitName,
            'production_lead_minutes' => $this->leadMinutes,
            'is_active' => $this->isActive,
            'main_photo_path' => $this->mainPhotoPath,
        ];
    }
}
