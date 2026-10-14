<?php

namespace App\Kitchen\app\Services\Inventory;

use App\Kitchen\app\Repositories\Inventory\InventoryRepository;
use App\Kitchen\core\Support\GlobalRegistry;
use App\Kitchen\core\Support\ShiftSession;

/** Add-only stock recovery for production incidents; it is not stocktaking. */
final class ProductionStockAdjustmentService
{
    public function __construct(private InventoryRepository $repository) {}

    public function screen(): array
    {
        $shop = $this->shopId();
        $response = $shop > 0 ? $this->repository->snapshot($shop) : [];
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $today = is_string($data['today'] ?? null) ? $data['today'] : '';

        return [
            'available' => (bool) ($response['success'] ?? false),
            'shop_id' => $shop,
            'today' => $today,
            'lines' => is_array($data['lines'] ?? null) ? $data['lines'] : [],
            'has_shift' => $shop > 0 && $today !== '' && ShiftSession::current($shop, $today) !== null,
        ];
    }

    public function add(array $input): array
    {
        $shop = $this->shopId();
        $user = GlobalRegistry::get('user') ?? [];
        $device = (int) ($user['device_id'] ?? 0);
        if ($shop < 1 || $device < 1) return $this->failure('shift_required', 403);

        $payload = $this->payload($input);
        if ($payload === null) return $this->failure('invalid_input', 422);

        // The API's local date is the source of truth for the signed shift.
        $snapshot = $this->repository->snapshot($shop);
        $data = is_array($snapshot['data'] ?? null) ? $snapshot['data'] : [];
        $today = is_string($data['today'] ?? null) ? $data['today'] : '';
        $shift = $today === '' ? null : ShiftSession::current($shop, $today);
        if (!($snapshot['success'] ?? false) || $shift === null) return $this->failure('shift_required', 403);

        // This emergency screen is deliberately restricted to products that can
        // be counted and sold as whole pieces.  It must not become a shortcut
        // around the weighted-product inventory contract.
        $line = null;
        foreach (($data['lines'] ?? []) as $candidate) {
            if ((int) ($candidate['id_product'] ?? 0) === $payload['id_product']) {
                $line = $candidate;
                break;
            }
        }
        if ($line === null || !(bool) ($line['is_piece_based'] ?? false) || (bool) ($line['is_divisible'] ?? false)) {
            return $this->failure('invalid_input', 422);
        }

        $payload['id_employee'] = (int) $shift['id'];
        $result = $this->repository->add($shop, $payload['id_product'], $payload);
        if (!($result['success'] ?? false)) {
            return $this->failure((int) ($result['code'] ?? 0) === 409 ? 'conflict' : 'failed', (int) ($result['code'] ?? 502));
        }
        ShiftSession::touch($shift);
        return ['success' => true, 'data' => $result['data'] ?? []];
    }

    private function payload(array $input): ?array
    {
        $id = filter_var($input['id_product'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $key = $input['idempotency_key'] ?? null;
        if ($id === false || !is_string($key) || !preg_match('/^[A-Za-z0-9_-]{16,100}$/D', $key)
            || !is_scalar($input['quantity'] ?? null) || is_bool($input['quantity']) || !preg_match('/^[1-9]\d{0,7}$/D', (string) $input['quantity'])
            || !is_scalar($input['expected_in_stock'] ?? null) || is_bool($input['expected_in_stock']) || !preg_match('/^\d{1,8}(?:\.\d{1,4})?$/D', (string) $input['expected_in_stock'])
            || !array_key_exists('expected_initialized_at', $input) || ($input['expected_initialized_at'] !== null && !is_string($input['expected_initialized_at']))
            || !is_scalar($input['expected_movement_id'] ?? null) || is_bool($input['expected_movement_id']) || !preg_match('/^\d{1,20}$/D', (string) $input['expected_movement_id'])) {
            return null;
        }
        return [
            'id_product' => (int) $id,
            'quantity' => (string) $input['quantity'],
            'expected_in_stock' => (string) $input['expected_in_stock'],
            'expected_initialized_at' => $input['expected_initialized_at'],
            'expected_movement_id' => (string) $input['expected_movement_id'],
            'idempotency_key' => $key,
        ];
    }

    private function shopId(): int
    {
        return (int) ((GlobalRegistry::get('user') ?? [])['shop_id'] ?? 0);
    }

    private function failure(string $reason, int $code): array
    {
        return ['success' => false, 'reason' => $reason, 'code' => $code];
    }
}
