<?php

namespace App\Kitchen\app\Models\Baking;

use JsonSerializable;

/**
 * Une fournée : un produit, une quantité, et trois étapes qui se suivent —
 * préparation, cuisson, finition — puis un délai avant la mise en rayon.
 *
 * Contrat d'API : docs/ENDPOINTS_CUISSON.md.
 *
 * Le statut fait autorité, pas l'horloge. Une fournée dont l'heure de cuisson
 * est passée mais qui est encore en préparation reste en préparation : le
 * planning est une intention, le statut est un fait.
 */
class BakingBatchModel implements JsonSerializable
{
    public const STATUS_PLANNED       = 'PLANNED';
    public const STATUS_PREPARING     = 'PREPARING';
    public const STATUS_READY_TO_BAKE = 'READY_TO_BAKE';
    public const STATUS_BAKING        = 'BAKING';
    public const STATUS_FINISHING     = 'FINISHING';
    public const STATUS_DONE          = 'DONE';

    public const STAGE_PREP   = 'prep';
    public const STAGE_COOK   = 'cook';
    public const STAGE_FINISH = 'finish';
    public const STAGE_DONE   = 'done';

    public const FINISH_LOT   = 'LOT';
    public const FINISH_PIECE = 'PIECE';

    private ?int $id;
    private ?int $idProduct;
    private ?string $name;
    private ?string $categoryName;
    private float $quantity;
    private ?string $unitName;
    private ?int $idOven;
    private ?string $ovenName;
    private ?int $temperature;

    private int $prepStart;      // minutes depuis minuit
    private int $prepMinutes;
    private int $cookStart;
    private int $cookMinutes;

    private string $finishType;
    private ?string $finishLabel;
    private float $finishFixedMinutes;
    private float $finishPerPieceMinutes;
    private int $shelfDelayMinutes;

    private string $status;
    private ?string $prepStartedAt;
    private ?string $cookStartedAt;
    private ?string $finishStartedAt;

    public function __construct(array $d)
    {
        $this->id           = isset($d['id']) ? (int)$d['id'] : null;
        $this->idProduct    = isset($d['id_product']) ? (int)$d['id_product'] : null;
        $this->name         = $d['name'] ?? null;
        $this->categoryName = $d['category_name'] ?? null;
        $this->quantity     = (float)($d['quantity'] ?? 0);
        $this->unitName     = $d['unit_name'] ?? null;
        $this->idOven       = isset($d['id_oven']) ? (int)$d['id_oven'] : null;
        $this->ovenName     = $d['oven_name'] ?? null;
        $this->temperature  = isset($d['temperature']) ? (int)$d['temperature'] : null;

        $this->prepStart   = self::toMinutes($d['prep_start'] ?? null);
        $this->prepMinutes = max(0, (int)($d['prep_minutes'] ?? 0));
        $this->cookStart   = self::toMinutes($d['cook_start'] ?? null);
        $this->cookMinutes = max(0, (int)($d['cook_minutes'] ?? 0));

        $this->finishType            = strtoupper((string)($d['finish_type'] ?? self::FINISH_LOT)) === self::FINISH_PIECE
            ? self::FINISH_PIECE
            : self::FINISH_LOT;
        $this->finishLabel           = $d['finish_label'] ?? null;
        $this->finishFixedMinutes    = (float)($d['finish_minutes'] ?? 0);
        $this->finishPerPieceMinutes = (float)($d['finish_per_piece_minutes'] ?? 0);
        $this->shelfDelayMinutes     = max(0, (int)($d['shelf_delay_minutes'] ?? 0));

        $this->status          = self::readStatus($d);
        $this->prepStartedAt   = $d['prep_started_at'] ?? null;
        $this->cookStartedAt   = $d['cook_started_at'] ?? null;
        $this->finishStartedAt = $d['finish_started_at'] ?? null;
    }

    /** « 6:40 », « 06:40 » et « 06:40:00 » désignent la même minute. */
    public static function toMinutes(?string $t): int
    {
        if ($t !== null && preg_match('/^(\d{1,2}):(\d{2})/', $t, $m)) {
            return (int)$m[1] * 60 + (int)$m[2];
        }
        return 0;
    }

    public static function toClock(int $minutes): string
    {
        $minutes = max(0, $minutes);
        return sprintf('%02d:%02d', intdiv($minutes, 60) % 24, $minutes % 60);
    }

    private static function readStatus(array $d): string
    {
        $raw = strtoupper((string)($d['status'] ?? ''));
        $all = [
            self::STATUS_PLANNED, self::STATUS_PREPARING, self::STATUS_READY_TO_BAKE,
            self::STATUS_BAKING, self::STATUS_FINISHING, self::STATUS_DONE,
        ];
        return in_array($raw, $all, true) ? $raw : self::STATUS_PLANNED;
    }

    // ── Identité ─────────────────────────────────────────────────────────
    public function getId(): ?int { return $this->id; }
    public function getIdProduct(): ?int { return $this->idProduct; }
    public function getName(): ?string { return $this->name; }
    public function getCategoryName(): ?string { return $this->categoryName; }
    public function getQuantity(): float { return $this->quantity; }
    public function getUnitName(): ?string { return $this->unitName; }
    public function getOvenName(): ?string { return $this->ovenName; }
    public function getTemperature(): ?int { return $this->temperature; }
    public function getStatus(): string { return $this->status; }

