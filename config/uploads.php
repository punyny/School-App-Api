<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Profile Image Limit (Validation)
    |--------------------------------------------------------------------------
    |
    | Value is in kilobytes. Keep this <= PHP/NGINX body limits.
    |
    */
    'profile_image_max_kb' => (int) env('UPLOAD_PROFILE_IMAGE_MAX_KB', 10240),
];

