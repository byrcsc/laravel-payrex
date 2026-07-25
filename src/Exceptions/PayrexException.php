<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Exceptions;

use ByRcsc\LaravelPayrex\Data\PayrexError;
use ByRcsc\LaravelPayrex\Support\Payload;
use Exception;
use Throwable;

/**
 * Base class for every error surfaced by this package.
 *
 * Catch this to handle anything PayRex-related; catch a subclass to react to a
 * specific failure mode.
 */
class PayrexException extends Exception
{
    /**
     * @param  list<PayrexError>  $errors
     * @param  array<string, mixed>|null  $response
     */
    public function __construct(
        string $message,
        public readonly array $errors = [],
        public readonly ?int $statusCode = null,
        public readonly ?array $response = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode ?? 0, $previous);
    }

    /**
     * Builds the most specific exception available for an API error response.
     *
     * @param  array<string, mixed>|null  $body
     */
    public static function fromResponse(
        int $status,
        ?array $body,
        string $method,
        string $url,
        bool $responseHasBody = false,
    ): self {
        $errors = self::parseErrors($body);
        $summary = self::summarize($errors);

        $class = match (true) {
            $status === 400 => InvalidRequestException::class,
            $status === 401 => AuthenticationException::class,
            $status === 403 => PermissionException::class,
            $status === 404 && $responseHasBody => ResourceNotFoundException::class,
            $status === 404 => RouteNotFoundException::class,
            $status === 422 => InvalidRequestException::class,
            $status === 429 => RateLimitException::class,
            default => ApiErrorException::class,
        };

        if ($class === RouteNotFoundException::class) {
            $summary = "Route {$method} {$url} not found.";
        }

        $message = $summary !== ''
            ? "PayRex API error ({$status}) for {$method} {$url}: {$summary}"
            : "PayRex API error ({$status}) for {$method} {$url}.";

        return new $class($message, $errors, $status, $body);
    }

    /**
     * @return list<PayrexError>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?PayrexError
    {
        return $this->errors[0] ?? null;
    }

    /**
     * Every error reported against a given request parameter.
     *
     * @return list<PayrexError>
     */
    public function errorsFor(string $parameter): array
    {
        return array_values(array_filter(
            $this->errors,
            fn (PayrexError $error): bool => $error->parameter === $parameter,
        ));
    }

    public function hasErrorCode(string $code): bool
    {
        foreach ($this->errors as $error) {
            if ($error->code === $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return list<PayrexError>
     */
    private static function parseErrors(?array $body): array
    {
        if ($body === null) {
            return [];
        }

        return array_map(PayrexError::from(...), Payload::objects($body, 'errors'));
    }

    /**
     * @param  list<PayrexError>  $errors
     */
    private static function summarize(array $errors): string
    {
        return implode('; ', array_map(
            fn (PayrexError $error): string => (string) $error,
            $errors,
        ));
    }
}
