<?php

namespace App\Http\Requests\Charging;

use App\Http\Resources\Api\ValidationErrorResource;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreChargingTransactionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'external_id' => ['required', 'string', 'max:255'],
            'station_code' => ['required', 'string', 'max:100'],
            'station_name' => ['nullable', 'string', 'max:255'],
            'charger_code' => ['required', 'string', 'max:100'],
            'charger_type' => ['nullable', 'string', 'max:100'],
            'kwh' => ['required', 'numeric', 'min:0'],
            'amount' => ['required', 'numeric', 'min:0'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'started_at' => ['nullable', 'date'],
            'finished_at' => ['required', 'date'],
            'phone' => ['nullable', 'string', 'max:50'],
            'plate_number' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', 'max:50'],
            'meta' => ['nullable', 'array'],
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
            'external_id.required' => 'ID eksternal transaksi (external_id) wajib diisi.',
            'station_code.required' => 'Kode stasiun SPKLU (station_code) wajib diisi.',
            'charger_code.required' => 'Kode charger (charger_code) wajib diisi.',
            'kwh.required' => 'Total konsumsi daya kWh (kwh) wajib diisi.',
            'kwh.numeric' => 'Total konsumsi kWh harus berupa angka.',
            'amount.required' => 'Nominal tagihan pengisian (amount) wajib diisi.',
            'amount.numeric' => 'Nominal tagihan harus berupa angka.',
            'finished_at.required' => 'Waktu pengisian selesai (finished_at) wajib diisi.',
            'finished_at.date' => 'Waktu pengisian selesai harus berformat tanggal/waktu yang valid.',
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
