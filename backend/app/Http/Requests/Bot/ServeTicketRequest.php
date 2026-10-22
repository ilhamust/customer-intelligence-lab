<?php

namespace App\Http\Requests\Bot;

use App\Http\Resources\Api\ValidationErrorResource;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ServeTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'admin_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'admin_name.max' => 'Admin name cannot exceed 100 characters.',
        ];
    }

    public function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            (new ValidationErrorResource($validator))->response()->setStatusCode(400)
        );
    }
}
