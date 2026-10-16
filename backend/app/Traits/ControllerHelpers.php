<?php

namespace App\Traits;

use App\Exceptions\BadRequestException;
use App\Exceptions\InternalServerException;
use App\Exceptions\NotFoundException;
use App\Http\Resources\Api\DataResource;
use App\Http\Resources\Api\ErrorResource;
use App\Http\Resources\Api\InternalErrorResource;
use Exception;
use Illuminate\Support\Facades\Log;

trait ControllerHelpers
{
    /**
     * Standard success data response.
     */
    protected function respondWithData(mixed $data, string $title = 'Success', int $code = 200, array $meta = []): DataResource
    {
        return (new DataResource($data))->setMeta($title, 'STATUS_OK', $code, $meta);
    }

    /**
     * Handle 404 NotFoundException.
     */
    protected function handleNotFound(NotFoundException $e, string $ctx): ErrorResource
    {
        Log::warning(sprintf('[%s] %s', $ctx, $e->getMessage()));

        return new ErrorResource($e->title(), $e->getMessage(), 404, 'STATUS_NOT_FOUND');
    }

    /**
     * Handle 400 BadRequestException.
     */
    protected function handleBadRequest(BadRequestException $e, string $ctx): ErrorResource
    {
        Log::warning(sprintf('[%s] %s', $ctx, $e->getMessage()));

        return new ErrorResource($e->title(), $e->getMessage(), 400, 'STATUS_BAD_REQUEST');
    }

    /**
     * Handle 500 InternalServerException.
     */
    protected function handleInternal(InternalServerException $e, string $ctx): InternalErrorResource
    {
        Log::error(sprintf('[%s] %s', $ctx, $e->getMessage()));

        return new InternalErrorResource($e->getMessage(), $e->title());
    }

    /**
     * Handle unexpected generic exception.
     */
    protected function handleUnexpected(Exception $e, string $ctx, string $fallbackMessage = 'An unexpected error occurred.'): InternalErrorResource
    {
        Log::error(sprintf('[%s] Unexpected exception: %s', $ctx, $e->getMessage()), [
            'trace' => $e->getTraceAsString(),
        ]);

        $detail = config('app.debug') ? $e->getMessage() : $fallbackMessage;

        return new InternalErrorResource($detail);
    }
}
