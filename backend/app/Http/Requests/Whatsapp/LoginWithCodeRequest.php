<?php

namespace App\Http\Requests\Whatsapp;

use App\Http\Resources\Api\ValidationErrorResource;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class LoginWithCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_id' => ['nullable', 'string', 'alpha_dash', 'max:50'],
            'phone' => ['required', 'string', 'regex:/^[0-9]+$/', 'min:9', 'max:18'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'Phone number is required for pairing code login.',
            'phone.regex' => 'Phone number must only contain digits (e.g. 628123456789).',
        ];
    }

    public function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            (new ValidationErrorResource($validator))->response()->setStatusCode(400)
        );
    }
}
