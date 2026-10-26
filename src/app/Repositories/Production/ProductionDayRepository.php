<?php
namespace App\Kitchen\app\Repositories\Production;
use App\Kitchen\core\Http\ApiClient;
class ProductionDayRepository
{
    public function __construct(private ApiClient $apiClient) {}
    public function sheet(int $shop,?string $date): array
    { return $this->apiClient->get('/shops/'.$shop.'/production-day'.($date===null?'':'?date='.rawurlencode($date))); }
    public function command(int $shop,string $operation,array $input): array
    { return $this->apiClient->post('/shops/'.$shop.'/production-day/'.$operation,$input); }
}
