<?php

namespace App\Http\Requests;

use App\Support\ExperienceCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveAttractionRequest extends FormRequest
{
    public function authorize(): bool { return true; } // Existing auth:admin + AdminMiddleware protect every write route.

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        return [
            'name' => $required.'|string|max:200', 'item_type' => [$required, Rule::in(['hotel', 'restaurant', 'attraction', 'activity', 'transport', 'shopping'])],
            'location_id' => 'sometimes|integer|exists:location,id',
            'slug' => ['sometimes', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D'],
            'description' => 'nullable|string|max:20000', 'address' => 'nullable|string|max:255',
            'experience_category' => ['nullable', Rule::in(array_keys(ExperienceCategory::LABELS))],
            'experience_summary' => 'nullable|string|max:500',
            'experience_duration_minutes' => 'nullable|integer|min:1|max:1440',
            'experience_source_url' => 'nullable|url:http,https|max:2048',
            'open_time' => 'nullable|date_format:H:i', 'close_time' => 'nullable|date_format:H:i',
            'lat' => 'nullable|numeric|between:-90,90', 'lng' => 'nullable|numeric|between:-180,180',
            'is_active' => 'sometimes|boolean', 'verify' => 'sometimes|boolean', 'publish' => 'sometimes|boolean',
            // Legacy estimates remain compatible with the existing Admin API. Public resources never return them.
            'price_min' => 'sometimes|integer|min:0|max:4294967295',
            'price_max' => 'sometimes|integer|min:0|max:4294967295|gte:price_min',
            'duration_hours' => 'sometimes|integer|min:0|max:24',
            'image_source' => 'nullable|required_with:images|string|max:500', 'image_caption' => 'nullable|string|max:200',
        ];
    }
}
