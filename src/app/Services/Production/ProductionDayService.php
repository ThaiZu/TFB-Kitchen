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
        // The API chooses the active day in the shop timezone and selected sector.
        // Ignore dates supplied by old pages or bookmarks.
        $result=$shop>0?$this->repository->sheet($shop,null,ProductionSector::current($shop)):[];$sheet=$result['data']??[];
        if(($sheet['opening_blocker']['reason']??null)==='previous_open'&&!empty($sheet['opening_blocker']['date'])){
            $result=$this->repository->sheet($shop,$sheet['opening_blocker']['date'],ProductionSector::current($shop));$sheet=$result['data']??[];
        }
        if(!empty($sheet['late_closing'])||($sheet['opening_blocker']['reason']??null)==='previous_open'){
            $sheet['can_prepare']=false;
            $sheet['default_view']='closing';
        }
        if(($result['success']??false)&&($sheet['status']??null)==='NOT_OPEN'&&($sheet['date']??null)===($sheet['today']??null)&&empty($sheet['opening_blocker'])){
            $plan=$this->plan();$model=$plan['data']??[];
            $ready=($plan['success']??false)&&($model['date']??null)===$sheet['date'];
            $sheet['production_plan_available']=$ready;
            $quantities=$ready?array_column($model['products'], 'quantity', 'id_product'):[];
            foreach($sheet['products'] as &$product)$product['planned_production_quantity']=$quantities[$product['id_product']]??0;
            unset($product);
        }
        if($result['success']??false)\App\Kitchen\core\Support\ShopClock::remember($shop,$sheet['today']??null);
        return ['available'=>(bool)($result['success']??false),'view'=>$sheet['default_view']??'opening','sheet'=>$sheet,
            'has_shift'=>isset($sheet['today']) && ShiftSession::current($shop,$sheet['today'])!==null,
            'api_error'=>$result['description']??null];
    }
    public function plan(int $offset=0): array
    {
        $shop=(int)(GlobalRegistry::get('user')['shop_id']??0);
        if($shop<1||$offset<0||$offset>2)return ['success'=>false,'code'=>422];
        try{
            $result=$this->repository->plan($shop,$offset,ProductionSector::current($shop));$data=$result['data']??null;
            if(($result['success']??false)&&is_array($data)&&($data['shop_id']??null)===$shop&&is_string($data['date']??null)&&is_array($data['products']??null))return $result;
            return ['success'=>false,'code'=>503];
        }catch(\Throwable){return ['success'=>false,'code'=>503];}
    }
    public function windows(string $date): array {if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date))return ['success'=>false,'code'=>422];return $this->repository->windows((int)(GlobalRegistry::get('user')['shop_id']??0),$date);}
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
    public function uploadPhoto(array $input): array
    {
        $shop=(int)(GlobalRegistry::get('user')['shop_id']??0);$shift=$shop>0?ShiftSession::current($shop):null;
        if($shift===null)return ['success'=>false,'code'=>403,'description'=>'KITCHEN_SHIFT_REQUIRED'];
        $result=$this->repository->command($shop,'photos',$input);if($result['success']??false)ShiftSession::touch($shift);return $result;
    }
    public function command(string $operation,array $input): array
    {
        $shop=(int)(GlobalRegistry::get('user')['shop_id']??0);
        $shift=$shop>0?ShiftSession::current($shop):null;
        if ($shift===null) return ['success'=>false,'code'=>403,'description'=>'KITCHEN_SHIFT_REQUIRED'];
        if (!in_array($operation,['open','adjust','close','prepare','prepare_add','close_draft','count','stock_count'],true)) return ['success'=>false,'code'=>422];
        if(in_array($operation,['prepare','prepare_add'],true)){
            $screen=$this->screen();
            if(!$screen['available'])return ['success'=>false,'code'=>503,'description'=>'KITCHEN_DAY_UNAVAILABLE'];
            if(!empty($screen['sheet']['late_closing'])||($screen['sheet']['opening_blocker']['reason']??null)==='previous_open')return ['success'=>false,'code'=>409,'description'=>'KITCHEN_DAY_PREVIOUS_OPEN'];
        }
        $result=$this->repository->command($shop,$operation,$input);
        if ($result['success']??false) ShiftSession::touch($shift);
        return $result;
    }
}
