<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'pusher' => [
        'key' => env('PUSHER_APP_KEY'),
        'secret' => env('PUSHER_APP_SECRET'),
        'app_id' => env('PUSHER_APP_ID'),
        'options' => [
            'cluster' => env('PUSHER_APP_CLUSTER'),
            'host' => env('PUSHER_HOST') ?: null,
            'port' => env('PUSHER_PORT', 443),
            'scheme' => env('PUSHER_SCHEME', 'https'),
            'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
        ],
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'telegram' => [
        'enabled' => env('TELEGRAM_ENABLED', false),
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'base_url' => env('TELEGRAM_API_BASE_URL', 'https://api.telegram.org'),
        'parse_mode' => env('TELEGRAM_PARSE_MODE', ''),
        'disable_web_page_preview' => env('TELEGRAM_DISABLE_WEB_PAGE_PREVIEW', true),
        'timeout_seconds' => env('TELEGRAM_TIMEOUT_SECONDS', 10),
        'group_chat_ids' => array_values(array_filter(
            array_map(
                static fn (string $id): string => trim($id),
                explode(',', (string) env('TELEGRAM_GROUP_CHAT_IDS', ''))
            ),
            static fn (string $id): bool => $id !== ''
        )),
        'group_allow_any_approver' => env('TELEGRAM_GROUP_ALLOW_ANY_APPROVER', false),
        'leave_action_buttons_enabled' => env('TELEGRAM_LEAVE_ACTION_BUTTONS_ENABLED', true),
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET', ''),
        'leave_action_ttl_minutes' => env('TELEGRAM_LEAVE_ACTION_TTL_MINUTES', 1440),
        'link_code_ttl_minutes' => env('TELEGRAM_LINK_CODE_TTL_MINUTES', 15),
    ],

];
