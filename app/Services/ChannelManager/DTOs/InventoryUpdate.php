<?php

namespace App\Services\ChannelManager\DTOs;



class InventoryUpdate
{
    public function __construct(
        public readonly int $roomTypeId,
        public readonly string $externalRoomTypeId,
        public readonly \DateTimeInterface $date,
        public readonly int $availableRooms,
        public readonly bool $stopSell = false,
    ) {}
}
