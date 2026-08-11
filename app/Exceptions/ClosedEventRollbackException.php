<?php

namespace App\Exceptions;

use Exception;

class ClosedEventRollbackException extends Exception
{
    protected $message = 'Cannot rollback budget for an event that is already closed.';
    protected $code = 422; // Unprocessable Content HTTP Status Code
}
