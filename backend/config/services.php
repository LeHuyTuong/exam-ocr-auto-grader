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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'gemini' => [
        'api_keys' => array_values(array_filter([
            env('GEMINI_API_KEY'),
            env('GEMINI_API_KEY_2'),
            env('GEMINI_API_KEY_3'),
            env('GEMINI_API_KEY_4'),
            env('GEMINI_API_KEY_5'),
            env('GEMINI_API_KEY_6'),
            env('GEMINI_API_KEY_7'),
            env('GEMINI_API_KEY_8'),
            env('GEMINI_API_KEY_9'),
        ])),
        'model' => env('GEMINI_MODEL', 'gemini-flash-latest'),
    ],

    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'model' => env('GROQ_VISION_MODEL', 'meta-llama/llama-4-scout-17b-16e-instruct'),
    ],

    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        'model' => env('OPENROUTER_VISION_MODEL', 'google/gemma-4-31b-it:free'),
    ],

    'mistral' => [
        'api_key' => env('MISTRAL_API_KEY'),
        'model' => env('MISTRAL_VISION_MODEL', 'mistral-small-latest'),
    ],

    'cloudinary' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key' => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
    ],

];
