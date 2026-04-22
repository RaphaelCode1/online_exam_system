<?php
/**
 * Debug Gemini API Connection
 */

$api_key = 'AIzaSyDRuR79V999bUAJjQc-WSd9QONCjOxry0o';
$model = 'gemini-2.0-flash';

echo "<h1>Gemini API Debugger</h1>";

// Test 1: Basic API connection
echo "<h2>Test 1: Basic API Connection</h2>";
echo "API Key: " . substr($api_key, 0, 10) . '...' . substr($api_key, -5) . "<br>";
echo "Model: {$model}<br><br>";

$url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";

$test_prompt = "Say 'Hello'";
$data = [
    'contents' => [
        [
            'parts' => [
                ['text' => $test_prompt]
            ]
        ]
    ],
    'generationConfig' => [
        'maxOutputTokens' => 50
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_VERBOSE, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

($ch);

echo "HTTP Status Code: {$httpCode}<br>";

if ($curlError) {
    echo "CURL Error: {$curlError}<br>";
}

if ($response) {
    echo "<strong>Response:</strong><br>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    
    $decoded = json_decode($response, true);
    if (isset($decoded['error'])) {
        echo "<div style='color: red;'>";
        echo "<strong>Error Message:</strong> " . htmlspecialchars($decoded['error']['message']) . "<br>";
        echo "<strong>Error Code:</strong> " . htmlspecialchars($decoded['error']['code']) . "<br>";
        echo "<strong>Error Status:</strong> " . htmlspecialchars($decoded['error']['status']) . "<br>";
        echo "</div>";
        
        // Check for specific error types
        if (strpos($decoded['error']['message'], 'API key not valid') !== false) {
            echo "<div style='color: red;'>❌ The API key is invalid or expired.</div>";
        } elseif (strpos($decoded['error']['message'], 'not enabled') !== false) {
            echo "<div style='color: red;'>❌ The Gemini API is not enabled for this project.</div>";
        } elseif (strpos($decoded['error']['message'], 'quota') !== false) {
            echo "<div style='color: red;'>❌ You have exceeded your API quota.</div>";
        }
    } elseif (isset($decoded['candidates'])) {
        echo "<div style='color: green;'>✅ API is working!</div>";
        echo "Response: " . htmlspecialchars($decoded['candidates'][0]['content']['parts'][0]['text']);
    }
} else {
    echo "<div style='color: red;'>No response received from API</div>";
}

// Test 2: Check if API key format is correct
echo "<h2>Test 2: API Key Format Check</h2>";
if (strlen($api_key) > 30) {
    echo "✅ API key length is valid (" . strlen($api_key) . " characters)<br>";
} else {
    echo "❌ API key seems too short<br>";
}

// Test 3: Check if cURL is enabled
echo "<h2>Test 3: cURL Check</h2>";
if (function_exists('curl_version')) {
    $curl_version = curl_version();
    echo "✅ cURL is enabled (version: " . $curl_version['version'] . ")<br>";
} else {
    echo "❌ cURL is NOT enabled! Please enable it in php.ini<br>";
}

// Test 4: Check if openssl is enabled (for HTTPS)
echo "<h2>Test 4: OpenSSL Check</h2>";
if (extension_loaded('openssl')) {
    echo "✅ OpenSSL is enabled<br>";
} else {
    echo "❌ OpenSSL is NOT enabled! Please enable it in php.ini<br>";
}

// Test 5: Instructions to fix common issues
echo "<h2>Fix Common Issues</h2>";
echo "<ul>";
echo "<li><strong>API Key Issues:</strong> Go to <a href='https://aistudio.google.com/app/apikey' target='_blank'>Google AI Studio</a> and create a NEW API key</li>";
echo "<li><strong>API Not Enabled:</strong> Go to <a href='https://console.cloud.google.com/apis/library/generativelanguage.googleapis.com' target='_blank'>Google Cloud Console</a> and enable 'Generative Language API'</li>";
echo "<li><strong>Billing Required:</strong> Even for free tier, you need to set up billing at <a href='https://console.cloud.google.com/billing' target='_blank'>Google Cloud Billing</a></li>";
echo "<li><strong>Wrong API Key:</strong> Make sure you're using a Gemini API key, not a general Google Cloud API key</li>";
echo "</ul>";

// Test 6: Try alternative models if first fails
if ($httpCode !== 200) {
    echo "<h2>Test 6: Trying Alternative Models</h2>";
    $alternative_models = ['gemini-2.5-flash', 'gemini-2.0-flash-lite', 'gemini-2.0-flash-001'];
    
    foreach ($alternative_models as $test_model) {
        echo "<strong>Testing model: {$test_model}</strong><br>";
        $test_url = "https://generativelanguage.googleapis.com/v1beta/models/{$test_model}:generateContent?key={$api_key}";
        
        $ch = curl_init($test_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $test_response = curl_exec($ch);
        $test_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        ($ch);
        
        if ($test_code === 200) {
            echo "<div style='color: green;'>✅ Model {$test_model} works!</div>";
            echo "Use this model: <strong>{$test_model}</strong><br>";
            break;
        } else {
            echo "<div style='color: red;'>❌ Model {$test_model} failed (HTTP {$test_code})</div>";
        }
        echo "<br>";
    }
}
?>