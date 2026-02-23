<?php

namespace App\Supplier\app\Http\Controllers\Auth;


use App\Supplier\app\Http\Controllers\Controller;
use App\Supplier\app\Http\Requests\LoginRequest;
use App\Supplier\app\Services\Auth\AuthGuard;
use App\Supplier\app\Services\Auth\AuthService;
use App\Supplier\core\Support\Route;

class AuthController extends Controller
{

    public function __construct(
        private AuthService $authService,
        private AuthGuard $guard

    ) {}

    #[Route('GET', '/auth')]
    public function index() {

        if ($this->guard->ensureAccessToken()) {
            redirect("/dashboard");
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $this->errors = LoginRequest::validateLogin($_POST);
            if (!empty($this->errors)) {
                $this->view("auth/login");
            }

            $logged = $this->authService->login($_POST);

            if ($logged) {
                redirect("/dashboard");
            } else {
                $this->errors["invalid_credentials"] = "Invalid login or password.";
            }
        }

        $this->view("auth/login");
    }


    #[Route('GET', '/logout')]
    public function logout() {
        $this->authService->logout();
        redirect('/auth');
    }

}