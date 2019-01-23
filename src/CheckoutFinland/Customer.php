<?php
namespace CheckoutFinland;

class Customer
{
    private $email;
    private $firstName;
    private $lastName;
    private $phone;
    private $vatId;

    public function __construct(
        string $email = '',
        string $firstName = '',
        string $lastName = '',
        string $phone = '',
        string $vatId = ''
    ) {
        $this->email = $email;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->phone = $phone;
        $this->vatId = $vatId;
    }

    public function expose(): array
    {
        return get_object_vars($this);
    }
}
