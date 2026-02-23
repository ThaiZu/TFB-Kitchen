<?php
namespace App\Supplier\app\Services\Helpers;


use App\Supplier\app\Repositories\Helpers\AllergenRepository;

class AllergenService
{
    public function __construct(
        private AllergenRepository $allergenRepository
    )
    {
    }

    public function getAll()
    {
        return $this->allergenRepository->getAll();
    }
}