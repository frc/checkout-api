<?php
namespace CheckoutFinland;

class Address
{
    private $streetAddress;
    private $postalCode;
    private $city;
    private $county;
    private $country;

    public function __construct(
        string $streetAddress = '',
        string $postalCode = '',
        string $city = '',
        string $county = '',
        string $country = ''
    ) {
        $this->streetAddress = $streetAddress;
        $this->postalCode = $postalCode;
        $this->city = $city;
        $this->county = $county;
        $this->country = $country;
    }

    public function expose(): array {
        return get_object_vars($this);
    }
}
