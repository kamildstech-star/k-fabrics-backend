<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreMeasurementRequest extends FormRequest
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
            'profile_name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'in:inches,cm'],
            'shirt_length' => ['nullable', 'numeric'],
            'shoulder' => ['nullable', 'numeric'],
            'chest' => ['nullable', 'numeric'],
            'waist' => ['nullable', 'numeric'],
            'hips' => ['nullable', 'numeric'],
            'sleeve_length' => ['nullable', 'numeric'],
            'armhole' => ['nullable', 'numeric'],
            'daman' => ['nullable', 'numeric'],
            'neck_front' => ['nullable', 'numeric'],
            'neck_back' => ['nullable', 'numeric'],
            'trouser_length' => ['nullable', 'numeric'],
            'trouser_waist' => ['nullable', 'numeric'],
            'pancha' => ['nullable', 'numeric'],
            'extra_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
