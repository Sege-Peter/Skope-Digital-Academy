/* Floating admin AI assistant: bubble in the bottom-right corner of every admin page.
   Talks to admin/ai-assistant (same conversation as the full AI Assistant page). */
(function () {
    'use strict';
    var host = document.getElementById('axAi');
    if (!host) return;
    var CSRF = host.dataset.csrf, NAME = host.dataset.name || 'there';
    var KEY = 'sda_axai_open', loaded = false, busy = false;

    var root = document.createElement('div');
    root.className = 'axai';
    root.innerHTML =
        '<div class="axai-panel" id="axaiPanel" role="dialog" aria-label="AI assistant" hidden>' +
        '  <div class="axai-head"><span class="ic"><i class="fas fa-wand-magic-sparkles"></i></span><span class="sp"><b>AI Assistant</b><small id="axaiLbl">Ready to help</small></span>' +
        '    <a href="ai-assistant" title="Open full screen" aria-label="Open full screen"><i class="fas fa-up-right-and-down-left-from-center"></i></a>' +
        '    <button type="button" id="axaiMin" title="Close" aria-label="Close"><i class="fas fa-minus"></i></button></div>' +
        '  <div class="axai-log" id="axaiLog" aria-live="polite">' +
        '    <div class="axai-hello" id="axaiHello"><b>Hi ' + esc(NAME) + ' 👋</b>Ask me to draft posts and emails, or to explain the academy\'s numbers.' +
        '      <div class="axai-chips">' +
        '        <button type="button" data-p="Give me a short summary of how the academy is doing based on the live numbers, with 3 things to focus on this week."><i class="fas fa-chart-line"></i> How are we doing?</button>' +
        '        <button type="button" data-p="Draft a short, friendly announcement for learners about "><i class="fas fa-bullhorn"></i> Draft an announcement</button>' +
        '        <button type="button" data-p="Help me write a polite reply to a learner who says: "><i class="fas fa-envelope"></i> Reply to a learner</button>' +
        '      </div></div>' +
        '  </div>' +
        '  <div class="axai-foot"><form class="axai-form" id="axaiForm"><textarea id="axaiIn" rows="1" placeholder="Ask anything…" aria-label="Message" maxlength="8000"></textarea>' +
        '    <button class="axai-send" id="axaiSend" type="submit" aria-label="Send" disabled><i class="fas fa-arrow-up"></i></button></form>' +
        '    <div class="axai-meta"><span>Check drafts before sending</span><button type="button" id="axaiNew"><i class="fas fa-rotate-left"></i> New chat</button></div></div>' +
        '</div>' +
        '<button type="button" class="axai-fab" id="axaiFab" aria-controls="axaiPanel" aria-expanded="false" aria-label="Open AI assistant"><i class="fas fa-wand-magic-sparkles w"></i><i class="fas fa-xmark x"></i></button>' +
        '<span class="axai-tip">AI Assistant</span>';
    document.body.appendChild(root);

    var $ = function (id) { return document.getElementById(id); };
    var panel = $('axaiPanel'), log = $('axaiLog'), inp = $('axaiIn'), send = $('axaiSend'), fab = $('axaiFab');

    function esc(s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
    function inline(s) { return s.replace(/`([^`]+)`/g, '<code>$1</code>').replace(/\*\*([^*]+)\*\*/g, '<b>$1</b>').replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<i>$2</i>'); }
    function md(src) {   // small, safe Markdown: escaped first, then simple formatting
        var out = [];
        esc(String(src).replace(/\r/g, '')).split(/```/).forEach(function (b, bi) {
            if (bi % 2) { out.push('<pre><code>' + b.replace(/^\w*\n/, '') + '</code></pre>'); return; }
            var list = null, para = [];
            function fp() { if (para.length) { out.push('<p>' + inline(para.join('<br>')) + '</p>'); para = []; } }
            function fl() { if (list) { out.push('<' + list.t + '>' + list.i.map(function (x) { return '<li>' + inline(x) + '</li>'; }).join('') + '</' + list.t + '>'); list = null; } }
            b.split('\n').forEach(function (l) {
                var m;
                if (/^\s*$/.test(l)) { fp(); fl(); }
                else if ((m = l.match(/^\s*#{1,6}\s+(.*)/))) { fp(); fl(); out.push('<h4>' + inline(m[1]) + '</h4>'); }
                else if (/^\s*(---|\*\*\*)\s*$/.test(l)) { fp(); fl(); out.push('<hr>'); }
                else if ((m = l.match(/^\s*[-*•]\s+(.*)/))) { fp(); if (!list || list.t !== 'ul') { fl(); list = { t: 'ul', i: [] }; } list.i.push(m[1]); }
                else if ((m = l.match(/^\s*\d+[.)]\s+(.*)/))) { fp(); if (!list || list.t !== 'ol') { fl(); list = { t: 'ol', i: [] }; } list.i.push(m[1]); }
                else { fl(); para.push(l); }
            });
            fp(); fl();
        });
        return out.join('');
    }
    function add(role, text, err) {
        $('axaiHello').hidden = true;
        var d = document.createElement('div');
        d.className = 'axai-m ' + (role === 'user' ? 'me' : 'bot');
        var b = document.createElement('div');
        b.className = 'axai-b';
        if (role === 'user') b.textContent = text;
        else if (err) b.innerHTML = '<span class="axai-err"><i class="fas fa-circle-exclamation"></i> ' + esc(text) + '</span>';
        else if (text !== '') {
            b.innerHTML = md(text) + '<button type="button" class="axai-copy"><i class="fas fa-copy"></i> Copy</button>';
            b.querySelector('.axai-copy').addEventListener('click', function (e) {
                var btn = e.currentTarget;
                if (navigator.clipboard) navigator.clipboard.writeText(text).then(function () { btn.innerHTML = '<i class="fas fa-check"></i> Copied'; setTimeout(function () { btn.innerHTML = '<i class="fas fa-copy"></i> Copy'; }, 1500); });
            });
        }
        d.appendChild(b);
        log.appendChild(d);
        log.scrollTop = log.scrollHeight;
        return d;
    }
    function load() {
        if (loaded) return;
        loaded = true;
        fetch('ai-assistant?history=1', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
            if (!j || !j.ok) return;
            $('axaiLbl').textContent = j.ready ? 'Powered by ' + j.label : 'No AI key yet – add it in Settings → AI';
            (j.chat || []).forEach(function (m) { add(m.role, m.content); });
        }).catch(function () {});
    }
    function open(v) {
        panel.hidden = !v;
        root.classList.toggle('is-open', v);
        fab.setAttribute('aria-expanded', v ? 'true' : 'false');
        fab.setAttribute('aria-label', v ? 'Close AI assistant' : 'Open AI assistant');
        try { sessionStorage.setItem(KEY, v ? '1' : '0'); } catch (e) {}
        if (v) { load(); setTimeout(function () { inp.focus(); log.scrollTop = log.scrollHeight; }, 50); }
    }
    fab.addEventListener('click', function () { open(panel.hidden); });
    $('axaiMin').addEventListener('click', function () { open(false); fab.focus(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden && !document.querySelector('dialog[open]')) open(false); });

    function size() { inp.style.height = 'auto'; inp.style.height = Math.min(120, inp.scrollHeight) + 'px'; send.disabled = busy || !inp.value.trim(); }
    inp.addEventListener('input', size);
    inp.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); if (!send.disabled) $('axaiForm').requestSubmit(); } });
    root.querySelectorAll('.axai-chips button').forEach(function (c) {
        c.addEventListener('click', function () {
            inp.value = c.dataset.p; size(); inp.focus();
            if (!/\s$/.test(c.dataset.p)) $('axaiForm').requestSubmit(); else inp.setSelectionRange(inp.value.length, inp.value.length);
        });
    });
    $('axaiForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var text = inp.value.trim(); if (!text || busy) return;
        busy = true; inp.value = ''; size();
        add('user', text);
        var t = add('assistant', ''); t.querySelector('.axai-b').innerHTML = '<span class="axai-typing"><span></span><span></span><span></span></span>';
        var fd = new FormData(); fd.append('csrf_token', CSRF); fd.append('action', 'send'); fd.append('message', text);
        fetch('ai-assistant', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); })
            .then(function (j) { t.remove(); j.ok ? add('assistant', j.reply) : add('assistant', j.error || 'Something went wrong.', true); })
            .catch(function () { t.remove(); add('assistant', 'Could not reach the server. Check your connection and try again.', true); })
            .then(function () { busy = false; size(); inp.focus(); });
    });
    $('axaiNew').addEventListener('click', function () {
        var fd = new FormData(); fd.append('csrf_token', CSRF); fd.append('action', 'reset');
        fetch('ai-assistant', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function () {
            log.querySelectorAll('.axai-m').forEach(function (m) { m.remove(); });
            $('axaiHello').hidden = false; inp.focus();
        });
    });
    // Stay open across page changes if it was open
    try { if (sessionStorage.getItem(KEY) === '1') open(true); } catch (e) {}
})();
