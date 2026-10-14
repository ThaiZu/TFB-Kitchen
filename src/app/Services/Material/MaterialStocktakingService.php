<?php

namespace App\Kitchen\app\Services\Material;

use App\Kitchen\app\Repositories\Material\MaterialStocktakingRepository;
use App\Kitchen\core\Support\GlobalRegistry;
use App\Kitchen\core\Support\ShiftSession;

/** Tablet boundary for complete material stocktakes, never product stock. */
final class MaterialStocktakingService
{
    public function __construct(private MaterialStocktakingRepository $repository) {}

    public function screen(): array
    {
        $shop = $this->shopId();
        $result = $shop > 0 ? $this->repository->materials($shop) : [];
        $lines = $this->lines($result['data'] ?? []);
        return [
            'available' => (bool) ($result['success'] ?? false),
            'shop_id' => $shop,
            'lines' => $lines,
            'has_shift' => $this->shift($shop) !== null,
        ];
    }

    public function previewScanner(array $input): array
    {
        $shop = $this->shopId();
        if ($this->shift($shop) === null) return $this->failure('shift_required', 403);
        $codes = $this->codes($input['codes'] ?? null);
        if ($codes === null) return $this->failure('invalid_codes', 422);
        $result = $this->repository->verifyCodes($shop, $codes);
        if (!($result['success'] ?? false)) return $this->failure('failed', (int) ($result['code'] ?? 502));
        $items = $this->scannerItems($result['data']['data'] ?? []);
        if ($items === []) return $this->failure('no_recognised_codes', 422);
        return ['success' => true, 'items' => $items];
    }

    public function save(array $input): array
    {
        $shop = $this->shopId();
        $shift = $this->shift($shop);
        if ($shift === null) return $this->failure('shift_required', 403);
        $type = $input['type'] ?? null;
        if ($type === 'manual') {
            $current = $this->repository->materials($shop);
            if (!($current['success'] ?? false)) return $this->failure('failed', (int) ($current['code'] ?? 502));
            $elements = $this->manualItems($input['quantities'] ?? null, $current['data'] ?? []);
            if ($elements === null) return $this->failure('manual_incomplete', 422);
            $payload = ['type' => 'MANUAL', 'id_employee' => (int) $shift['id'], 'elements' => $elements];
        } elseif ($type === 'scanner') {
            $codes = $this->codes($input['codes'] ?? null);
            if ($codes === null) return $this->failure('invalid_codes', 422);
            // Verify again immediately before the irreversible write. The browser
            // never gets to decide which material a barcode represents.
            $verified = $this->repository->verifyCodes($shop, $codes);
            if (!($verified['success'] ?? false)) return $this->failure('failed', (int) ($verified['code'] ?? 502));
            $elements = $this->scannerItems($verified['data']['data'] ?? []);
            if ($elements === []) return $this->failure('no_recognised_codes', 422);
            $payload = ['type' => 'SCANNER', 'id_employee' => (int) $shift['id'], 'elements' => $elements];
        } else {
            return $this->failure('invalid_input', 422);
        }

        $result = $this->repository->create($shop, $payload);
        if (!($result['success'] ?? false)) return $this->failure('failed', (int) ($result['code'] ?? 502));
        ShiftSession::touch($shift);
        return ['success' => true, 'inserted_id' => $result['inserted_id'] ?? null];
    }

    private function manualItems(mixed $quantities, mixed $rawLines): ?array
    {
        if (!is_array($quantities)) return null;
        $lines = $this->lines($rawLines);
        if ($lines === [] || count($lines) > 2000 || count($quantities) !== count($lines)) return null;
        $expected = array_fill_keys(array_map(static fn (array $line): string => (string) $line['id_material'], $lines), true);
        $items = [];
        foreach ($quantities as $id => $quantity) {
            if (!is_string($id) && !is_int($id)) return null;
            $id = (string) $id;
            if (!isset($expected[$id]) || !is_scalar($quantity) || is_bool($quantity)
                || !preg_match('/^\d{1,8}(?:\.\d{1,4})?$/D', (string) $quantity)) return null;
            unset($expected[$id]);
            $items[] = ['id_material' => (int) $id, 'counted_quantity_in_base_unit' => (string) $quantity];
        }
        return $expected === [] ? $items : null;
    }

    /** Keeps only the API-verified pack data required by scanner stocktaking. */
    private function scannerItems(mixed $items): array
    {
        if (!is_array($items) || count($items) > 2000) return [];
        $result = [];
        foreach ($items as $item) {
            if (!is_array($item) || filter_var($item['id_material'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false
                || !is_scalar($item['package_size_in_base_unit'] ?? null) || !is_numeric($item['package_size_in_base_unit'])
                || (float) $item['package_size_in_base_unit'] <= 0 || filter_var($item['id_unit'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false
                || !is_string($item['code'] ?? null) || $item['code'] === '') return [];
            $result[] = [
                'code' => $item['code'], 'id_package' => $item['id_package'] ?? null,
                'id_material' => (int) $item['id_material'], 'name' => (string) ($item['name'] ?? ''), 'unit_name' => (string) ($item['unit_name'] ?? ''),
                'package_size_in_base_unit' => (float) $item['package_size_in_base_unit'],
                'id_unit' => (int) $item['id_unit'], 'quantity' => 1,
            ];
        }
        return $result;
    }

    private function codes(mixed $codes): ?array
    {
        if (!is_array($codes) || $codes === [] || count($codes) > 2000) return null;
        $result = [];
        foreach ($codes as $code) {
            if (!is_string($code)) return null;
            $code = trim($code);
            if ($code === '' || mb_strlen($code) > 160) return null;
            $result[] = $code;
        }
        return $result;
    }

    private function lines(mixed $raw): array
    {
        if (!is_array($raw)) return [];
        $result = [];
        foreach ($raw as $line) {
            if (!is_array($line) || filter_var($line['id_material'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) continue;
            $result[] = [
                'id_material' => (int) $line['id_material'], 'name' => (string) ($line['material_name'] ?? ''),
                'category' => (string) ($line['category_name'] ?? ''), 'unit' => (string) ($line['unit_name'] ?? ''),
                'current_quantity' => (string) ($line['current_quantity'] ?? '0'),
            ];
        }
        usort($result, static fn (array $a, array $b): int => [$a['category'], $a['name'], $a['id_material']] <=> [$b['category'], $b['name'], $b['id_material']]);
        return $result;
    }

    private function shopId(): int { return (int) ((GlobalRegistry::get('user') ?? [])['shop_id'] ?? 0); }
    // A material count can cross midnight; it only needs the currently signed
    // Kitchen worker, not a production-plan day.
    private function shift(int $shop): ?array { return $shop > 0 ? ShiftSession::current($shop) : null; }
    private function failure(string $reason, int $code): array { return ['success' => false, 'reason' => $reason, 'code' => $code]; }
}
