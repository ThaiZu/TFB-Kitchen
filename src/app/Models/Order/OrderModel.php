<?php

namespace App\Kitchen\app\Models\Order;

class OrderModel
{
    private ?int $id;
    private ?int $idShop;
    private ?int $idClient;
    private ?int $idClientDepartment;
    private ?int $idTransaction;
    private ?string $comment;
    private ?string $pickUpDatetime;
    private ?int $idOrderStatus;
    private ?string $orderStatus;
    private ?int $idAcceptingEmployee;
    private ?string $acceptingEmployeeDisplayName;
    private ?string $acceptingEmployeeInitials;
    private ?int $idIssuingEmployee;
    private ?string $issuingEmployeeDisplayName;
    private ?string $issuingEmployeeInitials;
    private ?string $acceptingTimestamp;
    private ?string $completionTimestamp;
    private ?string $issuingTimestamp;
    private ?int $nonCollectionIdReason;
    private ?string $nonCollectionComment;
    private ?int $idPaymentType;
    private ?string $poHash;
    private ?float $totalValue;

    /** @var OrderProductModel[] */
    private array $products = [];

    private ?array $clientData;

    public function __construct(array $data)
    {
        $this->id = isset($data['id']) ? (int)$data['id'] : null;
        $this->idShop = isset($data['id_shop']) ? (int)$data['id_shop'] : null;
        $this->idClient = isset($data['id_client']) ? (int)$data['id_client'] : null;
        $this->idClientDepartment = isset($data['id_client_department']) ? (int)$data['id_client_department'] : null;
        $this->idTransaction = isset($data['id_transaction']) ? (int)$data['id_transaction'] : null;
        $this->comment = $data['comment'] ?? null;
        $this->pickUpDatetime = $data['pick_up_datetime'] ?? null;
        $this->idOrderStatus = isset($data['id_order_status']) ? (int)$data['id_order_status'] : null;
        $this->orderStatus = $data['order_status'] ?? null;
        $this->idAcceptingEmployee = isset($data['id_accepting_employee']) ? (int)$data['id_accepting_employee'] : null;
        $this->acceptingEmployeeDisplayName = $data['accepting_employee_display_name'] ?? null;
        $this->acceptingEmployeeInitials = $data['accepting_employee_initials'] ?? null;
        $this->idIssuingEmployee = isset($data['id_issuing_employee']) ? (int)$data['id_issuing_employee'] : null;
        $this->issuingEmployeeDisplayName = $data['issuing_employee_display_name'] ?? null;
        $this->issuingEmployeeInitials = $data['issuing_employee_initials'] ?? null;
        $this->acceptingTimestamp = $data['accepting_timestamp'] ?? null;
        $this->completionTimestamp = $data['completion_timestamp'] ?? null;
        $this->issuingTimestamp = $data['issuing_timestamp'] ?? null;
        $this->nonCollectionIdReason = isset($data['non_collection_id_reason']) ? (int)$data['non_collection_id_reason'] : null;
        $this->nonCollectionComment = $data['non_collection_comment'] ?? null;
        $this->idPaymentType = isset($data['id_payment_type']) ? (int)$data['id_payment_type'] : null;
        $this->poHash = $data['po_hash'] ?? null;
        $this->totalValue = isset($data['total_value']) ? (float)$data['total_value'] : null;
        $this->clientData = $data['client'] ?? null;

        foreach ($data['products'] ?? [] as $product) {
            if (is_array($product)) {
                $this->products[] = new OrderProductModel($product);
            }
        }
    }

    // ── Podstawowe gettery ────────────────────────────────────────────────────

    public function getId(): ?int { return $this->id; }
    public function getIdShop(): ?int { return $this->idShop; }
    public function getIdClient(): ?int { return $this->idClient; }
    public function getIdClientDepartment(): ?int { return $this->idClientDepartment; }
    public function getIdTransaction(): ?int { return $this->idTransaction; }
    public function getComment(): ?string { return $this->comment; }
    public function getPickUpDatetime(): ?string { return $this->pickUpDatetime; }
    public function getIdOrderStatus(): ?int { return $this->idOrderStatus; }
    public function getOrderStatus(): ?string { return $this->orderStatus; }
    public function getIdAcceptingEmployee(): ?int { return $this->idAcceptingEmployee; }
    public function getAcceptingEmployeeDisplayName(): ?string { return $this->acceptingEmployeeDisplayName; }
    public function getAcceptingEmployeeInitials(): ?string { return $this->acceptingEmployeeInitials; }
    public function getIdIssuingEmployee(): ?int { return $this->idIssuingEmployee; }
    public function getIssuingEmployeeDisplayName(): ?string { return $this->issuingEmployeeDisplayName; }
    public function getIssuingEmployeeInitials(): ?string { return $this->issuingEmployeeInitials; }
    public function getAcceptingTimestamp(): ?string { return $this->acceptingTimestamp; }
    public function getCompletionTimestamp(): ?string { return $this->completionTimestamp; }
    public function getIssuingTimestamp(): ?string { return $this->issuingTimestamp; }
    public function getNonCollectionIdReason(): ?int { return $this->nonCollectionIdReason; }
    public function getNonCollectionComment(): ?string { return $this->nonCollectionComment; }
    public function getIdPaymentType(): ?int { return $this->idPaymentType; }
    public function getPoHash(): ?string { return $this->poHash; }

    /** @return OrderProductModel[] */
    public function getProducts(): array { return $this->products; }

    // ── Wartość zamówienia ────────────────────────────────────────────────────

    /**
     * Zwraca całkowitą wartość brutto zamówienia.
     * Jeśli API zwróciło pole total_value (subquery), używa go.
     * W przeciwnym razie sumuje z pozycji zamówienia.
     */
    public function getTotalValue(): float
    {
        if ($this->totalValue !== null) {
            return $this->totalValue;
        }
        $total = 0.0;
        foreach ($this->products as $product) {
            $total += (float)($product->getTotalGrossValueAfterDiscount() ?? 0);
        }
        return $total;
    }

    // ── Dane klienta ──────────────────────────────────────────────────────────

    public function isB2B(): bool
    {
        return (bool)($this->clientData['is_b2b'] ?? false);
    }

    public function getClientName(): ?string
    {
        if (!$this->clientData) {
            return null;
        }
        if ($this->clientData['is_b2b']) {
            return $this->clientData['company_name'] ?? null;
        }
        $name = trim(($this->clientData['name'] ?? '') . ' ' . ($this->clientData['surname'] ?? ''));
        return $name ?: null;
    }

    public function getClientPhone(): ?string
    {
        return $this->clientData['phone'] ?? null;
    }

    public function getClientEmail(): ?string
    {
        return $this->clientData['email'] ?? null;
    }

    public function getClientTaxNumber(): ?string
    {
        return $this->clientData['tax_number'] ?? null;
    }

    public function getClientCity(): ?string
    {
        return $this->clientData['city'] ?? null;
    }

    public function getClientComment(): ?string
    {
        return $this->clientData['comment'] ?? null;
    }

    public function getClientPaymentTerms(): ?string
    {
        return $this->clientData['payment_terms'] ?? null;
    }

    public function getClientPersonalDiscountPercent(): ?float
    {
        $v = $this->clientData['personal_discount_percent'] ?? null;
        return $v !== null ? (float)$v : null;
    }
}

