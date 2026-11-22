/* Skope Digital Academy – Try it Yourself editor.
 * Config comes from tryit.php as window.TRYIT. All learner code runs inside a sandboxed
 * iframe (no same-origin), so it can't touch this page, cookies or the site. */
(() => {
  const cfg = window.TRYIT || {};
  const lang = cfg.lang || 'html';
  const $ = (id) => document.getElementById(id);

  /* ── Small helpers ─────────────────────────────────────────────────── */
  const store = {
    get(k, d = null) { try { const v = localStorage.getItem(k); return v === null ? d : v; } catch (e) { return d; } },
    set(k, v) { try { localStorage.setItem(k, v); return true; } catch (e) { return false; } },
    del(k) { try { localStorage.removeItem(k); } catch (e) { /* ignore */ } },
  };
  const PREF = 'sda-tryit-pref:';
  let toastTimer;
  function toast(msg) {
    const t = $('toast');
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('show'), 2200);
  }

  /* Branded dialog in place of the browser's confirm()/prompt(). Resolves true for OK. */
  function askDialog({ title, message, ok = 'OK', cancel = 'Cancel', icon = 'fa-circle-question', tone = 'info', field = null }) {
    const dlg = document.getElementById('confirmDlg');
    if (!dlg || !dlg.showModal) return Promise.resolve(field !== null ? true : window.confirm(message));
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmMsg').textContent = message;
    const ic = document.getElementById('confirmIcon');
    ic.className = 'ti-confirm-icon' + (tone === 'info' ? ' info' : '');
    ic.innerHTML = '<i class="fas ' + icon + '"></i>';
    const okBtn = document.getElementById('confirmOk');
    okBtn.textContent = ok;
    okBtn.className = 'ti-run' + (tone === 'warn' ? ' danger' : '');
    const cancelBtn = document.getElementById('confirmCancel');
    cancelBtn.textContent = cancel || '';
    cancelBtn.hidden = cancel === null;
    const f = document.getElementById('confirmField');
    f.hidden = field === null;
    if (field !== null) f.value = field;
    dlg.returnValue = 'cancel';
    dlg.showModal();
    if (field !== null) { f.focus(); f.select(); } else if (cancelBtn.hidden) { okBtn.focus(); } else { cancelBtn.focus(); }
    return new Promise((resolve) => dlg.addEventListener('close', () => resolve(dlg.returnValue === 'ok'), { once: true }));
  }
  const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const safeJs = (s) => JSON.stringify(s).replace(/<\//g, '<\\/').replace(/<!--/g, '<\\!--');
  const b64encode = (s) => btoa(unescape(encodeURIComponent(s))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  const b64decode = (s) => decodeURIComponent(escape(atob(s.replace(/-/g, '+').replace(/_/g, '/'))));
  const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };

  /* ── Initial code: shared link > saved edit > original ─────────────── */
  const original = cfg.code || '';
  let initial = original;
  let restored = false;
  const hash = new URLSearchParams(location.hash.slice(1));
  if (hash.get('code')) {
    try { initial = b64decode(hash.get('code')); } catch (e) { toast('That share link is damaged – showing the original code.'); }
  } else {
    const saved = store.get(cfg.storageKey);
    if (saved !== null && saved !== original) { initial = saved; restored = true; }
  }

  /* ── Editor (CodeMirror, with a plain textarea fallback) ───────────── */
  const modes = { html: 'htmlmixed', css: 'css', javascript: 'javascript', python: 'python', sql: 'text/x-sqlite', php: 'application/x-httpd-php' };
  const ta = $('code');
  ta.value = initial;
  let theme = store.get(PREF + 'theme', 'dark');
  let fontSize = parseInt(store.get(PREF + 'font', '14'), 10) || 14;
  let wrap = store.get(PREF + 'wrap', '0') === '1';
  let cm = null;
  if (window.CodeMirror) {
    cm = CodeMirror.fromTextArea(ta, {
      mode: modes[lang] || 'htmlmixed',
      theme: theme === 'dark' ? 'material-darker' : 'default',
      lineNumbers: true,
      lineWrapping: wrap,
      indentUnit: lang === 'python' ? 4 : 2,
      tabSize: lang === 'python' ? 4 : 2,
      indentWithTabs: false,
      autoCloseTags: lang === 'html' || lang === 'php',
      autoCloseBrackets: true,
      matchBrackets: true,
      matchTags: lang === 'html' ? { bothTags: true } : false,
      styleActiveLine: true,
      extraKeys: {
        'Ctrl-Enter': () => run(), 'Cmd-Enter': () => run(),
        'Ctrl-S': () => save(true), 'Cmd-S': () => save(true),
        'Ctrl-/': 'toggleComment', 'Cmd-/': 'toggleComment',
        Tab: (c) => c.somethingSelected() ? c.indentSelection('add') : c.replaceSelection(' '.repeat(c.getOption('indentUnit')), 'end'),
        'Shift-Tab': (c) => c.indentSelection('subtract'),
      },
    });
  } else {
    ta.addEventListener('keydown', (ev) => {
      if (ev.key === 'Enter' && (ev.ctrlKey || ev.metaKey)) { ev.preventDefault(); run(); }
      if (ev.key === 's' && (ev.ctrlKey || ev.metaKey)) { ev.preventDefault(); save(true); }
      if (ev.key === 'Tab') { ev.preventDefault(); ta.setRangeText(lang === 'python' ? '    ' : '  ', ta.selectionStart, ta.selectionEnd, 'end'); }
    });
  }
  const getCode = () => (cm ? cm.getValue() : ta.value);
  const setCode = (v) => { if (cm) { cm.setValue(v); cm.focus(); } else { ta.value = v; } onChange(); };

  if (restored) {
    $('restoreNote').hidden = false;
    $('restoreUndo').addEventListener('click', () => { setCode(original); store.del(cfg.storageKey); $('restoreNote').hidden = true; run(); });
  }

  /* ── Status bar, autosave ──────────────────────────────────────────── */
  function updateStatus() {
    const v = getCode();
    const lines = v === '' ? 0 : v.split('\n').length;
    $('stLines').textContent = `${lines} line${lines === 1 ? '' : 's'} · ${v.length.toLocaleString()} chars`;
  }
  function save(manual) {
    const v = getCode();
    const ok = v === original ? (store.del(cfg.storageKey), true) : store.set(cfg.storageKey, v);
    $('stSaved').innerHTML = v === original ? 'Original code' : ok ? '<span class="ok"><i class="fas fa-check"></i> Saved in this browser</span>' : 'Not saved (storage blocked)';
    if (manual) toast(ok ? 'Saved in this browser' : 'Your browser blocked saving');
  }
  const autosave = debounce(() => save(false), 700);
  const autorunLater = debounce(() => run(), 800);
  function onChange() {
    updateStatus();
    autosave();
    if ($('autoRun')?.checked) autorunLater();
  }
  if (cm) cm.on('change', onChange); else ta.addEventListener('input', onChange);
  updateStatus();

  /* ── Sandbox documents ─────────────────────────────────────────────── */
  // Sandboxed frames have no storage: reading localStorage throws, which breaks Pyodide and
  // learner examples. Give the frame a throwaway in-memory Storage instead.
  const storageShim = '<script>(function(){function m(){var d={};return{getItem:function(k){return Object.prototype.hasOwnProperty.call(d,k)?d[k]:null},' +
    'setItem:function(k,v){d[k]=String(v)},removeItem:function(k){delete d[k]},clear:function(){d={}},key:function(i){return Object.keys(d)[i]||null},get length(){return Object.keys(d).length}}}' +
    '["localStorage","sessionStorage"].forEach(function(n){try{window[n].length}catch(e){try{Object.defineProperty(window,n,{value:m(),configurable:true})}catch(e2){}}})})();<\/script>';
  // Forwards console output, errors and timing from inside the sandbox to this page.
  const bridge = (lineOffset) => storageShim + '<script>(function(){var off=' + lineOffset + ';function s(t,a){try{parent.postMessage({sdaTryit:1,t:t,m:Array.from(a).map(function(x){' +
    'if(x instanceof Error)return x.name+": "+x.message;if(x&&x.nodeType===1)return "<"+x.tagName.toLowerCase()+(x.id?"#"+x.id:"")+">";' +
    'try{return typeof x==="object"&&x!==null?JSON.stringify(x,null,1).replace(/\\n\\s*/g," "):String(x)}catch(e){return String(x)}}).join(" ")},"*")}catch(e){}}' +
    '["log","info","warn","error","debug"].forEach(function(k){var o=console[k];console[k]=function(){s(k==="debug"?"log":k,arguments);o.apply(console,arguments)}});' +
    'console.clear=function(){parent.postMessage({sdaTryit:1,t:"clear"},"*")};' +
    'window.onerror=function(m,u,l,c){s("error",[m+(l?" (line "+Math.max(1,l-off)+")":"")]);};' +
    'window.addEventListener("unhandledrejection",function(e){s("error",["Uncaught (in promise) "+(e.reason&&e.reason.message||e.reason)])});' +
    'window.addEventListener("load",function(){parent.postMessage({sdaTryit:1,t:"done"},"*")});})();<\/script>';

  const monoCss = '<style>body{margin:0;font:14px/1.55 "JetBrains Mono",ui-monospace,Consolas,monospace;color:#0f172a;background:#fff}' +
    '.st{padding:12px 14px;color:#64748b;font-family:Inter,system-ui,sans-serif}pre{margin:0;padding:12px 14px;white-space:pre-wrap;word-break:break-word}' +
    '.err{color:#b91c1c}table{border-collapse:collapse;margin:0 14px 14px;font:13px/1.4 Inter,system-ui,sans-serif}th,td{border:1px solid #e2e8f0;padding:6px 10px;text-align:left;vertical-align:top}' +
    'th{background:#f1f5f9;position:sticky;top:0}tr:nth-child(even) td{background:#fafbfc}.meta{padding:10px 14px 6px;font:600 12px Inter,system-ui,sans-serif;color:#475569}' +
    '.null{color:#94a3b8;font-style:italic}</style>';

  const builders = {
    html(src) { return /<head[^>]*>/i.test(src) ? src.replace(/<head[^>]*>/i, (m) => m + bridge(0)) : bridge(0) + src; },
    css(src) {
      return '<!DOCTYPE html><html><head>' + bridge(0) + '<style>\n' + src + '\n</style></head><body>' +
        '<div class="card"><h2>Preview</h2><p>Your CSS styles this sample page: a <a href="#">link</a>, a list and a button.</p>' +
        '<ul><li>Kisumu</li><li>Nairobi</li><li>Mombasa</li></ul><button>Button</button></div></body></html>';
    },
    javascript(src) {
      return '<!DOCTYPE html><html><head>' + bridge(1) + '<style>body{font-family:system-ui,sans-serif;padding:16px}</style></head><body><script>\n' +
        src.replace(/<\/script/gi, '<\\/script') + '\n<\/script></body></html>';
    },
    python(src) {
      return '<!DOCTYPE html><html><head><meta charset="utf-8">' + monoCss + storageShim +
        '<script src="https://cdn.jsdelivr.net/pyodide/v0.26.1/full/pyodide.js"><\/script></head><body><div class="st" id="st">Starting Python… (first run downloads ~10 MB, later runs are faster)</div><pre id="o"></pre><script>' +
        'const code=' + safeJs(src) + ';const o=document.getElementById("o"),st=document.getElementById("st");const t0=performance.now();' +
        'function add(t,c){const s=document.createElement("span");if(c)s.className=c;s.textContent=t;o.appendChild(s)}' +
        'function done(){parent.postMessage({sdaTryit:1,t:"done"},"*")}' +
        '(async()=>{try{const py=await loadPyodide({indexURL:"https://cdn.jsdelivr.net/pyodide/v0.26.1/full/"});' +
        'py.setStdout({batched:t=>add(t+"\\n")});py.setStderr({batched:t=>add(t+"\\n","err")});' +
        'py.setStdin({stdin:()=>{throw new Error("input() is not available in the editor: set the value in your code instead.")}});' +
        'st.textContent="Running…";await py.loadPackagesFromImports(code);await py.runPythonAsync(code);st.remove();if(!o.textContent)add("(the program printed nothing)","st")}' +
        'catch(e){st.remove();const m=String(e&&e.message||e);const lines=m.trim().split("\\n");const i=lines.findIndex(l=>/File "<exec>"/.test(l));' +
        'add((i>=0?lines.slice(i).join("\\n"):m).replace(/File "<exec>"/g,"Your code"),"err")}finally{done()}})();<\/script></body></html>';
    },
    sql(src) {
      return '<!DOCTYPE html><html><head><meta charset="utf-8">' + monoCss + storageShim +
        '<script src="https://cdnjs.cloudflare.com/ajax/libs/sql.js/1.8.0/sql-wasm.js"><\/script></head><body><div class="st" id="st">Loading database…</div><div id="o"></div><script>' +
        'const code=' + safeJs(src) + ',seed=' + safeJs(cfg.sampleDb || '') + ';const o=document.getElementById("o"),st=document.getElementById("st");' +
        'const esc=s=>String(s).replace(/[&<>"]/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;","\\"":"&quot;"}[c]));' +
        'function table(r){let h="<div class=meta>Number of records: "+r.values.length+"</div><table><thead><tr>"+r.columns.map(c=>"<th>"+esc(c)+"</th>").join("")+"</tr></thead><tbody>";' +
        'h+=r.values.map(v=>"<tr>"+v.map(x=>"<td>"+(x===null?"<span class=null>NULL</span>":esc(x))+"</td>").join("")+"</tr>").join("");return h+"</tbody></table>"}' +
        'function done(){parent.postMessage({sdaTryit:1,t:"done"},"*")}' +
        'initSqlJs({locateFile:f=>"https://cdnjs.cloudflare.com/ajax/libs/sql.js/1.8.0/"+f}).then(SQL=>{const db=new SQL.Database();db.run(seed);st.remove();' +
        'try{const res=db.exec(code);if(!res.length){o.innerHTML="<div class=meta>Statement ran successfully. Rows changed: "+db.getRowsModified()+"</div>"}else{o.innerHTML=res.map(table).join("")}}' +
        'catch(e){o.innerHTML="<pre class=err>Error: "+esc(e.message)+"</pre>"}' +
        'try{const t=db.exec("SELECT name FROM sqlite_master WHERE type=\'table\' AND name NOT LIKE \'sqlite_%\' ORDER BY name")[0];' +
        'const tables=t?t.values.map(v=>{const n=v[0];return{name:n,count:db.exec("SELECT COUNT(*) FROM \\""+n+"\\"")[0].values[0][0],cols:(db.exec("PRAGMA table_info(\\""+n+"\\")")[0]||{values:[]}).values.map(c=>c[1])}}):[];' +
        'parent.postMessage({sdaTryit:1,t:"schema",tables},"*")}catch(e){}done()' +
        '}).catch(e=>{st.textContent="Could not load the SQL engine. Check your internet connection.";done()})<\/script></body></html>';
    },
  };

  /* ── Run ───────────────────────────────────────────────────────────── */
  const frame = $('result');
  const consoleEl = $('console');
  let errors = 0, lines = 0, runStarted = 0;
  function consoleReset() {
    if (!consoleEl) return;
    consoleEl.textContent = '';
    errors = 0; lines = 0;
    updateBadge();
  }
  function updateBadge() {
    const b = $('consoleCount');
    if (!b) return;
    b.textContent = lines;
    b.classList.toggle('err', errors > 0);
  }
  function consoleAdd(type, msg) {
    if (!consoleEl) return;
    const icon = { error: '✖ ', warn: '⚠ ', info: 'ℹ ', sys: '' }[type] ?? '› ';
    const line = document.createElement('div');
    line.className = 'l-' + type;
    line.textContent = icon + msg;
    consoleEl.appendChild(line);
    consoleEl.scrollTop = consoleEl.scrollHeight;
    if (type !== 'sys') { lines++; if (type === 'error') errors++; updateBadge(); }
    if (type === 'error') $('consoleBox')?.classList.remove('collapsed');
  }
  function run() {
    if (!frame || !cfg.runnable) return;
    consoleReset();
    $('loading').hidden = false;
    $('loading').querySelector('span').textContent = lang === 'python' ? 'Running Python…' : lang === 'sql' ? 'Running query…' : 'Running…';
    runStarted = performance.now();
    frame.srcdoc = (builders[lang] || builders.html)(getCode());
    save(false);
  }
  window.addEventListener('message', (ev) => {
    if (!frame || ev.source !== frame.contentWindow || !ev.data || !ev.data.sdaTryit) return;
    const d = ev.data;
    if (d.t === 'done') {
      $('loading').hidden = true;
      const ms = Math.round(performance.now() - runStarted);
      $('stRun').textContent = `Ran at ${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })} · ${ms < 1000 ? ms + ' ms' : (ms / 1000).toFixed(1) + ' s'}`;
    } else if (d.t === 'clear') {
      consoleReset();
      consoleAdd('sys', 'Console was cleared');
    } else if (d.t === 'schema') {
      renderSchema(d.tables || []);
    } else {
      consoleAdd(d.t, d.m);
    }
  });

  /* SQL: clickable table list */
  function renderSchema(tables) {
    const box = $('dbTables');
    if (!box) return;
    box.innerHTML = '';
    tables.forEach((t) => {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'ti-db-table';
      b.title = `Show all rows of ${t.name}`;
      b.innerHTML = `<b>${esc(t.name)} <span>${t.count} rows</span></b><small>${t.cols.map(esc).join(', ')}</small>`;
      b.addEventListener('click', () => { setCode(`SELECT * FROM ${t.name};\n`); run(); });
      box.appendChild(b);
    });
    if (!tables.length) box.textContent = 'No tables.';
  }

  /* ── Tools ─────────────────────────────────────────────────────────── */
  $('runBtn')?.addEventListener('click', run);
  const autoRun = $('autoRun');
  if (autoRun) {
    autoRun.checked = store.get(PREF + 'autorun', '0') === '1';
    autoRun.addEventListener('change', () => { store.set(PREF + 'autorun', autoRun.checked ? '1' : '0'); if (autoRun.checked) run(); });
  }
  $('tplSelect')?.addEventListener('change', (ev) => {
    const i = ev.target.value;
    if (i === '') return;
    setCode(cfg.templateCode[+i]);
    ev.target.value = '';
    run();
    toast('Loaded “' + cfg.templates[+i] + '”');
  });
  $('resetBtn').addEventListener('click', async () => {
    if (getCode() !== original) {
      const yes = await askDialog({
        title: 'Reset to the original code?',
        message: 'Your changes in the editor will be replaced with the original ' + (cfg.isLesson ? 'example' : 'starter code') + '. This can’t be undone.',
        ok: 'Reset code', cancel: 'Keep my code', icon: 'fa-rotate-left', tone: 'warn',
      });
      if (!yes) return;
    }
    setCode(original);
    store.del(cfg.storageKey);
    $('restoreNote').hidden = true;
    run();
    toast('Original code restored');
  });
  $('copyBtn').addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(getCode()); toast('Code copied'); }
    catch (e) { ta.style.display = ''; ta.value = getCode(); ta.select(); document.execCommand('copy'); if (cm) ta.style.display = 'none'; toast('Code copied'); }
  });
  const ext = { html: 'html', css: 'css', javascript: 'js', python: 'py', sql: 'sql', php: 'php' }[lang] || 'txt';
  const mime = { html: 'text/html', css: 'text/css', javascript: 'text/javascript', python: 'text/x-python', sql: 'application/sql', php: 'application/x-php' }[lang] || 'text/plain';
  $('downloadBtn').addEventListener('click', () => {
    const blob = new Blob([getCode()], { type: mime + ';charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = (cfg.isLesson ? 'skope-example' : 'skope-' + lang) + '.' + ext;
    document.body.appendChild(a);
    a.click();
    setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 500);
    toast('Downloaded ' + a.download);
  });
  const fileInput = $('fileInput');
  fileInput.accept = '.' + ext + ',.txt' + (lang === 'html' ? ',.htm' : '');
  $('uploadBtn').addEventListener('click', () => fileInput.click());
  fileInput.addEventListener('change', () => {
    const f = fileInput.files[0];
    if (!f) return;
    if (f.size > 500 * 1024) { toast('That file is too big (max 500 KB).'); return; }
    const r = new FileReader();
    r.onload = () => { setCode(String(r.result)); run(); toast('Opened ' + f.name); };
    r.readAsText(f);
    fileInput.value = '';
  });
  $('shareBtn').addEventListener('click', async () => {
    const code = getCode();
    const url = location.href.split('#')[0] + '#code=' + b64encode(code);
    if (url.length > 8000) toast('Long code makes a long link – it may not work in every app.');
    try { await navigator.clipboard.writeText(url); toast('Share link copied – anyone with it sees this code'); }
    catch (e) {
      await askDialog({ title: 'Share this code', message: 'Copy this link and send it to anyone – it opens this exact code.', ok: 'Done', cancel: null, icon: 'fa-share-nodes', field: url });
    }
    history.replaceState(null, '', url);
  });

  /* Layout, wrap, theme, font */
  const main = $('tiMain');
  let stacked = store.get(PREF + 'layout', 'side') === 'stacked';
  let split = parseFloat(store.get(PREF + 'split', '50')) || 50;
  const applyLayout = () => {
    main.classList.toggle('stacked', stacked);
    main.style.setProperty('--ti-split', split + '%');
    $('layoutBtn').classList.toggle('on', stacked);
    cm?.refresh();
  };
  $('layoutBtn').addEventListener('click', () => { stacked = !stacked; store.set(PREF + 'layout', stacked ? 'stacked' : 'side'); applyLayout(); });
  const applyWrap = () => { cm?.setOption('lineWrapping', wrap); if (!cm) ta.style.whiteSpace = wrap ? 'pre-wrap' : 'pre'; $('wrapBtn').classList.toggle('on', wrap); };
  $('wrapBtn').addEventListener('click', () => { wrap = !wrap; store.set(PREF + 'wrap', wrap ? '1' : '0'); applyWrap(); });
  const applyTheme = () => {
    document.body.classList.toggle('light', theme === 'light');
    cm?.setOption('theme', theme === 'dark' ? 'material-darker' : 'default');
    $('themeBtn').title = theme === 'dark' ? 'Switch to light editor' : 'Switch to dark editor';
  };
  $('themeBtn').addEventListener('click', () => { theme = theme === 'dark' ? 'light' : 'dark'; store.set(PREF + 'theme', theme); applyTheme(); });
  const applyFont = () => {
    document.documentElement.style.setProperty('--ti-font', fontSize + 'px');
    if (!cm) ta.style.fontSize = fontSize + 'px';
    $('fontSize').textContent = fontSize + 'px';
    cm?.refresh();
  };
  $('fontDown').addEventListener('click', () => { fontSize = Math.max(11, fontSize - 1); store.set(PREF + 'font', fontSize); applyFont(); });
  $('fontUp').addEventListener('click', () => { fontSize = Math.min(24, fontSize + 1); store.set(PREF + 'font', fontSize); applyFont(); });
  applyLayout(); applyWrap(); applyTheme(); applyFont();

  /* Draggable divider (mouse, touch, keyboard) */
  const gutter = $('gutter');
  const isRows = () => stacked || window.matchMedia('(max-width: 860px)').matches;
  gutter.addEventListener('pointerdown', (ev) => {
    ev.preventDefault();
    gutter.setPointerCapture(ev.pointerId);
    document.body.classList.add('dragging');
    document.body.classList.toggle('rows', isRows());
    const rect = main.getBoundingClientRect();
    const move = (e) => {
      split = isRows() ? (e.clientY - rect.top) / rect.height * 100 : (e.clientX - rect.left) / rect.width * 100;
      split = Math.min(85, Math.max(15, split));
      main.style.setProperty('--ti-split', split + '%');
    };
    const up = () => {
      gutter.removeEventListener('pointermove', move);
      gutter.removeEventListener('pointerup', up);
      document.body.classList.remove('dragging', 'rows');
      store.set(PREF + 'split', split.toFixed(1));
      cm?.refresh();
    };
    gutter.addEventListener('pointermove', move);
    gutter.addEventListener('pointerup', up);
  });
  gutter.addEventListener('dblclick', () => { split = 50; applyLayout(); store.set(PREF + 'split', '50'); });
  gutter.addEventListener('keydown', (ev) => {
    const step = { ArrowLeft: -2, ArrowUp: -2, ArrowRight: 2, ArrowDown: 2 }[ev.key];
    if (!step) return;
    ev.preventDefault();
    split = Math.min(85, Math.max(15, split + step));
    applyLayout();
    store.set(PREF + 'split', split.toFixed(1));
  });

  /* Result size readout */
  const wrapEl = $('frameWrap');
  if (wrapEl && window.ResizeObserver) {
    new ResizeObserver(([entry]) => {
      const { width, height } = entry.contentRect;
      $('resultSize').textContent = `${Math.round(width)} × ${Math.round(height)} px`;
    }).observe(wrapEl);
  }

  /* Console controls */
  $('consoleToggle')?.addEventListener('click', () => $('consoleBox').classList.toggle('collapsed'));
  $('consoleClear')?.addEventListener('click', consoleReset);

  /* Open the result in a new window (still sandboxed) */
  $('popBtn')?.addEventListener('click', () => {
    const w = window.open('', '_blank');
    if (!w) { toast('Allow pop-ups to open the result in a new window.'); return; }
    w.opener = null;
    const doc = (builders[lang] || builders.html)(getCode());
    w.document.write('<!DOCTYPE html><title>Result – Skope Try it</title><style>html,body{margin:0;height:100%}iframe{border:0;width:100%;height:100%}</style>' +
      '<iframe sandbox="allow-scripts allow-modals allow-forms allow-popups"></iframe>');
    w.document.close();
    w.document.querySelector('iframe').srcdoc = doc;
  });

  /* Help */
  $('helpBtn').addEventListener('click', () => {
    const d = $('helpDlg');
    if (d.showModal) d.showModal(); else toast('Ctrl+Enter run · Ctrl+S save · Ctrl+/ comment');
  });

  /* First run */
  if (cfg.runnable) run();
  else $('stRun').textContent = 'This language is not run in the browser';
  cm?.focus();
})();
