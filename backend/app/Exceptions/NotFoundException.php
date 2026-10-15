<?php

namespace App\Exceptions;

class NotFoundException extends BaseException
{
    public function statusCode(): int
    {
        return 404;
    }

    public function title(): string
    {
        return 'Not Found';
    }
}
