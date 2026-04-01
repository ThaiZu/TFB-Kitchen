<?php
namespace App\Kitchen\app\Models\Me;

use JsonSerializable;

class ShopModel implements JsonSerializable
{
    private $id;
    private $representative_name;
    private $email;
    private $street;
    private $street_num;
    private $city;
    private $zip;
    private $phone;
    private $vat_number;
    private $opening_hours;
    private $closing_hours;

    public function __construct($data)
    {
        $this->id = $data['id'];
        $this->representative_name = $data['representative_name'];
        $this->email = $data['email'];
        $this->street = $data['street'];
        $this->street_num = $data['street_num'];
        $this->city = $data['city'];
        $this->zip = $data['zip'];
        $this->phone = $data['phone'];
        $this->vat_number = $data['vat_number'];
        $this->opening_hours = $data['opening_hours'];
        $this->closing_hours = $data['closing_hours'];
    }

    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->id,
            'representative_name' => $this->representative_name,
            'email' => $this->email,
            'street' => $this->street,
            'street_num' => $this->street_num,
            'city' => $this->city,
            'zip' => $this->zip,
        ];
    }

    public function getId() { return $this->id; }
    public function getRepresentativeName() { return $this->representative_name; }
    public function getEmail() { return $this->email; }
    public function getStreet() { return $this->street; }
    public function getStreetNumber() { return $this->street_num; }
    public function getCity() { return $this->city; }
    public function getZip() { return $this->zip; }
    public function getPhone() { return $this->phone; }
    public function getVatNumber() { return $this->vat_number; }
    public function getOpeningHours() { return $this->opening_hours; }
    public function getClosingHours() { return $this->closing_hours; }

}