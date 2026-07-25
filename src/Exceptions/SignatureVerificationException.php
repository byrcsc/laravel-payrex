<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * An inbound webhook payload failed signature verification.
 *
 * Answers the delivery with a 400 so PayRex records the failure and retries,
 * while still surfacing to your error reporter - a burst of these means either
 * a misconfigured secret or someone probing the endpoint.
 */
final class SignatureVerificationException extends PayrexException
{
    public function render(Request $request): JsonResponse
    {
        return new JsonResponse(['message' => 'Webhook signature verification failed.'], 400);
    }
}
