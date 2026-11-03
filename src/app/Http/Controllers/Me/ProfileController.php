<?php

namespace App\Kitchen\app\Http\Controllers\Me;


use App\Kitchen\app\Http\Controllers\Controller;
use App\Kitchen\app\Services\Me\DeviceService;
use App\Kitchen\core\Support\DeviceMode;
use App\Kitchen\core\Support\Route;

class ProfileController extends Controller
{

    public function __construct(
        private DeviceService $deviceService
    ) {}


    #[Route('GET', '/me')]
    public function index()
    {
        $data['supplier'] = $this->safeFetch(
            fn() => $this->deviceService->getMe(),
            $this->warnings,
            null,
            null
        );

        $this->view("me/about", $data);
    }

    /** Handle stale settings forms without restoring retired tablet modes. */
    #[Route('POST', '/me/settings')]
    public function save()
    {
        // Compatibility for stale cached forms: only Production remains selectable.
        DeviceMode::remember('production');
        redirect(ROOT.'/me');
    }
}
