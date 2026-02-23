<?php

namespace App\Supplier\app\Http\Controllers\Me;


use App\Supplier\app\Http\Controllers\Controller;
use App\Supplier\app\Services\Me\SupplierService;
use App\Supplier\core\Support\Route;

class ProfileController extends Controller
{

    public function __construct(
        private SupplierService $supplierService
    ) {}


    #[Route('GET', '/me')]
    public function index()
    {
        $data['supplier'] = $this->supplierService->getMe();

        $this->view("me/about", $data);
    }
}