<?php
namespace App\Kitchen\app\Models\Complaint;
class ComplaintModel
{
    private ?int    $id;
    private ?int    $id_shop;
    private ?int    $id_material;
    private ?string $material_name;
    private ?string $supplier_name;
    private ?string $supplier_sku;
    private ?string $reason_code;
    private ?string $reason_name;
    private ?string $description;
    private ?string $order_key;
    private ?string $status;
    private ?string $created_at;
    private ?string $updated_at;
    private ?string $complaint_key;
    private ?float  $qty;
    private ?string $unit_name;
    private ?string $production_response;
    private ?string $requested_action;
    private ?float  $accepted_qty;
    private ?string $compensation_method;
    private array   $attachments = [];
    public function __construct(array $data)
    {
        $this->id                  = isset($data['id']) ? (int)$data['id'] : null;
        $this->id_shop             = isset($data['id_shop']) ? (int)$data['id_shop'] : null;
        $this->id_material         = isset($data['id_material']) ? (int)$data['id_material'] : null;
        $this->material_name       = $data['material_name'] ?? null;
        $this->supplier_name       = $data['supplier_name'] ?? null;
        $this->supplier_sku        = $data['supplier_sku'] ?? null;
        $this->reason_code         = $data['reason_code'] ?? null;
        $this->reason_name         = $data['reason_name'] ?? null;
        $this->description         = $data['description'] ?? null;
        $this->order_key           = $data['order_key'] ?? null;
        $this->status              = $data['status'] ?? null;
        $this->created_at          = $data['created_at'] ?? null;
        $this->updated_at          = $data['updated_at'] ?? null;
        $this->complaint_key       = $data['complaint_key'] ?? null;
        $this->qty                 = isset($data['qty']) ? (float)$data['qty'] : null;
        $this->unit_name           = $data['unit_name'] ?? null;
        $this->production_response = $data['production_response'] ?? null;
        $this->requested_action    = $data['requested_action'] ?? null;
        $this->accepted_qty        = isset($data['accepted_qty']) ? (float)$data['accepted_qty'] : null;
        $this->compensation_method = $data['compensation_method'] ?? null;
        $this->attachments         = $data['attachments'] ?? [];
    }
    public function getId(): ?int                    { return $this->id; }
    public function getIdShop(): ?int                { return $this->id_shop; }
    public function getIdMaterial(): ?int            { return $this->id_material; }
    public function getMaterialName(): ?string       { return $this->material_name; }
    public function getSupplierName(): ?string       { return $this->supplier_name; }
    public function getSupplierSku(): ?string        { return $this->supplier_sku; }
    public function getReasonCode(): ?string         { return $this->reason_code; }
    public function getReasonName(): ?string         { return $this->reason_name; }
    public function getDescription(): ?string        { return $this->description; }
    public function getOrderKey(): ?string           { return $this->order_key; }
    public function getStatus(): ?string             { return $this->status; }
    public function getCreatedAt(): ?string          { return $this->created_at; }
    public function getUpdatedAt(): ?string          { return $this->updated_at; }
    public function getComplaintKey(): ?string       { return $this->complaint_key; }
    public function getQty(): ?float                 { return $this->qty; }
    public function getUnitName(): ?string           { return $this->unit_name; }
    public function getProductionResponse(): ?string { return $this->production_response; }
    public function getRequestedAction(): ?string    { return $this->requested_action; }
    public function getAcceptedQty(): ?float         { return $this->accepted_qty; }
    public function getCompensationMethod(): ?string { return $this->compensation_method; }
    public function getAttachments(): array          { return $this->attachments; }
}
