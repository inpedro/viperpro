<?php

namespace Tests\Feature\Integrations;

use Stripe\HttpClient\ClientInterface;

/**
 * In-memory Stripe API for tests (plugged in with ApiRequestor::setHttpClient). Implements just
 * the endpoints the adapter uses, with response shapes from the Stripe API reference.
 */
class FakeStripeClient implements ClientInterface
{
    public array $requests = [];
    public array $sessions = [];
    public bool $failRefunds = false;

    public function request($method, $absUrl, $headers, $params, $hasFile)
    {
        $this->requests[] = compact('method', 'absUrl', 'headers', 'params');
        $path = parse_url($absUrl, PHP_URL_PATH);

        if ($method === 'post' && $path === '/v1/checkout/sessions') {
            $id = 'cs_test_'.(count($this->sessions) + 1);
            $this->sessions[$id] = [
                'id' => $id, 'object' => 'checkout.session', 'url' => "https://checkout.stripe.com/c/pay/{$id}",
                'amount_total' => (int) $params['line_items'][0]['price_data']['unit_amount'],
                'currency' => $params['line_items'][0]['price_data']['currency'],
                'payment_status' => 'unpaid', 'status' => 'open', 'payment_intent' => 'pi_'.$id,
                'metadata' => $params['metadata'],
            ];

            return [json_encode($this->sessions[$id]), 200, []];
        }

        if ($method === 'get' && preg_match('#^/v1/checkout/sessions/(cs_[A-Za-z0-9_]+)$#', $path, $m)) {
            return isset($this->sessions[$m[1]])
                ? [json_encode($this->sessions[$m[1]]), 200, []]
                : [json_encode(['error' => ['type' => 'invalid_request_error', 'message' => 'No such checkout session']]), 404, []];
        }

        if ($method === 'post' && $path === '/v1/refunds') {
            return $this->failRefunds
                ? [json_encode(['error' => ['type' => 'invalid_request_error', 'message' => 'Refund failed']]), 400, []]
                : [json_encode(['id' => 're_1', 'object' => 'refund', 'status' => 'succeeded', 'amount' => (int) $params['amount']]), 200, []];
        }

        return [json_encode(['error' => ['type' => 'invalid_request_error', 'message' => "unhandled {$method} {$path}"]]), 404, []];
    }

    public function markPaid(string $id): void
    {
        $this->sessions[$id]['payment_status'] = 'paid';
        $this->sessions[$id]['status'] = 'complete';
    }
}
