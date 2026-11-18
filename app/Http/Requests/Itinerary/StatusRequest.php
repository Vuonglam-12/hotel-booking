<?php

namespace App\Http\Requests\Itinerary;

use Illuminate\Validation\Rule;

final class StatusRequest extends MutationRequest
{
    public function rules(): array
    {
        return [...$this->mutationRules(), 'status' => ['required', Rule::in(['draft', 'saved'])]];
    }
}
