<?php

/**
 * Debug script to test URL construction
 */

require_once __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

echo "=== Testing Guzzle base_uri behavior ===\n\n";

// Test 1: Without trailing slash
echo "Test 1: base_uri without trailing slash\n";
$client1 = new Client(['base_uri' => 'https://ai.blesta.com/api/v1']);
$request1 = $client1->getConfig('base_uri');
echo "Base URI configured: " . $request1 . "\n";

// Create a request to see the full URL
$container = [];
$history = Middleware::history($container);
$mock = new MockHandler([new Response(200, [], '{"data":{"total_credits":10}}')]);
$handlerStack = HandlerStack::create($mock);
$handlerStack->push($history);

$testClient1 = new Client(['base_uri' => 'https://ai.blesta.com/api/v1', 'handler' => $handlerStack]);
try {
    $testClient1->get('/auth/key');
} catch (\Exception $e) {}

if (isset($container[0])) {
    echo "Actual URL requested: " . $container[0]['request']->getUri() . "\n\n";
}

// Test 2: With trailing slash
echo "Test 2: base_uri WITH trailing slash\n";
$container2 = [];
$history2 = Middleware::history($container2);
$mock2 = new MockHandler([new Response(200, [], '{"data":{"total_credits":10}}')]);
$handlerStack2 = HandlerStack::create($mock2);
$handlerStack2->push($history2);

$testClient2 = new Client(['base_uri' => 'https://ai.blesta.com/api/v1/', 'handler' => $handlerStack2]);
try {
    $testClient2->get('/auth/key');
} catch (\Exception $e) {}

if (isset($container2[0])) {
    echo "Actual URL requested (with leading slash): " . $container2[0]['request']->getUri() . "\n";
}

// Test 2b: With trailing slash AND no leading slash on path
$container2b = [];
$history2b = Middleware::history($container2b);
$mock2b = new MockHandler([new Response(200, [], '{"data":{"total_credits":10}}')]);
$handlerStack2b = HandlerStack::create($mock2b);
$handlerStack2b->push($history2b);

$testClient2b = new Client(['base_uri' => 'https://ai.blesta.com/api/v1/', 'handler' => $handlerStack2b]);
try {
    $testClient2b->get('auth/key');
} catch (\Exception $e) {}

if (isset($container2b[0])) {
    echo "Actual URL requested (without leading slash): " . $container2b[0]['request']->getUri() . "\n\n";
}

// Test 3: Test with actual BlestaAiClient
echo "Test 3: Using BlestaAiClient\n";
use BlestaAi\Client\BlestaAiClient;

$reflection = new ReflectionClass(BlestaAiClient::class);
$constructor = $reflection->getConstructor();
$params = $constructor->getParameters();

echo "BlestaAiClient default baseUrl: ";
foreach ($params as $param) {
    if ($param->getName() === 'baseUrl' && $param->isDefaultValueAvailable()) {
        echo $param->getDefaultValue() . "\n";
    }
}

// Try to inspect the actual httpClient configuration
$client = new BlestaAiClient('test-key');
$httpClientProperty = $reflection->getProperty('httpClient');
$httpClientProperty->setAccessible(true);
$httpClient = $httpClientProperty->getValue($client);

echo "Guzzle base_uri: " . $httpClient->getConfig('base_uri') . "\n";

echo "\n=== Tests Complete ===\n";
