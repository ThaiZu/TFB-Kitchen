<?php

namespace App\Kitchen\app\Http\Controllers\Dashboard;


use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\core\Support\Route;

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