<?php

namespace App\Services\ChannelManager\DTOs;



class RestrictionUpdate
{
    public function __construct(
        public readonly int $roomTypeId,
        public readonly ?int $ratePlanId,
        public readonly string $externalRoomTypeId,
        public readonly ?string $externalRatePlanId,
        public readonly \DateTimeInterface $date,
        public readonly ?int $minStay = null,
        public readonly ?int $maxStay = null,
        public readonly bool $closedToArrival = false,
        public readonly bool $closedToDeparture = false,
        public readonly bool $stopSell = false,
    ) {}
}
