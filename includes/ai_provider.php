<?php
/**
 * AI provider switch: every AI feature (mentor, tutor lesson/quiz tools, admin insights) calls sda_ai_complete(),
 * which sends the prompt to Anthropic Claude or Google Gemini – chosen in Admin > Settings > AI.
 * Claude is called over HTTPS (api.anthropic.com/v1/messages) with cURL; no extra libraries.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/learning.php';

const SDA_CLAUDE_MODELS = ['claude-haiku-5-5', 'claude-sonnet-5-5', 'claude-opus-5-5'];
const SDA_CLAUDE_DEFAULT = 'claude-haiku-5-5';
const SDA_ANTHROPIC_VERSION = '2026-10-21';

function sda_anthropic_key(): string
{
    $k = trim(setting('anthropic_api_key', ''));
    if ($k === '' && defined('SDA_ANTHROPIC_KEY')) $k = (string)SDA_ANTHROPIC_KEY;      // includes/private/config.php
    if ($k === '') $k = (string)(getenv('ANTHROPIC_API_KEY') ?: '');
    return $k;
}

function sda_claude_model(): string
{
    $m = trim(setting('anthropic_model', ''));
    return preg_match('/^claude-[a-z0-9.-]+$/', $m) ? $m : SDA_CLAUDE_DEFAULT;
}

/** 'anthropic' or 'gemini': the chosen one, or whichever has a key if the chosen one has none. */
function sda_ai_provider(): string
{
    $hasClaude = sda_anthropic_key() !== '';
    $set = setting('ai_provider', '');
    $want = $set === 'gemini' ? 'gemini' : ($set === 'anthropic' || $hasClaude ? 'anthropic' : 'gemini');
    $hasGemini = defined('GEMINI_API_KEY') && GEMINI_API_KEY !== '';
    if ($want === 'anthropic' && !$hasClaude && $hasGemini) return 'gemini';
    if ($want === 'gemini' && !$hasGemini && $hasClaude) return 'anthropic';
    return $want;
}

function sda_ai_label(): string
{
    return sda_ai_provider() === 'anthropic' ? 'Claude (' . sda_claude_model() . ')' : 'Gemini (' . (defined('GEMINI_MODEL') ? GEMINI_MODEL : '') . ')';
}

/**
 * Send one prompt. Returns ['ok' => bool, 'text' => string, 'error' => string].
 * $o: max_tokens (default 2048), temperature (default 0.7), json (bool: ask for JSON only), timeout (seconds).
 */
function sda_ai_complete(string $prompt, string $system = '', array $o = []): array
{
    if (trim($prompt) === '') return ['ok' => false, 'text' => '', 'error' => 'Empty prompt.'];
    return sda_ai_provider() === 'anthropic' ? sda_claude_complete($prompt, $system, $o) : sda_gemini_complete($prompt, $system, $o);
}

function sda_claude_complete(string $prompt, string $system = '', array $o = []): array
{
    $key = sda_anthropic_key();
    if ($key === '') return ['ok' => false, 'text' => '', 'error' => 'No Anthropic API key is set (Admin > Settings > AI).'];
    if (!empty($o['json'])) $system = trim($system . "\n\nRespond with valid JSON only: no code fences, no text before or after the JSON.");
    $body = [
        'model'       => sda_claude_model(),
        'max_tokens'  => (int)($o['max_tokens'] ?? 2048),
        'messages'    => [['role' => 'user', 'content' => $prompt]],
    ];
    if ($system !== '') $body['system'] = $system;

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => (int)($o['timeout'] ?? 60),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['content-type: application/json', 'x-api-key: ' . $key, 'anthropic-version: ' . SDA_ANTHROPIC_VERSION],
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $err !== '') { error_log('[claude] curl: ' . $err); return ['ok' => false, 'text' => '', 'error' => 'Could not reach Anthropic: ' . $err]; }
    $d = json_decode((string)$raw, true);
    if ($code !== 200) {
        $msg = $d['error']['message'] ?? ('HTTP ' . $code);
        error_log('[claude] HTTP ' . $code . ' ' . ($d['error']['type'] ?? '') . ': ' . $msg);
        return ['ok' => false, 'text' => '', 'error' => $msg];
    }
    if (($d['stop_reason'] ?? '') === 'refusal') return ['ok' => false, 'text' => '', 'error' => 'The request was declined by the model.'];
    // The answer is in the text blocks of content[]
    $text = '';
    foreach ((array)($d['content'] ?? []) as $b) if (($b['type'] ?? '') === 'text') $text .= $b['text'];
    if (!empty($o['json'])) $text = preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($text));
    return ['ok' => trim($text) !== '', 'text' => $text, 'error' => trim($text) === '' ? 'Empty reply.' : ''];
}

function sda_gemini_complete(string $prompt, string $system = '', array $o = []): array
{
    if (!defined('GEMINI_API_KEY') || GEMINI_API_KEY === '') return ['ok' => false, 'text' => '', 'error' => 'No Gemini API key is set (Admin > Settings > AI).'];
    $payload = [
        'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
        'generationConfig' => ['temperature' => (float)($o['temperature'] ?? 0.7), 'maxOutputTokens' => (int)($o['max_tokens'] ?? 2048)],
    ];
    if (!empty($o['json'])) $payload['generationConfig']['responseMimeType'] = 'application/json';
    if ($system !== '') $payload['systemInstruction'] = ['parts' => [['text' => $system]]];
    $url = str_contains(GEMINI_API_URL, 'key=') ? GEMINI_API_URL : GEMINI_API_URL . '?key=' . GEMINI_API_KEY;
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => (int)($o['timeout'] ?? 60),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode($payload)]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $err !== '') return ['ok' => false, 'text' => '', 'error' => 'Could not reach Gemini: ' . $err];
    $d = json_decode((string)$raw, true);
    if ($code !== 200) { error_log('[gemini] HTTP ' . $code . ': ' . ($d['error']['message'] ?? '')); return ['ok' => false, 'text' => '', 'error' => $d['error']['message'] ?? ('HTTP ' . $code)]; }
    if (($d['candidates'][0]['finishReason'] ?? '') === 'SAFETY') return ['ok' => false, 'text' => '', 'error' => 'Blocked by safety filters.'];
    $text = (string)($d['candidates'][0]['content']['parts'][0]['text'] ?? '');
    return ['ok' => trim($text) !== '', 'text' => $text, 'error' => trim($text) === '' ? 'Empty reply.' : ''];
}
