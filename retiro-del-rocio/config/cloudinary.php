<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cloudinary credentials
    |--------------------------------------------------------------------------
    | Media hosting (onboarding background video, and future image/video
    | delivery). Fill these in .env — never commit real keys.
    */
    'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
    'api_key' => env('CLOUDINARY_API_KEY'),
    'api_secret' => env('CLOUDINARY_API_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Onboarding background video
    |--------------------------------------------------------------------------
    | Served to the tablet app via GET /v1/app/config. Set the full delivery
    | URL directly, OR set just the public id and let the cloud name build it.
    */
    'onboarding' => [
        'video_url' => env('ONBOARDING_VIDEO_URL'),
        'video_public_id' => env('ONBOARDING_VIDEO_PUBLIC_ID'),
    ],

];
