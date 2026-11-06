<?php
/** Offline device-session regressions; no real credentials or API/database access. */
require __DIR__.'/../vendor/autoload.php';
use App\Kitchen\app\Services\Auth\{AuthGuard,JwtService,SessionRefreshUnavailable,SessionResponse};
use App\Kitchen\app\Repositories\Auth\LoginRepository;
use App\Kitchen\core\Cookie\CookieManager;
use App\Kitchen\core\Http\ApiClient;

$checks=0;
function expectSession(bool $condition,string $message): void {
    global $checks; ++$checks;
    if (!$condition) throw new RuntimeException($message);
}
function fixtureJwt(int $expiry): string { return 'fixture.'.rtrim(strtr(base64_encode(json_encode(['exp'=>$expiry])),'+/','-_'),'=').'.fixture'; }
class FixtureRefreshRepository extends LoginRepository {
    public array $response=[];
    public bool $unavailable=false;
    public int $calls=0;
    public function __construct() {}
    public function refresh($token) {
        ++$this->calls;
        if($this->unavailable) throw new SessionRefreshUnavailable();
        return $this->response ?: null;
    }
}
class FixtureLoginClient extends ApiClient {
    public array $response=[];
    public function __construct() {}
    public function login($endpoint,$data) { return $this->response; }
}
$cookies=new CookieManager();$repo=new FixtureRefreshRepository();$guard=new AuthGuard($cookies,new JwtService(),$repo);
$_COOKIE=[];$cookies->setAuthCookie(fixtureJwt(time()+3600),date('Y-m-d H:i:s',time()+3600));
expectSession($guard->ensureAccessToken() && $repo->calls===0,'Fresh access avoids rotation');
$cookies->setAuthCookie(fixtureJwt(time()-1),date('Y-m-d H:i:s',time()-1));$cookies->setRefreshCookie('fixture-old');
$repo->response=['access_token'=>fixtureJwt(time()+50400),'refresh_token'=>'fixture-new','refresh_expires_in'=>5184000];
expectSession($guard->ensureAccessToken(),'Expired access is renewed');
expectSession($cookies->getRefreshToken()==='fixture-new','Rotated token is available within the same request');
expectSession(abs(strtotime($cookies->getRefreshTokenExpiryTime())-time()-5184000)<3,'Refresh cookie lasts sixty days');
$repo->unavailable=true;
$cookies->setAuthCookie(fixtureJwt(time()+60),date('Y-m-d H:i:s',time()+60));
expectSession($guard->ensureAccessToken(),'Temporary outage preserves still-valid access');
$cookies->setAuthCookie(fixtureJwt(time()-1),date('Y-m-d H:i:s',time()-1));
try {$guard->ensureAccessToken();throw new RuntimeException('Expected temporary failure');} catch(SessionRefreshUnavailable) { ++$checks; }
expectSession($cookies->getRefreshToken()==='fixture-new','Temporary failure preserves refresh cookie');
$repo->unavailable=false;$repo->response=[];
expectSession(!$guard->ensureAccessToken(),'Explicit invalid refresh requires login');
$cookies->unsetCookies();expectSession(!$guard->ensureAccessToken()&&$_COOKIE===[],'Logout clears all device cookies');
$client=new FixtureLoginClient();$repository=new LoginRepository($client);
foreach([0,500,503] as $status){$client->response=['auth_http_status'=>$status];try{$repository->refresh('fixture');throw new RuntimeException('Expected outage');}catch(SessionRefreshUnavailable){++$checks;}}
foreach([400,401,403,422] as $status){$client->response=['auth_http_status'=>$status];expectSession($repository->refresh('fixture')===null,'Definitively invalid credentials do not retry forever');}
$client->response=['access_token'=>'fixture-access','refresh_token'=>'fixture-refresh','auth_http_status'=>200,'refresh_expires_in'=>5184000];
expectSession($repository->refresh('fixture')['refresh_expires_in']===5184000,'Refresh TTL survives API client/repository boundary');
$_GET=[];
$response=SessionResponse::create(['HTTP_ACCEPT'=>'application/json'],false,'https://fixture.test/kitchen');
expectSession($response->getStatusCode()===401&&!$response->headers->has('Location')&&json_decode($response->getContent(),true)['description']==='KITCHEN_AUTH_REQUIRED','AJAX gets JSON 401 instead of login HTML');
$response=SessionResponse::create(['HTTP_X_REQUESTED_WITH'=>'XMLHttpRequest'],true,'/kitchen');
expectSession($response->getStatusCode()===503&&$response->headers->get('Retry-After')==='30'&&$response->headers->getCookies()===[],'Temporary outage is retryable without clearing cookies');
expectSession(SessionResponse::create([],false,'/kitchen')->headers->get('Location')==='/kitchen/auth','Normal expired page goes to login');
echo "Kitchen session: $checks checks PASS\n";
