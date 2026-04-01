<?php

namespace App\Kitchen\app\Http\Controllers\Knowledge\Recipe;


use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\app\Services\Knowledge\Recipe\RecipeService;
use App\Kitchen\core\Support\Route;

class RecipeController extends Controller
{

    public function __construct(
        private RecipeService $recipeService
    ) {}

    #[Route('GET', '/knowledge/recipes')]
    public function index()
    {
        $data['recipes'] = $this->recipeService->getAll();

        $this->view("knowledge/recipe/overview", $data);
    }
}