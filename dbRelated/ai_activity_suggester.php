<?php
session_start();
require_once __DIR__ . '/operation.php';

// Start output buffering to catch any unexpected output
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');

try {
    // 1. Security & Setup
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method Not Allowed', 405);
    }
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized', 401);
    }
    if (!function_exists('curl_init')) {
        throw new Exception('cURL extension is not enabled in PHP.', 500);
    }

    // 2. Get User Input (Lab Instructions)
    $input = json_decode(file_get_contents('php://input'), true);
    $userQuery = $input['instructions'] ?? '';
    if (empty($userQuery)) {
        throw new Exception('Instructions cannot be empty.', 400);
    }

    // 3. Prepare Data for AI
    $db = new DataManager();
    $inventory = $db->getInventoryShop();
    $inventoryList = "";
    $inventoryMap = []; // For quick lookup after AI response
    foreach ($inventory as $item) {
        $name = $item['Item_Name'];
        $desc = $item['Description'] ?: 'No description available.';
        $inventoryList .= "- Item: \"$name\", Description: \"$desc\"\n";
        $inventoryMap[strtolower($name)] = $item; // Use lowercase for case-insensitive matching
    }

    // 4. Construct the AI Prompt
    $systemInstruction = "You are a helpful and meticulous laboratory assistant. Your task is to analyze a set of laboratory instructions and identify ALL necessary items from a provided inventory list.

Here is the complete inventory you are allowed to choose from:
$inventoryList

User's instructions: \"$userQuery\"

Based ONLY on the user's instructions and the inventory list provided, identify all relevant items. Be thorough. For example, if an instruction mentions 'heating a liquid', you should suggest a 'Beaker' and a 'Hot Plate' if they exist in the inventory.

Return your answer ONLY as a valid JSON array of strings, where each string is the exact 'Item_Name' from the inventory. Do not include any items not on the list. Do not include quantities.

Example format:
[
  \"Beaker\",
  \"Hydrochloric Acid\",
  \"Graduated Cylinder\"
]";

    // 5. Call the Gemini API
    require_once __DIR__ . '/../HTML_Demo/api_secrets.php';
    $apiKey = $GEMINI_API_KEY;
   $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $apiKey;


    $data = ['contents' => [['parts' => [['text' => $systemInstruction]]]]];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        throw new Exception('cURL Error: ' . curl_error($ch), 500);
    }
    curl_close($ch);

    // 6. Process and Return Response
    $responseData = json_decode($response, true);

    if ($httpCode === 200 && isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        $botReplyText = $responseData['candidates'][0]['content']['parts'][0]['text'];
        
        $cleanedJsonString = trim($botReplyText);
        if (preg_match('/(\[.*\])/s', $botReplyText, $jsonMatch)) {
            $cleanedJsonString = $jsonMatch[0];
        }

        $suggestedNames = json_decode($cleanedJsonString, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($suggestedNames)) {
            $suggestedItems = [];
            foreach ($suggestedNames as $name) {
                if (isset($inventoryMap[strtolower(trim($name))])) {
                    $suggestedItems[] = $inventoryMap[strtolower(trim($name))];
                }
            }
            echo json_encode($suggestedItems);
        } else {
            throw new Exception('AI returned an invalid format.', 500);
        }
    } else {
        $errorMsg = $responseData['error']['message'] ?? 'Unknown API Error or unexpected response structure.';
        throw new Exception('API Error: ' . $errorMsg, $httpCode !== 200 ? $httpCode : 500);
    }

} catch (Throwable $e) {
    ob_clean();
    $code = $e->getCode() >= 400 ? $e->getCode() : 500;
    http_response_code($code);
    echo json_encode(['error' => 'Server Error: ' . $e->getMessage()]);
}
?>