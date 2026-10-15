<?php

namespace App\Http\Requests\Bot;

use App\Http\Resources\Api\ValidationErrorResource;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:4000'],
            'sender_type' => ['nullable', 'string', 'in:human,ai'],
        ];
    }

    public function messages(): array
    {
        return [
            'text.required' => 'Message content is required.',
            'text.max' => 'Message content cannot exceed 4000 characters.',
        ];
    }

    public function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            (new ValidationErrorResource($validator))->response()->setStatusCode(400)
        );
    }
}
