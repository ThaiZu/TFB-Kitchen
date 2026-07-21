<?php

namespace App\Kitchen\app\Http\Controllers\Client;

use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\app\Services\Client\ClientService;
use App\Kitchen\core\Support\Route;
use Symfony\Component\HttpFoundation\Request;

class ClientController extends Controller
{
    public function __construct(
        private ClientService $clientService
    ) {}

    #[Route('GET', '/clients')]
    public function index(): void
    {
        header('Location: ' . ROOT . '/orders/new', true, 302);
        exit;
    }

    #[Route('POST', '/ajax/clients')]
    public function ajaxInsert(): void
    {
        $request = Request::createFromGlobals();
        $data = $this->getJson($request);


        if (empty($data)) {
            $response = $this->json(['status' => 'error', 'description' => 'Brak danych klienta.'], 400);
            $response->send();
            exit;
        }

        $response = $this->clientService->insert($data);
        $status = (int)($response['code'] ?? 400);

        $json = $this->json($response, $status);
        $json->send();
        exit;
    }
}
