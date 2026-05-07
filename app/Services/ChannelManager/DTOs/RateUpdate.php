<?php

namespace App\Services\ChannelManager\DTOs;



class RateUpdate
{
    public function __construct(
        public readonly int $roomTypeId,
        public readonly int $ratePlanId,
        public readonly string $externalRoomTypeId,
        public readonly string $externalRatePlanId,
        public readonly \DateTimeInterface $date,
        public readonly float $singleRate,
        public readonly float $doubleRate,
        public readonly ?float $extraAdultRate = null,
        public readonly ?float $extraChildRate = null,
        public readonly string $currency = 'INR',
    ) {}
}
