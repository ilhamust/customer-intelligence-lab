<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DataResource extends JsonResource
{
    protected string $title = 'Success';

    protected string $status = 'STATUS_OK';

    protected int $code = 200;

    protected array $meta = [];

    /**
     * Fluent setter for response metadata.
     */
    public function setMeta(string $title = 'Success', string $status = 'STATUS_OK', int $code = 200, array $meta = []): static
    {
        $this->title = $title;
        $this->status = $status;
        $this->code = $code;
        $this->meta = $meta;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->resource,
            'title' => $this->title,
            'status' => $this->status,
            'code' => $this->code,
            'meta' => (object) $this->meta,
        ];
    }

    public function withResponse($request, $response): void
    {
        $response->setStatusCode($this->code);
    }
}
