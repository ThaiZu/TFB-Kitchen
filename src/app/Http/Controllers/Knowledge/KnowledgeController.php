<?php

namespace App\Kitchen\app\Http\Controllers\Knowledge;


use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\app\Services\Knowledge\Recipe\RecipeService;
use App\Kitchen\core\Support\Route;

class KnowledgeController extends Controller
{

    public function __construct(
    ) {}

    #[Route('GET', '/knowledge')]
    public function index()
    {

        $this->view("knowledge/overview", []);
    }
}