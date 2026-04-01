<?php

namespace App\Kitchen\app\Models\Knowledge\Product;

use JsonSerializable;

class CompetenceModel implements JsonSerializable
{
    private $id;
    private $name;
    private $description;

    public function __construct($data)
    {
        $this->id = $data['id'] ?? null;
        $this->name = $data['name'] ?? null;
        $this->description = $data['description'] ?? null;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description
        ];
    }

    public function getId() { return $this->id; }
    public function getName() { return $this->name; }
    public function getDescription() { return $this->description; }
}

