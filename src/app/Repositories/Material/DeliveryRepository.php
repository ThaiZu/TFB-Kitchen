<?php
namespace App\Kitchen\app\Repositories\Material;
use App\Kitchen\core\Http\ApiClient;

class DeliveryRepository
{
    public function __construct(private ApiClient $api) {}
    public function recent(int $shop): array { return $this->api->get("/shops/{$shop}/kitchen-deliveries"); }
    public function detail(int $shop,int $order): array { return $this->api->get("/shops/{$shop}/kitchen-deliveries/{$order}"); }
    public function receive(int $shop,int $order,array $payload): array { return $this->api->post("/shops/{$shop}/kitchen-deliveries/{$order}/receive",$payload); }
}
