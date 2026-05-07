<?php

namespace App\Services\Integrations\PaymentGateway;

use App\Models\Property;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * RazorpayGateway — Razorpay payment integration.
 *
 * Uses Razorpay's REST API (no SDK dependency).
 * Per-property credentials stored in property->settings or tenant->channel_manager_config
 * (encrypted via Laravel's encrypted cast).
 *
 * Flow:
 *   1. PMS calls createOrder() → Razorpay returns order_id
 *   2. Frontend launches Razorpay checkout with order_id
 *   3. On success, frontend posts (payment_id, signature) back to PMS
 *   4. PMS calls verifyPayment() to confirm signature
 *   5. PMS records Payment row + closes folio
 *
 * Webhook: Razorpay POSTs to /api/webhooks/razorpay/{property_id} on payment events.
 *
 * Refund: PMS calls refund() with payment_id and amount; Razorpay processes.
 */
class RazorpayGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'razorpay';
    }

    public function createOrder(Property $property, array $params): array
    {
        [$keyId, $keySecret] = $this->credentials($property);

        $payload = [
            'amount' => (int) round(($params['amount'] ?? 0) * 100), // paise
            'currency' => $params['currency'] ?? 'INR',
            'receipt' => $params['receipt'] ?? null,
            'notes' => $params['notes'] ?? [],
        ];

        try {
            $response = Http::withBasicAuth($keyId, $keySecret)
                ->acceptJson()
                ->asJson()
                ->timeout(15)
                ->post('https://api.razorpay.com/v1/orders', $payload);

            if (! $response->successful()) {
                throw new \RuntimeException('Razorpay createOrder failed: ' . $response->body());
            }

            $body = $response->json();
            return [
                'order_id' => $body['id'],
                'amount' => $body['amount'] / 100,
                'currency' => $body['currency'],
                'key_id' => $keyId, // Frontend needs this to launch checkout
                'raw' => $body,
            ];
        } catch (\Throwable $e) {
            Log::error('Razorpay order creation failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function verifyPayment(Property $property, array $params): bool
    {
        [$keyId, $keySecret] = $this->credentials($property);

        $orderId = $params['razorpay_order_id'] ?? null;
        $paymentId = $params['razorpay_payment_id'] ?? null;
        $signature = $params['razorpay_signature'] ?? null;

        if (! $orderId || ! $paymentId || ! $signature) return false;

        $expected = hash_hmac('sha256', "{$orderId}|{$paymentId}", $keySecret);
        return hash_equals($expected, $signature);
    }

    public function refund(Property $property, string $paymentId, float $amount, ?string $reason = null): array
    {
        [$keyId, $keySecret] = $this->credentials($property);

        $response = Http::withBasicAuth($keyId, $keySecret)
            ->acceptJson()
            ->asJson()
            ->timeout(15)
            ->post("https://api.razorpay.com/v1/payments/{$paymentId}/refund", [
                'amount' => (int) round($amount * 100),
                'notes' => ['reason' => $reason ?? 'Refund'],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Razorpay refund failed: ' . $response->body());
        }
        return $response->json();
    }

    public function verifyWebhook(Property $property, string $rawBody, array $headers): ?array
    {
        $secret = $this->webhookSecret($property);
        $signature = $headers['x-razorpay-signature'] ?? $headers['X-Razorpay-Signature'] ?? null;
        if (! $signature || ! $secret) return null;

        $expected = hash_hmac('sha256', $rawBody, $secret);
        if (! hash_equals($expected, $signature)) return null;

        return json_decode($rawBody, true);
    }

    private function credentials(Property $property): array
    {
        $settings = $property->settings ?? [];
        $tenantConfig = $property->tenant->channel_manager_config ?? [];

        $keyId = $settings['razorpay_key_id']
            ?? $tenantConfig['razorpay_key_id']
            ?? config('services.razorpay.key_id');
        $keySecret = $settings['razorpay_key_secret']
            ?? $tenantConfig['razorpay_key_secret']
            ?? config('services.razorpay.key_secret');

        if (! $keyId || ! $keySecret) {
            throw new \RuntimeException('Razorpay credentials not configured for property ' . $property->id);
        }
        return [$keyId, $keySecret];
    }

    private function webhookSecret(Property $property): ?string
    {
        return $property->settings['razorpay_webhook_secret']
            ?? config('services.razorpay.webhook_secret');
    }
}
