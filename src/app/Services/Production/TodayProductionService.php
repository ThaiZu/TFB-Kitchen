<?php

namespace App\Kitchen\app\Services\Production;

use App\Kitchen\app\Repositories\Production\TodayProductionRepository;
use App\Kitchen\core\Support\GlobalRegistry;
use App\Kitchen\core\Support\ShiftSession;

final class TodayProductionService
{
    public function __construct(private TodayProductionRepository $repository) {}

    /**
     * The API owns the business date and remaining quantities. Kitchen only
     * presents the compact work list; it does not recalculate a plan locally.
     */
    public function view(): array
    {
        $shopId = (int) (GlobalRegistry::get('user')['shop_id'] ?? 0);
        $response = $shopId > 0 ? $this->repository->today($shopId) : [];
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $cycles = $this->cycles($data['cycles'] ?? [], (string) ($data['now'] ?? ''));

        return [
            'available' => (bool) ($response['success'] ?? false),
            'date' => (string) ($data['date'] ?? ''),
            'id_plan' => is_string($data['id_plan'] ?? null) ? $data['id_plan'] : null,
            'exists' => (bool) ($data['exists'] ?? false),
            'cycles' => $cycles,
            'categories' => $this->categories($cycles),
            'can_confirm' => $shopId > 0 && ($data['date'] ?? '') !== '' && ShiftSession::current($shopId,(string)$data['date']) !== null,
            'can_adjust' => $shopId > 0 && ($data['date'] ?? '') !== '' && ShiftSession::current($shopId,(string)$data['date']) !== null,
        ];
    }

