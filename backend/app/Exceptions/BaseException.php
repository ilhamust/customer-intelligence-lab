<?php

namespace App\Exceptions;

use Exception;

abstract class BaseException extends Exception
{
    abstract public function statusCode(): int;

    abstract public function title(): string;
}
