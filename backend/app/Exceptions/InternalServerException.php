<?php

namespace App\Exceptions;

class InternalServerException extends BaseException
{
    public function statusCode(): int
    {
        return 500;
    }

    public function title(): string
    {
        return 'Internal Server Error';
    }
}
