<?php

namespace App\Services\Integrations\PaymentGateway;

use App\Models\Property;

/**
 * Generic payment gateway contract. Any provider (Razorpay, Stripe, PayU, CCAvenue)
 * implements this and is wired into the booking engine + folio settlement.
 */
interface PaymentGateway
{
    public function name(): string;

    /**
     * Create a payment order. Returns a gateway-side order_id + client token
     * the frontend uses to launch the payment widget.
     */
    public function createOrder(Property $property, array $params): array;

    /**
     * Verify a payment after the client returns. Validates signature/HMAC.
     * Returns true if payment is confirmed authentic and successful.
     */
    public function verifyPayment(Property $property, array $params): bool;

    /**
     * Issue a refund.
     */
    public function refund(Property $property, string $paymentId, float $amount, ?string $reason = null): array;

    /**
     * Verify webhook signature. Returns the parsed event payload if valid, null otherwise.
     */
    public function verifyWebhook(Property $property, string $rawBody, array $headers): ?array;
}
