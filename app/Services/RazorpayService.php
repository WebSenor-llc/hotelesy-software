<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Thin Razorpay wrapper. Falls back to a stub when keys aren't configured
 * (so the dev environment works without live credentials). All real calls
 * go to https://api.razorpay.com.
 *
 * Methods implemented:
 *   - createOrder() : one-time payment (monthly / yearly upfront)
 *   - createSubscription() : recurring monthly subscription
 *   - createMandateOrder() : ₹1 mandate-auth charge (used for free trial)
 *   - verifyWebhook() : HMAC verify the webhook signature
 *   - verifyPaymentSignature() : verify checkout response
 */
class RazorpayService
{
    private string $keyId;
    private string $keySecret;
    private bool $stubMode;

    public function __construct()
    {
        $this->keyId = (string) config('razorpay.key_id');
        $this->keySecret = (string) config('razorpay.key_secret');
        $this->stubMode = empty($this->keyId) || empty($this->keySecret);
    }

    public function isStubMode(): bool
    {
        return $this->stubMode;
    }

    public function publicKey(): string
    {
        return $this->keyId ?: 'rzp_test_stub';
    }

    /**
     * Create a one-time order — used for "Pay ₹X for monthly/yearly".
     * amountInPaise: integer (e.g. 99900 = ₹999.00).
     * Returns array: ['id' => 'order_xxx', 'amount' => N, 'currency' => 'INR', ...]
     */
    public function createOrder(int $amountInPaise, string $receipt, array $notes = []): array
    {
        if ($this->stubMode) {
            return [
                'id'       => 'order_stub_' . Str::random(14),
                'amount'   => $amountInPaise,
                'currency' => config('razorpay.currency'),
                'status'   => 'created',
                'receipt'  => $receipt,
                'stub'     => true,
            ];
        }

        $resp = Http::withBasicAuth($this->keyId, $this->keySecret)
            ->acceptJson()
            ->post('https://api.razorpay.com/v1/orders', [
                'amount'   => $amountInPaise,
                'currency' => config('razorpay.currency'),
                'receipt'  => $receipt,
                'notes'    => $notes,
            ]);

        if (! $resp->successful()) {
            Log::warning('Razorpay createOrder failed', ['body' => $resp->body()]);
            throw new \RuntimeException('Razorpay order creation failed: ' . $resp->body());
        }
        return $resp->json();
    }

    /**
     * Create a recurring subscription. Used for trial → ₹999/mo.
     * planId: a Razorpay plan ID configured in their dashboard.
     * customerNotify: 1 to send Razorpay's notification, 0 to silence.
     */
    public function createSubscription(string $planId, int $totalCount = 12, ?int $startAt = null, array $notes = []): array
    {
        if ($this->stubMode) {
            return [
                'id'           => 'sub_stub_' . Str::random(14),
                'plan_id'      => $planId,
                'status'       => 'created',
                'total_count'  => $totalCount,
                'start_at'     => $startAt ?? now()->addDays(config('razorpay.trial_days', 30))->timestamp,
                'short_url'    => null,
                'stub'         => true,
            ];
        }

        $payload = [
            'plan_id'         => $planId,
            'total_count'     => $totalCount,
            'customer_notify' => 1,
            'notes'           => $notes,
        ];
        if ($startAt) $payload['start_at'] = $startAt;

        $resp = Http::withBasicAuth($this->keyId, $this->keySecret)
            ->acceptJson()
            ->post('https://api.razorpay.com/v1/subscriptions', $payload);

        if (! $resp->successful()) {
            Log::warning('Razorpay createSubscription failed', ['body' => $resp->body()]);
            throw new \RuntimeException('Razorpay subscription creation failed: ' . $resp->body());
        }
        return $resp->json();
    }

    /**
     * Mandate-authorisation order: ₹1 charge that captures a UPI mandate
     * so future automatic charges (₹999/month) can debit the user.
     * Razorpay treats this as a normal order with the "method:upi" + recurring="true".
     * On the front-end, the user signs the UPI mandate during checkout.
     */
    public function createMandateOrder(string $receipt, array $notes = []): array
    {
        $amount = (int) config('razorpay.mandate_amount_paise', 100);
        return $this->createOrder($amount, $receipt, array_merge($notes, ['mandate' => 'true']));
    }

    /**
     * Verify checkout response signature.
     * orderId + paymentId returned from Razorpay → signature must match HMAC(orderId|paymentId, secret).
     */
    public function verifyPaymentSignature(string $orderId, string $paymentId, string $signature): bool
    {
        if ($this->stubMode) {
            return str_starts_with($paymentId, 'pay_stub_') || str_starts_with($orderId, 'order_stub_');
        }
        $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, $this->keySecret);
        return hash_equals($expected, $signature);
    }

    /**
     * Verify Razorpay webhook signature (X-Razorpay-Signature header).
     */
    public function verifyWebhook(string $rawBody, string $signature): bool
    {
        $secret = (string) config('razorpay.webhook_secret');
        if (empty($secret)) return $this->stubMode;
        $expected = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, $signature);
    }
}
