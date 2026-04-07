<?php
/**
 * Run from project root: php check-firebase.php
 * Tests if Firebase Realtime Database is connected to Laravel.
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Firebase connection check\n";
echo str_repeat('-', 50) . "\n";

$dbUrl = config('firebase.projects.app.database.url');
$credentials = config('firebase.projects.app.credentials');

echo "FIREBASE_DATABASE_URL: " . ($dbUrl ?: '(not set)') . "\n";
echo "FIREBASE_CREDENTIALS: " . ($credentials ?: '(not set)') . "\n";

$credentialsPath = $credentials && !str_starts_with($credentials, '{')
    ? base_path($credentials)
    : $credentials;
echo "Credentials file exists: " . (is_file($credentialsPath) ? 'Yes' : 'No') . "\n";

echo "\nTesting connection...\n";

try {
    $db = app(\App\Services\FirebaseRealtimeService::class);
    $data = $db->all('flavors');
    echo "\n*** SUCCESS: Firebase Realtime Database is connected. ***\n";
    echo "Read test: 'flavors' collection has " . count($data) . " record(s).\n";
} catch (\Throwable $e) {
    echo "\n*** FAILED: " . $e->getMessage() . " ***\n";
    exit(1);
}
