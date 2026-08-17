<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OTP Hashing
    |--------------------------------------------------------------------------
    |
    | Whether password-reset OTPs are hashed at rest. This must stay true in
    | production — the false path exists only for local/staging debugging,
    | where being able to read a stored OTP directly saves time. Storing OTPs
    | in plaintext is a real risk if the database is ever exposed, even for
    | codes this short-lived. Never set OTP_HASHING_ENABLED=false in production.
    |
    */

    'otp_hashing_enabled' => (bool) env('OTP_HASHING_ENABLED', true),

];
