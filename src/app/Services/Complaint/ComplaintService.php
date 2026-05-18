<?php
namespace App\Kitchen\app\Services\Complaint;
use App\Kitchen\app\Models\Complaint\ComplaintModel;
use App\Kitchen\app\Repositories\Complaint\ComplaintRepository;
use App\Kitchen\core\Support\GlobalRegistry;
class ComplaintService
{
    public function __construct(
        private ComplaintRepository $complaintRepository
    ) {}
    /** @return ComplaintModel[] */
    public function getAll(): array
    {
        $shopId = (int)(GlobalRegistry::get('user')['shop_id'] ?? 0);
        if ($shopId <= 0) {
            return [];
        }
        return $this->complaintRepository->getByShop($shopId);
    }
    public function getAllReasons(): array
    {
        return $this->complaintRepository->getAllReasons();
    }
    public function insert(array $post, array $files = []): array
    {
        return $this->complaintRepository->insert($post, $files);
    }
    public function getPresignedUrl(string $attachmentId): array
    {
        return $this->complaintRepository->getPresignedUrl($attachmentId);
    }
}
