<?php
require __DIR__.'/../vendor/autoload.php';
$_SERVER['SERVER_NAME']='localhost';$_SERVER['REQUEST_METHOD']='GET';$_GET['url']='knowledge/products/101';
foreach(['JWT_SECRET','JWT_ISSUER','JWT_ACCESS_TOKEN_EXPIRY','JWT_REFRESH_TOKEN_EXPIRY','DEFAULT_COUNTRY','CURRENCY','CURRENCY_SYMBOL','APP_NAME','APP_DESC'] as $key)$_ENV[$key]='';
require __DIR__.'/../config/app.php';require __DIR__.'/../src/core/Support/functions.php';
use App\Kitchen\core\Support\{GlobalRegistry,DeviceMode,ShopClock};
use App\Kitchen\app\Models\Knowledge\Product\ProductDetailModel;
GlobalRegistry::set('user',['shop_id'=>2,'device_id'=>7]);DeviceMode::rules()->applyConfig(null);ShopClock::remember(2,'2026-11-17');
$container=require __DIR__.'/../src/core/Container/Container.php';$controller=$container->get(\App\Kitchen\app\Http\Controllers\Controller::class);
$count=0;$check=function($ok,$message)use(&$count){++$count;if(!$ok)throw new RuntimeException($message);};
$dir=getenv('KITCHEN_BAKING_FIXTURE_DIR')?:sys_get_temp_dir().'/kitchen-baking';if(!is_dir($dir))mkdir($dir,0777,true);
$settings=['temperature_c'=>180,'preheat_temperature_c'=>200,'humidity_pct'=>0,'steam_seconds'=>0,'cooling_minutes'=>12,'program'=>'P2 <script>bad()</script>','mode'=>'Convection','fan_setting'=>'Slow','before_baking'=>"Defrost\nProof",'loading_instructions'=>'Load trays','readiness_instructions'=>'Golden crust','after_baking'=>'Cool on rack'];
$baking=['shop_id'=>2,'product_id'=>101,'stages'=>[
 ['id'=>11,'description'=>'Bake croissant','duration_seconds'=>900,'products_per_tray'=>8,'trays_per_oven'=>4,'batch_capacity'=>32,'baking_settings'=>$settings,'photo_1_url'=>'https://images.example/stage.jpg'],
 ['id'=>12,'description'=>'Legacy stage','duration_seconds'=>60,'products_per_tray'=>1,'trays_per_oven'=>1,'batch_capacity'=>1,'baking_settings'=>null],
], 'local'=>[
 'categories'=>['temperature_c'=>175,'bake_minutes'=>20,'pieces_per_tray'=>6,'ovens'=>[],'steps'=>null],
 'products'=>['temperature_c'=>190,'bake_minutes'=>18,'pieces_per_tray'=>8,'ovens'=>[['name'=>'Shop oven','capacity_trays'=>6,'heat_minutes'=>0,'change_minutes'=>2,'available_from'=>'06:00','available_until'=>'18:00']], 'steps'=>[['name'=>'Prepare tray','phase'=>'before','duration_seconds'=>0,'duration_basis'=>'tray','operators'=>[['name'=>'Baker']]]]],
]];
$baking['instructions'] = [
    'pieces_per_tray' => 12,
    'notes_1' => "Leave a gap\nKeep trays even",
    'notes_2' => "Check <script>example()</script>\nCool on rack",
    'programs' => [
        ['id' => 1, 'oven_id' => 'main', 'shop_id' => 2, 'oven_name' => 'Main oven', 'number' => 7, 'description' => "Steam at start\nOpen flap at end", 'temperature_c' => 185, 'capacity_trays' => 6],
        ['id' => 2, 'oven_id' => 'spare', 'shop_id' => 2, 'oven_name' => 'Spare oven', 'number' => 7, 'description' => 'Lower fan', 'temperature_c' => null, 'capacity_trays' => null],
    ],
    'galleries' => [
        'correct' => [
            ['id' => 1, 'url' => 'https://images.example/correct-1.jpg', 'original_name' => 'Correct 1.jpg'],
            ['id' => 2, 'url' => 'https://images.example/correct-2.jpg', 'original_name' => 'Correct 2.jpg'],
        ],
        'incorrect' => [
            ['id' => 3, 'url' => 'https://images.example/incorrect-1.jpg', 'original_name' => 'Incorrect 1.jpg'],
            ['id' => 4, 'url' => 'https://images.example/incorrect-2.jpg', 'original_name' => 'Incorrect 2.jpg'],
        ],
    ],
];
$data=['object'=>['id'=>101,'name'=>'Croissant <test>','webshop_image'=>['url'=>'https://images.example/product.jpg']],'baking_available'=>true,'baking'=>$baking];
foreach(['pl','en','fr','nl','it'] as $lang){
 GlobalRegistry::set('lang_code',$lang);ob_start();$controller->view('knowledge/product/detail',['product'=>new ProductDetailModel($data)]);$html=ob_get_clean();
 $dom=new DOMDocument();@$dom->loadHTML($html);$xp=new DOMXPath($dom);
 $check($xp->query('//*[@role="tab" and @aria-selected="true" and @id="ficheGeneralTab"]')->length===1,'General selected by default');
 $check($xp->query('//*[@id="ficheBakingPanel" and @hidden]')->length===1,'Baking starts hidden');
 $check($xp->query('//*[@data-baking-stage]')->length===2,'Multiple oven stages and legacy settings');
 $check($xp->query('//*[@data-baking-local]')->length===2,'Category and product settings separated');
 $check($xp->evaluate('string(//*[@data-baking-pieces])')==='12','Configured tray count visible');
 $check($xp->query('//*[@data-baking-program]')->length===2,'Same program number on two named ovens');
 $check(str_contains($xp->evaluate('string((//*[@data-baking-program])[1])'),"Steam at start\nOpen flap at end"),'Program description and line breaks preserved');
 $check($xp->evaluate('string(//*[@data-baking-note="notes_1"])')===$baking['instructions']['notes_1'],'First note preserves line breaks');
 $check($xp->evaluate('string(//*[@data-baking-note="notes_2"])')===$baking['instructions']['notes_2']&&!str_contains($html,'<script>example()</script>'),'Second note is independent and escaped');
 $check($xp->query('//*[@data-baking-gallery="correct"]//button')->length===2,'Correct gallery images');
 $check($xp->query('//*[@data-baking-gallery="incorrect"]//button')->length===2,'Incorrect gallery images');
 $check(!str_contains($html,'<script>bad()</script>')&&str_contains($html,'P2 &lt;script&gt;'),'Baking descriptions escaped');
 $json=json_decode(file_get_contents(__DIR__.'/../src/core/I18n/translations/page/'.$lang.'/knowledge.json'),true);
 foreach($json as $key=>$label)if(str_starts_with($key,'bk_'))$check($label!=='','Baking label translated');
 $check(str_contains($html,$json['bk_humidity_pct'].'</dt><dd>0</dd>'),'Zero humidity remains visible');
 $check(str_contains($html,$json['bk_steam_seconds'].'</dt><dd>0</dd>'),'Zero steam remains visible');
 $check(!str_contains($html,'bk_tab_general'),'No untranslated keys');
 file_put_contents($dir.'/baking-'.$lang.'.html',$html);
}
GlobalRegistry::set('lang_code','pl');
foreach(['empty'=>true,'unavailable'=>false] as $name=>$available){$variant=$data;$variant['baking_available']=$available;$variant['baking']=['stages'=>[],'local'=>[]];ob_start();$controller->view('knowledge/product/detail',['product'=>new ProductDetailModel($variant)]);file_put_contents($dir.'/baking-'.$name.'.html',ob_get_clean());}
$variant = $data;
$variant['baking'] = ['stages' => [], 'local' => [], 'instructions' => ['notes_2' => "Partial configuration\nWithout a shop program"]];
ob_start();
$controller->view('knowledge/product/detail', ['product' => new ProductDetailModel($variant)]);
file_put_contents($dir.'/baking-partial.html', ob_get_clean());
// Repository reads only the signed-in shop's settings and keeps general data if unavailable.
$api=new class($data) extends \App\Kitchen\core\Http\ApiClient {
 public array $calls=[];public bool $fail=false;public int $shop=2;
 public function __construct(private array $sheet){}
 public function get($path,$decode=true){$this->calls[]=$path;if(str_contains($path,'baking-parameters'))return ['success'=>!$this->fail,'data'=>array_replace($this->sheet['baking'],['shop_id'=>$this->shop])];if(str_contains($path,'webshop-image'))return ['success'=>true,'data'=>['image'=>['url'=>'https://images.example/product.jpg']]];return ['success'=>true,'data'=>['object'=>$this->sheet['object']]];}
};
$repository=new \App\Kitchen\app\Repositories\Knowledge\Product\TechnicalSheetRepository($api);
$check($repository->getById(101)->isBakingAvailable(),'Baking read succeeds');$check(in_array('/shops/2/products/101/baking-parameters',$api->calls,true),'Shop-scoped API used');
$check($repository->getById(101)->getBaking()['instructions']===$baking['instructions'],'Read-through preserves API product instructions');
$api->fail=true;$failed=$repository->getById(101);$check(!$failed->isBakingAvailable()&&$failed->getName()==='Croissant <test>','General card survives optional baking failure');
$api->fail=false;$api->shop=9;$check(!$repository->getById(101)->isBakingAvailable(),'Unexpected shop response rejected');
GlobalRegistry::set('user',['shop_id'=>9,'device_id'=>8]);
$check($repository->getById(101)->isBakingAvailable()&&in_array('/shops/9/products/101/baking-parameters',$api->calls,true),'Changed session shop selects different endpoint');
echo "Knowledge baking: $count checks PASS\n";
