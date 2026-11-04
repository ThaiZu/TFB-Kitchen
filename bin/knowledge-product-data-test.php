<?php
require __DIR__.'/../vendor/autoload.php';
use App\Kitchen\app\Models\Knowledge\Product\ProductModel;
use App\Kitchen\app\Repositories\Knowledge\Product\ProductRepository;
use App\Kitchen\core\Http\ApiClient;
if(!defined('ROOT'))define('ROOT','/kitchen');
$checks=0;$check=function($ok,$label)use(&$checks){$checks++;if(!$ok)throw new RuntimeException($label);};
$product=new ProductModel(['id'=>11,'main_photo_path'=>'https://images.example/recipe.jpg','webshop_image'=>['url'=>'https://images.example/webshop.jpg']]);
$check($product->getMainPhotoUrl()==='https://images.example/webshop.jpg','Storefront image wins over the recipe image');
$index=json_decode(file_get_contents(__DIR__.'/../public/assets/webshop-product-images/index.json'),true,512,JSON_THROW_ON_ERROR);$check(count($index)>0,'WebShop local image fallback is bundled');
foreach($index as $id=>$filename){$product=new ProductModel(['id'=>(int)$id]);$check($product->hasMainPhoto()&&$product->getMainPhotoUrl()==='/kitchen/assets/webshop-product-images/'.$filename&&is_file(__DIR__.'/../public/assets/webshop-product-images/'.$filename),'Indexed local image is available without probing extensions');}
$api=new class extends ApiClient{public array $response=['success'=>true,'data'=>[]];public function __construct(){}public function get($path,$decode=true){return $this->response;}};
$repository=new ProductRepository($api);$check($repository->getAvailableToSale(2)===[],'A valid empty catalogue stays empty');
foreach([['success'=>false,'code'=>503],['success'=>true,'data'=>null]] as $response){$api->response=$response;try{$repository->getAvailableToSale(2);throw new RuntimeException('Failure hidden as empty list');}catch(RuntimeException $error){$check($error->getMessage()==='PRODUCT_CATALOG_UNAVAILABLE','API error is different from no products');}}
echo "Knowledge product data: $checks checks PASS\n";
