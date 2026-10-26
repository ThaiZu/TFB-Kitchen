<?php
namespace App\Kitchen\app\Services\Production;
use App\Kitchen\app\Repositories\Production\ProductionDayRepository;
use App\Kitchen\core\Support\{GlobalRegistry,ShiftSession};
class ProductionDayService
{
    public function __construct(private ProductionDayRepository $repository) {}
    public function screen(?string $date=null): array
    {
        $shop=(int)(GlobalRegistry::get('user')['shop_id']??0);
        if ($date!==null && !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)) return ['available'=>false];
        $result=$shop>0?$this->repository->sheet($shop,$date):[];$sheet=$result['data']??[];
        return ['available'=>(bool)($result['success']??false),'sheet'=>$sheet,
            'has_shift'=>isset($sheet['today']) && ShiftSession::current($shop,$sheet['today'])!==null,
            'api_error'=>$result['description']??null];
    }
    public function command(string $operation,array $input): array
    {
        $shop=(int)(GlobalRegistry::get('user')['shop_id']??0);
        $shift=$shop>0?ShiftSession::current($shop):null;
        if ($shift===null) return ['success'=>false,'code'=>403,'description'=>'KITCHEN_SHIFT_REQUIRED'];
        if (!in_array($operation,['open','adjust','close'],true)) return ['success'=>false,'code'=>422];
        $result=$this->repository->command($shop,$operation,$input);
        if ($result['success']??false) ShiftSession::touch($shift);
        return $result;
    }
}
