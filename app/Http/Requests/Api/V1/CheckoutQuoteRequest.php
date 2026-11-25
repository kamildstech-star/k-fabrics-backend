<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutQuoteRequest extends FormRequest
{
    public const MEASUREMENTS = [
        'shirt_length', 'shoulder', 'chest', 'waist', 'hips', 'sleeve_length', 'armhole',
        'daman', 'neck_front', 'neck_back', 'trouser_length', 'trouser_waist', 'pancha',
    ];

    private const STYLES = ['neck_style', 'sleeve_style', 'trouser_style', 'special_instructions'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['items', 'standalone_stitchings', 'standalone_stitching'] as $field) {
            if (is_string($this->input($field))) {
                $value = json_decode($this->input($field), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->merge([$field => $value]);
                }
            }
        }
        if (! $this->has('standalone_stitchings') && is_array($this->input('standalone_stitching'))) {
            $this->merge(['standalone_stitchings' => [$this->input('standalone_stitching')]]);
        }
        if ($this->filled('coupon_code') && is_string($this->input('coupon_code'))) {
            $this->merge(['coupon_code' => strtoupper(trim($this->input('coupon_code')))]);
        }
    }

    public function rules(): array
    {
        $measurementKeys = 'unit,'.implode(',', self::MEASUREMENTS);
        $styleKeys = implode(',', self::STYLES);
        $rules = [
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'items' => ['required_without:standalone_stitchings', 'nullable', 'array', 'list', 'max:25'],
            'items.*' => ['required', 'array:product_id,variant_id,quantity,custom_fitting'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.variant_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'items.*.custom_fitting' => ['nullable', "array:$measurementKeys,$styleKeys"],
            'standalone_stitchings' => ['required_without:items', 'nullable', 'array', 'list', 'max:5'],
            'standalone_stitchings.*' => ['required', "array:stitching_service_id,fabric_delivery_method,measurement_method,measurements,$styleKeys"],
            'standalone_stitchings.*.stitching_service_id' => ['required', 'integer', 'min:1'],
            'standalone_stitchings.*.fabric_delivery_method' => ['nullable', 'in:courier_by_customer'],
            'standalone_stitchings.*.measurement_method' => ['required', 'in:manual,sample'],
            'standalone_stitchings.*.measurements' => ['nullable', "array:$measurementKeys"],
        ];
        foreach (['items.*.custom_fitting', 'standalone_stitchings.*.measurements'] as $path) {
            $rules["$path.unit"] = ['nullable', 'in:inches,cm'];
            foreach (self::MEASUREMENTS as $field) {
                $rules["$path.$field"] = ['nullable', 'numeric', 'decimal:0,2', 'between:0.01,300'];
            }
        }
        foreach (['items.*.custom_fitting', 'standalone_stitchings.*'] as $path) {
            foreach (self::STYLES as $field) {
                $rules["$path.$field"] = ['nullable', 'string', $field === 'special_instructions' ? 'max:1000' : 'max:255'];
            }
        }

        return $rules;
    }
}
