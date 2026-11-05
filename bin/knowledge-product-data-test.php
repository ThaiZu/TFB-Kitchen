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
$detailApi=new class extends ApiClient{
 public array $calls=[];public bool $imageFails=false;public bool $notFound=false;public ?array $image=['url'=>'https://images.example/webshop.jpg'];
 public function __construct(){}
 public function get($path,$decode=true){$this->calls[]=$path;if(str_contains($path,'technical-sheet/raw')){preg_match('~/products/(\d+)/~',$path,$m);return ['success'=>!$this->notFound,'data'=>['object'=>['id'=>(int)$m[1],'name'=>'Bread'],'photos'=>['main_photo_path'=>'https://images.example/recipe.jpg','photo_1_path'=>'https://images.example/technical.jpg']]];}return ['success'=>!$this->imageFails,'data'=>['image'=>$this->image]];}
};
$details=new \App\Kitchen\app\Repositories\Knowledge\Product\TechnicalSheetRepository($detailApi);
$detail=$details->getById(11);$listProduct=new ProductModel(['id'=>11,'webshop_image'=>['url'=>'https://images.example/webshop.jpg']]);$check($detail->getMainProductPhotoUrl()===$listProduct->getMainPhotoUrl(),'Detail reads the same storefront image source');
$check($detail->getPhotos()->getPhoto1Url()==='https://images.example/technical.jpg','Additional technical photos remain unchanged');
$check(count($detailApi->calls)===2&&str_ends_with($detailApi->calls[1],'/products/11/webshop-image'),'Only the selected product image is requested');
$detailApi->image=null;$fallbackId=(int)array_key_first($index);$fallback=$details->getById($fallbackId);$check($fallback->getMainProductPhotoUrl()===(new ProductModel(['id'=>$fallbackId]))->getMainPhotoUrl(),'Detail and list share the exact local fallback');
$detailApi->imageFails=true;$check($details->getById($fallbackId)!==null,'An unavailable optional image does not remove the technical sheet');
$detailApi->notFound=true;$before=count($detailApi->calls);$check($details->getById(999)===null&&count($detailApi->calls)===$before+1,'No image read for an unknown product');
echo "Knowledge product data: $checks checks PASS\n";
