<?php
namespace CheckoutFinland;

use function error_log;
use GuzzleHttp;
use Frc;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\Exception\UnsatisfiedDependencyException;

use CheckoutFinland\Item;
use CheckoutFinland\Customer;
use CheckoutFinland\Address;
use CheckoutFinland\UrlPair;

class Api
{
    private $merchantId;
    private $merchantSecret;
    private $serverUrl;
    private $serviceName;

    private const DEFAULT_PAYMENT_OPTS = array(
        'stamp' => '',
        'hmacAlgorithm' => 'sha256',
        'httpMethod' => 'post',
        'items' => [],
        'customer' => null,
        'deliveryAddress' => null,
        'invoicingAddress' => null,
        'redirectUrls' => null,
        'callbackUrls' => null
    );

    private const MANDATORY_PAYMENT_FIELDS = array(
        'stamp',
        'reference',
        'amount',
        'language',
        'items',
        'customer',
        'deliveryAddress',
        'invoicingAddress',
        'redirectUrls',
        'callbackUrls'
    );

    public function __construct(string $merchantId, string $merchantSecret, string $serviceName = '', string $serverUrl = 'https://api.checkout.fi') {
        $this->merchantId     = $merchantId;
        $this->merchantSecret = $merchantSecret;
        $this->serverUrl      = $serverUrl;
        $this->serviceName = $serviceName;
    }

    public function openPayment(
        string $reference,
        int $amount,
        string $currency,
        string $language,
        array $opts = [],
        string $failureUrl = ''
    ): array {

        $opts = array_merge(
            API::DEFAULT_PAYMENT_OPTS,
            // default stamp to uuidv4, has to be generated outside of the const declaration
            ['stamp' => Uuid::uuid4()],
            $opts
        );

        // assert $items is an array of items?
        Api::arrayAll(function ($item) {
            return get_class($item) == 'Item';
        }, $opts['items']);

        // make sure all parameter vars contain an appropriate object
        $opts['customer'] = $opts['customer'] ?? new Customer();
        $opts['deliveryAddress'] = $opts['deliveryAddress'] ?? new Address();
        $opts['invoicingAddress'] = $opts['invoicingAddress'] ?? new Address();
        $opts['redirectUrls'] = $opts['redirectUrls'] ?? new UrlPair();
        $opts['callbackUrls'] = $opts['callbackUrls'] ?? new UrlPair();

        $body = array_merge(// pick mandatory fields from $opts and map into array for easy passing to json_decode
            Api::exposeMandatoryFields($opts), // merge with the fields not passed through $opts
            [
                'reference' => $reference,
                'amount'    => $amount,
                'currency'  => $currency,
                'language'  => $language,
            ]
        );

        $body = Api::arrayFilter($body);
        $body = json_encode($body);

        $headers = [
            'checkout-account'   => $this->merchantId,
            'checkout-algorithm' => $opts['hmacAlgorithm'],
            'checkout-method'    => 'POST',
            'checkout-nonce'     => $reference,
            'checkout-timestamp' => date('c'),
            'content-type'       => 'application/json; charset=utf-8'
        ];

        $headers['signature'] = Api::calculateHMAC($this->merchantSecret, $opts['hmacAlgorithm'], $headers, $body);

        $client = new \GuzzleHttp\Client(['headers' => $headers]);

        $response = null;
        try {
            $response = $client->post($this->serverUrl . '/payments', ['body' => $body]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {

            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $error = "== {$this->serviceName} Payment GuzzleHttp error ==\n" . "Unexpected HTTP status code: {$response->getStatusCode()}\n" . "Request headers: " . json_encode($headers) . "\n" . "Request body: {$body}\n\n" . "Message: {$e->getMessage()}\n\n";
                $notify = Frc\Slack\Notification::getInstance();
                $notify->sendMessageToSlack($error);
                error_log($error);

                if (!empty($failureUrl)) {
                    session_start();
                    $_SESSION['orderError'] = $e->getMessage();
                    wp_redirect($failureUrl);
                    exit();
                }
            }
        }

        $responseBody = $response->getBody()->getContents();

        // Flatten Guzzle response headers
        $responseHeaders = array_column(array_map(function ($key, $value) {
            return [$key, $value[0]];
        }, array_keys($response->getHeaders()), array_values($response->getHeaders())), 1, 0);

        if (!$this->validateHmac($response->getHeader('signature')[0], $opts['hmacAlgorithm'], $responseHeaders, $responseBody)) {
            $error = "== {$this->serviceName} Payment error ==\n". "Response HMAC signature mismatch!\n" . "Response headers: " . json_encode($responseHeaders) . "\n" . "Response body: " . json_decode($responseBody) . "\n" . "Request headers: " . json_encode($headers) . "\n" . "Request body: {$body}\n\n";
            $notify = Frc\Slack\Notification::getInstance();
            $notify->sendMessageToSlack($error);
            error_log($error);

            if (!empty($failureUrl)) {
                session_start();
                $_SESSION['orderError'] = $error;
                wp_redirect($failureUrl);
                exit();
            }

        }

        error_log("== {$this->serviceName} Payment ==\n\nRequest ID: {$response->getHeader('cof-request-id')[0]}\n\n");

        return ['response' => $response, 'headers' => $responseHeaders, 'body' => json_decode($responseBody)];
    }

    private static function exposeMandatoryFields($opts):array {
        return array_map(function ($field) {
            if (method_exists($field, 'expose')) {
                return Api::arrayFilter($field->expose());
            } elseif (gettype($field) == 'array') {
                return array_map(function ($i) {
                    return Api::arrayFilter($i->expose());
                }, $field);
            }

            return $field;
        }, Api::arrayPick(Api::MANDATORY_PAYMENT_FIELDS, $opts));
    }

    /**
     * Calculate Checkout HMAC
     *
     * @param string $secret Merchant shared secret key
     * @param string $hmacAlgorithm
     * @param array[string]string   $params HTTP headers or query string
     * @param string $body HTTP request body, empty string for GET requests
     *
     * @return string SHA-256 HMAC
     */
    private static function calculateHmac(string $secret, string $hmacAlgorithm, array $params, string $body = '') {
        // Keep only checkout- params, more relevant for response validation. Filter query
        // string parameters the same way - the signature includes only checkout- values.
        $includedKeys = array_filter(array_keys($params), function ($key) {
            return preg_match('/^checkout-/', $key);
        });

        // Keys must be sorted alphabetically
        sort($includedKeys, SORT_STRING);

        $hmacPayload = array_map(function ($key) use ($params) {
            return join(':', [$key, $params[$key]]);
        }, $includedKeys);

        array_push($hmacPayload, $body);
        return hash_hmac($hmacAlgorithm, join("\n", $hmacPayload), $secret);
    }

    protected function validateHmac(string $signature, string $hmacAlgorithm, array $params, string $body = ''):bool {
        $hmac = Api::calculateHmac($this->merchantSecret, $hmacAlgorithm, $params, $body);
        return ($signature === $hmac);
    }

    private static function arrayAll($func, $array):bool {
        return array_reduce($array, function ($carry, $item) use ($func) {
            return $carry ? $func($item) : false;
        }, true);
    }

    private static function arrayPick(array $keys, array $items):array {
        return array_filter($items, function ($v, $k) use ($keys) {
            return in_array($k, $keys);
        }, ARRAY_FILTER_USE_BOTH);
    }

    private static function arrayFilter(array $items) {
        return array_filter($items);
    }


}
