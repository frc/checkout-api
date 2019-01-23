<?php
namespace CheckoutFinland;

class UrlPair
{
    private $success;
    private $cancel;

    public function __construct(
        string $success = '',
        string $cancel = ''
    ) {
        $this->success = $success;
        $this->cancel = $cancel;
    }

    public function expose(): array
    {
        return get_object_vars($this);
    }
}
