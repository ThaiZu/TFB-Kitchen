<?php

namespace App\Kitchen\app\Http\Controllers\Order;

use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\app\Services\Order\OrderService;
use App\Kitchen\core\Support\Route;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService
    ) {}

    /**
     * Lista zamówień dla wybranego dnia.
     * GET /orders[?date=Y-m-d&client_name=...&pending_only=0]
     */
    #[Route('GET', '/orders')]
    public function index(): void
    {
        $date        = $_GET['date']        ?? date('Y-m-d');
        $clientName  = trim($_GET['client_name'] ?? '');
        $pendingOnly = !isset($_GET['pending_only']) || (int)$_GET['pending_only'] !== 0;

        // Walidacja formatu daty
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        $orders = $this->safeFetch(
            fn() => $this->orderService->getOrders($date, $clientName ?: null, $pendingOnly),
            $this->errors,
            null,
            []
        );

        $this->view('orders/overview', [
            'orders'      => $orders,
            'date'        => $date,
            'client_name' => $clientName,
            'pending_only' => $pendingOnly,
            'today'       => date('Y-m-d'),
        ]);
    }

    /**
     * Szczegóły zamówienia.
     * GET /orders/{id}
     */
    #[Route('GET', '/orders/{id:\d+}')]
    public function show(int $id): void
    {
        $order = $this->safeFetch(
            fn() => $this->orderService->getById($id),
            $this->errors,
            null,
            null
        );

        if ($order === null) {
            $this->errors[] = 'Zamówienie nie zostało znalezione.';
            $this->view('errors/404', []);
            return;
        }

        $this->view('orders/detail', [
            'order' => $order,
        ]);
    }
}

