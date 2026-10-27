<?php
namespace App\Kitchen\app\Repositories\Production;
use App\Kitchen\core\Http\ApiClient;
class ProductionDayRepository
{
    public function __construct(private ApiClient $apiClient) {}
    public function sheet(int $shop,?string $date,?int $sector=null): array
    { return $this->apiClient->get('/shops/'.$shop.'/production-day'.'?'.http_build_query(array_filter(['date'=>$date,'sector_id'=>$sector],fn($v)=>$v!==null))); }
    public function sectors(int $shop): array {return $this->apiClient->get('/shops/'.$shop.'/production-day/sectors');}
    public function command(int $shop,string $operation,array $input): array
    { return $this->apiClient->post('/shops/'.$shop.'/production-day/'.$operation,$input); }
}
