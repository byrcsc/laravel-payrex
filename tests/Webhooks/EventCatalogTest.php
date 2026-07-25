<?php

declare(strict_types=1);

/*
 * The default event map must match the documented catalog exactly - no
 * invented types, none missed.
 * https://docs.payrex.com/docs/api/events/event_types (verified 2026-07-25)
 */
it('maps exactly the documented PayRex event catalog', function () {
    $documented = [
        'billing_statement.created', 'billing_statement.updated', 'billing_statement.deleted',
        'billing_statement.finalized', 'billing_statement.sent', 'billing_statement.marked_uncollectible',
        'billing_statement.voided', 'billing_statement.paid', 'billing_statement.will_be_due',
        'billing_statement.overdue',
        'billing_statement_line_item.created', 'billing_statement_line_item.updated',
        'billing_statement_line_item.deleted',
        'checkout_session.expired',
        'payment_intent.awaiting_capture', 'payment_intent.succeeded',
        'setup_intent.succeeded',
        'payout.created', 'payout.deposited',
        'refund.created', 'refund.updated',
    ];

    $configured = array_keys((array) config('payrex.webhooks.events'));

    sort($documented);
    sort($configured);

    expect($configured)->toBe($documented);
});
