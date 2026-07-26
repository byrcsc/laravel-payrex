<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Commands;

use BackedEnum;
use ByRcsc\LaravelPayrex\Data\WebhookEvent;
use ByRcsc\LaravelPayrex\Enums\BillingStatementStatus;
use ByRcsc\LaravelPayrex\Enums\CheckoutSessionStatus;
use ByRcsc\LaravelPayrex\Enums\PaymentIntentStatus;
use ByRcsc\LaravelPayrex\Enums\PaymentStatus;
use ByRcsc\LaravelPayrex\Enums\PayoutStatus;
use ByRcsc\LaravelPayrex\Enums\RefundStatus;
use ByRcsc\LaravelPayrex\Enums\SetupIntentStatus;
use ByRcsc\LaravelPayrex\Events\PayrexWebhookEvent;
use ByRcsc\LaravelPayrex\Events\PayrexWebhookReceived;
use ByRcsc\LaravelPayrex\Support\WebhookEventMap;
use Illuminate\Contracts\Events\Dispatcher;

use function Laravel\Prompts\search;

/**
 * Dispatches a made-up event so listeners can be exercised without waiting on
 * a real PayRex delivery.
 *
 * This is a wiring test, not a payload test. Nothing here goes over the
 * network and no signature is involved: the event is built in-process and
 * handed straight to the dispatcher, exactly as the webhook controller would.
 * The resource body is deliberately thin - enough for the DTOs to hydrate, not
 * a faithful copy of what PayRex sends.
 */
final class WebhookTestCommand extends Command
{
    /**
     * Status enums by resource, so a synthetic event can carry a status that
     * really exists rather than one invented to look plausible.
     *
     * @var array<string, class-string<BackedEnum>>
     */
    private const STATUSES = [
        'payment_intent' => PaymentIntentStatus::class,
        'setup_intent' => SetupIntentStatus::class,
        'checkout_session' => CheckoutSessionStatus::class,
        'payment' => PaymentStatus::class,
        'refund' => RefundStatus::class,
        'payout' => PayoutStatus::class,
        'billing_statement' => BillingStatementStatus::class,
    ];

    protected $signature = 'payrex:webhook-test
        {type? : The PayRex event type to dispatch}';

    protected $description = 'Dispatch a synthetic PayRex webhook event to exercise your listeners';

    public function handle(Dispatcher $events): int
    {
        $available = WebhookEventTypes::configured($this->laravel);

        if ($available === []) {
            $this->components->error('No event types are mapped in config/payrex.php, so there is nothing to dispatch.');

            return self::FAILURE;
        }

        $type = $this->resolveType($available);

        if ($type === null) {
            return self::FAILURE;
        }

        $event = WebhookEvent::from($this->syntheticPayload($type));

        $events->dispatch(new PayrexWebhookReceived($event));

        $mapped = $this->mappedEvent($type);

        if ($mapped !== null) {
            $events->dispatch(new $mapped($event));
        }

        $this->components->info("Dispatched a synthetic [{$type}] event as [{$event->id}].");
        $this->components->twoColumnDetail('PayrexWebhookReceived', 'dispatched');
        $this->components->twoColumnDetail($mapped ?? 'Typed event', $mapped === null ? 'none mapped' : 'dispatched');
        $this->newLine();
        $this->components->warn(
            'The payload is synthetic and unsigned. It proves your listeners are wired up, not that they handle real PayRex data.'
        );

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $available
     */
    private function resolveType(array $available): ?string
    {
        $type = $this->argument('type');

        if (is_string($type) && $type !== '') {
            if (! in_array($type, $available, true)) {
                $this->components->error("[{$type}] is not mapped in config/payrex.php.");
                $this->components->bulletList($available);

                return null;
            }

            return $type;
        }

        $chosen = search(
            label: 'Which event type should be dispatched?',
            options: fn (string $value) => $value === ''
                ? $available
                : array_values(array_filter($available, fn (string $option) => str_contains($option, $value))),
        );

        return is_string($chosen) ? $chosen : null;
    }

    /**
     * @return class-string<PayrexWebhookEvent>|null
     */
    private function mappedEvent(string $type): ?string
    {
        return WebhookEventMap::validate(
            $this->laravel->make('config')->get('payrex.webhooks.events', [])
        )[$type] ?? null;
    }

    /**
     * A structurally valid event envelope for the given type.
     *
     * @return array<string, mixed>
     */
    private function syntheticPayload(string $type): array
    {
        $resource = str_contains($type, '.') ? strstr($type, '.', true) : $type;
        $resource = $resource === false ? $type : $resource;

        return [
            'id' => 'evt_test_'.bin2hex(random_bytes(8)),
            'resource' => 'event',
            'type' => $type,
            'livemode' => false,
            'pending_webhooks' => 0,
            'created_at' => time(),
            'updated_at' => time(),
            'data' => [
                'resource' => array_filter([
                    'resource' => $resource,
                    'id' => $this->syntheticId($resource),
                    'amount' => 10000,
                    'currency' => 'PHP',
                    'status' => $this->syntheticStatus($resource, $type),
                    'livemode' => false,
                    'created_at' => time(),
                ], fn (mixed $value): bool => $value !== null),
                'previous_attributes' => null,
            ],
        ];
    }

    private function syntheticId(string $resource): string
    {
        return $resource.'_test_'.bin2hex(random_bytes(6));
    }

    /**
     * The event's own suffix, but only when it names a real case of the
     * resource's status enum. Anything else gets no status at all rather than
     * an invented one.
     */
    private function syntheticStatus(string $resource, string $type): ?string
    {
        $enum = self::STATUSES[$resource] ?? null;

        if ($enum === null) {
            return null;
        }

        $suffix = str_contains($type, '.') ? substr(strrchr($type, '.') ?: '.', 1) : '';

        return $enum::tryFrom($suffix)?->value;
    }
}
