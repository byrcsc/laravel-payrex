<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Http\Middleware;

use ByRcsc\LaravelPayrex\Exceptions\SignatureVerificationException;
use ByRcsc\LaravelPayrex\Support\Payload;
use ByRcsc\LaravelPayrex\Support\WebhookSignature;
use Closure;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects any webhook delivery that is not signed with the configured secret.
 *
 * Verification runs against the raw request body, so this must sit ahead of
 * anything that would rewrite it.
 */
final class VerifyPayrexSignature
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws SignatureVerificationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $payrex = Payload::asObject($this->config->get('payrex')) ?? [];
        $webhooks = Payload::object($payrex, 'webhooks') ?? [];
        $payload = $request->getContent();

        WebhookSignature::verify(
            payload: $payload,
            header: $request->header(Payload::string($webhooks, 'header', 'Payrex-Signature')),
            secret: Payload::string($payrex, 'webhook_secret'),
            tolerance: Payload::int($webhooks, 'tolerance', WebhookSignature::DEFAULT_TOLERANCE),
        );

        return $next($request);
    }
}
