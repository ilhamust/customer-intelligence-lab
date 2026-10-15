<?php

namespace App\Exceptions;

class BadRequestException extends BaseException
{
    public function statusCode(): int
    {
        return 400;
    }

    public function title(): string
    {
        return 'Bad Request';
    }
}
