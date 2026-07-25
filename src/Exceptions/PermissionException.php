<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Exceptions;

/**
 * The API key is valid but not allowed to perform this operation (HTTP 403).
 */
final class PermissionException extends PayrexException {}
