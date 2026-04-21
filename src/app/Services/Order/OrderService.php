<?php

namespace App\Kitchen\app\Services\Order;

use App\Kitchen\app\Models\Order\OrderModel;
use App\Kitchen\app\Repositories\Order\OrderRepository;
use App\Kitchen\core\Support\GlobalRegistry;

class OrderService
{
    public function __construct(
        private OrderRepository $orderRepository
    ) {}

    /**
     * Pobiera listę zamówień dla sklepu zalogowanego urządzenia.
     *
     * @param string|null $date        Dzień odbioru (Y-m-d). Domyślnie: dziś.
     * @param string|null $clientName  Fragment nazwy klienta do wyszukiwania.
     * @param bool        $pendingOnly Tylko oczekujące na realizację.
     * @return OrderModel[]
     */
    public function getOrders(?string $date = null, ?string $clientName = null, bool $pendingOnly = true): array
    {
        $shopId = (int)(GlobalRegistry::get('user')['shop_id'] ?? 0);

        if ($shopId <= 0) {
            return [];
        }

        return $this->orderRepository->getByShop($shopId, $date, $clientName, $pendingOnly);
    }

    /**
     * Pobiera szczegóły zamówienia.
     *
     * @param int $id ID zamówienia
     * @return OrderModel|null
     */
    public function getById(int $id): ?OrderModel
    {
        return $this->orderRepository->getById($id);
    }
}

