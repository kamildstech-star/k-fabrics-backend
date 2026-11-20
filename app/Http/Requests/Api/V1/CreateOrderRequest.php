<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Decode JSON-encoded fields if sent via multipart/form-data
        if (is_string($this->items)) {
            $decoded = json_decode($this->items, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['items' => $decoded]);
            }
        }

        if (is_string($this->standalone_stitching)) {
            $decoded = json_decode($this->standalone_stitching, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['standalone_stitching' => $decoded]);
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
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'shipping_address' => ['required', 'string'],
            'city' => ['required', 'string', 'max:100'],
            'payment_method' => ['required', 'string', 'in:cod,bank_transfer,jazzcash,easypaisa,card'],
            'verification_token' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'coupon_code' => ['nullable', 'string', 'max:50'],

            // Either ready-to-wear items or standalone stitching must be present
            'items' => ['required_without:standalone_stitching', 'nullable', 'array'],
            'items.*.product_id' => ['required_with:items', 'integer', 'exists:products,id'],
            'items.*.variant_id' => ['required_with:items', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.custom_fitting' => ['nullable', 'array'],
            'items.*.custom_fitting.unit' => ['nullable', 'string', 'in:inches,cm'],
            'items.*.custom_fitting.shirt_length' => ['nullable', 'numeric'],
            'items.*.custom_fitting.shoulder' => ['nullable', 'numeric'],
            'items.*.custom_fitting.chest' => ['nullable', 'numeric'],
            'items.*.custom_fitting.waist' => ['nullable', 'numeric'],
            'items.*.custom_fitting.hips' => ['nullable', 'numeric'],
            'items.*.custom_fitting.sleeve_length' => ['nullable', 'numeric'],
            'items.*.custom_fitting.armhole' => ['nullable', 'numeric'],
            'items.*.custom_fitting.daman' => ['nullable', 'numeric'],
            'items.*.custom_fitting.neck_front' => ['nullable', 'numeric'],
            'items.*.custom_fitting.neck_back' => ['nullable', 'numeric'],
            'items.*.custom_fitting.trouser_length' => ['nullable', 'numeric'],
            'items.*.custom_fitting.trouser_waist' => ['nullable', 'numeric'],
            'items.*.custom_fitting.pancha' => ['nullable', 'numeric'],
            'items.*.custom_fitting.neck_style' => ['nullable', 'string', 'max:255'],
            'items.*.custom_fitting.sleeve_style' => ['nullable', 'string', 'max:255'],
            'items.*.custom_fitting.trouser_style' => ['nullable', 'string', 'max:255'],
            'items.*.custom_fitting.special_instructions' => ['nullable', 'string'],

            // Standalone stitching fields
            'standalone_stitching' => ['required_without:items', 'nullable', 'array'],
            'standalone_stitching.stitching_service_id' => ['required_with:standalone_stitching', 'integer', 'exists:stitching_services,id'],
            'standalone_stitching.fabric_delivery_method' => ['nullable', 'string', 'in:courier_by_customer,rider_pickup,shop_dropoff'],
            'standalone_stitching.neck_style' => ['nullable', 'string', 'max:255'],
            'standalone_stitching.sleeve_style' => ['nullable', 'string', 'max:255'],
            'standalone_stitching.trouser_style' => ['nullable', 'string', 'max:255'],
            'standalone_stitching.special_instructions' => ['nullable', 'string'],
            'standalone_stitching.measurements' => ['nullable', 'array'],

            // Reference image file upload
            'design_reference_image' => ['nullable'],
        ];
    }
}
