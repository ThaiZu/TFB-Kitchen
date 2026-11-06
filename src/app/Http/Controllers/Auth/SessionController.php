<?php
namespace App\Kitchen\app\Http\Controllers\Auth;
use App\Kitchen\app\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
final class SessionController extends Controller
{
    /** Protected by the normal middleware; no employee shift or inventory action. */
    public function status(): JsonResponse
    {
        return new JsonResponse(['success'=>true],200,['Cache-Control'=>'private, no-store']);
    }
}
