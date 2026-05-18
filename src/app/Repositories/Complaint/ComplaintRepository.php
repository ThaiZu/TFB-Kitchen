<?php
namespace App\Kitchen\app\Repositories\Complaint;
use App\Kitchen\app\Models\Complaint\ComplaintModel;
use App\Kitchen\core\Http\ApiClient;
class ComplaintRepository
{
    public function __construct(
        private ApiClient $apiClient
    ) {}
    /** @return ComplaintModel[] */
    public function getByShop(int $shopId): array
    {
        $response = $this->apiClient->get("/shops/{$shopId}/material-complaints");
        if (!($response['success'] ?? false)) {
            return [];
        }
        $complaints = [];
        foreach ($response['data'] ?? [] as $item) {
            if (is_array($item)) {
                $complaints[] = new ComplaintModel($item);
            }
        }
        usort($complaints, static function (ComplaintModel $a, ComplaintModel $b) {
            return strtotime((string)$b->getCreatedAt()) - strtotime((string)$a->getCreatedAt());
        });
        return $complaints;
    }
    public function getAllReasons(): array
    {
        $response = $this->apiClient->get('/material-complaint-reasons');
        if (!($response['success'] ?? false)) {
            return [];
        }
        return $response['data'] ?? [];
    }
    public function insert(array $data, array $files = []): array
    {
        if (!empty($files['attachments']['name'][0])) {
            $data['contains_attachments'] = 1;
        } else {
            $data['contains_attachments'] = 0;
        }
        $resComplaint = $this->apiClient->post('/material-complaints', $data);
        if (!($resComplaint['success'] ?? false)) {
            return ['complaint' => $resComplaint, 'attachments' => null];
        }
        $insertedId = $resComplaint['inserted_id'] ?? null;
        if ($insertedId && !empty($files['attachments']['name'][0])) {
            $attachments  = $files['attachments'];
            $validIndices = array_keys(
                array_filter($attachments['error'], fn($err) => $err === UPLOAD_ERR_OK)
            );
            if (!empty($validIndices)) {
                $cleanFiles = [];
                foreach ($attachments as $key => $values) {
                    $cleanFiles[$key] = array_values(
                        array_intersect_key($values, array_flip($validIndices))
                    );
                }
                $resAttach = $this->apiClient->postMultipart(
                    "/material-complaints/{$insertedId}/attachments",
                    [],
                    ['attachments' => $cleanFiles]
                );
                return ['complaint' => $resComplaint, 'attachments' => $resAttach];
            }
        }
        return ['complaint' => $resComplaint, 'attachments' => false];
    }
    public function getPresignedUrl(string $attachmentId): array
    {
        return $this->apiClient->get("/material-complaints/attachments/{$attachmentId}/presigned-url");
    }
}
