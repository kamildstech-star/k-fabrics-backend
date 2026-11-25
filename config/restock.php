<?php

return [
    // Opt in only when staff have an approved sender and own the restock queue.
    'manual_alerts_enabled' => env('RESTOCK_MANUAL_ALERTS_ENABLED', false),
];
