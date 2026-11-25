<?php

return [
    // Manual confirmation is an explicit checkout flow; the order is not dispatchable until confirmed.
    'cod_mode' => env('CHECKOUT_COD_MODE', 'manual'),
    'bank_transfer_instructions' => env('CHECKOUT_BANK_TRANSFER_INSTRUCTIONS'),
    'receipt_email_enabled' => env('CHECKOUT_RECEIPT_EMAIL_ENABLED', false),
    'receipt_email_mailer' => env('CHECKOUT_RECEIPT_EMAIL_MAILER'),
    'receipt_tracking_url' => env('CHECKOUT_RECEIPT_TRACKING_URL'),
    'custom_fitting_fee_minor' => 30000,
    'shipping_fee_minor' => 25000,
    'free_shipping_at_minor' => 500000,
];
