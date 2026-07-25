<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Exceptions;

/**
 * PayRex returned a successful response whose non-empty body was not a JSON object.
 */
final class UnexpectedResponseException extends PayrexException {}
