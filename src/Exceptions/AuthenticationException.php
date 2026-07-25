<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Exceptions;

/**
 * The secret API key was missing, malformed, or rejected (HTTP 401).
 */
final class AuthenticationException extends PayrexException {}
