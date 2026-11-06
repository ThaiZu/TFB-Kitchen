<?php

namespace App\Kitchen\app\Http\Controllers\Auth;


use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\app\Http\Requests\LoginRequest;
use App\Kitchen\app\Services\Auth\AuthGuard;
use App\Kitchen\app\Services\Auth\SessionRefreshUnavailable;
use App\Kitchen\app\Services\Auth\SessionResponse;
use App\Kitchen\app\Services\Auth\AuthService;
use App\Kitchen\app\Services\Shop\ShopService;
use App\Kitchen\core\Support\DeviceMode;
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

        // Une tablette de comptoir doit se rallumer sur son back-office, pas
        // sur le tableau de bord cuisine : l'accueil dépend du mode.
        $home = DeviceMode::rules()->home(DeviceMode::current());

        try {
            $authenticated = $this->guard->ensureAccessToken();
        } catch (SessionRefreshUnavailable) {
            SessionResponse::create($_SERVER, true, ROOT)->send();
            return;
        }
        if ($authenticated) {
            redirect($home);
            return;
        }

        $data['shops']=$this->shopService->getAll();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $this->errors = LoginRequest::validateLogin($_POST);

            if (!empty($this->errors)) {
                $this->view("auth/login", $data);
            }

            $logged = $this->authService->login($_POST);

            if ($logged) {
                redirect($home);
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