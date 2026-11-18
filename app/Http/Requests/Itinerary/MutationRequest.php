<?php

namespace App\Http\Requests\Itinerary;

use App\Rules\ItineraryInteger;
use Illuminate\Foundation\Http\FormRequest;

abstract class MutationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function validationData(): array
    {
        return [...parent::validationData(), 'request_key' => $this->header('Idempotency-Key')];
    }

    protected function mutationRules(): array
    {
        return [
            'request_key' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/D'],
            'expected_revision' => $this->route('id') ? ['bail', 'required', new ItineraryInteger, 'integer', 'min:1'] : ['prohibited'],
            'customer_id' => ['prohibited'], 'destination' => ['prohibited'], 'schema_version' => ['prohibited'],
            'revision' => ['prohibited'], 'total_days' => ['prohibited'], 'activities' => ['prohibited'],
            'costs' => ['prohibited'], 'room_options' => ['prohibited'], 'provider_mapping' => ['prohibited'],
        ];
    }
}
