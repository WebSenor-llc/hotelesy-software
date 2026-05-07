<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Folio;
use App\Models\Reservation;
use App\Services\Billing\FolioService;
use App\Services\Billing\PaymentService;
use App\Services\FrontOffice\CheckInService;
use App\Services\FrontOffice\CheckOutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FrontOfficeController extends Controller
{
    public function __construct(
        private readonly CheckInService $checkIn,
        private readonly CheckOutService $checkOut,
        private readonly FolioService $folio,
        private readonly PaymentService $payments,
    ) {}

    public function checkInAction(Request $request, Reservation $reservation): JsonResponse
    {
        $data = $request->validate([
            'arrival_time' => ['nullable', 'date_format:H:i'],
            'room_assignments' => ['nullable', 'array'],
            'room_assignments.*' => ['integer', 'exists:rooms,id'],
            'id_type' => ['nullable', 'string', 'max:50'],
            'id_number' => ['nullable', 'string', 'max:100'],
            'key_card' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $reservation = $this->checkIn->checkIn($reservation, $data);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $reservation]);
    }

    public function checkOutAction(Request $request, Reservation $reservation): JsonResponse
    {
        $data = $request->validate([
            'departure_time' => ['nullable', 'date_format:H:i'],
            'force_checkout' => ['nullable', 'boolean'],
        ]);

        try {
            $reservation = $this->checkOut->checkOut($reservation, $data);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $reservation]);
    }

    public function postCharge(Request $request, Folio $folio): JsonResponse
    {
        $data = $request->validate([
            'charge_date' => ['nullable', 'date'],
            'charge_time' => ['nullable', 'date_format:H:i:s'],
            'business_date' => ['nullable', 'date'],
            'category' => ['required', Rule::in([
                'room', 'food', 'beverage', 'laundry', 'mini_bar', 'telephone',
                'spa', 'misc', 'damage', 'extra_bed', 'package', 'discount',
                'service_charge', 'tax', 'transfer', 'adjustment',
            ])],
            'description' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'amount' => ['required', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'skip_tax' => ['nullable', 'boolean'],
        ]);

        try {
            $charge = $this->folio->postCharge($folio, $data);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $charge], 201);
    }

    public function recordPayment(Request $request, Folio $folio): JsonResponse
    {
        $data = $request->validate([
            'payment_date' => ['nullable', 'date'],
            'business_date' => ['nullable', 'date'],
            'mode' => ['required', Rule::in([
                'cash', 'card', 'upi', 'bank_transfer', 'cheque',
                'company_credit', 'advance_adjust', 'gateway', 'refund',
            ])],
            'amount' => ['required', 'numeric'],
            'currency' => ['nullable', 'string', 'size:3'],
            'card_last4' => ['nullable', 'string', 'size:4'],
            'card_brand' => ['nullable', 'string', 'max:20'],
            'card_holder_name' => ['nullable', 'string', 'max:100'],
            'approval_code' => ['nullable', 'string', 'max:50'],
            'upi_reference' => ['nullable', 'string', 'max:100'],
            'transaction_reference' => ['nullable', 'string', 'max:100'],
            'gateway_payment_id' => ['nullable', 'string', 'max:100'],
            'cheque_number' => ['nullable', 'string', 'max:50'],
            'cheque_date' => ['nullable', 'date'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $payment = $this->payments->record($folio, $data);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $payment], 201);
    }

    public function settle(Folio $folio): JsonResponse
    {
        $folio->recomputeTotals();
        $folio->refresh();

        if (abs((float) $folio->balance) > 0.01) {
            return response()->json([
                'message' => "Cannot settle folio {$folio->folio_number}: balance is {$folio->balance}.",
            ], 422);
        }

        $folio->update([
            'status' => 'settled',
            'settled_at' => now(),
            'settled_by' => auth()->id(),
        ]);

        return response()->json(['data' => $folio->fresh()]);
    }
}
