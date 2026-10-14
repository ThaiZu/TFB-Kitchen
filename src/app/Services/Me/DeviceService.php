<?php
namespace App\Kitchen\app\Services\Me;

use App\Kitchen\app\Repositories\Me\DeviceRepository;
use App\Kitchen\core\Support\GlobalRegistry;

class DeviceService
{
    public function __construct(
        private DeviceRepository $deviceRepository
    )
    {
    }

    public function getMe()
    {
        return $this->deviceRepository->getMe();
    }
}