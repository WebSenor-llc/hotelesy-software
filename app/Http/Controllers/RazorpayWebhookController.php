<?php

namespace App\Http\Controllers;

use App\Models\License;
use App\Models\SubscriptionTransaction;
use App\Services\RazorpayService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Razorpay webhook receiver. Configure in Razorpay dashboard:
 *   URL: https://yourdomain.com/webhooks/razorpay
 *   Secret: env RAZORPAY_WEBHOOK_SECRET
 * Subscribe to: payment.authorized, payment.captured, payment.failed,
 *               subscription.charged, subscription.cancelled
 */
class RazorpayWebhookController extends Controller
{
    public function handle(Request $request, RazorpayService $razorpay)
    {
        $raw = $request->getContent();
        $sig = (string) $request->header('X-Razorpay-Signature');

        if (! $razorpay->verifyWebhook($raw, $sig)) {
            Log::warning('Razorpay webhook signature mismatch');
            return response()->json(['ok' => false], 401);
        }

        $event = $request->input('event');
        $payload = $request->input('payload', []);

        app(TenantContext::class)->bypass(function () use ($event, $payload) {
            switch ($event) {
                case 'payment.captured':
                case 'payment.authorized':
                    $this->onPaymentSuccess($payload);
                    break;
                case 'payment.failed':
                    $this->onPaymentFailed($payload);
                    break;
                case 'subscription.charged':
                    $this->onSubscriptionCharged($payload);
                    break;
                case 'subscription.cancelled':
                    $this->onSubscriptionCancelled($payload);
                    break;
                default:
                    Log::info('Razorpay webhook ignored: ' . $event);
            }
        });

        return response()->json(['ok' => true]);
    }

    private function onPaymentSuccess(array $payload): void
    {
        $payment = $payload['payment']['entity'] ?? [];
        $orderId = $payment['order_id'] ?? null;
        $paymentId = $payment['id'] ?? null;
        if (! $orderId) return;

        $tx = SubscriptionTransaction::where('razorpay_order_id', $orderId)->first();
        if (! $tx) return;

        $tx->update([
            'status' => SubscriptionTransaction::STATUS_CAPTURED,
            'razorpay_payment_id' => $paymentId,
            'paid_at' => now(),
            'payment_method' => $payment['method'] ?? null,
            'razorpay_payload' => array_merge($tx->razorpay_payload ?? [], ['webhook' => $payment]),
        ]);

        if ($tx->license_id) {
            $license = License::find($tx->license_id);
            if ($license) {
                if ($tx->is_mandate) {
                    $license->update(['mandate_status' => 'active']);
                } else {
                    $license->update(['status' => License::STATUS_ACTIVE]);
                }
            }
        }
    }

    private function onPaymentFailed(array $payload): void
    {
        $payment = $payload['payment']['entity'] ?? [];
        $orderId = $payment['order_id'] ?? null;
        if (! $orderId) return;

        $tx = SubscriptionTransaction::where('razorpay_order_id', $orderId)->first();
        if (! $tx) return;
        $tx->update([
            'status' => SubscriptionTransaction::STATUS_FAILED,
            'failure_reason' => $payment['error_description'] ?? 'Unknown',
        ]);
    }

    private function onSubscriptionCharged(array $payload): void
    {
        $sub = $payload['subscription']['entity'] ?? [];
        $payment = $payload['payment']['entity'] ?? [];
        $subId = $sub['id'] ?? null;
        if (! $subId) return;

        $license = License::where('razorpay_subscription_id', $subId)->first();
        if (! $license) return;

        SubscriptionTransaction::create([
            'tenant_id' => $license->tenant_id,
            'license_id' => $license->id,
            'subscription_plan_id' => $license->subscription_plan_id,
            'type' => SubscriptionTransaction::TYPE_SUBSCRIPTION,
            'status' => SubscriptionTransaction::STATUS_CAPTURED,
            'billing_cycle' => 'monthly',
            'amount' => ($payment['amount'] ?? 0) / 100,
            'currency' => $payment['currency'] ?? 'INR',
            'razorpay_order_id' => $payment['order_id'] ?? null,
            'razorpay_payment_id' => $payment['id'] ?? null,
            'razorpay_subscription_id' => $subId,
            'invoice_number' => SubscriptionTransaction::generateInvoiceNumber(),
            'description' => 'Auto-renewal — monthly',
            'paid_at' => now(),
            'razorpay_payload' => $payload,
        ]);

        $license->update([
            'status' => License::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
            'next_billing_at' => now()->addMonth(),
            'last_validated_at' => now(),
            'billing_cycle' => 'monthly',
        ]);
    }

    private function onSubscriptionCancelled(array $payload): void
    {
        $sub = $payload['subscription']['entity'] ?? [];
        $subId = $sub['id'] ?? null;
        if (! $subId) return;
        $license = License::where('razorpay_subscription_id', $subId)->first();
        if (! $license) return;
        $license->update([
            'auto_renew' => false,
            'mandate_status' => 'cancelled',
        ]);
    }
}
