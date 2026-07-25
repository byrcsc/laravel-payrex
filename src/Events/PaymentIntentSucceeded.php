<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Events;

/**
 * The payer completed a payment and the funds are captured.
 */
final class PaymentIntentSucceeded extends PayrexWebhookEvent {}
