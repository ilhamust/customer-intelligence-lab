<?php

namespace App\Http\Resources\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ValidationErrorResource extends JsonResource
{
    public static $wrap = null;

    protected array $errors = [];
    protected string $firstError = 'Validation failed.';

    public function __construct(Validator $validator)
    {
        parent::__construct(null);
        $this->errors = $validator->errors()->toArray();
        $this->firstError = $validator->errors()->first() ?: 'Validation failed.';
    }

    public function toArray(Request $request): array
    {
        return [
            'errors' => [
                [
                    'title' => 'Validation Error',
                    'details' => $this->firstError,
                    'code' => 400,
                    'status' => 'STATUS_VALIDATION_ERROR',
                    'meta' => [
                        'fields' => $this->errors,
                    ],
                ],
            ],
        ];
    }

    public function withResponse($request, $response): void
    {
        $response->setStatusCode(400);
    }
}
