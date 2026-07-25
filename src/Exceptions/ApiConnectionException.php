<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Exceptions;

/**
 * The PayRex API could not be reached - DNS failure, timeout, or TLS problem.
 */
final class ApiConnectionException extends PayrexException {}
