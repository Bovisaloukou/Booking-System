<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider_id' => ['required', 'exists:providers,id'],
            'service_id' => ['required', 'exists:services,id'],
            'time_slot_id' => ['required', 'exists:time_slots,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'provider_id.required' => 'Le prestataire est obligatoire.',
            'provider_id.exists' => 'Le prestataire sélectionné n\'existe pas.',
            'service_id.required' => 'Le service est obligatoire.',
            'service_id.exists' => 'Le service sélectionné n\'existe pas.',
            'time_slot_id.required' => 'Le créneau horaire est obligatoire.',
            'time_slot_id.exists' => 'Le créneau sélectionné n\'existe pas.',
        ];
    }
}
