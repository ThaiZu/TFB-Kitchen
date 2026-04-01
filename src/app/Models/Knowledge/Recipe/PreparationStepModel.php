<?php

namespace App\Kitchen\app\Models\Knowledge\Recipe;

use JsonSerializable;

class PreparationStepModel implements JsonSerializable
{
    private $step_number;
    private $step_description;

    public function __construct($data)
    {
        $this->step_number = $data['step_number'] ?? null;
        $this->step_description = $data['step_description'] ?? null;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'step_number' => $this->step_number,
            'step_description' => $this->step_description
        ];
    }

    public function getStepNumber() { return $this->step_number; }
    public function getStepDescription() { return $this->step_description; }
}


