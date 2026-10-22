<?php

namespace App\Http\Requests\Bot;

use App\Http\Resources\Api\ValidationErrorResource;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ResolveTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resolution_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'resolution_notes.max' => 'Resolution notes cannot exceed 2000 characters.',
        ];
    }

    public function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            (new ValidationErrorResource($validator))->response()->setStatusCode(400)
        );
    }
}
