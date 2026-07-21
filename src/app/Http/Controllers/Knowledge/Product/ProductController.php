<?php

namespace App\Kitchen\app\Http\Controllers\Knowledge\Product;


use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\app\Services\Knowledge\Product\ProductService;
use App\Kitchen\app\Services\Knowledge\Recipe\RecipeService;
use App\Kitchen\core\Support\Route;

class ProductController extends Controller
{

    public function __construct(
        private ProductService $productService
    ) {}

    #[Route('GET', '/knowledge/products')]
    public function index()
    {
        $data['products'] = $this->productService->getAvailableToSale();

        $this->view("knowledge/product/overview", $data);
    }

}