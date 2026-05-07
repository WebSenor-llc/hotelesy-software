<?php

namespace App\Services\ChannelManager\DTOs;



class SyncResult
{
    public function __construct(
        public readonly bool $success,
        public readonly int $recordsProcessed = 0,
        public readonly int $recordsFailed = 0,
        public readonly ?string $errorMessage = null,
        public readonly array $rawRequest = [],
        public readonly array $rawResponse = [],
        public readonly ?string $externalReferenceId = null,
    ) {}

    public static function success(int $processed, array $request = [], array $response = []): self
    {
        return new self(true, $processed, 0, null, $request, $response);
    }

    public static function failure(string $error, array $request = [], array $response = []): self
    {
        return new self(false, 0, 1, $error, $request, $response);
    }
}
