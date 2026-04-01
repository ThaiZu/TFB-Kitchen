<?php

namespace App\Kitchen\app\Models\Knowledge\Recipe;

use JsonSerializable;

class PreparationModel implements JsonSerializable
{
    private $types; // Array of PreparationTypeModel
    private $steps; // Array of PreparationStepModel

    public function __construct($data)
    {
        $this->types = [];
        if (isset($data['types']) && is_array($data['types'])) {
            foreach ($data['types'] as $type) {
                $this->types[] = new PreparationTypeModel($type);
            }
        }

        $this->steps = [];
        if (isset($data['steps']) && is_array($data['steps'])) {
            foreach ($data['steps'] as $step) {
                $this->steps[] = new PreparationStepModel($step);
            }
        }
    }

    public function jsonSerialize(): mixed
    {
        return [
            'types' => $this->types,
            'steps' => $this->steps
        ];
    }

    public function getTypes() { return $this->types; }
    public function getSteps() { return $this->steps; }
    public function hasTypes(): bool { return !empty($this->types); }
    public function hasSteps(): bool { return !empty($this->steps); }

    public function getTotalDurationSeconds(): int
    {
        $total = 0;
        foreach ($this->types as $type) {
            $total += $type->getDurationSecond() ?? 0;
        }
        return $total;
    }

    public function getTotalDurationMinutes(): float
    {
        return round($this->getTotalDurationSeconds() / 60, 1);
    }
}

