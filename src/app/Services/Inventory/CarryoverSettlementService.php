<?php

namespace App\Kitchen\app\Services\Inventory;

use App\Kitchen\app\Repositories\Inventory\CarryoverSettlementRepository;
use App\Kitchen\core\Support\GlobalRegistry;
use App\Kitchen\core\Support\ShiftSession;

/** Server-side boundary for the API's immutable day-end carryover decision. */
final class CarryoverSettlementService
{
    public function __construct(private CarryoverSettlementRepository $repository) {}

    public function screen(): array
    {
        $shopId = $this->shopId();
        $result = $shopId > 0 ? $this->repository->sheet($shopId) : [];
        $data = is_array($result['data'] ?? null) ? $result['data'] : [];
        $sourceDate = (string) ($data['source_date'] ?? '');
        return [
            'shop_id' => $shopId,
            'available' => (bool) ($result['success'] ?? false),
            'source_date' => $sourceDate,
            'target_date' => (string) ($data['target_date'] ?? ''),
            'timezone' => (string) ($data['timezone'] ?? ''),
            'items' => is_array($data['items'] ?? null) ? $data['items'] : [],
            'waste_reasons' => is_array($data['waste_reasons'] ?? null) ? $data['waste_reasons'] : [],
            'from_previous_day' => is_array($data['from_previous_day'] ?? null) ? $data['from_previous_day'] : [],
            'has_shift' => $shopId > 0 && $sourceDate !== '' && ShiftSession::current($shopId, $sourceDate) !== null,
        ];
    }

    public function create(array $input): array
    {
        $shopId = $this->shopId();
        $user = GlobalRegistry::get('user') ?? [];
        if ($shopId <= 0 || (int) ($user['device_id'] ?? 0) <= 0) return $this->failure('shift_required', 403);

        // The server obtains the local source day from API, never from a plan date or browser input.
        $sheet = $this->repository->sheet($shopId);
        $data = is_array($sheet['data'] ?? null) ? $sheet['data'] : [];
        $sourceDate = (string) ($data['source_date'] ?? '');
        if (!($sheet['success'] ?? false) || $sourceDate === '' || ShiftSession::current($shopId, $sourceDate) === null) {
            return $this->failure('shift_required', 403);
        }
        $payload = $this->payload($input, $data['waste_reasons'] ?? []);
        if ($payload === null) return $this->failure('invalid_input', 422);

        $result = $this->repository->create($shopId, $payload);
        if (!($result['success'] ?? false)) {
            $description = (string) ($result['description'] ?? '');
            $reason = (int) ($result['code'] ?? 0) === 409 ? 'conflict'
                : (in_array($description, ['KITCHEN_SHIFT_REQUIRED', 'KITCHEN_SHIFT_INVALID', 'INVENTORY_ACTOR_FORBIDDEN'], true) ? 'shift_required' : 'failed');
            return ['success' => false, 'reason' => $reason, 'code' => (int) ($result['code'] ?? 502),
                'errors' => is_array($result['errors'] ?? null) ? $result['errors'] : []];
        }
        return ['success' => true, 'data' => $result['data'] ?? []];
    }

    private function payload(array $input, mixed $reasons): ?array
    {
        $key = $input['idempotency_key'] ?? null;
        $lines = $input['lines'] ?? null;
        if (!is_string($key) || !preg_match('/^[A-Za-z0-9_-]{16,100}$/D', $key)
            || !is_array($lines) || $lines === [] || count($lines) > 100 || !is_array($reasons)) return null;
        $allowed = [];
        foreach ($reasons as $reason) if (is_array($reason) && is_string($reason['key'] ?? null)) $allowed[$reason['key']] = (bool) ($reason['comment_required'] ?? false);
        $clean = [];
        foreach ($lines as $line) {
            if (!is_array($line) || filter_var($line['id_product'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false
                || !is_scalar($line['carryover_quantity'] ?? null) || is_bool($line['carryover_quantity']) || !preg_match('/^\d{1,8}$/D', (string) $line['carryover_quantity'])
                || !is_scalar($line['waste_quantity'] ?? null) || is_bool($line['waste_quantity']) || !preg_match('/^\d{1,8}$/D', (string) $line['waste_quantity'])
                || !is_scalar($line['expected_in_stock'] ?? null) || is_bool($line['expected_in_stock']) || !preg_match('/^\d{1,8}(?:\.\d{1,4})?$/D', (string) $line['expected_in_stock'])
                || !is_scalar($line['expected_movement_id'] ?? null) || is_bool($line['expected_movement_id']) || !preg_match('/^\d{1,20}$/D', (string) $line['expected_movement_id'])) return null;
            $product = (int) $line['id_product'];
            if (isset($clean[$product]) || (int) $line['carryover_quantity'] + (int) $line['waste_quantity'] !== (int) $line['expected_in_stock']) return null;
            $reason = $line['waste_reason'] ?? null;
            $comment = $line['waste_comment'] ?? null;
            if ((int) $line['waste_quantity'] > 0) {
                if (!is_string($reason) || !array_key_exists($reason, $allowed) || ($comment !== null && !is_string($comment))) return null;
                $comment = trim((string) $comment);
                if (mb_strlen($comment) > 500 || ($allowed[$reason] && $comment === '')) return null;
            } else { $reason = null; $comment = null; }
            $clean[$product] = [
                'id_product' => $product, 'carryover_quantity' => (string) $line['carryover_quantity'],
                'waste_quantity' => (string) $line['waste_quantity'], 'waste_reason' => $reason,
                'waste_comment' => $comment === '' ? null : $comment,
                'expected_in_stock' => (string) $line['expected_in_stock'], 'expected_movement_id' => (string) $line['expected_movement_id'],
            ];
        }
        return ['idempotency_key' => $key, 'lines' => array_values($clean)];
    }

    private function shopId(): int { $user = GlobalRegistry::get('user') ?? []; return (int) ($user['shop_id'] ?? 0); }
    private function failure(string $reason, int $code): array { return ['success' => false, 'reason' => $reason, 'code' => $code]; }
}
