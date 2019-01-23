<?php
namespace CheckoutFinland;

class Comission
{
    private $merchant;
    private $amount;

    public function __construct(
        int $merchant = 0,
        int $amount = 0
    ) {
        $this->merchant = $merchant;
        $this->amount = $amount;
    }

    public function expose()
    {
        return get_object_vars($this);
    }
}
