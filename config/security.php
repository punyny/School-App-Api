<?php

return [
    'admin_ip_allowlist' => array_values(array_filter(array_map(
        static fn (string $item): string => trim($item),
        explode(',', (string) env('SECURITY_ADMIN_IP_ALLOWLIST', ''))
    ), static fn (string $item): bool => $item !== '')),

    'login' => [
        'max_attempts' => (int) env('SECURITY_LOGIN_MAX_ATTEMPTS', 5),
        'decay_seconds' => (int) env('SECURITY_LOGIN_DECAY_SECONDS', 60),
    ],

    'password' => [
        'min_length' => (int) env('SECURITY_PASSWORD_MIN_LENGTH', 10),
        'require_letters' => env('SECURITY_PASSWORD_REQUIRE_LETTERS', true),
        'require_numbers' => env('SECURITY_PASSWORD_REQUIRE_NUMBERS', true),
        'require_symbols' => env('SECURITY_PASSWORD_REQUIRE_SYMBOLS', false),
        'require_mixed_case' => env('SECURITY_PASSWORD_REQUIRE_MIXED_CASE', false),
    ],

    'headers' => [
        'x_frame_options' => env('SECURITY_X_FRAME_OPTIONS', 'SAMEORIGIN'),
        'x_content_type_options' => env('SECURITY_X_CONTENT_TYPE_OPTIONS', 'nosniff'),
        'referrer_policy' => env('SECURITY_REFERRER_POLICY', 'strict-origin-when-cross-origin'),
        'permissions_policy' => env('SECURITY_PERMISSIONS_POLICY', 'camera=(), microphone=(), geolocation=()'),
        'strict_transport_security' => env('SECURITY_HSTS', 'max-age=31536000; includeSubDomains'),
        'content_security_policy' => env('SECURITY_CONTENT_SECURITY_POLICY', ''),
    ],

    'monitoring' => [
        'slow_api_ms' => (int) env('SECURITY_SLOW_API_MS', 1200),
    ],
];
