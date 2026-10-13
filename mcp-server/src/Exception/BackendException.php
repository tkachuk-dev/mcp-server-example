<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Base for errors whose message is safe to show to the model as-is.
 */
abstract class BackendException extends \RuntimeException
{
}
