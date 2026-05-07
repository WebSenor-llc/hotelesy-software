<?php

namespace App\Services\ChannelManager\Drivers;

use App\Models\ChannelSyncLog;
use App\Models\Property;
use App\Services\ChannelManager\Contracts\ChannelManagerDriver;
use App\Services\ChannelManager\DTOs\PulledBooking;
use App\Services\ChannelManager\DTOs\SyncResult;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AxisRooms channel manager driver.
 *
 * AxisRooms uses a hybrid REST + XML approach. The endpoint structure below
 * is based on AxisRooms' published partner documentation patterns; exact
 * field names will need verification against the partner kit Piyush receives
 * after signing the partner agreement.
 *
 * Auth: HTTP Basic with username + password per hotel.
 * Production base: https://api.axisrooms.com/api
 *
 * KNOWN ENDPOINTS (from AxisRooms partner docs, subject to change):
 *  - POST /booking/v3/availability     -> push inventory
 *  - POST /booking/v3/rates            -> push rates
 *  - POST /booking/v3/restriction      -> push restrictions
 *  - GET  /booking/v3/reservations     -> pull bookings
 *  - POST /booking/v3/acknowledge      -> ack pulled booking
 *
 * IMPORTANT: This driver is structurally complete but the exact XML/JSON
 * field schema must be verified against AxisRooms' current partner spec.
 * Do NOT ship to production without sandbox testing against their staging.
 */
class AxisRoomsDriver implements ChannelManagerDriver
{
    public function name(): string
    {
        return 'axisrooms';
    }

