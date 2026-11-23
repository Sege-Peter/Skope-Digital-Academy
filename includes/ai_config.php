<?php
/**
 * Skope Digital Academy - AI Configuration
 * Powers Gemini Pro Integration
 */

// DO NOT SHARE THIS FILE
require_once __DIR__ . '/db.php';   // defines GEMINI_API_KEY (Admin > Settings > AI, or includes/private/config.php)
// GEMINI_API_URL (with the current model) comes from includes/db.php

require_once __DIR__ . '/ai_provider.php';

/**
 * Ask the configured AI (Claude or Gemini) for a JSON answer. Returns the JSON text, or null on failure.
 */
function callGemini(string $prompt): ?string {
    $r = sda_ai_complete($prompt, '', ['json' => true, 'max_tokens' => 4096]);
    if (!$r['ok']) { error_log('[ai] ' . sda_ai_label() . ': ' . $r['error']); return null; }
    return $r['text'];
}
