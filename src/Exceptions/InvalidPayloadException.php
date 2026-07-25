<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * An inbound webhook body passed signature verification but was not a
 * well-formed PayRex event.
 */
final class InvalidPayloadException extends PayrexException
{
    public function render(Request $request): JsonResponse
    {
        return new JsonResponse(['message' => 'Webhook payload could not be parsed.'], 400);
    }
}
