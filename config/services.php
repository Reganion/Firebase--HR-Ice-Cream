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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Google Sign-In (ID token verification)
    'google' => [
        'client_id' => env('GOOGLE_WEB_CLIENT_ID') ?: env('GOOGLE_CLIENT_ID'),
        'android_client_id' => env('GOOGLE_ANDROID_CLIENT_ID', ''),
        'ios_client_id' => env('GOOGLE_IOS_CLIENT_ID', ''),
    ],

    // google = Google Geocoding + Directions + Maps SDK key. osm = free Nominatim + OSRM + flutter_map tiles (study / low traffic).
    'maps' => [
        'provider' => env('MAPS_PROVIDER', 'google'),
    ],

    // Google Maps Platform: Geocoding, Directions (server). Optional separate key for Maps SDK in Flutter (restrict by bundle ID / SHA-1).
    'google_maps' => [
        'api_key' => env('GOOGLE_MAPS_API_KEY', ''),
        'client_key' => env('GOOGLE_MAPS_CLIENT_KEY', env('GOOGLE_MAPS_API_KEY', '')),
    ],

    // OpenStreetMap stack (used when maps.provider=osm)
    'osm' => [
        'nominatim_url' => rtrim((string) env('OSM_NOMINATIM_URL', 'https://nominatim.openstreetmap.org'), '/'),
        'osrm_url' => rtrim((string) env('OSM_OSRM_URL', 'https://router.project-osrm.org'), '/'),
        'nominatim_email' => env('OSM_NOMINATIM_EMAIL', ''),
        'user_agent' => env('OSM_HTTP_USER_AGENT', 'HR-IceCream-API/1.0'),
        'tile_url_template' => env('OSM_TILE_URL_TEMPLATE', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'tile_attribution' => env('OSM_TILE_ATTRIBUTION', '© OpenStreetMap contributors'),
    ],

    // Firebase Realtime Database URL (for admin real-time orders/notifications/chat)
    'firebase_realtime_url' => env('FIREBASE_DATABASE_URL', ''),

    // Firebase Web / JS SDK (Firestore listeners in admin layout). From Firebase Console → Project settings → Your apps → Web.
    'firebase_web' => [
        'api_key' => env('FIREBASE_WEB_API_KEY', ''),
        'auth_domain' => env('FIREBASE_WEB_AUTH_DOMAIN', ''),
        'project_id' => env('FIREBASE_PROJECT_ID', env('GOOGLE_CLOUD_PROJECT', '')),
        'storage_bucket' => env('FIREBASE_WEB_STORAGE_BUCKET', ''),
        'messaging_sender_id' => env('FIREBASE_WEB_MESSAGING_SENDER_ID', ''),
        'app_id' => env('FIREBASE_WEB_APP_ID', ''),
    ],

];
