<?php

namespace App\Exceptions;

use Exception;

class InvalidAdtlAmountException extends Exception
{
    protected $message = 'Invalid amount.';
    protected $code = 402; // Unprocessable Content HTTP Status Code
}
