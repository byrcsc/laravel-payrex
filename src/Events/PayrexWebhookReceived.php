<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Events;

/**
 * Dispatched for every webhook delivery that passes signature verification, whatever its type. Listen here to handle types this package does not map to a class of their own.
 */
final class PayrexWebhookReceived extends PayrexWebhookEvent {}
