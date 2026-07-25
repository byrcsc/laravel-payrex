<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Exceptions;

/**
 * PayRex returned an error this package does not map to a more specific type.
 */
final class ApiErrorException extends PayrexException {}
