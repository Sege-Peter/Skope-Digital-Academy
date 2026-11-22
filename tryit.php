<?php
/**
 * "Try it Yourself" code editor (W3Schools-style).
 *   tryit?lesson=ID&ex=N  → the lesson's Nth example (tutorial library), or its single code example (older lessons)
 *   tryit?lang=html|css|javascript|python|sql → playground with templates
 *   …#code=<base64url>    → shared code (see "Share")
 *
 * All code runs inside a sandboxed iframe (allow-scripts, no same-origin), so it can never
 * reach the learner's session or this site. Python runs on Pyodide, SQL on sql.js (SQLite)
 * against the Skope Shop sample database, both loaded inside that sandbox. UI: assets/js/tryit.js.
 */
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/learning.php';
require_once 'includes/lesson_render.php';

const TRYIT_LANGS = ['html', 'css', 'javascript', 'python', 'sql', 'php'];
const RUNNABLE = ['html', 'css', 'javascript', 'python', 'sql'];

$user = isLoggedIn() ? currentUser() : null;
$lesson_id = (int)($_GET['lesson'] ?? 0);
$exN = max(1, (int)($_GET['ex'] ?? 1));
$lang = in_array($_GET['lang'] ?? '', TRYIT_LANGS, true) ? $_GET['lang'] : 'html';
$title = 'Code editor';
$back = 'learn';
$backLabel = 'Tutorials';
$denied = null;
$expected = null;
$exCount = 0;

