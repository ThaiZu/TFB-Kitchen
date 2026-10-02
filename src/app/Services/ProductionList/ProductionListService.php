<?php
namespace App\Kitchen\app\Services\ProductionList;
use App\Kitchen\app\Repositories\ProductionList\ProductionListRepository;
use App\Kitchen\core\Support\GlobalRegistry;
use App\Kitchen\core\Support\ShiftSession;
use DateTimeImmutable;
final class ProductionListService
{
    public function __construct(private ProductionListRepository $repository) {}
    public function readDate(mixed $value): string
    { return $this->validDate($value) ? $value : (new DateTimeImmutable('now', new \DateTimeZone('Europe/Brussels')))->format('Y-m-d'); }
    public function readMargin(mixed $value): float
    { return is_scalar($value) && is_numeric($value) ? min(10000, max(0, (float) $value)) : 0; }
    public function build(string $date, float $margin): array
    {
        $shopId = $this->shopId();
        $response = $shopId > 0 ? $this->repository->plan($shopId, $date, $margin) : [];
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        return [
            'shop_id' => $shopId, 'plan_date' => $date, 'margin' => $data['margin'] ?? $margin,
            'revision' => $data['revision'] ?? 0,
            'available' => (bool) ($response['success'] ?? false),
            'history_from' => $data['history_from'] ?? '', 'history_to' => $data['history_to'] ?? '',
            'lines' => $data['lines'] ?? [], 'excluded_products' => $data['excluded_products'] ?? [],
            'production_history' => $data['production_history'] ?? [],
            'is_today' => $date === ($data['today'] ?? (new DateTimeImmutable('now', new \DateTimeZone('Europe/Brussels')))->format('Y-m-d')),
            'has_shift' => ShiftSession::current($shopId, $date) !== null,
        ];
    }
    public function saved(string $date): array
    {
        $response=$this->shopId()>0 ? $this->repository->saved($this->shopId(),$date) : [];
        $data=is_array($response['data'] ?? null) ? $response['data'] : [];
        return ['shop_id'=>$this->shopId(),'plan_date'=>$date,'available'=>(bool)($response['success'] ?? false),'saved_plan'=>$data,
            'is_today'=>$date === ($data['today'] ?? (new DateTimeImmutable('now',new \DateTimeZone('Europe/Brussels')))->format('Y-m-d')),
            'has_shift'=>ShiftSession::current($this->shopId(),$date) !== null];
    }
    public function pdf(string $date): array
    { return $this->shopId()>0 ? $this->repository->pdf($this->shopId(),$date) : ['success'=>false]; }
    public function save(array $input): array
    {
        if (!$this->validDate($input['date'] ?? null) || !$this->quantity($input['margin'] ?? null, true)
            || (float) $input['margin'] > 10000 || !is_array($input['lines'] ?? null) || $input['lines'] === []
            || filter_var($input['revision'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {
            return $this->failure('invalid_input', 422);
        }
        $lines = [];
        foreach ($input['lines'] as $line) {
            if (!is_array($line) || !filter_var($line['id_product'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
                || !$this->quantity($line['planned_quantity'] ?? null, true)) return $this->failure('invalid_input', 422);
            $lines[] = ['id_product' => (int) $line['id_product'], 'planned_quantity' => (float) $line['planned_quantity']];
        }
        if ($this->shopId() <= 0) return $this->failure('forbidden', 403);
        return $this->repository->save($this->shopId(), ['date' => $input['date'], 'margin' => (float) $input['margin'],
            'revision' => (int) $input['revision'], 'lines' => $lines]);
    }
    public function receive(array $input): array
    {
        if (!$this->validDate($input['date'] ?? null) || !$this->quantity($input['quantity'] ?? null, false)
            || !filter_var($input['id_product'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            || !is_string($input['idempotency_key'] ?? null)
            || !preg_match('/^[a-zA-Z0-9_-]{16,100}$/', $input['idempotency_key'])) return $this->failure('invalid_input', 422);
        $user = GlobalRegistry::get('user') ?? [];
        $shift = ShiftSession::current($this->shopId(), $input['date']);
        if ($this->shopId() <= 0 || (int) ($user['device_id'] ?? 0) <= 0 || $shift === null) return $this->failure('shift_required', 403);
        $expectations=[];
        if (array_key_exists('expected_revision',$input) || array_key_exists('expected_produced_quantity',$input)) {
            if (!$this->quantity($input['expected_produced_quantity'] ?? null,true)
                || filter_var($input['expected_revision'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) === false) return $this->failure('invalid_input',422);
            $expectations=['expected_revision'=>(int)$input['expected_revision'],'expected_produced_quantity'=>(float)$input['expected_produced_quantity']];
        }
        $result = $this->repository->receive($this->shopId(), [
            'date' => $input['date'], 'id_product' => (int) $input['id_product'],
            'quantity' => (float) $input['quantity'], 'idempotency_key' => $input['idempotency_key'],
            'id_device' => (int) $user['device_id'], 'id_employee' => (int) $shift['id'],
        ] + $expectations);
        if (!($result['success'] ?? false)) $result['reason']=(int)($result['code'] ?? 0) === 409 ? 'conflict' : 'failed';
        return $result;
    }
    private function shopId(): int { return (int) (GlobalRegistry::get('user')['shop_id'] ?? 0); }
    private function validDate(mixed $value): bool
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return false;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }
    private function quantity(mixed $value, bool $allowZero): bool
    {
        return is_scalar($value) && !is_bool($value) && is_numeric($value) && is_finite((float) $value)
            && ($allowZero ? (float) $value >= 0 : (float) $value > 0) && (float) $value <= 999999;
    }
    private function failure(string $reason, int $code): array
    { return ['success' => false, 'code' => $code, 'reason' => $reason]; }
}
