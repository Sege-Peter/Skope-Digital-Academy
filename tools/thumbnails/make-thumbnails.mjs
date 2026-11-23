// Generates designed course thumbnails (960x540 WebP) with headless Chrome and saves them as each
// course's thumbnail.  Run from the project root:
//   node tools/thumbnails/make-thumbnails.mjs            -> courses with no thumbnail or a generated one
//   node tools/thumbnails/make-thumbnails.mjs --all      -> also replaces uploaded thumbnails
//   node tools/thumbnails/make-thumbnails.mjs 18 21      -> only these course ids
// Needs: XAMPP MySQL running, Google Chrome installed, Node 22+ (global WebSocket).
import { spawn, execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const MYSQL = process.env.MYSQL || 'C:/xampp/mysql/bin/mysql.exe';
const DB = process.env.DB || 'skopedigital';
const CHROME = process.env.CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const OUT = path.join(ROOT, 'uploads/courses');
const args = process.argv.slice(2);
const ALL = args.includes('--all');
const IDS = args.filter(a => /^\d+$/.test(a)).map(Number);

const sql = q => execFileSync(MYSQL, ['-uroot', DB, '-N', '-B', '-e', q], { encoding: 'utf8' });
const rows = sql(`SELECT c.id, c.slug, c.title, IFNULL(cat.name,''), IFNULL(c.level,'beginner'), IFNULL(c.thumbnail,''),
  (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id), IFNULL(c.is_open_tutorial,0), IFNULL(c.price,0)
  FROM courses c LEFT JOIN categories cat ON cat.id = c.category_id WHERE c.status = 'published' ORDER BY c.id`)
  .trim().split('\n').filter(Boolean).map(l => {
    const [id, slug, title, cat, level, thumb, lessons, tut] = l.split('\t');
    return { id: +id, slug, title, cat, level, thumb, lessons: +lessons, tut: tut === '1' };
  })
  .filter(c => IDS.length ? IDS.includes(c.id) : (ALL || !c.thumb || c.thumb.startsWith('thumb-')));

// ── Look per course: palette + icon + motif ───────────────────────────────
const PAL = {
  code:     ['#0b1324', '#0f2a4a', '#00BFFF', '#38bdf8'],
  data:     ['#0b1324', '#13233f', '#22d3ee', '#a78bfa'],
  security: ['#06141b', '#0b2a2a', '#10b981', '#34d399'],
  ai:       ['#120b2e', '#2a1260', '#a78bfa', '#f472b6'],
  business: ['#1a0f05', '#3b1d06', '#FF8C00', '#fbbf24'],
  finance:  ['#06140f', '#0c3324', '#10b981', '#fbbf24'],
  career:   ['#0b1324', '#1e1b4b', '#818cf8', '#00BFFF'],
  design:   ['#1a0b1e', '#3b0d3a', '#f472b6', '#FF8C00'],
  web:      ['#0b1324', '#0c2b40', '#00BFFF', '#FF8C00'],
};
const LOOK = {
  'javascript-foundations': ['code', 'fab fa-js', 'code', 'JS'],
  'javascript': ['code', 'fab fa-js', 'code', 'JS'],
  'javascript-programming': ['code', 'fab fa-js', 'code', 'JS'],
  'css': ['web', 'fab fa-css3-alt', 'code', 'CSS'],
  'html': ['web', 'fab fa-html5', 'code', 'HTML'],
  'php': ['code', 'fab fa-php', 'code', 'PHP'],
  'python': ['code', 'fab fa-python', 'code', 'PY'],
  'python-programming': ['code', 'fab fa-python', 'code', 'PY'],
  'sql': ['data', 'fas fa-database', 'table', 'SQL'],
  'intro-to-coding': ['code', 'fas fa-code', 'code', '</>'],
  'software-development': ['code', 'fas fa-laptop-code', 'code', 'DEV'],
  'web-design': ['web', 'fas fa-pen-ruler', 'browser', 'WEB'],
  'data-analytics': ['data', 'fas fa-chart-pie', 'bars', 'DATA'],
  'computer-packages': ['data', 'fas fa-table-cells', 'table', 'XLS'],
  'intro-cybersecurity': ['security', 'fas fa-shield-halved', 'grid', 'SEC'],
  'networking-basics': ['security', 'fas fa-network-wired', 'nodes', 'NET'],
  'intro-to-ai': ['ai', 'fas fa-brain', 'nodes', 'AI'],
  'bookkeeping': ['finance', 'fas fa-book', 'table', 'KES'],
  'business-finance': ['finance', 'fas fa-sack-dollar', 'bars', 'KES'],
  'financial-literacy': ['finance', 'fas fa-piggy-bank', 'bars', 'KES'],
  'math-refresher': ['career', 'fas fa-square-root-variable', 'grid', '∑'],
  'entrepreneurship': ['business', 'fas fa-rocket', 'bars', 'BIZ'],
  'digital-marketing': ['business', 'fas fa-bullhorn', 'bars', 'MKT'],
  'google-ads-analytics': ['business', 'fas fa-chart-line', 'bars', 'ADS'],
  'online-work-freelancing': ['business', 'fas fa-laptop-house', 'browser', 'WORK'],
  'virtual-assistant': ['business', 'fas fa-headset', 'chat', 'VA'],
  'communication-skills': ['career', 'fas fa-comments', 'chat', 'TALK'],
  'cv-interview-skills': ['career', 'fas fa-file-lines', 'browser', 'CV'],
  'learning-how-to-learn': ['career', 'fas fa-lightbulb', 'nodes', 'LEARN'],
  'customer-service': ['career', 'fas fa-handshake-angle', 'chat', 'CARE'],
  'graphic-design': ['design', 'fas fa-palette', 'shapes', 'ART'],
  'typing-skills': ['career', 'fas fa-keyboard', 'keys', 'ABC'],
};
const fallback = c => {
  const k = c.cat.toLowerCase();
  if (k.includes('design')) return ['design', 'fas fa-palette', 'shapes', 'ART'];
  if (k.includes('business')) return ['business', 'fas fa-briefcase', 'bars', 'BIZ'];
  if (k.includes('financ')) return ['finance', 'fas fa-coins', 'bars', 'KES'];
  if (k.includes('intel')) return ['ai', 'fas fa-brain', 'nodes', 'AI'];
  if (k.includes('career')) return ['career', 'fas fa-user-graduate', 'chat', 'SKILL'];
  return ['code', 'fas fa-laptop-code', 'code', '</>'];
};

const esc = s => String(s).replace(/[&<>"]/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ch]));

function motif(kind, a, b) {
  switch (kind) {
    case 'code': return `<div class="m-code">${['<span style="color:' + b + '">const</span> skill = learn();', '<span style="color:' + a + '">if</span> (practice) {', '&nbsp;&nbsp;level++;', '}', '<span style="color:' + b + '">return</span> career;']
      .map((l, i) => `<div style="opacity:${0.95 - i * .12}"><i>${i + 1}</i>${l}</div>`).join('')}</div>`;
    case 'bars': return `<div class="m-bars">${[38, 58, 46, 74, 64, 92].map((h, i) => `<span style="height:${h}%;opacity:${.35 + i * .11}"></span>`).join('')}<svg viewBox="0 0 300 120" preserveAspectRatio="none"><polyline points="10,100 60,72 110,84 160,46 210,58 290,14" fill="none" stroke="${b}" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg></div>`;
    case 'table': return `<div class="m-table">${Array.from({ length: 5 }, (_, r) => `<div>${Array.from({ length: 4 }, (_, c) => `<span style="${r === 0 ? 'background:' + a + '55' : (r + c) % 3 === 0 ? 'background:' + b + '33' : ''}"></span>`).join('')}</div>`).join('')}</div>`;
    case 'nodes': {
      const pts = [[40, 40], [150, 20], [250, 60], [90, 120], [200, 140], [290, 150], [30, 170]];
      const ed = [[0, 1], [1, 2], [0, 3], [1, 3], [1, 4], [2, 4], [2, 5], [3, 4], [4, 5], [3, 6]];
      return `<svg class="m-nodes" viewBox="0 0 320 190">${ed.map(([p, q]) => `<line x1="${pts[p][0]}" y1="${pts[p][1]}" x2="${pts[q][0]}" y2="${pts[q][1]}" stroke="${a}" stroke-opacity=".45" stroke-width="2"/>`).join('')}${pts.map(([x, y], i) => `<circle cx="${x}" cy="${y}" r="${i % 3 ? 7 : 11}" fill="${i % 2 ? b : a}"/>`).join('')}</svg>`;
    }
    case 'chat': return `<div class="m-chat"><div style="border-color:${a}66"><span></span><span style="width:60%"></span></div><div class="r" style="background:${a}"><span></span><span style="width:50%"></span></div><div style="border-color:${b}66"><span style="width:70%"></span></div></div>`;
    case 'browser': return `<div class="m-browser"><div class="bar"><i></i><i></i><i></i></div><div class="body"><span style="background:${a};width:55%;height:14px"></span><span></span><span style="width:80%"></span><div class="cols"><b style="background:${b}44"></b><b style="background:${a}44"></b><b></b></div></div></div>`;
    case 'shapes': return `<div class="m-shapes"><span class="c" style="background:${a}"></span><span class="s" style="border-color:${b}"></span><span class="t" style="border-bottom-color:${b}aa"></span><span class="c2" style="border-color:${a}"></span></div>`;
    case 'keys': return `<div class="m-keys">${['QWERTYUIOP', 'ASDFGHJKL', 'ZXCVBNM'].map((r, ri) => `<div style="padding-left:${ri * 18}px">${[...r].map(k => `<span${'FJ'.includes(k) ? ` style="background:${a};color:#0b1324"` : ''}>${k}</span>`).join('')}</div>`).join('')}</div>`;
    case 'grid': default: return `<div class="m-grid">${Array.from({ length: 24 }, (_, i) => `<span style="${[3, 8, 13, 15, 20].includes(i) ? 'background:' + a + ';opacity:.9' : ''}"></span>`).join('')}</div>`;
  }
}

function page(c) {
  const [pal, icon, mot, tag] = LOOK[c.slug] || fallback(c);
  const [bg1, bg2, a, b] = PAL[pal];
  const title = c.title.replace(/\s*\(([^)]+)\)\s*$/, '');
  const sub = (c.title.match(/\(([^)]+)\)/) || [])[1] || '';
  const meta = [c.tut ? 'Free tutorial' : c.price > 0 ? 'KES ' + c.price.toLocaleString('en-KE') : 'Free course', c.lessons ? `${c.lessons} lessons` : ''].filter(Boolean);
  return `<!doctype html><html><head><meta charset="utf-8">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@700;800&family=Inter:wght@600;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
html,body{width:960px;height:540px;overflow:hidden}
body{font-family:Inter,sans-serif;color:#fff;position:relative;
  background:radial-gradient(circle at 85% 20%, ${a}40, transparent 45%), radial-gradient(circle at 0% 110%, ${b}33, transparent 50%), linear-gradient(135deg, ${bg1} 0%, ${bg2} 100%)}
.dots{position:absolute;inset:0;background-image:radial-gradient(rgba(255,255,255,.07) 1.5px,transparent 1.5px);background-size:26px 26px;
  -webkit-mask-image:linear-gradient(110deg,#000 10%,transparent 70%)}
.ring{position:absolute;border-radius:50%;border:2px solid ${a}30}
.tag{position:absolute;right:-20px;bottom:-60px;font:800 300px/1 Poppins,sans-serif;letter-spacing:-12px;color:transparent;-webkit-text-stroke:2px ${a}22;white-space:nowrap}
.art{position:absolute;right:56px;top:112px;width:360px;height:250px}
.motif{position:absolute;inset:0;opacity:.9}
.badge{position:absolute;left:-46px;bottom:-26px;width:132px;height:132px;border-radius:34px;display:grid;place-items:center;
  background:linear-gradient(145deg, ${a}, ${b});box-shadow:0 30px 60px -20px ${a}aa, inset 0 2px 0 rgba(255,255,255,.35);font-size:64px;color:#fff}
.copy{position:absolute;left:64px;bottom:64px;width:420px}
.title{font:800 ${title.length > 26 ? 48 : title.length > 11 ? 56 : 68}px/1.04 Poppins,sans-serif;letter-spacing:-1.5px;text-shadow:0 4px 30px rgba(0,0,0,.35)}
.title span{color:${a}}
.subt{margin-top:10px;font:700 22px Inter,sans-serif;color:${b}}
.meta{display:flex;gap:10px;margin-top:22px;flex-wrap:wrap}
.meta b{font:700 20px Inter,sans-serif;padding:8px 16px;border-radius:99px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.16);color:#e2e8f0}
.meta b:first-child{background:${a};border-color:${a};color:#0b1324}
.brand{position:absolute;left:64px;top:118px;display:flex;align-items:center;gap:10px;font:800 17px Poppins,sans-serif;letter-spacing:.5px;color:#cbd5e1}
.brand i{width:30px;height:30px;border-radius:9px;display:grid;place-items:center;background:rgba(255,255,255,.12);font-size:14px;color:${a}}
.brand em{font-style:normal;color:#FF8C00}
/* motifs */
.m-code{position:absolute;inset:0;padding:26px 24px;border-radius:22px;background:rgba(2,6,23,.55);border:1px solid rgba(255,255,255,.1);font:500 21px/1.75 'JetBrains Mono',monospace;color:#e2e8f0;backdrop-filter:blur(4px)}
.m-code i{display:inline-block;width:30px;color:#475569;font-style:normal}
.m-bars{position:absolute;inset:0;display:flex;align-items:flex-end;gap:16px;padding:20px 26px;border-radius:22px;background:rgba(2,6,23,.45);border:1px solid rgba(255,255,255,.1)}
.m-bars span{flex:1;border-radius:10px 10px 4px 4px;background:linear-gradient(180deg, ${a}, ${a}55)}
.m-bars svg{position:absolute;left:20px;right:20px;top:24px;width:calc(100% - 40px);height:55%}
.m-table{position:absolute;inset:0;display:grid;gap:10px;padding:22px;border-radius:22px;background:rgba(2,6,23,.45);border:1px solid rgba(255,255,255,.1)}
.m-table div{display:grid;grid-template-columns:1.4fr 1fr 1fr 1fr;gap:10px}
.m-table span{border-radius:7px;background:rgba(255,255,255,.08)}
.m-nodes{position:absolute;inset:0;width:100%;height:100%}
.m-chat{position:absolute;inset:0;display:grid;gap:16px;align-content:center}
.m-chat div{width:72%;padding:18px 20px;border-radius:22px 22px 22px 6px;border:2px solid;background:rgba(2,6,23,.45);display:grid;gap:10px}
.m-chat div.r{justify-self:end;border:0;border-radius:22px 22px 6px 22px}
.m-chat span{height:12px;border-radius:6px;background:rgba(255,255,255,.35)}
.m-chat div.r span{background:rgba(11,19,36,.45)}
.m-browser{position:absolute;inset:0;border-radius:20px;overflow:hidden;background:rgba(2,6,23,.5);border:1px solid rgba(255,255,255,.12)}
.m-browser .bar{height:38px;display:flex;gap:8px;align-items:center;padding:0 16px;background:rgba(255,255,255,.07)}
.m-browser .bar i{width:12px;height:12px;border-radius:50%;background:rgba(255,255,255,.25)}
.m-browser .body{padding:20px;display:grid;gap:12px}
.m-browser .body > span{display:block;height:10px;border-radius:5px;background:rgba(255,255,255,.18)}
.m-browser .cols{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:6px}
.m-browser .cols b{height:78px;border-radius:12px;background:rgba(255,255,255,.08)}
.m-shapes span{position:absolute;display:block}
.m-shapes .c{width:150px;height:150px;border-radius:50%;right:20px;top:0;opacity:.9}
.m-shapes .s{width:130px;height:130px;border:10px solid;border-radius:26px;left:60px;top:30px;transform:rotate(-14deg)}
.m-shapes .t{width:0;height:0;border-left:80px solid transparent;border-right:80px solid transparent;border-bottom:140px solid;right:110px;bottom:0}
.m-shapes .c2{width:70px;height:70px;border:8px solid;border-radius:50%;left:10px;bottom:10px}
.m-keys{position:absolute;inset:0;display:grid;gap:10px;align-content:center;padding:18px;border-radius:22px;background:rgba(2,6,23,.45);border:1px solid rgba(255,255,255,.1)}
.m-keys div{display:flex;gap:7px}
.m-keys span{width:28px;height:42px;border-radius:7px;display:grid;place-items:center;font:700 14px Inter,sans-serif;color:#cbd5e1;background:rgba(255,255,255,.1);box-shadow:inset 0 -3px 0 rgba(0,0,0,.3)}
.m-grid{position:absolute;inset:0;display:grid;grid-template-columns:repeat(6,1fr);gap:12px;padding:22px;border-radius:22px;background:rgba(2,6,23,.45);border:1px solid rgba(255,255,255,.1)}
.m-grid span{border-radius:10px;background:rgba(255,255,255,.08)}
</style></head><body>
<div class="dots"></div>
<div class="ring" style="width:560px;height:560px;right:-150px;top:-170px"></div>
<div class="ring" style="width:380px;height:380px;right:-60px;top:-80px"></div>
<div class="tag">${esc(tag)}</div>
<div class="art"><div class="motif">${motif(mot, a, b)}</div><div class="badge"><i class="${icon}"></i></div></div>
<div class="copy">
  <div class="title">${esc(title)}</div>
  ${sub ? `<div class="subt">${esc(sub)}</div>` : ''}
  <div class="meta">${meta.map(m => `<b>${esc(m)}</b>`).join('')}</div>
</div>
</body></html>`;
}

// ── Render ────────────────────────────────────────────────────────────────
const sleep = ms => new Promise(r => setTimeout(r, ms));
const port = 9600 + Math.floor(Math.random() * 300);
const tmp = fs.mkdtempSync(path.join(process.env.TEMP || '/tmp', 'sda-thumbs-'));
const chrome = spawn(CHROME, ['--headless=new', '--hide-scrollbars', `--remote-debugging-port=${port}`, `--user-data-dir=${tmp}`, 'about:blank']);
let tabs;
for (let i = 0; i < 60; i++) { try { tabs = await (await fetch(`http://127.0.0.1:${port}/json`)).json(); if (tabs.find(t => t.type === 'page')) break; } catch { } await sleep(250); }
const ws = new WebSocket(tabs.find(t => t.type === 'page').webSocketDebuggerUrl);
await new Promise(r => ws.onopen = r);
let seq = 0; const pending = {};
ws.onmessage = e => { const m = JSON.parse(e.data); if (m.id && pending[m.id]) { pending[m.id](m); delete pending[m.id]; } };
const send = (method, params = {}) => new Promise(r => { const i = ++seq; pending[i] = r; ws.send(JSON.stringify({ id: i, method, params })); });
await send('Emulation.setDeviceMetricsOverride', { width: 960, height: 540, deviceScaleFactor: 1, mobile: false });
await send('Page.enable');

fs.mkdirSync(OUT, { recursive: true });
for (const c of rows) {
  const html = path.join(tmp, `c${c.id}.html`);
  fs.writeFileSync(html, page(c));
  await send('Page.navigate', { url: 'file:///' + html.replace(/\\/g, '/') });
  await sleep(600);
  await send('Runtime.evaluate', { expression: 'document.fonts.ready.then(()=>new Promise(r=>setTimeout(r,250)))', awaitPromise: true });
  const shot = await send('Page.captureScreenshot', { format: 'webp', quality: 88 });
  const file = `thumb-${c.slug}.webp`;
  fs.writeFileSync(path.join(OUT, file), Buffer.from(shot.result.data, 'base64'));
  sql(`UPDATE courses SET thumbnail='${file.replace(/'/g, "''")}' WHERE id=${c.id}`);
  console.log(`#${c.id} ${c.title} -> uploads/courses/${file}`);
}
chrome.kill();
await sleep(500);
try { fs.rmSync(tmp, { recursive: true, force: true }); } catch { /* Chrome may still hold the profile; it's only a temp folder */ }
process.exit(0);
