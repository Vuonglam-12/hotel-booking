<?php

namespace App\Http\Requests\Itinerary;

final class DeleteRequest extends MutationRequest
{
    public function rules(): array
    {
        return $this->mutationRules();
    }
}
