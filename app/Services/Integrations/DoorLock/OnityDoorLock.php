<?php

namespace App\Services\Integrations\DoorLock;

use App\Models\Property;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Onity HT24 / Advance door lock adapter.
 *
 * Onity provides the "Onity Front Desk Encoder Software" which exposes a
 * local HTTP API at http://localhost:8080/onity/api on the front desk PC.
 * The encoder is a USB device that writes to RFID cards.
 *
 * Mobile keys (BLE / NFC) require the Onity OnPortal cloud and a separate
 * partner agreement for the SDK.
 *
 * IMPORTANT: This is a stub. Real implementation requires:
 *   1. Onity Encoder Software installed on each front desk PC
 *   2. Property-specific Onity site code (provided by Onity)
 *   3. Network reachability between PMS and front-desk PCs
 *   4. Onity partner certification (typically 4-8 weeks)
 *
 * To go live, fill in encodeCard() with the actual Onity local API call shape.
 */
class OnityDoorLock implements DoorLock
{
    public function name(): string
    {
        return 'onity';
    }

    public function issueKey(
        Property $property,
        Reservation $reservation,
        Room $room,
        ?\DateTimeInterface $validFrom = null,
        ?\DateTimeInterface $validUntil = null
    ): array {
        $payload = [
            'site_code' => $this->siteCode($property),
            'room_number' => $room->room_number,
            'guest_name' => $reservation->guest_name,
            'reservation_number' => $reservation->reservation_number,
            'valid_from' => ($validFrom ?? $reservation->arrival_date)->format('Y-m-d\TH:i:s'),
            'valid_until' => ($validUntil ?? $reservation->departure_date)->format('Y-m-d\TH:i:s'),
            'access_level' => 'guest',
            'allow_extra_zones' => false,
        ];

        try {
            $response = Http::baseUrl($this->encoderUrl($property))
                ->timeout(15)
                ->acceptJson()
                ->asJson()
                ->post('/onity/api/encode', $payload);

            if (! $response->successful()) {
                throw new \RuntimeException('Onity encode failed: ' . $response->body());
            }
            $body = $response->json();
            return [
                'key_reference' => $body['key_id'] ?? null,
                'encoded_at' => now()->toIso8601String(),
                'raw' => $body,
            ];
        } catch (\Throwable $e) {
            Log::error('Onity issueKey failed', [
                'property_id' => $property->id,
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function revokeKey(Property $property, string $keyReference): bool
    {
        try {
            $response = Http::baseUrl($this->encoderUrl($property))
                ->timeout(10)
                ->post('/onity/api/revoke', ['key_id' => $keyReference]);
            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('Onity revokeKey failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function ping(Property $property): bool
    {
        try {
            return Http::baseUrl($this->encoderUrl($property))->timeout(3)->get('/onity/api/health')->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    private function encoderUrl(Property $property): string
    {
        return $property->settings['onity_encoder_url']
            ?? config('services.onity.encoder_url', 'http://localhost:8080');
    }

    private function siteCode(Property $property): string
    {
        $code = $property->settings['onity_site_code'] ?? null;
        if (! $code) throw new \RuntimeException('Onity site_code not configured for property ' . $property->id);
        return $code;
    }
}
