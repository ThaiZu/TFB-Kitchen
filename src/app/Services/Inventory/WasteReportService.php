<?php

namespace App\Kitchen\app\Services\Inventory;

use App\Kitchen\app\Repositories\Inventory\InventoryRepository;
use App\Kitchen\app\Repositories\Inventory\WasteReportRepository;
use App\Kitchen\core\Support\GlobalRegistry;
use App\Kitchen\core\Support\ShiftSession;

/** Kitchen boundary for the API's atomic waste-report contract. */
final class WasteReportService
{
    public function __construct(
        private InventoryRepository $inventoryRepository,
        private WasteReportRepository $wasteRepository,
    ) {}

    public function screen(): array
    {
        $shopId = $this->shopId();
        $snapshot = $shopId > 0 ? $this->inventoryRepository->snapshot($shopId) : [];
        $reasons = $shopId > 0 ? $this->wasteRepository->reasons($shopId) : [];
        $history = $shopId > 0 ? $this->wasteRepository->today($shopId) : [];

        $snapshotData = is_array($snapshot['data'] ?? null) ? $snapshot['data'] : [];
        $reasonsData = is_array($reasons['data'] ?? null) ? $reasons['data'] : [];
        $historyData = is_array($history['data'] ?? null) ? $history['data'] : [];
        // The waste-history endpoint is authoritative for the store-local day.
        $today = (string) ($historyData['date'] ?? $snapshotData['today'] ?? '');

        return [
            'shop_id' => $shopId,
            'available' => (bool) ($snapshot['success'] ?? false),
            'reasons_available' => (bool) ($reasons['success'] ?? false),
            'history_available' => (bool) ($history['success'] ?? false),
            'today' => $today,
            'timezone' => (string) ($historyData['timezone'] ?? ''),
            'lines' => is_array($snapshotData['lines'] ?? null) ? $snapshotData['lines'] : [],
            'reasons' => $this->displayReasons($reasonsData['items'] ?? []),
            'history' => is_array($historyData['items'] ?? null) ? $historyData['items'] : [],
            'has_shift' => $shopId > 0 && $today !== '' && ShiftSession::current($shopId, $today) !== null,
        ];
    }

    public function create(array $input): array
    {
        $shopId = $this->shopId();
        $user = GlobalRegistry::get('user') ?? [];
        $deviceId = (int) ($user['device_id'] ?? 0);
        if ($shopId <= 0 || $deviceId <= 0) {
            return $this->failure('shift_required', 403);
        }

        // The date is obtained from the existing store-local inventory snapshot,
        // never from a production-plan date supplied by the browser.
        $snapshot = $this->inventoryRepository->snapshot($shopId);
        $snapshotData = is_array($snapshot['data'] ?? null) ? $snapshot['data'] : [];
        $today = (string) ($snapshotData['today'] ?? '');
        if (!($snapshot['success'] ?? false) || $today === '' || ShiftSession::current($shopId, $today) === null) {
            return $this->failure('shift_required', 403);
        }

        $reasonsResponse = $this->wasteRepository->reasons($shopId);
        if (!($reasonsResponse['success'] ?? false)) {
            return $this->failure('reasons_unavailable', 502);
        }
        $reasonsData = is_array($reasonsResponse['data'] ?? null) ? $reasonsResponse['data'] : [];
        $payload = $this->payload($input, $this->displayReasons($reasonsData['items'] ?? []));
        if ($payload === null) {
            return $this->failure('invalid_input', 422);
        }

        // Do not add shop, device or employee identifiers from the browser.
        // The API obtains device from JWT and employee from Kitchen ShiftSession.
        $result = $this->wasteRepository->create($shopId, $payload);
        if (!($result['success'] ?? false)) {
            $description = (string) ($result['description'] ?? '');
            $reason = (int) ($result['code'] ?? 0) === 409 ? 'conflict'
                : (in_array($description, ['KITCHEN_SHIFT_REQUIRED', 'KITCHEN_SHIFT_INVALID', 'INVENTORY_ACTOR_FORBIDDEN'], true)
                    ? 'shift_required' : 'failed');
            return [
                'success' => false,
                'reason' => $reason,
                'code' => (int) ($result['code'] ?? 502),
                'errors' => is_array($result['errors'] ?? null) ? $result['errors'] : [],
            ];
        }

        return ['success' => true, 'data' => $result['data'] ?? []];
    }

    private function payload(array $input, mixed $reasons): ?array
    {
        $key = $input['idempotency_key'] ?? null;
        $lines = $input['lines'] ?? null;
        if (!is_string($key) || !preg_match('/^[A-Za-z0-9_-]{16,100}$/D', $key)
            || !is_array($lines) || $lines === [] || count($lines) > 100 || !is_array($reasons)) {
            return null;
        }

        $allowed = [];
        foreach ($reasons as $reason) {
            if (is_array($reason) && is_string($reason['key'] ?? null)) {
                $allowed[$reason['key']] = (bool) ($reason['comment_required'] ?? false);
            }
        }
        $clean = [];
        foreach ($lines as $line) {
            if (!is_array($line)
                || filter_var($line['id_product'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false
                || !is_scalar($line['quantity'] ?? null) || is_bool($line['quantity'])
                || !preg_match('/^[1-9]\d{0,7}$/D', (string) $line['quantity'])
                || !is_string($line['reason'] ?? null) || !array_key_exists($line['reason'], $allowed)
                || !is_scalar($line['expected_in_stock'] ?? null) || is_bool($line['expected_in_stock'])
                || !preg_match('/^\d{1,8}(?:\.\d{1,4})?$/D', (string) $line['expected_in_stock'])
                || !is_scalar($line['expected_movement_id'] ?? null) || is_bool($line['expected_movement_id'])
                || !preg_match('/^\d{1,20}$/D', (string) $line['expected_movement_id'])) {
                return null;
            }
            $product = (int) $line['id_product'];
            if (isset($clean[$product])) return null; // API has one product per atomic report.
            $comment = $line['comment'] ?? null;
            if ($comment !== null && !is_string($comment)) return null;
            $comment = $comment === null ? null : trim($comment);
            if ($comment !== null && mb_strlen($comment) > 500) return null;
            if ($comment === '') $comment = null;
            if ($allowed[$line['reason']] && $comment === null) return null;
            $clean[$product] = [
                'id_product' => $product,
                'quantity' => (string) $line['quantity'],
                'reason' => $line['reason'],
                'expected_in_stock' => (string) $line['expected_in_stock'],
                'expected_movement_id' => (string) $line['expected_movement_id'],
            ];
            if ($comment !== null) $clean[$product]['comment'] = $comment;
        }

        return ['idempotency_key' => $key, 'lines' => array_values($clean)];
    }

    private function shopId(): int
    {
        $user = GlobalRegistry::get('user') ?? [];
        return (int) ($user['shop_id'] ?? 0);
    }

    private function failure(string $reason, int $code): array
    {
        return ['success' => false, 'reason' => $reason, 'code' => $code];
    }

    /** API owns labels and reason keys; carryover is never a Kitchen waste choice. */
    private function displayReasons(mixed $items): array
    {
        if (!is_array($items)) return [];
        return array_values(array_filter($items, static fn (mixed $item): bool =>
            is_array($item) && ($item['key'] ?? null) !== 'carryover'
        ));
    }
}
