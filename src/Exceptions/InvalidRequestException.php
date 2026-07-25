<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Exceptions;

/**
 * The request was rejected as invalid (HTTP 400/422). Inspect `errors()` for the offending parameters.
 */
final class InvalidRequestException extends PayrexException {}
