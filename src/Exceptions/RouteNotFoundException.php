<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Exceptions;

/**
 * PayRex has no such endpoint (HTTP 404 with an empty body).
 */
final class RouteNotFoundException extends PayrexException {}
