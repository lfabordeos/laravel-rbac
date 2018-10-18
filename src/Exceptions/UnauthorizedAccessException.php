<?php

namespace RRRBAC\Exceptions;

use Exception;

class UnauthorizedAccessException extends Exception
{
    protected $code = 403;

    protected $message = 'Forbidden';
}
