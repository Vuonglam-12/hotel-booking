<?php

// Enabled only by the owned, loopback MySQL demo launcher. Never read an .env toggle.
return [
    'enabled' => false, // Existing isolated V2 demo stays separate.
    'local_enabled' => true,
    'hold_minutes' => 10,
    'max_pending_bookings' => 3,
    'max_pending_rooms' => 10,
    'confirmation_mail_enabled' => env('BOOKING_CONFIRMATION_MAIL', env('APP_ENV') === 'local'),
    'confirmation_mail_path' => storage_path('app/private/booking-confirmation-mail'),
    'local_manifest' => storage_path('app/private/booking-demo-live/approval.json'),
    'local_manifest_sha256' => '21e6eb2f09135856909da1656bc801fb6374135e609f69d9a3c39cfbae4698c7',
];
