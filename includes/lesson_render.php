<?php
/**
 * Lesson renderer for the tutorial library (format: content/README.md).
 *
 * render_lesson($markdown, $ctx)  → safe HTML (everything is escaped first)
 * lesson_examples($markdown)      → [['lang'=>, 'code'=>, 'output'=>], …]  (1-based order = Try-it ex=N)
 *
 * $ctx keys: lesson_id (int, for Try-it links), link (callable slug => url, for [text](lesson-slug)).
 */

if (!function_exists('e')) {
    function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

/** Split Markdown into ['type' => 'text', 'lines' => [...]] and ['type' => 'fence', 'info' => '', 'body' => ''] blocks. */
function lr_blocks(string $md): array {
    $md = str_replace(["\r\n", "\r"], "\n", $md);
    $lines = explode("\n", $md);
    $blocks = [];
    $text = [];
    $n = count($lines);
    for ($i = 0; $i < $n; $i++) {
        $line = $lines[$i];
        if (preg_match('/^\s*(`{3,})\s*([^`]*)$/', $line, $m)) {
            $ticks = strlen($m[1]);
            $body = [];
            $closed = false;
            for ($j = $i + 1; $j < $n; $j++) {
                if (preg_match('/^\s*`{' . $ticks . ',}\s*$/', $lines[$j])) { $closed = true; break; }
                $body[] = $lines[$j];
            }
            if (!$closed) { $text[] = $line; continue; }   // stray backticks: treat as text
            if ($text) { $blocks[] = ['type' => 'text', 'lines' => $text]; $text = []; }
            $blocks[] = ['type' => 'fence', 'info' => strtolower(trim($m[2])), 'body' => implode("\n", $body)];
            $i = $j;
            continue;
        }
        $text[] = $line;
    }
    if ($text) $blocks[] = ['type' => 'text', 'lines' => $text];
    return $blocks;
}

/** Runnable examples in order, each with the output block that directly follows it (if any). */
function lesson_examples(string $md): array {
    $out = [];
    $blocks = lr_blocks($md);
    foreach ($blocks as $k => $b) {
        if ($b['type'] !== 'fence' || !preg_match('/^example\s+([a-z0-9+#-]+)/', $b['info'], $m)) continue;
        $ex = ['lang' => lr_lang($m[1]), 'code' => $b['body'], 'output' => null];
        $next = lr_next_meaningful($blocks, $k);
        if ($next !== null && $blocks[$next]['type'] === 'fence' && $blocks[$next]['info'] === 'output') {
            $ex['output'] = $blocks[$next]['body'];
        }
        $out[] = $ex;
    }
    return $out;
}

function lr_next_meaningful(array $blocks, int $k): ?int {
    for ($i = $k + 1; $i < count($blocks); $i++) {
        if ($blocks[$i]['type'] === 'text' && trim(implode('', $blocks[$i]['lines'])) === '') continue;
        return $i;
    }
    return null;
}

function lr_lang(string $l): string {
    $l = strtolower($l);
    return ['js' => 'javascript', 'py' => 'python', 'htm' => 'html', 'sqlite' => 'sql', 'mysql' => 'sql'][$l] ?? $l;
}

/** Prism class for a language. */
function lr_prism(string $lang): string {
    $map = ['html' => 'markup', 'xml' => 'markup', 'javascript' => 'javascript', 'css' => 'css', 'python' => 'python',
            'sql' => 'sql', 'php' => 'php', 'json' => 'json', 'bash' => 'bash', 'shell' => 'bash', 'text' => 'none'];
    return 'language-' . ($map[$lang] ?? ($lang ?: 'none'));
}

function lr_slugify(string $s): string {
    return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(strip_tags($s))), '-');
}