    // ── Créneaux planifiés ───────────────────────────────────────────────
    public function getPrepStart(): int { return $this->prepStart; }
    public function getPrepEnd(): int { return $this->prepStart + $this->prepMinutes; }
    public function getCookStart(): int { return $this->cookStart; }
    public function getCookEnd(): int { return $this->cookStart + $this->cookMinutes; }
    public function getFinishStart(): int { return $this->getCookEnd(); }

    /**
     * Durée de finition.
     *
     * Au lot, c'est une constante. À la pièce, elle suit la quantité — et
     * c'est toute la différence : une fournée nappée de 36 éclairs mobilise
     * quelqu'un 36 minutes, pas 10.
     */
    public function getFinishMinutes(): float
    {
        if ($this->finishType === self::FINISH_PIECE) {
            return $this->quantity * $this->finishPerPieceMinutes;
        }
        return $this->finishFixedMinutes;
    }

    public function getFinishEnd(): int { return (int)round($this->getFinishStart() + $this->getFinishMinutes()); }
    public function isFinishPerPiece(): bool { return $this->finishType === self::FINISH_PIECE; }
    public function getFinishPerPieceMinutes(): float { return $this->finishPerPieceMinutes; }
    public function getFinishLabel(): ?string { return $this->finishLabel; }
    public function getShelfDelayMinutes(): int { return $this->shelfDelayMinutes; }

    /** L'heure qui compte pour le client : quand le produit est vendable. */
    public function getShelfTime(): int { return $this->getFinishEnd() + $this->shelfDelayMinutes; }
    public function getShelfClock(): string { return self::toClock($this->getShelfTime()); }

    public function getPrepStartClock(): string { return self::toClock($this->prepStart); }
    public function getCookStartClock(): string { return self::toClock($this->cookStart); }
    public function getFinishStartClock(): string { return self::toClock($this->getFinishStart()); }

    // ── Étape en cours ───────────────────────────────────────────────────
    public function getStage(): string
    {
        return match ($this->status) {
            self::STATUS_BAKING    => self::STAGE_COOK,
            self::STATUS_FINISHING => self::STAGE_FINISH,
            self::STATUS_DONE      => self::STAGE_DONE,
            default                => self::STAGE_PREP,
        };
    }

    public function isDone(): bool { return $this->status === self::STATUS_DONE; }
    /** Rien n'a commencé : la fournée est affichée en retrait. */
    public function isWaiting(): bool { return $this->status === self::STATUS_PLANNED; }
    /** Préparation finie, la fournée attend un four. */
    public function isReadyToBake(): bool { return $this->status === self::STATUS_READY_TO_BAKE; }

    /** Fin planifiée de l'étape en cours. */
    public function getStageEnd(): int
    {
        return match ($this->getStage()) {
            self::STAGE_COOK   => $this->getCookEnd(),
            self::STAGE_FINISH => $this->getFinishEnd(),
            // Préparation terminée : l'échéance qui compte devient l'enfournement.
            default => $this->isReadyToBake() ? $this->cookStart : $this->getPrepEnd(),
        };
    }

    public function getStageEndClock(): string { return self::toClock($this->getStageEnd()); }

    private function getStageStart(): int
    {
        return match ($this->getStage()) {
            self::STAGE_COOK   => $this->cookStart,
            self::STAGE_FINISH => $this->getFinishStart(),
            default => $this->isReadyToBake() ? $this->getPrepEnd() : $this->prepStart,
        };
    }

    /**
     * Avancement de l'étape en cours, borné à [0, 100].
     *
     * Assis sur le créneau planifié : sans horodatage réel servi par l'API,
     * c'est une indication, pas une mesure. La barre l'assume plutôt que de
     * rester vide.
     */
    public function getProgressPercent(int $nowMinutes): int
    {
        $start = $this->getStageStart();
        $end   = $this->getStageEnd();
        if ($end <= $start) {
            return $nowMinutes >= $end ? 100 : 0;
        }
        return (int)max(0, min(100, round(($nowMinutes - $start) / ($end - $start) * 100)));
    }

    /** Statut à demander pour faire avancer la fournée d'une étape. */
    public function getNextStatus(): ?string
    {
        return match ($this->status) {
            self::STATUS_PLANNED       => self::STATUS_PREPARING,
            self::STATUS_PREPARING     => self::STATUS_READY_TO_BAKE,
            self::STATUS_READY_TO_BAKE => self::STATUS_BAKING,
            self::STATUS_BAKING        => self::STATUS_FINISHING,
            self::STATUS_FINISHING     => self::STATUS_DONE,
            default                    => null,
        };
    }

    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->id,
            'id_product' => $this->idProduct,
            'name' => $this->name,
            'category_name' => $this->categoryName,
            'quantity' => $this->quantity,
            'unit_name' => $this->unitName,
            'id_oven' => $this->idOven,
            'oven_name' => $this->ovenName,
            'temperature' => $this->temperature,
            'prep_start' => self::toClock($this->prepStart),
            'prep_minutes' => $this->prepMinutes,
            'cook_start' => self::toClock($this->cookStart),
            'cook_minutes' => $this->cookMinutes,
            'finish_type' => $this->finishType,
            'finish_label' => $this->finishLabel,
            'finish_minutes' => $this->getFinishMinutes(),
            'finish_per_piece_minutes' => $this->finishPerPieceMinutes,
            'shelf_delay_minutes' => $this->shelfDelayMinutes,
            'shelf_time' => $this->getShelfClock(),
            'status' => $this->status,
            'stage' => $this->getStage(),
        ];
    }
}