// Templates for the playground (first one is the starter).
$templates = [
    'html' => [
        'Starter page' => "<!DOCTYPE html>\n<html>\n<head>\n  <title>My page</title>\n  <style>\n    body { font-family: sans-serif; padding: 20px; }\n    h1 { color: #0080ff; }\n  </style>\n</head>\n<body>\n\n<h1>Hello, Skope!</h1>\n<p>Edit the code on the left, then press <b>Run</b>.</p>\n<button onclick=\"this.textContent = 'Clicked!'\">Click me</button>\n\n</body>\n</html>\n",
        'Headings & text' => "<!DOCTYPE html>\n<html>\n<body>\n\n<h1>Karibu Kisumu</h1>\n<h2>The lakeside city</h2>\n<p>Kisumu sits on the shores of <strong>Lake Victoria</strong>.</p>\n<p>It is known for <em>fish</em>, sunsets and the <mark>Impala Sanctuary</mark>.</p>\n<hr>\n<blockquote>“Every expert was once a beginner.”</blockquote>\n\n</body>\n</html>\n",
        'Links & images' => "<!DOCTYPE html>\n<html>\n<body>\n\n<h2>Links and images</h2>\n<p><a href=\"https://www.w3.org/\" target=\"_blank\">Visit the W3C</a></p>\n<img src=\"https://picsum.photos/320/180\" alt=\"A random photo\" width=\"320\" height=\"180\">\n\n</body>\n</html>\n",
        'Table' => "<!DOCTYPE html>\n<html>\n<head>\n<style>\n  table { border-collapse: collapse; font-family: sans-serif; }\n  th, td { border: 1px solid #cbd5e1; padding: 8px 12px; text-align: left; }\n  th { background: #f1f5f9; }\n</style>\n</head>\n<body>\n\n<table>\n  <tr><th>Name</th><th>Town</th><th>Course</th></tr>\n  <tr><td>Achieng</td><td>Kisumu</td><td>HTML</td></tr>\n  <tr><td>Brian</td><td>Nairobi</td><td>Python</td></tr>\n  <tr><td>Fatuma</td><td>Mombasa</td><td>SQL</td></tr>\n</table>\n\n</body>\n</html>\n",
        'Form' => "<!DOCTYPE html>\n<html>\n<head>\n<style>\n  body { font-family: sans-serif; padding: 20px; }\n  label { display: block; margin-top: 12px; font-weight: bold; }\n  input, select { padding: 8px; width: 260px; }\n  button { margin-top: 16px; padding: 10px 18px; }\n</style>\n</head>\n<body>\n\n<form onsubmit=\"event.preventDefault(); document.getElementById('out').textContent = 'Thanks, ' + this.name.value + '!';\">\n  <label for=\"name\">Full name</label>\n  <input id=\"name\" name=\"name\" required>\n  <label for=\"course\">Course</label>\n  <select id=\"course\"><option>HTML</option><option>CSS</option><option>Python</option></select>\n  <br><button type=\"submit\">Register</button>\n</form>\n<p id=\"out\"></p>\n\n</body>\n</html>\n",
        'Flexbox cards' => "<!DOCTYPE html>\n<html>\n<head>\n<style>\n  body { font-family: sans-serif; background: #f6f8fb; padding: 20px; }\n  .row { display: flex; gap: 16px; flex-wrap: wrap; }\n  .card { flex: 1 1 160px; background: #fff; border-radius: 12px; padding: 18px; box-shadow: 0 6px 20px rgba(0,0,0,.08); }\n  .card h3 { margin-top: 0; color: #0080ff; }\n</style>\n</head>\n<body>\n\n<div class=\"row\">\n  <div class=\"card\"><h3>HTML</h3><p>Structure</p></div>\n  <div class=\"card\"><h3>CSS</h3><p>Style</p></div>\n  <div class=\"card\"><h3>JavaScript</h3><p>Behaviour</p></div>\n</div>\n\n</body>\n</html>\n",
        'Button with JavaScript' => "<!DOCTYPE html>\n<html>\n<body>\n\n<h2 id=\"count\">0</h2>\n<button id=\"add\">Add one</button>\n\n<script>\n  let n = 0;\n  document.getElementById('add').addEventListener('click', () => {\n    n++;\n    document.getElementById('count').textContent = n;\n    console.log('Clicked', n, 'times');\n  });\n</script>\n\n</body>\n</html>\n",
    ],
    'css' => [
        'Card' => "body {\n  font-family: sans-serif;\n  background: #f6f8fb;\n  padding: 20px;\n}\n\n.card {\n  background: white;\n  border-radius: 12px;\n  padding: 20px;\n  box-shadow: 0 6px 20px rgba(0,0,0,.08);\n}\n\nh2 { color: #0080ff; }\n",
        'Buttons' => "button {\n  background: #ff8c00;\n  color: white;\n  border: none;\n  padding: 10px 18px;\n  border-radius: 8px;\n  font-size: 16px;\n  cursor: pointer;\n  transition: transform .2s;\n}\n\nbutton:hover {\n  transform: translateY(-2px);\n  background: #e67e00;\n}\n\na { color: #0080ff; font-weight: bold; }\n",
        'Dark theme' => "body {\n  background: #0f172a;\n  color: #e2e8f0;\n  font-family: Georgia, serif;\n  padding: 24px;\n}\n\n.card {\n  border: 1px solid #334155;\n  border-radius: 14px;\n  padding: 20px;\n}\n\nh2 { color: #fbbf24; }\nbutton { background: #22d3ee; border: 0; padding: 8px 14px; border-radius: 6px; }\n",
    ],
    'javascript' => [
        'Hello' => "// console.log output appears below the result\nconst skills = ['HTML', 'CSS', 'JavaScript'];\nskills.forEach((s, i) => console.log(i + 1, s));\n\ndocument.body.innerHTML = '<h2>' + skills.join(' → ') + '</h2>';\n",
        'Array methods' => "const prices = [1200, 450, 3800, 900, 2500];\n\nconst total = prices.reduce((sum, p) => sum + p, 0);\nconst expensive = prices.filter(p => p > 1000);\nconst withVat = prices.map(p => Math.round(p * 1.16));\n\nconsole.log('Total:', total);\nconsole.log('Over 1000:', expensive);\nconsole.log('With VAT:', withVat);\n",
        'Objects & classes' => "class Student {\n  constructor(name, town) {\n    this.name = name;\n    this.town = town;\n  }\n  greet() {\n    return `Hi, I'm \${this.name} from \${this.town}`;\n  }\n}\n\nconst a = new Student('Amina', 'Kisumu');\nconsole.log(a.greet());\nconsole.log(a);\n",
        'Build a list (DOM)' => "const towns = ['Kisumu', 'Nairobi', 'Mombasa', 'Eldoret'];\n\nconst h = document.createElement('h2');\nh.textContent = 'Kenyan towns';\nconst ul = document.createElement('ul');\nfor (const t of towns) {\n  const li = document.createElement('li');\n  li.textContent = t;\n  ul.appendChild(li);\n}\ndocument.body.append(h, ul);\nconsole.log(towns.length, 'towns listed');\n",
        'Timers & async' => "const wait = (ms) => new Promise(r => setTimeout(r, ms));\n\nasync function countdown() {\n  for (let i = 3; i > 0; i--) {\n    console.log(i);\n    document.body.textContent = i;\n    await wait(700);\n  }\n  document.body.textContent = 'Liftoff! 🚀';\n  console.log('Done');\n}\ncountdown();\n",
    ],
    'python' => [
        'Hello' => "# Real Python 3, running in your browser\ntowns = [\"Kisumu\", \"Nairobi\", \"Mombasa\"]\n\nfor i, town in enumerate(towns, start=1):\n    print(f\"{i}. Karibu {town}!\")\n\nprint(\"Total:\", len(towns))\n",
        'Functions' => "def vat(price, rate=0.16):\n    \"\"\"Return the price including VAT.\"\"\"\n    return round(price * (1 + rate), 2)\n\nfor p in [100, 2500, 9999]:\n    print(f\"KES {p:,} -> KES {vat(p):,}\")\n",
        'Dictionaries' => "student = {\"name\": \"Brian\", \"town\": \"Nairobi\", \"scores\": [78, 91, 85]}\n\nprint(student[\"name\"], \"from\", student[\"town\"])\nprint(\"Average:\", sum(student[\"scores\"]) / len(student[\"scores\"]))\n\nfor key, value in student.items():\n    print(f\"{key:>7}: {value}\")\n",
        'Classes' => "class Account:\n    def __init__(self, owner, balance=0):\n        self.owner = owner\n        self.balance = balance\n\n    def deposit(self, amount):\n        self.balance += amount\n        return self.balance\n\nacc = Account(\"Achieng\")\nacc.deposit(500)\nacc.deposit(250)\nprint(f\"{acc.owner} has KES {acc.balance}\")\n",
        'Math & random' => "import math, random\n\nrandom.seed(7)\nprint(\"Square root of 81:\", math.sqrt(81))\nprint(\"Pi to 4 places:\", round(math.pi, 4))\nprint(\"Dice rolls:\", [random.randint(1, 6) for _ in range(5)])\n",
    ],
    'sql' => [
        'Kenyan customers' => "-- Runs against the Skope Shop sample database\nSELECT name, city, country\nFROM customers\nWHERE country = 'Kenya'\nORDER BY name;\n",
        'All products' => "SELECT * FROM products;\n",
        'Join orders' => "SELECT o.order_id, o.order_date, c.name AS customer, o.status\nFROM orders AS o\nJOIN customers AS c ON c.customer_id = o.customer_id\nORDER BY o.order_date;\n",
        'Customers per country' => "SELECT country, COUNT(*) AS customers\nFROM customers\nGROUP BY country\nORDER BY customers DESC;\n",
        'Best-selling products' => "SELECT p.name, SUM(oi.quantity) AS units, SUM(oi.quantity * p.price) AS revenue\nFROM order_items AS oi\nJOIN products AS p ON p.product_id = oi.product_id\nGROUP BY p.name\nORDER BY revenue DESC\nLIMIT 5;\n",
        'Insert then select' => "INSERT INTO customers (name, contact, city, country, joined)\nVALUES ('Skope Learners', 'You', 'Kisumu', 'Kenya', DATE('now'));\n\nSELECT * FROM customers ORDER BY customer_id DESC LIMIT 3;\n",
    ],
    'php' => ['Hello' => "<?php\n\$name = \"Skope\";\necho \"Hello, \$name!\";\n"],
];
$code = reset($templates[$lang]);

