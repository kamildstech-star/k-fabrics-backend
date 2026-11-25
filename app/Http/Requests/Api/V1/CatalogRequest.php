<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxPrice = ['nullable', 'numeric', 'between:0,99999999.99'];
        if ($this->filled('min_price')) {
            $maxPrice[] = 'gte:min_price';
        }

        return [
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'size' => ['nullable', 'string', 'max:30'],
            'color' => ['nullable', 'string', 'max:100'],
            'fabric_type' => ['nullable', 'string', 'max:100'],
            'piece_type' => ['nullable', 'string', 'max:30'],
            'min_price' => ['nullable', 'numeric', 'between:0,99999999.99'],
            'max_price' => $maxPrice,
            'is_featured' => ['nullable', Rule::in(['true', 'false', '1', '0', 'yes', 'no', 'on', 'off'])],
            'sort' => ['nullable', Rule::in(['relevance', 'newest', 'price_asc', 'price_desc'])],
            'per_page' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
