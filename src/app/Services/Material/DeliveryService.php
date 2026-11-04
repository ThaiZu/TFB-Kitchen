<?php
namespace App\Kitchen\app\Services\Material;
use App\Kitchen\app\Repositories\Material\DeliveryRepository;
use App\Kitchen\core\Support\GlobalRegistry;
use App\Kitchen\core\Support\ShiftSession;

class DeliveryService
{
    public function __construct(private DeliveryRepository $repository) {}
    public function screen(?int $order=null): array
    {
        $shop=$this->shop();
        $result=$shop>0 ? ($order===null ? $this->repository->recent($shop) : $this->repository->detail($shop,$order)) : [];
        return [
            'available'=>($result['success'] ?? false)===true,
            'orders'=>$order===null ? ($result['data'] ?? []) : [],
            'order'=>$order!==null ? ($result['data'] ?? null) : null,
            'has_shift'=>ShiftSession::current($shop)!==null,
        ];
    }
    public function receive(int $order,array $input): array
    {
        $shop=$this->shop(); $shift=$shop>0 ? ShiftSession::current($shop) : null;
        if (!$shift) return ['success'=>false,'code'=>403,'reason'=>'KITCHEN_SHIFT_REQUIRED'];
        // Do not forward browser-supplied employee IDs, prices or material IDs.
        $payload=array_intersect_key($input,array_flip(['shipment_revision','delivered_on','quantities','confirm_exclude_unmapped_products']));
        $result=$this->repository->receive($shop,$order,$payload);
        if (!($result['success'] ?? false)) return [
            'success'=>false,'code'=>((int)($result['code'] ?? 0)>=400 && (int)$result['code']<=599) ? (int)$result['code'] : 502,
            'reason'=>$result['description'] ?? 'DELIVERY_UNAVAILABLE','errors'=>$result['errors'] ?? [],
        ];
        ShiftSession::touch($shift);
        return ['success'=>true,'data'=>$result['data'] ?? []];
    }
    private function shop(): int { return (int)((GlobalRegistry::get('user') ?? [])['shop_id'] ?? 0); }
}
