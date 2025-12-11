<?php

/**
 * Simple connection test
 */

require_once __DIR__ . '/../vendor/autoload.php';

use BlestaAi\Client\BlestaAiClient;

echo "Testing connection to ai.blesta.com...\n\n";

// Test with a dummy key to see if we can reach the endpoint
$client = new BlestaAiClient('sk_test_key_12345');

try {
    $balance = $client->getCredits();
    echo "Success! Balance: $" . $balance . "\n";
} catch (\BlestaAi\Client\Exceptions\AuthenticationException $e) {
    echo "✓ API endpoint reached successfully!\n";
    echo "  (Got authentication error as expected with test key)\n";
    echo "  Error: " . $e->getMessage() . "\n";
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "  Code: " . $e->getCode() . "\n";
}

echo "\nIf you see 'API endpoint reached successfully', the library is working correctly.\n";
echo "Now replace 'sk_test_key_12345' with your actual API key in check_credits.php\n";
