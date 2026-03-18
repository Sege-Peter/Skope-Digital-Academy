<?php
/**
 * Skope Digital Academy - AI Configuration
 * Powers Gemini Pro Integration
 */

// DO NOT SHARE THIS FILE
define('GEMINI_API_KEY', 'AIzaSyBd5dAZF2BFxnY9pLATkUdLebVM8DvVu6U');
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent');

/**
 * Core function to interact with Gemini API
 */
function callGemini(string $prompt): ?string {
    $data = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ]
    ];

    $ch = curl_init(GEMINI_API_URL . "?key=" . GEMINI_API_KEY);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local XAMPP dev

    $response = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        error_log("Gemini CURL Error: " . $err);
        return null;
    }

    $result = json_decode($response, true);
    
    if ($httpCode !== 200) {
        error_log("Gemini API Error (HTTP $httpCode): " . ($result['error']['message'] ?? $response));
        return null;
    }

    return $result['candidates'][0]['content']['parts'][0]['text'] ?? null;
}
