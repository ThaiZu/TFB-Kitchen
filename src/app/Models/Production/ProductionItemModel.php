<?php

namespace App\Kitchen\app\Models\Production;

use JsonSerializable;

/**
 * Une ligne du plan de production du jour.
 *
 * Contrat d'API : docs/ENDPOINTS_PRODUCTION.md. Les champs facultatifs
 * restent à null quand le serveur ne les fournit pas — la vue les omet
 * plutôt que d'afficher un tiret pour chacun.
 */
class ProductionItemModel implements JsonSerializable
{
    public const STATUS_TODO        = 'TODO';
    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const STATUS_DONE        = 'DONE';
    public const STATUS_CANCELLED   = 'CANCELLED';

    private ?int $id;
    private ?int $idProduct;
    private ?string $name;
    private ?string $categoryName;
    private float $quantityPlanned;
    private float $quantityDone;
    private ?string $unitName;
    private string $status;
    private ?string $slot;
    private ?int $priority;
    private ?string $note;
    private ?string $mainPhotoPath;
    private ?string $startedAt;
    private ?string $completedAt;
    private ?string $completedBy;

    public function __construct(array $d)
    {
        $this->id              = isset($d['id']) ? (int)$d['id'] : null;
        $this->idProduct       = isset($d['id_product']) ? (int)$d['id_product'] : null;
        $this->name            = $d['name'] ?? null;
        $this->categoryName    = $d['category_name'] ?? null;
        $this->quantityPlanned = (float)($d['quantity_planned'] ?? 0);
        $this->quantityDone    = (float)($d['quantity_done'] ?? 0);
        $this->unitName        = $d['unit_name'] ?? null;
        $this->slot            = $d['slot'] ?? null;
        $this->priority        = isset($d['priority']) ? (int)$d['priority'] : null;
        $this->note            = $d['note'] ?? null;
        $this->mainPhotoPath   = $d['main_photo_path'] ?? ($d['photos']['main_photo_path'] ?? null);
        $this->startedAt       = $d['started_at'] ?? null;
        $this->completedAt     = $d['completed_at'] ?? null;
        $this->completedBy     = $d['completed_by'] ?? null;
        $this->status          = self::readStatus($d);
    }

    /**
     * Normalise le statut, et le déduit des quantités s'il manque.
     *
     * Le serveur reste l'autorité — c'est écrit dans le contrat. Mais un plan
     * qui arriverait sans statut donnerait sinon tout « à faire », y compris
     * des lignes déjà terminées.
     */
    private static function readStatus(array $d): string
    {
        $raw = strtoupper((string)($d['status'] ?? ''));
        if (in_array($raw, [self::STATUS_TODO, self::STATUS_IN_PROGRESS, self::STATUS_DONE, self::STATUS_CANCELLED], true)) {
            return $raw;
        }

        $planned = (float)($d['quantity_planned'] ?? 0);
        $done    = (float)($d['quantity_done'] ?? 0);

        if ($planned > 0 && $done >= $planned) {
            return self::STATUS_DONE;
        }
        return $done > 0 ? self::STATUS_IN_PROGRESS : self::STATUS_TODO;
    }

    public function getId(): ?int { return $this->id; }
    public function getIdProduct(): ?int { return $this->idProduct; }
    public function getName(): ?string { return $this->name; }
    public function getCategoryName(): ?string { return $this->categoryName; }
    public function getQuantityPlanned(): float { return $this->quantityPlanned; }
    public function getQuantityDone(): float { return $this->quantityDone; }
    public function getUnitName(): ?string { return $this->unitName; }
    public function getStatus(): string { return $this->status; }
    public function getSlot(): ?string { return $this->slot; }
    public function getPriority(): ?int { return $this->priority; }
    public function getNote(): ?string { return $this->note; }
    public function getStartedAt(): ?string { return $this->startedAt; }
    public function getCompletedAt(): ?string { return $this->completedAt; }
    public function getCompletedBy(): ?string { return $this->completedBy; }

    public function isTodo(): bool { return $this->status === self::STATUS_TODO; }
    public function isInProgress(): bool { return $this->status === self::STATUS_IN_PROGRESS; }
    public function isDone(): bool { return $this->status === self::STATUS_DONE; }
    public function isCancelled(): bool { return $this->status === self::STATUS_CANCELLED; }

    /** Reste à produire, jamais négatif. */
    public function getQuantityRemaining(): float
    {
        return max(0, $this->quantityPlanned - $this->quantityDone);
    }

    /** Avancement en pourcentage, borné à 100. */
    public function getProgressPercent(): int
    {
        if ($this->quantityPlanned <= 0) {
            return $this->isDone() ? 100 : 0;
        }
        return (int)min(100, round($this->quantityDone / $this->quantityPlanned * 100));
    }

    public function hasMainPhoto(): bool { return !empty($this->mainPhotoPath); }

    /** Même résolution r2:// que les photos produit. */
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
            'id' => $this->id,
            'id_product' => $this->idProduct,
            'name' => $this->name,
            'category_name' => $this->categoryName,
            'quantity_planned' => $this->quantityPlanned,
            'quantity_done' => $this->quantityDone,
            'unit_name' => $this->unitName,
            'status' => $this->status,
            'slot' => $this->slot,
            'priority' => $this->priority,
            'note' => $this->note,
            'main_photo_path' => $this->mainPhotoPath,
            'started_at' => $this->startedAt,
            'completed_at' => $this->completedAt,
            'completed_by' => $this->completedBy,
        ];
    }
}
