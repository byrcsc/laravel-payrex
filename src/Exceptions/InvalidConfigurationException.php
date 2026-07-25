<?php

declare(strict_types=1);

namespace ByRcsc\LaravelPayrex\Exceptions;

/**
 * The published PayRex configuration contains a value the package cannot use.
 */
final class InvalidConfigurationException extends PayrexException {}
