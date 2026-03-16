<?php
session_start();
require_once __DIR__ . '/operation.php';

// Start output buffering to catch any unexpected output before JSON
ob_start();

// Prevent PHP warnings/notices from breaking the JSON response
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

// Wrap the entire logic in a try-catch to ensure all errors are caught and returned as JSON
try {




// 1. Security Checks
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
if (!function_exists('curl_init')) {
    http_response_code(500);
    echo json_encode(['error' => 'cURL extension is not enabled in PHP. Please enable it in php.ini.']);
    exit;
}

// 2. Get User Input
$input = json_decode(file_get_contents('php://input'), true);
$userQuery = $input['query'] ?? '';
if (empty($userQuery)) {
    http_response_code(400);
    echo json_encode(['error' => 'Search query cannot be empty.']);
    exit;
}

// 3. Prepare Data for AI
try {
    $db = new DataManager();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}
$inventory = $db->getInventoryShop();
$inventoryList = "";
foreach ($inventory as $item) {
    $name = $item['Item_Name'];
    $desc = $item['Description'] ?: 'No description available.';
    $inventoryList .= "- Item: \"$name\", Description: \"$desc\"\n";
}

// 4. Construct the AI Prompt
$systemInstruction = "You are a helpful lab assistant. Your task is to find relevant items from an inventory list based on a user's descriptive query.

Here is the inventory you can choose from:
$inventoryList

User's request: \"$userQuery\"

Based on the user's request, identify up to 5 of the most relevant items from the inventory. For each item, provide a very brief, 10-word explanation for why it's a good match.

Return your answer ONLY as a valid JSON array of objects. Each object must have two keys: \"item_name\" and \"reason\". Do not include any text, markdown, or explanations outside of the JSON array itself.

Example format:
[
  {\"item_name\": \"Beaker\", \"reason\": \"A glass container used for mixing and heating various liquids.\"},
  {\"item_name\": \"Graduated Cylinder\", \"reason\": \"Used for accurately measuring the volume of different liquids.\"}
]";

// 5. Call the Gemini API
$secretsFile = __DIR__ . '/../HTML_Demo/api_secrets.php';
if (!file_exists($secretsFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'Server Configuration Error: API secrets file is missing.']);
    exit;
}
require_once $secretsFile;
$apiKey = $GEMINI_API_KEY;
$apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $apiKey;

$data = ['contents' => [['parts' => [['text' => $systemInstruction]]]]];

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local XAMPP
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Check for cURL errors
if (curl_errno($ch)) {
    $curl_error_message = curl_error($ch);
    curl_close($ch);
    http_response_code(500);
    echo json_encode(['error' => 'cURL Error: ' . $curl_error_message]);
    exit;
}
curl_close($ch);

// 6. Process and Return Response
$responseData = json_decode($response, true);

// Check if Gemini API returned a non-200 status code or if the response is malformed
if ($httpCode === 200 && isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
    $botReplyText = $responseData['candidates'][0]['content']['parts'][0]['text'];

    // Clean the AI response to extract only the JSON part.
    // This regex finds the first occurrence of `[` or `{` and captures everything until the last `]` or `}`.
    // It's designed to strip markdown fences (e.g., ```json ... ```) and introductory text.
    $cleanedJsonString = $botReplyText;
    if (preg_match('/(\[.*\]|\{.*\})/s', $botReplyText, $jsonMatch)) {
        // The match is in the first element of the results array
        $cleanedJsonString = $jsonMatch[0];
    }

    $jsonReply = json_decode($cleanedJsonString, true);

    if (json_last_error() === JSON_ERROR_NONE) {
        // Success! Send the clean JSON back to the frontend.
        echo json_encode($jsonReply);
    } else {
        // This happens if the regex cleaning fails and the string is still not valid JSON.
        http_response_code(500); // AI returned 200 but the content is not valid JSON
        echo json_encode(['error' => 'AI returned an invalid format.']);
    }
} else {
    // If Gemini API itself returned an error status code, use that.
    // Otherwise, it's an unknown error from Gemini or malformed response.
    if ($httpCode !== 200) {
        http_response_code($httpCode);
    } else {
        http_response_code(500); // Default to 500 if Gemini returned 200 but response structure is unexpected
    }
    $errorMsg = $responseData['error']['message'] ?? 'Unknown API Error or unexpected response structure. Check your API key and model name.';
    echo json_encode(['error' => 'API Error: ' . $errorMsg]);
}

} catch (Throwable $e) { // Catch any uncaught exceptions or errors
    ob_clean(); // Clean any previous output
    http_response_code(500);
    echo json_encode(['error' => 'Server Error: ' . $e->getMessage()]);
}
?>