/** Inline Markdown on one line of text (escaped first). */
function lr_inline(string $s, array $ctx): string {
    $codes = [];
    // Protect `code` spans before anything else.
    $s = preg_replace_callback('/`([^`]+)`/', function ($m) use (&$codes) {
        $codes[] = '<code>' . e($m[1]) . '</code>';
        return "\x01" . (count($codes) - 1) . "\x02";
    }, $s);
    $links = [];
    $s = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function ($m) use (&$links, $ctx) {
        $label = $m[1];
        $url = $m[2];
        $attrs = '';
        if (preg_match('~^https?://~i', $url)) {
            $attrs = ' target="_blank" rel="noopener"';
        } elseif (preg_match('/^[a-z0-9][a-z0-9-]*(#[\w-]+)?$/', $url) && isset($ctx['link'])) {
            [$slug, $hash] = array_pad(explode('#', $url, 2), 2, null);
            $url = ($ctx['link'])($slug) . ($hash ? '#' . $hash : '');
        } elseif (!preg_match('~^(#|/|\.{0,2}/|[a-z0-9_-]+(\?|$))~i', $url) || preg_match('/^\s*javascript:/i', $url)) {
            return e($m[0]);   // unknown/unsafe scheme: show as text
        }
        $links[] = '<a href="' . e($url) . '"' . $attrs . '>' . e($label) . '</a>';
        return "\x03" . (count($links) - 1) . "\x04";
    }, $s);
    $s = e($s);
    $s = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $s);
    $s = preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/', '<em>$1</em>', $s);
    $s = preg_replace_callback("/\x03(\d+)\x04/", fn($m) => $links[(int)$m[1]], $s);
    // Links may contain code spans in their label (already escaped); restore codes last.
    return preg_replace_callback("/\x01(\d+)\x02/", fn($m) => $codes[(int)$m[1]], $s);
}

function lr_table(array $rows, array $ctx): string {
    $split = function (string $row): array {
        $row = trim($row);
        $row = preg_replace('/^\||\|$/', '', $row);
        $cells = preg_split('/(?<!\\\\)\|/', $row);
        return array_map(fn($c) => str_replace('\|', '|', trim($c)), $cells);
    };
    $head = $split(array_shift($rows));
    array_shift($rows); // separator
    $h = '<div class="lr-table-wrap"><table class="lr-table"><thead><tr>';
    foreach ($head as $c) $h .= '<th>' . lr_inline($c, $ctx) . '</th>';
    $h .= '</tr></thead><tbody>';
    foreach ($rows as $r) {
        $h .= '<tr>';
        foreach ($split($r) as $c) $h .= '<td>' . lr_inline($c, $ctx) . '</td>';
        $h .= '</tr>';
    }
    return $h . '</tbody></table></div>';
}

/** Render a text block (headings, paragraphs, lists, tables, callouts). */
function lr_text(array $lines, array $ctx): string {
    $out = [];
    $para = [];
    $list = null; $items = [];
    $flushPara = function () use (&$para, &$out, $ctx) {
        if ($para) { $out[] = '<p>' . implode(' ', array_map(fn($l) => lr_inline($l, $ctx), $para)) . '</p>'; $para = []; }
    };
    $flushList = function () use (&$list, &$items, &$out, $ctx) {
        if ($list) {
            $out[] = "<$list>" . implode('', array_map(fn($i) => '<li>' . lr_inline($i, $ctx) . '</li>', $items)) . "</$list>";
            $list = null; $items = [];
        }
    };
    $n = count($lines);
    for ($i = 0; $i < $n; $i++) {
        $raw = $lines[$i];
        $t = trim($raw);
        if ($t === '') { $flushPara(); $flushList(); continue; }

        if (preg_match('/^(#{1,4})\s+(.+)$/', $t, $m)) {
            $flushPara(); $flushList();
            $lvl = min(4, strlen($m[1]) + ($m[1] === '#' ? 1 : 0));
            $out[] = "<h$lvl id=\"" . e(lr_slugify($m[2])) . "\">" . lr_inline($m[2], $ctx) . "</h$lvl>";
            continue;
        }
        // Table: a | row followed by a |---| separator.
        if ($t[0] === '|' && $i + 1 < $n && preg_match('/^\|?\s*:?-{3,}/', trim($lines[$i + 1]))) {
            $flushPara(); $flushList();
            $rows = [];
            while ($i < $n && trim($lines[$i]) !== '' && trim($lines[$i])[0] === '|') { $rows[] = $lines[$i]; $i++; }
            $i--;
            $out[] = lr_table($rows, $ctx);
            continue;
        }
        // Callout / blockquote: consecutive "> " lines.
        if ($t[0] === '>') {
            $flushPara(); $flushList();
            $q = [];
            while ($i < $n && ($tt = trim($lines[$i])) !== '' && $tt[0] === '>') { $q[] = ltrim(substr($tt, 1)); $i++; }
            $i--;
            $body = implode(' ', $q);
            $kind = 'note';
            if (preg_match('/^\*\*(Note|Tip|Warning|Important|Remember)\s*:?\*\*\s*:?\s*/i', $body, $m)) {
                $kind = ['note' => 'note', 'tip' => 'tip', 'warning' => 'warn', 'important' => 'warn', 'remember' => 'tip'][strtolower($m[1])];
                $label = ucfirst(strtolower($m[1]));
                $body = substr($body, strlen($m[0]));
                $out[] = '<div class="lr-callout ' . $kind . '"><strong>' . e($label) . ':</strong> ' . lr_inline($body, $ctx) . '</div>';
            } else {
                $out[] = '<blockquote class="lr-callout note">' . lr_inline($body, $ctx) . '</blockquote>';
            }
            continue;
        }
        if (preg_match('/^[-*+]\s+(.+)$/', $t, $m)) {
            $flushPara();
            if ($list !== 'ul') { $flushList(); $list = 'ul'; }
            $items[] = $m[1];
            continue;
        }
        if (preg_match('/^\d+[.)]\s+(.+)$/', $t, $m)) {
            $flushPara();
            if ($list !== 'ol') { $flushList(); $list = 'ol'; }
            $items[] = $m[1];
            continue;
        }
        if ($list && preg_match('/^\s{2,}\S/', $raw)) { $items[count($items) - 1] .= ' ' . $t; continue; } // wrapped list item
        $flushList();
        $para[] = $t;
    }
    $flushPara(); $flushList();
    return implode("\n", $out);
}

