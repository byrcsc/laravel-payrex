<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Exceptions;

/**
 * Too many requests were sent in too short a window (HTTP 429).
 */
final class RateLimitException extends PayrexException {}
