<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Data;

use ByRcsc\LaravelPayrex\Enums\WebhookStatus;
use ByRcsc\LaravelPayrex\Support\Payload;
use Carbon\CarbonImmutable;

/**
 * A webhook endpoint registered with PayRex.
 *
 * `secretKey` is the signing secret for this endpoint - the value that belongs
 * in `PAYREX_WEBHOOK_SECRET`. PayRex only returns it in full when the endpoint
 * is created.
 */
final readonly class WebhookEndpoint
{
    /**
     * @param  list<string>  $events
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public ?string $url = null,
        public ?WebhookStatus $status = null,
        public ?string $description = null,
        public ?string $secretKey = null,
        public array $events = [],
        public ?bool $livemode = null,
        public ?CarbonImmutable $createdAt = null,
        public ?CarbonImmutable $updatedAt = null,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function from(array $data): self
    {
        return new self(
            id: Payload::string($data, 'id'),
            url: Payload::nullableString($data, 'url'),
            status: Payload::enum($data, 'status', WebhookStatus::class),
            description: Payload::nullableString($data, 'description'),
            secretKey: Payload::nullableString($data, 'secret_key'),
            events: Payload::strings($data, 'events'),
            livemode: Payload::nullableBool($data, 'livemode'),
            createdAt: Payload::dateTime($data, 'created_at'),
            updatedAt: Payload::dateTime($data, 'updated_at'),
            raw: $data,
        );
    }

    public function isEnabled(): bool
    {
        return $this->status === WebhookStatus::Enabled;
    }
}
