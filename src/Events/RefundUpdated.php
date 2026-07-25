<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Events;

/**
 * A refund changed state - most usefully, it settled or failed.
 */
final class RefundUpdated extends PayrexWebhookEvent {}
