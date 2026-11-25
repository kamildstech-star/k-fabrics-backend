<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

class CreateOrderRequest extends CheckoutQuoteRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    public function rules(): array
    {
        return parent::rules() + [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'shipping_address' => ['required', 'string', 'max:1000'],
            'city' => ['required', 'string', 'max:100'],
            // Provider availability is checked after idempotency lookup.
            'payment_method' => ['required', Rule::in(['cod', 'bank_transfer'])],
            'verification_token' => ['nullable', 'string', 'max:255'],
            'expected_total_amount' => ['nullable', 'numeric', 'decimal:0,2', 'between:0,99999999.99'],
            'idempotency_key' => ['required', 'string', 'min:24', 'max:128', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'design_reference_image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'design_reference_images' => ['nullable', 'array', 'max:5'],
            'design_reference_images.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            $count = count((array) $this->input('standalone_stitchings', []));
            foreach (array_keys((array) $this->file('design_reference_images', [])) as $index) {
                if (! is_int($index) || $index < 0 || $index >= $count) {
                    $validator->errors()->add('design_reference_images', 'Each reference requires a matching stitching booking.');
                    break;
                }
            }
            if ($this->hasFile('design_reference_image') && ($count !== 1 || $this->hasFile('design_reference_images'))) {
                $validator->errors()->add('design_reference_image', 'This reference requires one matching stitching booking.');
            }
        }];
    }
}
