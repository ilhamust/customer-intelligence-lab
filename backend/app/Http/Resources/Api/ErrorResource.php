<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ErrorResource extends JsonResource
{
    public static $wrap = null;

    public function __construct(
        protected string $title,
        protected string $details,
        protected int $code = 400,
        protected string $status = 'STATUS_ERROR',
        protected array $meta = []
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
                    'code' => $this->code,
                    'status' => $this->status,
                    'meta' => $this->meta,
                ],
            ],
        ];
    }

    public function withResponse($request, $response): void
    {
        $response->setStatusCode($this->code);
    }
}
