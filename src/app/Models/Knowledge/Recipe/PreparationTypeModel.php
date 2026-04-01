<?php

namespace App\Kitchen\app\Models\Knowledge\Recipe;

use JsonSerializable;

class PreparationTypeModel implements JsonSerializable
{
    private $id;
    private $name;
    private $duration_second;

    public function __construct($data)
    {
        $this->id = $data['id'] ?? null;
        $this->name = $data['name'] ?? null;
        $this->duration_second = $data['duration_second'] ?? null;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'duration_second' => $this->duration_second
        ];
    }

    public function getId() { return $this->id; }
    public function getName() { return $this->name; }
    public function getDurationSecond() { return $this->duration_second; }
    public function getDurationMinutes(): ?float
    {
        return $this->duration_second ? round($this->duration_second / 60, 1) : null;
    }
}