    public function confirmCycle(array $input): array
    {
        $user=GlobalRegistry::get('user') ?? []; $shop=(int)($user['shop_id'] ?? 0);
        $date=$input['date'] ?? null; $plan=$input['id_plan'] ?? null; $cycle=$input['id_cycle'] ?? null; $request=$input['request_id'] ?? null;
        if ($shop<1 || !is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)
            || !is_string($plan) || !preg_match('/^[1-9][0-9]{0,18}$/D',$plan)
            || !is_string($cycle) || !preg_match('/^[1-9][0-9]{0,18}$/D',$cycle)
            || !is_string($request) || !preg_match('/^[A-Za-z0-9._:-]{16,80}$/D',$request)) return $this->failure('invalid_input',422);
        $shift=ShiftSession::current($shop,$date);
        if ($shift===null) return $this->failure('shift_required',403);
        $result=$this->repository->confirmCycle($shop,$cycle,['id_plan'=>$plan,'id_employee'=>(int)$shift['id'],'request_id'=>$request]);
        if (!($result['success'] ?? false)) return $this->failure((int)($result['code'] ?? 0)===409 ? 'conflict' : 'failed',(int)($result['code'] ?? 502));
        ShiftSession::touch($shift);
        return ['success'=>true,'replayed'=>(bool)(($result['data']['replayed'] ?? false))];
    }

    /** Save target quantities for an unconfirmed cycle; this endpoint never writes stock. */
    public function adjustCycleTarget(array $input): array
    {
        $user=GlobalRegistry::get('user') ?? []; $shop=(int)($user['shop_id'] ?? 0);
        $date=$input['date'] ?? null; $plan=$input['id_plan'] ?? null; $cycle=$input['id_cycle'] ?? null; $request=$input['request_id'] ?? null; $lines=$input['lines'] ?? null;
        if ($shop<1 || !is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)
            || !is_string($plan) || !preg_match('/^[1-9][0-9]{0,18}$/D',$plan)
            || !is_string($cycle) || !preg_match('/^[1-9][0-9]{0,18}$/D',$cycle)
            || !is_string($request) || !preg_match('/^[A-Za-z0-9._:-]{16,80}$/D',$request)
            || !is_array($lines) || $lines===[]) return $this->failure('invalid_input',422);
        $normalized=[]; $seen=[];
        foreach ($lines as $line) {
            if (!is_array($line) || !is_string($line['id_cycle_line'] ?? null) || !preg_match('/^[1-9][0-9]{0,18}$/D',$line['id_cycle_line'])
                || !is_int($line['id_product'] ?? null) || $line['id_product']<1 || !is_string($line['target_quantity'] ?? null)
                || !preg_match('/^(0|[1-9][0-9]{0,7})(?:\.0{1,4})?$/D',$line['target_quantity']) || isset($seen[$line['id_cycle_line']])) return $this->failure('invalid_input',422);
            $seen[$line['id_cycle_line']]=true;
            $normalized[]=['id_cycle_line'=>$line['id_cycle_line'],'id_product'=>$line['id_product'],'target_quantity'=>$this->wholeDecimal($line['target_quantity'])];
        }
        $shift=ShiftSession::current($shop,$date);
        if ($shift===null) return $this->failure('shift_required',403);
        $result=$this->repository->adjustCycleTarget($shop,$cycle,['id_plan'=>$plan,'id_employee'=>(int)$shift['id'],'request_id'=>$request,'lines'=>$normalized]);
        if (!($result['success'] ?? false)) return $this->failure((int)($result['code'] ?? 0)===409 ? 'conflict' : 'failed',(int)($result['code'] ?? 502));
        ShiftSession::touch($shift);
        return ['success'=>true,'replayed'=>(bool)(($result['data']['replayed'] ?? false))];
    }

    /** @return list<array{key:string,name:string,time:string,end_time:string,is_current:bool,lines:list<array>}> */
    private function cycles(mixed $source, string $now): array
    {
        if (!is_array($source)) {
            return [];
        }

        $cycles = [];
        foreach ($source as $index => $cycle) {
            if (!is_array($cycle)) {
                continue;
            }
            $time = (string) ($cycle['time'] ?? '');
            if (!preg_match('/^[0-2][0-9]:[0-5][0-9]$/D', $time)) {
                continue;
            }
            $cycles[] = [
                'id_cycle' => (string) ($cycle['id_cycle'] ?? ''),
                'key' => (string) ($cycle['key'] ?? ('cycle_' . $index)),
                'name' => (string) ($cycle['name'] ?? ''),
                'time' => $time,
                'end_time' => (string) ($cycle['end_time'] ?? ''),
                'is_current' => false,
                'lines' => is_array($cycle['lines'] ?? null) ? $cycle['lines'] : [],
            ];
        }
        usort($cycles, static fn(array $left, array $right): int => $left['time'] <=> $right['time']);

        $selected = $this->currentOrNextCycle($cycles, $now);
        foreach ($cycles as &$cycle) {
            $cycle['is_current'] = $cycle['key'] === $selected;
        }
        unset($cycle);
        return $cycles;
    }

    /** @param list<array{key:string,time:string,end_time:string}> $cycles */
    private function currentOrNextCycle(array $cycles, string $now): ?string
    {
        if ($cycles === []) {
            return null;
        }

        if (!preg_match('/^[0-2][0-9]:[0-5][0-9]$/D', $now)) {
            return $cycles[0]['key'];
        }
        foreach ($cycles as $cycle) {
            $end = $cycle['end_time'];
            if ($now >= $cycle['time'] && ($end === '' || $now < $end)) {
                return $cycle['key'];
            }
        }
        foreach ($cycles as $cycle) {
            if ($now < $cycle['time']) {
                return $cycle['key'];
            }
        }
        return $cycles[array_key_last($cycles)]['key'];
    }

    /** @param list<array{key:string,lines:list<array>}> $cycles @return list<array{id_category:int|null,name:string,lines:list<array>}> */
    private function categories(array $cycles): array
    {
        $groups = [];
        foreach ($cycles as $cycle) {
            foreach ($cycle['lines'] as $line) {
                if (!is_array($line) || !isset($line['id_product'])) {
                    continue;
                }
                $product = (int) $line['id_product'];
                $category = isset($line['id_category']) && is_int($line['id_category']) ? $line['id_category'] : null;
                $groupKey = $category === null ? 'none' : (string) $category;
                if (!isset($groups[$groupKey])) {
                    $groups[$groupKey] = [
                        'id_category' => $category,
                        'name' => trim((string) ($line['category_name'] ?? '')),
                        'lines' => [],
                    ];
                }
                if (!isset($groups[$groupKey]['lines'][$product])) {
                    $groups[$groupKey]['lines'][$product] = [
                        'id_product' => $product,
                        'name' => (string) ($line['name'] ?? ('#' . $product)),
                        'quantities' => [],
                        'target_quantities' => [],
                        'cycle_lines' => [],
                        'total_quantity' => '0.0000',
                    ];
                }
                $groups[$groupKey]['lines'][$product]['quantities'][$cycle['key']] = (string) ($line['quantity'] ?? '0');
                $groups[$groupKey]['lines'][$product]['target_quantities'][$cycle['key']] = (string) ($line['target_quantity'] ?? $line['quantity'] ?? '0');
                $groups[$groupKey]['lines'][$product]['cycle_lines'][$cycle['key']] = (string) ($line['id_cycle_line'] ?? '');
            }
        }

        foreach ($groups as &$group) {
            foreach ($group['lines'] as &$line) {
                $line['total_quantity'] = $this->sumQuantities($line['quantities']);
            }
            unset($line);
            $group['lines'] = array_values($group['lines']);
            usort($group['lines'], static fn(array $left, array $right): int => strnatcasecmp($left['name'], $right['name']));
        }
        unset($group);
        $groups = array_values($groups);
        usort($groups, static fn(array $left, array $right): int => strnatcasecmp($left['name'], $right['name']));
        return $groups;
    }

    /** @param array<string,string> $quantities */
    private function sumQuantities(array $quantities): string
    {
        $total = 0;
        foreach ($quantities as $quantity) {
            if (!preg_match('/^([0-9]+)(?:\.([0-9]{1,4}))?$/D', $quantity, $match)) {
                continue;
            }
            $total += ((int) $match[1] * 10000) + (int) str_pad($match[2] ?? '', 4, '0');
        }
        return intdiv($total, 10000) . '.' . str_pad((string) ($total % 10000), 4, '0', STR_PAD_LEFT);
    }

    private function wholeDecimal(string $quantity): string
    {
        return (string) ((int) $quantity) . '.0000';
    }

    private function failure(string $reason,int $code): array
    {
        return ['success'=>false,'reason'=>$reason,'code'=>$code];
    }
}