if ($lesson_id) {
    $stmt = $pdo->prepare("SELECT l.*, c.title AS course_title, c.slug AS course_slug, c.status AS course_status, c.is_open_tutorial, c.tutor_id, c.tutorial_short
                           FROM lessons l JOIN courses c ON c.id = l.course_id WHERE l.id = ?");
    $stmt->execute([$lesson_id]);
    $lesson = $stmt->fetch();
    $examples = $lesson ? lesson_examples((string)$lesson['content']) : [];
    $exCount = count($examples);
    $source = null;
    if ($examples) {
        $exN = min($exN, $exCount);
        $source = $examples[$exN - 1];
    } elseif ($lesson && $lesson['code_example'] !== null && $lesson['code_example'] !== '') {
        $source = ['lang' => lr_lang($lesson['code_lang'] ?: 'html'), 'code' => $lesson['code_example'], 'output' => null];
    }
    if (!$lesson || !$source) {
        $denied = 'This lesson has no code example.';
    } else {
        $is_staff = $user && ($user['role'] === 'admin' || ($user['role'] === 'tutor' && (int)$lesson['tutor_id'] === (int)$user['id']));
        $enrollment = ($user && $user['role'] === 'student') ? get_enrollment($pdo, (int)$user['id'], (int)$lesson['course_id']) : null;
        [$ok, $why] = reader_can_view(['is_open_tutorial' => $lesson['is_open_tutorial']], $lesson, $enrollment);
        if (!$is_staff && ($lesson['course_status'] !== 'published' || !$ok)) {
            $denied = $why ?: 'This example is not available.';
        } else {
            $code = $source['code'];
            $lang = in_array($source['lang'], TRYIT_LANGS, true) ? $source['lang'] : 'html';
            $expected = $source['output'];
            $title = $lesson['title'];
        }
        $back = learn_url(['id' => $lesson['course_id'], 'slug' => $lesson['course_slug']], $lesson);
        $backLabel = 'Back to lesson';
    }
}
$runnable = in_array($lang, RUNNABLE, true);
$sampleDb = $lang === 'sql' ? (string)@file_get_contents(__DIR__ . '/content/tutorials/sql/_sample_db.sql') : '';
$langNames = ['html' => 'HTML', 'css' => 'CSS', 'javascript' => 'JavaScript', 'python' => 'Python', 'sql' => 'SQL', 'php' => 'PHP'];
$cm = 'https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16';
$config = [
    'lang' => $lang,
    'code' => $code,
    'runnable' => $runnable,
    'sampleDb' => $sampleDb,
    'templates' => $lesson_id ? [] : array_keys($templates[$lang] ?? []),
    'templateCode' => $lesson_id ? [] : array_values($templates[$lang] ?? []),
    'storageKey' => 'sda-tryit:' . ($lesson_id ? "lesson:$lesson_id:$exN" : "lang:$lang"),
    'isLesson' => (bool)$lesson_id,
    'expected' => $expected,
];
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<base href="<?= e(sda_base_href()) ?>">
<title><?= e($title) ?> – <?= e($langNames[$lang]) ?> editor – Skope Digital Academy</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?= $cm ?>/codemirror.min.css">
<link rel="stylesheet" href="<?= $cm ?>/theme/material-darker.min.css">
<link rel="stylesheet" href="assets/css/tryit.css?v=2">
<link rel="icon" type="image/png" href="assets/images/Skope Digital  logo.png">
</head>
<body class="ti">
<header class="ti-top">
  <a class="ti-back" href="<?= e($back) ?>" title="<?= e($backLabel) ?>"><i class="fas fa-arrow-left"></i><span><?= e($backLabel) ?></span></a>
  <a class="ti-brand" href="learn" title="Skope tutorials"><img src="assets/images/Skope Digital  logo.png" alt=""><span>Try it <b>Yourself</b></span></a>
  <div class="ti-title" title="<?= e($title) ?>"><?= e($title) ?></div>
  <?php if (!$lesson_id): ?>
    <nav class="ti-langs" aria-label="Language">
      <?php foreach (['html' => 'HTML', 'css' => 'CSS', 'javascript' => 'JS', 'python' => 'Python', 'sql' => 'SQL'] as $k => $v): ?>
        <a href="tryit?lang=<?= $k ?>" class="<?= $lang === $k ? 'on' : '' ?>"><?= $v ?></a>
      <?php endforeach; ?>
    </nav>
  <?php elseif ($exCount > 1 && !$denied): ?>
    <nav class="ti-ex" aria-label="Examples in this lesson">
      <a class="<?= $exN <= 1 ? 'off' : '' ?>" href="tryit?lesson=<?= $lesson_id ?>&amp;ex=<?= max(1, $exN - 1) ?>" title="Previous example"><i class="fas fa-chevron-left"></i></a>
      <span>Example <?= $exN ?> of <?= $exCount ?></span>
      <a class="<?= $exN >= $exCount ? 'off' : '' ?>" href="tryit?lesson=<?= $lesson_id ?>&amp;ex=<?= min($exCount, $exN + 1) ?>" title="Next example"><i class="fas fa-chevron-right"></i></a>
    </nav>
  <?php endif; ?>
  <?php if (!$denied && $runnable): ?>
    <button class="ti-run" id="runBtn" type="button" title="Run (Ctrl+Enter)"><i class="fas fa-play"></i> Run <span class="kbd">Ctrl ↵</span></button>
  <?php endif; ?>
</header>

<?php if ($denied): ?>
  <main class="ti-denied"><div><i class="fas fa-lock"></i><h1><?= e($denied) ?></h1><p><a href="<?= e($back) ?>">Go back</a> or open the <a href="tryit?lang=html">free code editor</a>.</p></div></main>
<?php else: ?>
<div class="ti-tools" role="toolbar" aria-label="Editor tools">
  <?php if (!$lesson_id && count($templates[$lang] ?? []) > 1): ?>
    <label class="ti-select"><i class="fas fa-shapes"></i><select id="tplSelect" aria-label="Load a template">
      <option value="">Templates…</option>
      <?php foreach (array_keys($templates[$lang]) as $i => $name): ?><option value="<?= $i ?>"><?= e($name) ?></option><?php endforeach; ?>
    </select></label>
  <?php endif; ?>
  <?php if ($runnable && in_array($lang, ['html', 'css', 'javascript'], true)): ?>
    <label class="ti-toggle" title="Run automatically as you type"><input type="checkbox" id="autoRun"> <span>Auto-run</span></label>
  <?php endif; ?>
  <span class="ti-sep"></span>
  <button type="button" id="resetBtn" title="Restore the original code"><i class="fas fa-rotate-left"></i><span>Reset</span></button>
  <button type="button" id="copyBtn" title="Copy code"><i class="fas fa-copy"></i><span>Copy</span></button>
  <button type="button" id="downloadBtn" title="Download as a file"><i class="fas fa-download"></i><span>Download</span></button>
  <button type="button" id="uploadBtn" title="Open a file from your computer"><i class="fas fa-folder-open"></i><span>Open file</span></button>
  <input type="file" id="fileInput" hidden>
  <button type="button" id="shareBtn" title="Copy a link to this code"><i class="fas fa-share-nodes"></i><span>Share</span></button>
  <span class="ti-sep"></span>
  <button type="button" id="layoutBtn" title="Switch between side-by-side and stacked"><i class="fas fa-table-columns"></i><span>Layout</span></button>
  <button type="button" id="wrapBtn" title="Toggle word wrap"><i class="fas fa-paragraph"></i><span>Wrap</span></button>
  <button type="button" id="themeBtn" title="Light / dark editor"><i class="fas fa-circle-half-stroke"></i><span>Theme</span></button>
  <button type="button" id="fontDown" title="Smaller text" aria-label="Smaller text"><i class="fas fa-minus"></i></button>
  <span class="ti-font" id="fontSize">14px</span>
  <button type="button" id="fontUp" title="Larger text" aria-label="Larger text"><i class="fas fa-plus"></i></button>
  <span class="ti-sep"></span>
  <button type="button" id="helpBtn" title="Keyboard shortcuts"><i class="fas fa-keyboard"></i><span>Shortcuts</span></button>
</div>

<main class="ti-main" id="tiMain">
  <section class="ti-pane ti-editor" aria-label="Code">
    <div class="ti-pane-head"><span><i class="fas fa-code"></i> <?= e($langNames[$lang]) ?></span><span class="ti-note" id="restoreNote" hidden>Restored your last edit · <button type="button" id="restoreUndo">Use original</button></span></div>
    <textarea id="code" spellcheck="false" aria-label="Code"><?= e($code) ?></textarea>
  </section>
  <div class="ti-gutter" id="gutter" role="separator" aria-orientation="vertical" aria-label="Resize panes" tabindex="0"></div>
  <section class="ti-pane ti-result" aria-label="Result">
    <div class="ti-pane-head">
      <span><i class="fas fa-display"></i> Result</span>
      <span class="ti-size" id="resultSize"></span>
      <?php if ($runnable): ?><button type="button" id="popBtn" title="Open result in a new window"><i class="fas fa-up-right-from-square"></i></button><?php endif; ?>
    </div>
    <?php if ($runnable): ?>
      <div class="ti-frame-wrap" id="frameWrap">
        <iframe id="result" title="Result" sandbox="allow-scripts allow-modals allow-forms allow-popups"></iframe>
        <div class="ti-loading" id="loading" hidden><i class="fas fa-circle-notch fa-spin"></i> <span>Running…</span></div>
      </div>
      <?php if ($lang === 'sql'): ?>
        <div class="ti-db" id="dbPanel"><div class="ti-db-head"><i class="fas fa-database"></i> Your database <small>click a table to query it · resets every run</small></div><div id="dbTables" class="ti-db-tables">Loading…</div></div>
      <?php endif; ?>
      <?php if (in_array($lang, ['html', 'css', 'javascript'], true)): ?>
        <div class="ti-console" id="consoleBox">
          <div class="ti-console-head"><button type="button" id="consoleToggle"><i class="fas fa-terminal"></i> Console <span class="ti-badge" id="consoleCount">0</span></button><button type="button" id="consoleClear" title="Clear console"><i class="fas fa-ban"></i></button></div>
          <pre id="console" aria-live="polite"></pre>
        </div>
      <?php endif; ?>
      <?php if ($expected !== null && $lang !== 'sql'): ?><details class="ti-expected" open><summary>Expected output</summary><pre><?= e($expected) ?></pre></details><?php endif; ?>
    <?php else: ?>
      <div class="ti-server">
        <i class="fab fa-php"></i>
        <h2><?= e($langNames[$lang]) ?> runs on a web server</h2>
        <p>Browsers can't run PHP. To try this code: install <a href="learn/php/installing-php">XAMPP</a>, save it as a file in <code>htdocs</code> (use <b>Download</b> above), then open it at <code>http://localhost/yourfile.php</code>.</p>
        <?php if ($expected !== null): ?><div class="ti-server-out"><b>Output</b><pre><?= e($expected) ?></pre></div><?php endif; ?>
      </div>
    <?php endif; ?>
  </section>
</main>

<footer class="ti-status" id="status">
  <span><?= e($langNames[$lang]) ?></span>
  <span id="stLines">0 lines</span>
  <span id="stSaved"></span>
  <span id="stRun"></span>
  <span class="ti-status-right">Ctrl+Enter run · Ctrl+/ comment · Ctrl+S save</span>
</footer>

<div class="ti-toast" id="toast" role="status" aria-live="polite"></div>

<dialog class="ti-help" id="helpDlg">
  <h2><i class="fas fa-keyboard"></i> Keyboard shortcuts</h2>
  <table>
    <tr><td><kbd>Ctrl</kbd> + <kbd>Enter</kbd></td><td>Run the code</td></tr>
    <tr><td><kbd>Ctrl</kbd> + <kbd>S</kbd></td><td>Save in this browser</td></tr>
    <tr><td><kbd>Ctrl</kbd> + <kbd>/</kbd></td><td>Comment / uncomment line</td></tr>
    <tr><td><kbd>Tab</kbd> / <kbd>Shift</kbd> + <kbd>Tab</kbd></td><td>Indent / outdent</td></tr>
    <tr><td><kbd>Ctrl</kbd> + <kbd>Z</kbd> / <kbd>Ctrl</kbd> + <kbd>Y</kbd></td><td>Undo / redo</td></tr>
    <tr><td><kbd>Ctrl</kbd> + <kbd>F</kbd></td><td>Find (browser search)</td></tr>
  </table>
  <p>Your code is saved automatically in this browser. <b>Share</b> copies a link that opens this exact code. Code runs in a safe sandbox – it can't see your account.</p>
  <form method="dialog"><button class="ti-run">Got it</button></form>
</dialog>

<dialog class="ti-confirm" id="confirmDlg" aria-labelledby="confirmTitle" aria-describedby="confirmMsg">
  <form method="dialog">
    <div class="ti-confirm-icon" id="confirmIcon"><i class="fas fa-rotate-left"></i></div>
    <h2 id="confirmTitle">Are you sure?</h2>
    <p id="confirmMsg"></p>
    <input type="text" id="confirmField" readonly hidden aria-label="Link">
    <div class="ti-confirm-actions">
      <button value="cancel" class="ti-btn-ghost" id="confirmCancel">Cancel</button>
      <button value="ok" class="ti-run" id="confirmOk">OK</button>
    </div>
  </form>
</dialog>

<script>window.TRYIT = <?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= $cm ?>/codemirror.min.js"></script>
<script src="<?= $cm ?>/mode/xml/xml.min.js"></script>
<script src="<?= $cm ?>/mode/javascript/javascript.min.js"></script>
<script src="<?= $cm ?>/mode/css/css.min.js"></script>
<script src="<?= $cm ?>/mode/htmlmixed/htmlmixed.min.js"></script>
<script src="<?= $cm ?>/mode/python/python.min.js"></script>
<script src="<?= $cm ?>/mode/sql/sql.min.js"></script>
<script src="<?= $cm ?>/mode/clike/clike.min.js"></script>
<script src="<?= $cm ?>/mode/php/php.min.js"></script>
<script src="<?= $cm ?>/addon/edit/closetag.min.js"></script>
<script src="<?= $cm ?>/addon/edit/closebrackets.min.js"></script>
<script src="<?= $cm ?>/addon/edit/matchbrackets.min.js"></script>
<script src="<?= $cm ?>/addon/fold/xml-fold.min.js"></script>
<script src="<?= $cm ?>/addon/edit/matchtags.min.js"></script>
<script src="<?= $cm ?>/addon/selection/active-line.min.js"></script>
<script src="<?= $cm ?>/addon/comment/comment.min.js"></script>
<script src="assets/js/tryit.js?v=3"></script>
<?php endif; ?>
</body>
</html>
