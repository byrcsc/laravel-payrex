<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Events;

/**
 * A refund was opened against a payment.
 */
final class RefundCreated extends PayrexWebhookEvent {}
