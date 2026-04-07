<?php
/**
 * Run from project root: php check-firestore.php
 * Tests if Cloud Firestore is connected to Laravel.
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Firestore connection check\n";
echo str_repeat('-', 50) . "\n";

$credentials = config('firebase.projects.app.credentials');
$projectId = config('firebase.projects.app.project_id') ?: env('GOOGLE_CLOUD_PROJECT');

echo "FIREBASE_CREDENTIALS: " . ($credentials ?: '(not set)') . "\n";
echo "GOOGLE_CLOUD_PROJECT: " . ($projectId ?: '(auto/from credentials)') . "\n";

$credentialsPath = $credentials && !str_starts_with($credentials, '{')
    ? base_path($credentials)
    : $credentials;
echo "Credentials file exists: " . (is_file($credentialsPath) ? 'Yes' : 'No') . "\n";

echo "\nTesting Firestore connection...\n";

try {
    $fs = app(\App\Services\FirestoreService::class);
    $docs = $fs->all('_healthcheck');
    echo "\n*** SUCCESS: Firestore is connected. ***\n";
    echo "Read test: '_healthcheck' collection has " . count($docs) . " document(s).\n";
} catch (\Throwable $e) {
    echo "\n*** FAILED: " . $e->getMessage() . " ***\n";
    exit(1);
}
