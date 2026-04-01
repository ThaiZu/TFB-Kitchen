<?php

namespace App\Kitchen\app\Http\Controllers\Auth;


use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\app\Http\Requests\LoginRequest;
use App\Kitchen\app\Services\Auth\AuthGuard;
use App\Kitchen\app\Services\Auth\AuthService;
use App\Kitchen\app\Services\Shop\ShopService;
use App\Kitchen\core\Support\Route;

class AuthController extends Controller
{

    public function __construct(
        private AuthService $authService,
        private AuthGuard $guard,
        private ShopService $shopService

    ) {}

    #[Route('GET', '/auth')]
    public function index() {

        $data['shops'] = $this->shopService->getAll();
        if ($this->guard->ensureAccessToken()) {
            redirect("/dashboard");
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $this->errors = LoginRequest::validateLogin($_POST);

            if (!empty($this->errors)) {
                $this->view("auth/login", $data);
            }

            $logged = $this->authService->login($_POST);

            if ($logged) {
                redirect("/dashboard");
            } else {
                $this->errors["invalid_credentials"] = "error_invalid_credentials";
            }
        }

        $this->view("auth/login", $data);
    }


    #[Route('GET', '/logout')]
    public function logout() {
        $this->authService->logout();
        redirect('/auth');
    }

}