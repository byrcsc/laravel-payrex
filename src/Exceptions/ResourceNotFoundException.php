<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Exceptions;

/**
 * The route exists but the requested resource does not (HTTP 404).
 */
final class ResourceNotFoundException extends PayrexException {}
