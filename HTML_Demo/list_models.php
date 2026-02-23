<?php
// Run this file in your browser: http://localhost/LabFlow/HTML_Demo/list_models.php
// It lists all Gemini models available to your API key so you can pick the right one.

header('Content-Type: text/plain');

// 1. Configuration (Using the key from your chat_api.php)
$secretsFile = __DIR__ . '/api_secrets.php';
if (!file_exists($secretsFile)) {
    die("Error: api_secrets.php not found. Please create it with your API key.\n");
}
require_once $secretsFile;
$apiKey = $GEMINI_API_KEY;
$apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $apiKey;

// 2. Send GET Request to Google
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPGET, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Fix for local XAMPP SSL issues

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo 'Connection Error: ' . curl_error($ch);
    exit;
}
curl_close($ch);

// 3. Display Results
$data = json_decode($response, true);

if ($httpCode === 200 && isset($data['models'])) {
    echo "--- Available Models for your API Key ---\n\n";
    foreach ($data['models'] as $model) {
        echo "Model Name: " . $model['name'] . "\n";
        echo "Supported Methods: " . implode(", ", $model['supportedGenerationMethods']) . "\n";
        echo "---------------------------------------\n";
    }
} else {
    echo "API Error ($httpCode):\n";
    print_r($data);
}
?>