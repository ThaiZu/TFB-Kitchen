<?php

namespace App\Kitchen\app\Http\Controllers\Me;


use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\app\Services\Me\KitchenService;
use App\Kitchen\core\Support\Route;

class ProfileController extends Controller
{

    public function __construct(
        private KitchenService $supplierService
    ) {}


    #[Route('GET', '/me')]
    public function index()
    {
        $data['supplier'] = $this->supplierService->getMe();

        $this->view("me/about", $data);
    }
}