<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Session Inactivity & Idle Timeout Settings
    |--------------------------------------------------------------------------
    |
    | Total seconds of user inactivity before auto-logout occurs.
    | Default is 900 seconds (15 minutes).
    | Warning window is 120 seconds (2 minutes).
    |
    */
    'idle_timeout_seconds' => (int) env('PMIS_IDLE_TIMEOUT_SECONDS', 900),
    'idle_warning_seconds' => (int) env('PMIS_IDLE_WARNING_SECONDS', 120),
];
