<?php

namespace App\Kitchen\app\Repositories\Order;

use App\Kitchen\app\Models\Order\OrderModel;
use App\Kitchen\core\Http\ApiClient;

class OrderRepository
{
    public function __construct(
        private ApiClient $apiClient
    ) {}

    /**
     * Pobiera zamówienia dla danego sklepu z filtrowaniem po dniu odbioru i nazwie klienta.
     *
     * @param int         $shopId      ID sklepu
     * @param string|null $date        Dzień odbioru (format Y-m-d); null = bieżący dzień
     * @param string|null $clientName  Fragment nazwy klienta (LIKE)
     * @param bool        $pendingOnly Filtruj tylko oczekujące (status=new / id_order_status=1 / NULL)
     * @return OrderModel[]
     */
    public function getByShop(int $shopId, ?string $date = null, ?string $clientName = null, bool $pendingOnly = true): array
    {
        $params = [];

        // Domyślnie filtruj po bieżącym dniu
        $targetDate = $date ?? date('Y-m-d');
        $params['date_from'] = $targetDate;
        $params['date_to']   = $targetDate;

        if ($clientName !== null && $clientName !== '') {
            $params['client_name'] = $clientName;
        }

        if ($pendingOnly) {
            $params['pending_only'] = 1;
        }

        $response = $this->apiClient->where("/shops/{$shopId}/client-orders", $params);

        if (!($response['success'] ?? false)) {
            return [];
        }

        $orders = [];
        foreach ($response['data'] ?? [] as $item) {
            if (is_array($item)) {
                $orders[] = new OrderModel($item);
            }
        }

        return $orders;
    }

    /**
     * Pobiera szczegóły pojedynczego zamówienia wraz z pozycjami.
     *
     * @param int $id ID zamówienia
     * @return OrderModel|null
     */
    public function getById(int $id): ?OrderModel
    {
        $response = $this->apiClient->get("/client-orders/{$id}?include=products");

        if (!($response['success'] ?? false)) {
            return null;
        }

        $data = $response['data'] ?? null;
        if (!is_array($data)) {
            return null;
        }

        return new OrderModel($data);
    }

    public function insert(array $data): array
    {
        return $this->apiClient->post('/client-orders', $data);
    }

    public function update(int $id, array $data): array
    {
        return $this->apiClient->patch("/client-orders/{$id}", $data);
    }

    public function delete(int $id): array
    {
        return $this->apiClient->delete("/client-orders/{$id}");
    }
}

