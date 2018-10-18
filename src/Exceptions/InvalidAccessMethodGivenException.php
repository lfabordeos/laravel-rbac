<?php

namespace RRRBAC\Exceptions;

use Exception;

class InvalidAccessMethodGivenException extends Exception
{
    protected $code = 500;

    protected $message = 'Invalid access method given.';
}
