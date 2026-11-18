<?php

// No .env activation in this phase. The optional provider also requires the owned MySQL test guard.
return [
    'prepared_routes'=>false, 'integration_complete'=>false, 'final_integration'=>false,
    'banking_instructions'=>null, 'vnpay_merchant_verified'=>false,
    'manual_verification_minutes'=>15, // Administrative review window, never an inventory extension.
    'delivery_safety'=>false, 'delivery_max_attempts'=>3, 'delivery_backoff_seconds'=>[60,300,900],
    'staging_checkout'=>false, 'staging_mail'=>null,
    'production_manifest'=>null, // Separate deployment approval, never enabled by a test flag.
];