    public function ping(Property $property): bool
    {
        try {
            $response = $this->http($property)->timeout(10)->get('/ping');
            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('AxisRooms ping failed', [
                'property_id' => $property->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function pushInventory(Property $property, Collection $updates): SyncResult
    {
        $startedAt = microtime(true);

        $payload = [
            'hotel_id' => $this->hotelId($property),
            'inventory' => $updates->map(fn($u) => [
                'room_type_id' => $u->externalRoomTypeId,
                'date' => $u->date->format('Y-m-d'),
                'available' => $u->availableRooms,
                'stop_sell' => $u->stopSell ? 1 : 0,
            ])->values()->all(),
        ];

        $log = $this->openLog($property, 'inventory_push', $payload);

        try {
            $response = $this->http($property)
                ->post('/booking/v3/availability', $payload);

            $body = $response->json() ?? [];
            $success = $response->successful() && ($body['status'] ?? null) === 'success';

            $this->closeLog(
                $log,
                $success ? 'success' : 'failed',
                $body,
                $updates->count(),
                $success ? 0 : $updates->count(),
                $body['message'] ?? null,
                $startedAt
            );

            return $success
                ? SyncResult::success($updates->count(), $payload, $body)
                : SyncResult::failure($body['message'] ?? 'Push failed', $payload, $body);

        } catch (\Throwable $e) {
            $this->closeLog($log, 'failed', [], 0, $updates->count(), $e->getMessage(), $startedAt);
            return SyncResult::failure($e->getMessage(), $payload);
        }
    }

    public function pushRates(Property $property, Collection $updates): SyncResult
    {
        $startedAt = microtime(true);

        $payload = [
            'hotel_id' => $this->hotelId($property),
            'rates' => $updates->map(fn($u) => [
                'room_type_id' => $u->externalRoomTypeId,
                'rate_plan_id' => $u->externalRatePlanId,
                'date' => $u->date->format('Y-m-d'),
                'single_rate' => $u->singleRate,
                'double_rate' => $u->doubleRate,
                'extra_adult' => $u->extraAdultRate,
                'extra_child' => $u->extraChildRate,
                'currency' => $u->currency,
            ])->values()->all(),
        ];

        $log = $this->openLog($property, 'rate_push', $payload);

        try {
            $response = $this->http($property)
                ->post('/booking/v3/rates', $payload);

            $body = $response->json() ?? [];
            $success = $response->successful() && ($body['status'] ?? null) === 'success';

            $this->closeLog(
                $log,
                $success ? 'success' : 'failed',
                $body,
                $updates->count(),
                $success ? 0 : $updates->count(),
                $body['message'] ?? null,
                $startedAt
            );

            return $success
                ? SyncResult::success($updates->count(), $payload, $body)
                : SyncResult::failure($body['message'] ?? 'Push failed', $payload, $body);

        } catch (\Throwable $e) {
            $this->closeLog($log, 'failed', [], 0, $updates->count(), $e->getMessage(), $startedAt);
            return SyncResult::failure($e->getMessage(), $payload);
        }
    }

    public function pushRestrictions(Property $property, Collection $updates): SyncResult
    {
        $startedAt = microtime(true);

        $payload = [
            'hotel_id' => $this->hotelId($property),
            'restrictions' => $updates->map(fn($u) => [
                'room_type_id' => $u->externalRoomTypeId,
                'rate_plan_id' => $u->externalRatePlanId,
                'date' => $u->date->format('Y-m-d'),
                'min_stay' => $u->minStay,
                'max_stay' => $u->maxStay,
                'closed_to_arrival' => $u->closedToArrival ? 1 : 0,
                'closed_to_departure' => $u->closedToDeparture ? 1 : 0,
                'stop_sell' => $u->stopSell ? 1 : 0,
            ])->values()->all(),
        ];

        $log = $this->openLog($property, 'restriction_push', $payload);

        try {
            $response = $this->http($property)
                ->post('/booking/v3/restriction', $payload);

            $body = $response->json() ?? [];
            $success = $response->successful() && ($body['status'] ?? null) === 'success';

            $this->closeLog(
                $log,
                $success ? 'success' : 'failed',
                $body,
                $updates->count(),
                $success ? 0 : $updates->count(),
                $body['message'] ?? null,
                $startedAt
            );

            return $success
                ? SyncResult::success($updates->count(), $payload, $body)
                : SyncResult::failure($body['message'] ?? 'Push failed', $payload, $body);

        } catch (\Throwable $e) {
            $this->closeLog($log, 'failed', [], 0, $updates->count(), $e->getMessage(), $startedAt);
            return SyncResult::failure($e->getMessage(), $payload);
        }
    }

    public function pullBookings(Property $property, ?\DateTimeInterface $since = null): Collection
    {
        $log = $this->openLog($property, 'booking_pull', []);
        $startedAt = microtime(true);

        try {
            $response = $this->http($property)->get('/booking/v3/reservations', [
                'hotel_id' => $this->hotelId($property),
                'modified_after' => $since?->format('Y-m-d\TH:i:s') ?? now()->subHours(2)->format('Y-m-d\TH:i:s'),
            ]);

            if (!$response->successful()) {
                $this->closeLog($log, 'failed', $response->json() ?? [], 0, 0, 'Pull failed: ' . $response->status(), $startedAt);
                return collect();
            }

            $body = $response->json();
            $bookings = collect($body['bookings'] ?? [])->map(fn($b) => $this->normaliseBooking($b));

            $this->closeLog($log, 'success', $body, $bookings->count(), 0, null, $startedAt);
            return $bookings;

        } catch (\Throwable $e) {
            $this->closeLog($log, 'failed', [], 0, 0, $e->getMessage(), $startedAt);
            return collect();
        }
    }

    public function acknowledgeBooking(Property $property, string $externalBookingId): bool
    {
        try {
            $response = $this->http($property)->post('/booking/v3/acknowledge', [
                'hotel_id' => $this->hotelId($property),
                'booking_id' => $externalBookingId,
            ]);
            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('AxisRooms acknowledge failed', [
                'booking_id' => $externalBookingId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /* ---------------- helpers ---------------- */

    private function http(Property $property): PendingRequest
    {
        $config = $this->config($property);

        return Http::baseUrl($config['base_url'])
            ->withBasicAuth($config['username'], $config['password'])
            ->timeout($config['timeout'])
            ->acceptJson()
            ->asJson()
            ->retry(2, 500, throw: false);
    }

    private function config(Property $property): array
    {
        $tenantConfig = $property->tenant->channel_manager_config ?? [];

        return [
            'base_url' => $tenantConfig['axisrooms_base_url'] ?? config('services.axisrooms.base_url'),
            'username' => $tenantConfig['axisrooms_username'] ?? config('services.axisrooms.username'),
            'password' => $tenantConfig['axisrooms_password'] ?? config('services.axisrooms.password'),
            'hotel_id' => $tenantConfig['axisrooms_hotel_id'] ?? null,
            'timeout' => $tenantConfig['axisrooms_timeout'] ?? config('services.axisrooms.timeout', 30),
        ];
    }

    private function hotelId(Property $property): string
    {
        $hotelId = $this->config($property)['hotel_id'];
        if (empty($hotelId)) {
            throw new \RuntimeException("AxisRooms hotel_id not configured for property {$property->id}");
        }
        return $hotelId;
    }

    private function normaliseBooking(array $b): PulledBooking
    {
        return new PulledBooking(
            externalBookingId: (string) $b['booking_id'],
            channelCode: (string) ($b['channel_code'] ?? 'unknown'),
            channelName: (string) ($b['channel_name'] ?? 'Unknown'),
            action: $b['status'] ?? 'new',
            guestFirstName: (string) ($b['guest']['first_name'] ?? 'Guest'),
            guestLastName: $b['guest']['last_name'] ?? null,
            guestEmail: $b['guest']['email'] ?? null,
            guestPhone: $b['guest']['phone'] ?? null,
            guestCountry: $b['guest']['country'] ?? 'IN',
            arrivalDate: new \DateTimeImmutable($b['check_in']),
            departureDate: new \DateTimeImmutable($b['check_out']),
            adults: (int) ($b['adults'] ?? 1),
            children: (int) ($b['children'] ?? 0),
            rooms: $b['rooms'] ?? [],
            totalAmount: (float) ($b['total_amount'] ?? 0),
            currency: (string) ($b['currency'] ?? 'INR'),
            payAtHotel: (bool) ($b['pay_at_hotel'] ?? false),
            specialRequests: $b['special_requests'] ?? null,
            rawPayload: $b,
        );
    }

    private function openLog(Property $property, string $operation, array $payload): ChannelSyncLog
    {
        return ChannelSyncLog::create([
            'tenant_id' => $property->tenant_id,
            'property_id' => $property->id,
            'channel' => 'axisrooms',
            'direction' => str_contains($operation, 'pull') ? 'pull' : 'push',
            'operation' => $operation,
            'payload_sent' => $payload,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    private function closeLog(
        ChannelSyncLog $log,
        string $status,
        array $response,
        int $processed,
        int $failed,
        ?string $error,
        float $startedAt
    ): void {
        $log->update([
            'status' => $status,
            'payload_received' => $response,
            'records_processed' => $processed,
            'records_failed' => $failed,
            'error_message' => $error,
            'completed_at' => now(),
            'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
        ]);
    }
}
