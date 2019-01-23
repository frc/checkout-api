<?php

namespace CheckoutFinland;

use CheckoutFinland\Comission;

class Item {

    private $unitPrice;
    private $units;
    private $vatPercentage;
    private $productCode;
    private $deliveryDate;
    private $description;
    private $category;
    private $merchant;
    private $stamp;
    private $reference;
    private $comission;

    public function __construct(int $unitPrice = 0,
        int $units = 0,
        int $vatPercentage = 0,
        string $productCode = '',
        string $deliveryDate = '',
        string $description = '',
        string $category = '',
        int $merchant = 0,
        int $stamp = 0,
        int $reference = 0,
        Comission $comission = null) {
        $this->unitPrice     = $unitPrice;
        $this->units         = $units;
        $this->vatPercentage = $vatPercentage;
        $this->productCode   = $productCode;
        $this->deliveryDate  = $deliveryDate;
        $this->description   = $description;
        $this->category      = $category;
        $this->merchant      = $merchant;
        $this->stamp         = $stamp;
        $this->reference     = $reference;
        $this->comission     = $comission ?? new Comission();
    }

    public function expose():array {
        $comissionData = $this->comission ? array_filter($this->comission->expose()) : new stdClass;

        return array_replace(
            get_object_vars($this),
            array('comission' => $comissionData)
        );
    }
}
