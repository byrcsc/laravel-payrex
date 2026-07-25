<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Events;

use ByRcsc\LaravelPayrex\Data\WebhookEvent;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Base class for every webhook event this package dispatches.
 *
 * Type-hint this in a listener to catch all of them, or type-hint a subclass to
 * narrow to a single PayRex event type.
 */
abstract class PayrexWebhookEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly WebhookEvent $event,
    ) {}

    /**
     * The PayRex event type, e.g. `payment_intent.succeeded`.
     */
    public function type(): string
    {
        return $this->event->type;
    }
}
