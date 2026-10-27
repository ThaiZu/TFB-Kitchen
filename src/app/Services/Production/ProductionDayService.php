<?php
namespace App\Kitchen\app\Services\Production;
use App\Kitchen\app\Repositories\Production\ProductionDayRepository;
use App\Kitchen\core\Support\{GlobalRegistry,ShiftSession,ProductionSector};
class ProductionDayService
{
    public function __construct(private ProductionDayRepository $repository) {}
    public function screen(?string $date=null): array
    {
        $shop=(int)(GlobalRegistry::get('user')['shop_id']??0);
        if ($date!==null && !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)) return ['available'=>false];
        $result=$shop>0?$this->repository->sheet($shop,$date,ProductionSector::current($shop)):[];$sheet=$result['data']??[];
        return ['available'=>(bool)($result['success']??false),'view'=>$sheet['default_view']??'opening','sheet'=>$sheet,
            'has_shift'=>isset($sheet['today']) && ShiftSession::current($shop,$sheet['today'])!==null,
            'api_error'=>$result['description']??null];
    }
    public function sectors(): array
    {
        $shop=(int)(GlobalRegistry::get('user')['shop_id']??0);
        $result=$shop>0?$this->repository->sectors($shop):[];
        return ['success'=>(bool)($result['success']??false),'items'=>$result['data']['items']??[],'selected'=>ProductionSector::current($shop)];
    }
    public function selectSector(mixed $sector): array
    {
        if($sector!==null && (!is_int($sector)||$sector<1))return ['success'=>false,'code'=>422];
        $catalog=$this->sectors();
        if(!$catalog['success'])return ['success'=>false,'code'=>503];
        if($sector!==null && !in_array($sector,array_map('intval',array_column($catalog['items'],'id')),true))return ['success'=>false,'code'=>422];
        ProductionSector::save((int)GlobalRegistry::get('user')['shop_id'],$sector,$sector===null?null:array_column($catalog['items'],'name','id')[$sector]);
        return ['success'=>true,'selected'=>$sector];
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
