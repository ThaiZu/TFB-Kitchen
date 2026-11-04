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
        header('Cache-Control: private, no-store');
        $data=['products'=>[],'catalog_unavailable'=>false];
        try{$data['products']=$this->productService->getAvailableToSale(['include'=>['kitchen_cards']]);}
        catch(\Throwable $error){error_log('Kitchen product catalogue unavailable: '.get_class($error).' ('.$error->getCode().')');$data['catalog_unavailable']=true;}

        $this->view("knowledge/product/overview", $data);
    }

}