<?php

namespace RRRBAC\Exceptions;

use Exception;

class InvalidObjectNameGivenException extends Exception
{
    protected $code = 500;

    protected $message = 'Invalid object name given.';
}