function lr_exercise(string $body, int $n, array $ctx): string {
    $q = ''; $hint = ''; $answers = []; $code = [];
    foreach (explode("\n", $body) as $line) {
        if (preg_match('/^Q:\s*(.*)$/', $line, $m) && $q === '') { $q = $m[1]; continue; }
        if (preg_match('/^A:\s*(.*)$/', $line, $m)) { $answers = array_map('trim', explode(' | ', $m[1])); continue; }
        if (preg_match('/^H:\s*(.*)$/', $line, $m)) { $hint = $m[1]; continue; }
        $code[] = $line;
    }
    while ($code && trim(end($code)) === '') array_pop($code);
    while ($code && trim($code[0]) === '') array_shift($code);
    // Blanks written on the Q: line become inputs too (they come first, so answer order is kept).
    if (preg_match('/_{3,}/', $q)) {
        array_unshift($code, $q, ...($code ? [''] : []));
        $q = count($answers) > 1 ? 'Fill in the blanks.' : 'Fill in the blank.';
    }
    $alts = array_map(fn($a) => array_values(array_filter(array_map('trim', explode(' / ', $a)), 'strlen')), $answers);
    $k = 0;
    $html = preg_replace_callback('/_{3,}/', function () use (&$k, $alts) {
        $w = max(3, ...array_map('mb_strlen', $alts[$k] ?? ['xxx']));
        $i = $k++;
        return '<input class="lr-blank" data-i="' . $i . '" size="' . min(30, $w + 1) . '" autocomplete="off" autocapitalize="off" spellcheck="false" aria-label="Blank ' . ($i + 1) . '">';
    }, e(implode("\n", $code)));
    return '<section class="lr-exercise" data-n="' . $n . '" data-answers="' . e(json_encode($alts)) . '">'
        . '<div class="lr-ex-head"><i class="fas fa-pen-to-square"></i> Exercise</div>'
        . '<p class="lr-ex-q">' . lr_inline($q, $ctx) . '</p>'
        . '<pre class="lr-ex-code"><code>' . $html . '</code></pre>'
        . '<div class="lr-ex-actions"><button type="button" class="lr-btn lr-btn-ok" data-act="check">Submit answer</button>'
        . '<button type="button" class="lr-btn lr-btn-ghost" data-act="show">Show answer</button>'
        . ($hint !== '' ? '<button type="button" class="lr-btn lr-btn-ghost" data-act="hint">Hint</button>' : '')
        . '<span class="lr-ex-result" role="status"></span></div>'
        . ($hint !== '' ? '<p class="lr-ex-hint" hidden><i class="fas fa-lightbulb"></i> ' . lr_inline($hint, $ctx) . '</p>' : '')
        . '</section>';
}

