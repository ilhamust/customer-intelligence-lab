<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InternalErrorResource extends JsonResource
{
    public static $wrap = null;

    public function __construct(
        protected string $details = 'An unexpected internal error occurred.',
        protected string $title = 'Internal Server Error'
    ) {
        parent::__construct(null);
    }

    public function toArray(Request $request): array
    {
        return [
            'errors' => [
                [
                    'title' => $this->title,
                    'details' => $this->details,
                    'code' => 500,
                    'status' => 'STATUS_INTERNAL_ERROR',
                    'meta' => [],
                ],
            ],
        ];
    }

    public function withResponse($request, $response): void
    {
        $response->setStatusCode(500);
    }
}
