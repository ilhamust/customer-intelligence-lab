<?php

namespace App\Http\Requests\Staff;

use App\Http\Resources\Api\ValidationErrorResource;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreStationStaffRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation by normalizing the phone number to 628... format.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('phone') && is_string($this->input('phone'))) {
            $clean = preg_replace('/[^\d]/', '', $this->input('phone'));
            if (! empty($clean)) {
                if (str_starts_with($clean, '0')) {
                    $clean = '62' . substr($clean, 1);
                } elseif (str_starts_with($clean, '8')) {
                    $clean = '62' . $clean;
                }
                $this->merge(['phone' => $clean]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'min:10', 'max:16', 'regex:/^62\d{8,14}$/'],
            'role' => ['required', 'string', Rule::in(['field_technician', 'site_operator', 'station_manager'])],
            'station_name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'is_on_duty' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Custom validation error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama staf lapangan wajib diisi.',
            'phone.required' => 'Nomor WhatsApp staf wajib diisi.',
            'phone.regex' => 'Format nomor WhatsApp harus valid (misal: 0812... atau 62812...).',
            'phone.min' => 'Nomor WhatsApp minimal terdiri dari 10 digit.',
            'phone.max' => 'Nomor WhatsApp maksimal terdiri dari 16 digit.',
            'role.required' => 'Peran/jabatan staf (role) wajib dipilih.',
            'role.in' => 'Peran staf harus salah satu dari: field_technician, site_operator, atau station_manager.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    public function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            (new ValidationErrorResource($validator))->response()->setStatusCode(400)
        );
    }
}

