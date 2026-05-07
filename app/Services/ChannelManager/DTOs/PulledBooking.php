<?php

namespace App\Services\ChannelManager\DTOs;



class PulledBooking
{
    public function __construct(
        public readonly string $externalBookingId,
        public readonly string $channelCode,    // e.g. 'booking_com', 'mmt'
        public readonly string $channelName,
        public readonly string $action,         // 'new' | 'modified' | 'cancelled'
        public readonly string $guestFirstName,
        public readonly ?string $guestLastName,
        public readonly ?string $guestEmail,
        public readonly ?string $guestPhone,
        public readonly ?string $guestCountry,
        public readonly \DateTimeInterface $arrivalDate,
        public readonly \DateTimeInterface $departureDate,
        public readonly int $adults,
        public readonly int $children,
        public readonly array $rooms,           // [['external_room_type_id'=>..., 'external_rate_plan_id'=>..., 'rate'=>..., 'count'=>...]]
        public readonly float $totalAmount,
        public readonly string $currency,
        public readonly bool $payAtHotel,
        public readonly ?string $specialRequests = null,
        public readonly array $rawPayload = [],
    ) {}
}