function lr_quiz(string $body, int $n, array $ctx): string {
    $questions = [];
    $cur = null;
    foreach (explode("\n", $body) as $line) {
        $t = trim($line);
        if (preg_match('/^Q:\s*(.+)$/', $t, $m)) {
            if ($cur) $questions[] = $cur;
            $cur = ['q' => $m[1], 'opts' => [], 'correct' => null];
        } elseif ($cur && preg_match('/^[-*]\s+(.+?)(\s\*)?$/', $t, $m)) {
            if (!empty($m[2])) $cur['correct'] = count($cur['opts']);
            $cur['opts'][] = $m[1];
        }
    }
    if ($cur) $questions[] = $cur;
    $h = '<form class="lr-quiz" data-quiz="' . $n . '" onsubmit="return false">'
       . '<div class="lr-ex-head"><i class="fas fa-circle-question"></i> Quiz <span class="lr-quiz-count">' . count($questions) . ' questions</span></div>';
    foreach ($questions as $qi => $q) {
        $h .= '<fieldset class="lr-quiz-q" data-correct="' . (int)$q['correct'] . '"><legend><span>' . ($qi + 1) . '.</span> ' . lr_inline($q['q'], $ctx) . '</legend>';
        foreach ($q['opts'] as $oi => $o) {
            $h .= '<label><input type="radio" name="q' . $n . '_' . $qi . '" value="' . $oi . '"> <span>' . lr_inline($o, $ctx) . '</span></label>';
        }
        $h .= '</fieldset>';
    }
    return $h . '<div class="lr-ex-actions"><button type="button" class="lr-btn lr-btn-ok" data-act="grade">Check my answers</button>'
        . '<button type="button" class="lr-btn lr-btn-ghost" data-act="reset">Try again</button><span class="lr-ex-result" role="status"></span></div></form>';
}

function render_lesson(string $md, array $ctx = []): string {
    $blocks = lr_blocks($md);
    $html = [];
    $exN = 0; $qzN = 0; $exerciseN = 0;
    $lessonId = (int)($ctx['lesson_id'] ?? 0);
    $skip = [];
    foreach ($blocks as $k => $b) {
        if (isset($skip[$k])) continue;
        if ($b['type'] === 'text') { $html[] = lr_text($b['lines'], $ctx); continue; }
        $info = $b['info'];
        if (preg_match('/^example\s+([a-z0-9+#-]+)/', $info, $m)) {
            $exN++;
            $lang = lr_lang($m[1]);
            $box = '<div class="lr-example"><div class="lr-ex-head"><i class="fas fa-code"></i> Example <span class="lr-lang">' . e(strtoupper($lang)) . '</span></div>'
                 . '<pre class="lr-code"><code class="' . lr_prism($lang) . '">' . e($b['body']) . '</code></pre>';
            $next = lr_next_meaningful($blocks, $k);
            if ($next !== null && $blocks[$next]['type'] === 'fence' && $blocks[$next]['info'] === 'output') {
                $box .= '<div class="lr-ex-out"><span>Result</span><pre>' . e($blocks[$next]['body']) . '</pre></div>';
                for ($s = $k + 1; $s <= $next; $s++) $skip[$s] = true;
            }
            if ($lessonId) {
                $label = $lang === 'php' ? 'Open in editor' : 'Try it Yourself &raquo;';
                $box .= '<a class="lr-btn lr-btn-ok" href="tryit?lesson=' . $lessonId . '&amp;ex=' . $exN . '" target="_blank" rel="noopener">' . $label . '</a>';
            }
            $html[] = $box . '</div>';
        } elseif ($info === 'output') {
            $html[] = '<div class="lr-ex-out standalone"><span>Result</span><pre>' . e($b['body']) . '</pre></div>';
        } elseif ($info === 'exercise') {
            $html[] = lr_exercise($b['body'], ++$exerciseN, $ctx);
        } elseif ($info === 'quiz') {
            $html[] = lr_quiz($b['body'], ++$qzN, $ctx);
        } else {
            $lang = lr_lang(explode(' ', $info)[0] ?? '');
            $html[] = '<pre class="lr-code"><code class="' . lr_prism($lang) . '">' . e($b['body']) . '</code></pre>';
        }
    }
    return implode("\n", $html);
}
