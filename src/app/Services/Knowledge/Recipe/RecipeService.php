<?php
namespace App\Kitchen\app\Services\Knowledge\Recipe;

use App\Kitchen\app\Repositories\Knowledge\Recipe\RecipeRepository;
use App\Kitchen\app\Repositories\Me\DeviceRepository;
use App\Kitchen\core\Support\GlobalRegistry;

class RecipeService
{
    public function __construct(
        private RecipeRepository $recipeRepository
    )
    {
    }

    public function getAll()
    {
        return $this->recipeRepository->getAll(GlobalRegistry::get('lang_code'));
    }
}