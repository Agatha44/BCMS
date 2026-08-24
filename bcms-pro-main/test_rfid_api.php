<?php
/**
 * Test script for RFID Gate API
 * Run this script to test the RFID gate control endpoints
 */

// Configuration
$baseUrl = 'https://bridge-core-dev.nssf.go.tz/api';
$testRfidTag = 'E123456789ABCDEF'; // Replace with actual RFID tag from your database

echo "=== RFID Gate API Test ===\n\n";

// Test 1: Get vehicle information by RFID tag
echo "1. Testing vehicle lookup by RFID tag...\n";
$vehicleInfoUrl = $baseUrl . '/rfid/vehicle-info?rfid_tag_no=' . urlencode($testRfidTag);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $vehicleInfoUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: " . $response . "\n\n";

// Test 2: Process RFID access (gate control)
echo "2. Testing RFID access processing...\n";
$processAccessUrl = $baseUrl . '/rfid/process-access';

$postData = [
    'rfid_tag_no' => $testRfidTag,
    'lane_id' => 1 // Use actual lane_id from your database
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $processAccessUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: " . $response . "\n\n";

echo "=== Test Complete ===\n";

// Instructions
echo "\n=== Instructions ===\n";
echo "1. Replace \$testRfidTag with an actual RFID tag from your vehicle table\n";
echo "2. Replace lane_id with the actual lane_id from your lane table\n";
echo "3. Make sure the vehicle has an active bundle subscription\n";
echo "4. Run this script: php test_rfid_api.php\n";
echo "5. Check the responses for successful vehicle lookup and gate control\n";
?>
