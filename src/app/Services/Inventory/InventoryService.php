<?php
namespace App\Kitchen\app\Services\Inventory;
use App\Kitchen\app\Repositories\Inventory\InventoryRepository;
use App\Kitchen\core\Support\GlobalRegistry;
use App\Kitchen\core\Support\ShiftSession;
final class InventoryService
{
    public function __construct(private InventoryRepository $repository) {}
    public function snapshot(): array
    {
        $shop = (int)(GlobalRegistry::get('user')['shop_id'] ?? 0);
        $result = $shop > 0 ? $this->repository->snapshot($shop) : [];
        $data = is_array($result['data'] ?? null) ? $result['data'] : [];
        $today = $data['today'] ?? '';
        return ['shop_id'=>$shop, 'available'=>(bool)($result['success'] ?? false),
            'snapshot_date'=>$today, 'lines'=>$data['lines'] ?? [],
            'has_shift'=>$today !== '' && ShiftSession::current($shop,$today) !== null];
    }
    public function count(array $input): array
    {
        $user = GlobalRegistry::get('user') ?? [];
        $shop = (int)($user['shop_id'] ?? 0);
        $date = $input['date'] ?? '';
        if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)
            || !filter_var($input['id_product'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])
            || !array_key_exists('expected_initialized_at',$input)
            || ($input['expected_initialized_at'] !== null && !is_string($input['expected_initialized_at']))
            || !is_string($input['idempotency_key'] ?? null)
            || !preg_match('/^[A-Za-z0-9_-]{16,100}$/D',$input['idempotency_key'])
            || !is_string($input['expected_movement_id'] ?? null)
            || !preg_match('/^\d+$/D',$input['expected_movement_id'])) return $this->failure('invalid_input',422);
        foreach (['in_stock','expected_in_stock'] as $field) {
            if (!is_scalar($input[$field] ?? null) || is_bool($input[$field])
                || !preg_match('/^\d{1,8}(?:\.\d{1,4})?$/D',(string)$input[$field])) return $this->failure('invalid_input',422);
        }
        $shift = ShiftSession::current($shop,$date);
        if ($shop <= 0 || (int)($user['device_id'] ?? 0) <= 0 || $shift === null) return $this->failure('shift_required',403);
        // Device/employee/shop always come from the signed server-side context.
        $payload = array_intersect_key($input,array_flip(['in_stock','expected_in_stock','expected_initialized_at','expected_movement_id','idempotency_key']));
        $payload['id_device']=(int)$user['device_id'];
        $payload['id_employee']=(int)$shift['id'];
        $result=$this->repository->count($shop,(int)$input['id_product'],$payload);
        if (!($result['success'] ?? false)) $result['reason']=(int)($result['code'] ?? 0) === 409 ? 'conflict' : 'failed';
        return $result;
    }
    private function failure(string $reason,int $code): array
    { return ['success'=>false,'reason'=>$reason,'code'=>$code]; }
}
