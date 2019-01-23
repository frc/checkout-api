<?php
/**
 * Created by PhpStorm.
 * User: janneaalto
 * Date: 02/10/2018
 * Time: 21.31
 */

namespace Frc\checkoutApi;

use CheckoutFinland;

require_once(dirname(__FILE__) . '/CheckoutFinland/Api.php');
require_once(dirname(__FILE__) . '/CheckoutFinland/Address.php');
require_once(dirname(__FILE__) . '/CheckoutFinland/Comission.php');
require_once(dirname(__FILE__) . '/CheckoutFinland/Customer.php');
require_once(dirname(__FILE__) . '/CheckoutFinland/Item.php');
require_once(dirname(__FILE__) . '/CheckoutFinland/UrlPair.php');

class Checkout extends CheckoutFinland\Api {

    private $customer = null;
    private $items = [];
    private $urls = null;
    private $amount = 0;

    public function __construct(string $serverUrl = 'https://api.checkout.fi', string $serviceName = '') {
        $merchantId = getenv('CHECKOUT_MERCHANT_ID');
        $merchantSecret = getenv('CHECKOUT_MERCHANT_SECRET');
        parent::__construct($merchantId, $merchantSecret, $serverUrl, $serviceName);
    }

    /**
     * @return null
     */
    public function getCustomer() {
        return $this->customer;
    }

    /**
     * @param null $customer
     */
    public function setCustomer(string $email = '', string $firstName = '', string $lastName = '', string $phone = '', string $vatId = ''):void {
        $this->customer = new CheckoutFinland\Customer($email, $firstName, $lastName, $phone, $vatId);
    }

    /**
     * @return null
     */
    public function getItems() {
        return $this->items;
    }

    /**
     * @param null $items
     */
    public function setItem(float $unitPrice = 0, int $units = 0, int $vatPercentage = 0, string $productCode = '', string $deliveryDate = '', string $description = ''):void {
        $item = new CheckoutFinland\Item($unitPrice * 100, $units, $vatPercentage, $productCode, $deliveryDate, $description);
        $exposedItem = $item->expose();
        $this->amount += $exposedItem['unitPrice'] * $exposedItem['units'];
        $this->items[] = $item;
    }

    /**
     * @return null
     */
    public function getUrls() {
        return $this->urls;
    }

    /**
     * @param string $success
     * @param string $cancel
     */
    public function setUrls(string $success = '', string $cancel = ''):void {
        $this->urls = new CheckoutFinland\UrlPair($success, $cancel);
    }

    public function createOrder($orderHash, $failureUrl) {

        $order = [
            'items' => $this->items,
            'customer' => $this->customer,
            'redirectUrls' => $this->urls,
            'callbackUrls' => $this->urls,
        ];

        // checkout callback urls need ssl
        if (getenv('WP_DEBUG') && getenv('WP_DEBUG') == 'true') {
            unset($order['callbackUrls']);
        }

        return parent::openPayment($orderHash, $this->amount, 'EUR', 'FI', $order, $failureUrl);
    }

    public function filterValidationParams(array $params): array {
        $includedKeys = array_filter(array_keys($params), function ($key) {
            return preg_match('/^checkout-/', $key);
        });
        return $includedKeys;
    }

    public function hasValidationParams(array $params): bool {
        return !empty($this->filterValidationParams($params));
    }

    public function validateSignature(array $params) {
        return $this->validateHmac($params['signature'], $params['checkout-algorithm'], $params);
    }
}
