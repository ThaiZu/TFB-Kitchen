<?php

namespace App\Kitchen\app\Http\Controllers\Knowledge\Recipe;


use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\core\Support\Route;

class TechnicalSheetController extends Controller
{

    public function __construct(

    ) {}

    #[Route('GET', '/knowledge/recipes/{id:\d+}')]
    public function show($id)
    {


        $this->view("knowledge/recipe/show");
    }
}