<?php

namespace App\Supplier\app\Http\Controllers\Dashboard;


use App\Supplier\app\Http\Controllers\Controller;
use App\Supplier\core\Support\Route;

class DashboardController extends Controller
{

    public function __construct(

    ) {}

    #[Route('GET', '/dashboard')]
    public function index()
    {

        $this->view("dashboard/dashboard");
    }
}