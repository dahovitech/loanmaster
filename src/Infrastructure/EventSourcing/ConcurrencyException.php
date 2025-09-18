<?php

namespace App\Infrastructure\EventSourcing;

use Exception;

/**
 * Exception levée en cas de conflit de concurrence
 */
class ConcurrencyException extends Exception
{
}
