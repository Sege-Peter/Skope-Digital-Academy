/* Skope Digital Academy – tutorial reader: lesson menu, exercises, quizzes. */
(() => {
  // Mobile lesson menu (tutorial pages handle it in includes/learn-top.php together with the site menu)
  const side = document.getElementById('lrSide');
  if (!document.getElementById('lrSiteBtn')) document.getElementById('lrMenu')?.addEventListener('click', () => {
    const open = side.classList.toggle('open');
    document.body.classList.toggle('lr-menu-open', open);
  });
  // Bring the current lesson into view inside the sidebar only (never scroll the page itself).
  const on = side?.querySelector('.lr-item.on');
  if (on && side.scrollHeight > side.clientHeight) side.scrollTop = Math.max(0, on.offsetTop - side.clientHeight / 2);

  const norm = (s) => String(s).trim().replace(/\s+/g, ' ').toLowerCase();

  // Exercises: fill in the blanks
  document.querySelectorAll('.lr-exercise').forEach((ex) => {
    let answers = [];
    try { answers = JSON.parse(ex.dataset.answers || '[]'); } catch (e) { /* malformed: leave empty */ }
    const blanks = [...ex.querySelectorAll('.lr-blank')];
    const result = ex.querySelector('.lr-ex-result');
    const say = (text, ok) => { result.textContent = text; result.className = 'lr-ex-result ' + (ok ? 'ok' : 'bad'); };

    ex.addEventListener('click', (ev) => {
      const act = ev.target.closest('[data-act]')?.dataset.act;
      if (act === 'check') {
        let right = 0;
        blanks.forEach((b, i) => {
          const ok = (answers[i] || []).some((a) => norm(a) === norm(b.value));
          b.classList.toggle('ok', ok);
          b.classList.toggle('bad', !ok);
          if (ok) right++;
        });
        if (right === blanks.length) {
          say('Correct! Well done.', true);
          if (!ex.dataset.revealed) document.dispatchEvent(new CustomEvent('sda:exercise', { detail: { n: Number(ex.dataset.n || 1) } }));
        }
        else say(`${right} of ${blanks.length} correct – try again.`, false);
      } else if (act === 'show') {
        ex.dataset.revealed = '1';   // no XP for an exercise once its answer was revealed
        blanks.forEach((b, i) => { b.value = (answers[i] || [''])[0]; b.classList.remove('bad'); b.classList.add('ok'); });
        say('Here is the answer.', true);
      } else if (act === 'hint') {
        ex.querySelector('.lr-ex-hint')?.toggleAttribute('hidden');
      }
    });
    blanks.forEach((b, i) => b.addEventListener('keydown', (ev) => {
      if (ev.key === 'Enter') { ev.preventDefault(); (blanks[i + 1] || ex.querySelector('[data-act="check"]')).focus(); if (!blanks[i + 1]) ex.querySelector('[data-act="check"]').click(); }
    }));
  });

  // Quizzes: multiple choice, graded in the browser
  document.querySelectorAll('.lr-quiz').forEach((quiz) => {
    const result = quiz.querySelector('.lr-ex-result');
    quiz.addEventListener('click', (ev) => {
      const act = ev.target.closest('[data-act]')?.dataset.act;
      if (!act) return;
      const qs = [...quiz.querySelectorAll('.lr-quiz-q')];
      if (act === 'reset') {
        quiz.reset();
        qs.forEach((q) => q.querySelectorAll('label').forEach((l) => l.classList.remove('ok', 'bad')));
        result.textContent = '';
        quiz.scrollIntoView({ behavior: 'smooth', block: 'start' });
        return;
      }
      let score = 0, answered = 0;
      qs.forEach((q) => {
        const correct = Number(q.dataset.correct);
        const labels = [...q.querySelectorAll('label')];
        const picked = q.querySelector('input:checked');
        labels.forEach((l) => l.classList.remove('ok', 'bad'));
        labels[correct]?.classList.add('ok');
        if (picked) {
          answered++;
          if (Number(picked.value) === correct) score++;
          else picked.closest('label').classList.add('bad');
        }
      });
      const pct = Math.round(score / qs.length * 100);
      if (answered === qs.length) document.dispatchEvent(new CustomEvent('sda:quiz', { detail: { n: Number(quiz.dataset.quiz || 1), score, total: qs.length } }));
      result.className = 'lr-ex-result ' + (pct >= 70 ? 'ok' : 'bad');
      result.textContent = `You scored ${score} / ${qs.length} (${pct}%)` + (answered < qs.length ? ` – ${qs.length - answered} unanswered` : '') + (pct >= 70 ? '. Great work!' : '. Review the lessons and try again.');
    });
  });
})();

/* ── Learning engine: auto-tracking, XP, badges, certificates, reviews ── */
(() => {
  const T = window.SDA_TRACK;
  const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

  /* XP toast + level chip */
  let toastTimer;
  function xpToast(text) {
    const t = document.getElementById('xpToast');
    if (!t) return;
    t.innerHTML = text;
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('show'), 2600);
  }
  function updateLevel(level) {
    if (!level) return;
    const num = document.getElementById('lvNum'), bar = document.getElementById('lvBar'), xp = document.getElementById('lvXp');
    if (num) {
      if (Number(num.textContent) < level.level) xpToast(`<i class="fas fa-arrow-up"></i> Level up! You're now level <b>${level.level}</b> · ${esc(level.title)}`);
      num.textContent = level.level;
    }
    if (bar) bar.style.width = level.pct + '%';
    if (xp) xp.textContent = Number(level.xp).toLocaleString();
  }

  /* Badge / certificate celebration dialog */
  const queue = [];
  function celebrate(items) { queue.push(...items); if (queue.length === items.length) showNext(); }
  function showNext() {
    const box = document.getElementById('celebrate');
    const item = queue[0];
    if (!box || !item) { if (box) box.hidden = true; return; }
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    box.innerHTML = '<div class="lr-celebrate-card" role="dialog" aria-modal="true" aria-labelledby="celTitle">' +
      (reduce ? '' : '<div class="confetti" aria-hidden="true">' + Array.from({ length: 18 }, (_, i) => `<i style="--i:${i}"></i>`).join('') + '</div>') +
      (item.type === 'cert'
        ? (item.locked
          ? `<div class="cel-icon cert"><i class="fas fa-award"></i></div><p class="cel-kicker">Course completed</p><h2 id="celTitle">${esc(item.title)}</h2><p>Congratulations! Your certificate has been generated. Unlock it for <b>${esc(item.fee_label)}</b> to download and share it.</p>` +
            `<div class="cel-actions"><a class="lr-btn lr-btn-accent" href="certificate-pay?code=${encodeURIComponent(item.code)}"><i class="fas fa-lock-open"></i> Unlock certificate</a><button type="button" class="lr-btn lr-btn-ghost" data-close>Later</button></div>`
          : `<div class="cel-icon cert"><i class="fas fa-award"></i></div><p class="cel-kicker">Certificate earned</p><h2 id="celTitle">${esc(item.title)}</h2><p>Congratulations! Your certificate is ready to download and share.</p>` +
            `<div class="cel-actions"><a class="lr-btn lr-btn-accent" href="${esc(item.url)}" target="_blank" rel="noopener"><i class="fas fa-certificate"></i> View certificate</a><button type="button" class="lr-btn lr-btn-ghost" data-close>Later</button></div>`)
        : `<div class="cel-icon" style="--bc:${esc(item.color || '#0080ff')}"><i class="fas ${esc(item.icon || 'fa-award')}"></i></div><p class="cel-kicker">${item.kind === 'skill' ? 'Skill badge earned' : item.kind === 'path' ? 'Path badge earned' : 'New badge'}</p><h2 id="celTitle">${esc(item.name)}</h2><p>${esc(item.description)}</p>` +
          `<div class="cel-actions"><a class="lr-btn lr-btn-ghost" href="student/achievements">See all badges</a><button type="button" class="lr-btn lr-btn-primary" data-close>Awesome!</button></div>`) +
      '</div>';
    box.hidden = false;
    box.querySelector('[data-close]')?.focus();
  }
  document.addEventListener('click', (ev) => {
    const box = document.getElementById('celebrate');
    if (!box || box.hidden) return;
    if (ev.target.closest('[data-close]') || ev.target === box) { queue.shift(); showNext(); }
  });
  document.addEventListener('keydown', (ev) => {
    const box = document.getElementById('celebrate');
    if (ev.key === 'Escape' && box && !box.hidden) { queue.shift(); showNext(); }
  });

  function handle(res) {
    if (!res || !res.ok && res.ok !== undefined) return;
    if (res.xp > 0) xpToast(`<i class="fas fa-bolt"></i> <b>+${res.xp} XP</b>`);
    updateLevel(res.level);
    const items = (res.badges || []).map((b) => ({ type: 'badge', ...b }));
    if (res.certificate && res.certificate.new !== false) items.push({ type: 'cert', title: res.certificate.title, code: res.certificate.code, locked: !!res.certificate.locked, fee_label: res.certificate.fee_label, url: res.certificate.url || ('certificate?code=' + encodeURIComponent(res.certificate.code)) });
    if (items.length) celebrate(items);
    if (typeof res.progress === 'number') {
      const bar = document.querySelector('.lr-progress span');
      if (bar) bar.style.width = Math.round(res.progress) + '%';
    }
  }

  // Celebrations queued by a full-page action (e.g. "Mark complete" in the classroom or reader).
  if (window.SDA_FLASH) handle({ ok: true, xp: window.SDA_FLASH.xp, badges: window.SDA_FLASH.badges, certificate: window.SDA_FLASH.certificate ? { ...window.SDA_FLASH.certificate, url: 'certificate?code=' + encodeURIComponent(window.SDA_FLASH.certificate.code) } : null });

  async function send(action, data = {}, beacon = false) {
    if (!T) return null;
    const body = JSON.stringify({ action, lesson_id: T.lesson, ...data });
    try {
      const r = await fetch(T.endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': T.csrf }, body, keepalive: beacon, credentials: 'same-origin' });
      const json = await r.json().catch(() => null);
      return json;
    } catch (e) { return null; }
  }

  if (T) {
    // 1. Lesson opened
    send('view').then(handle);

    // 2. Active reading time (only while visible and the learner interacted in the last minute)
    let lastActive = Date.now(), active = 0, unsent = 0;
    ['scroll', 'keydown', 'mousemove', 'pointerdown', 'touchstart'].forEach((e) => window.addEventListener(e, () => { lastActive = Date.now(); }, { passive: true }));
    setInterval(() => {
      if (document.visibilityState === 'visible' && Date.now() - lastActive < 60000) { active++; unsent++; }
      if (unsent >= 20) { send('time', { seconds: unsent }); unsent = 0; }
      maybeComplete();
    }, 1000);
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'hidden' && unsent > 0) { send('time', { seconds: unsent }, true); unsent = 0; } });

    // 3. Auto-complete: reached the end of the lesson and read for long enough
    let reachedEnd = false, completed = !!T.completed;
    const end = document.querySelector('.lr-done-row') || document.querySelector('.lr-nav-bottom');
    if (end && 'IntersectionObserver' in window) {
      new IntersectionObserver((entries) => { if (entries.some((e) => e.isIntersecting)) { reachedEnd = true; maybeComplete(); } }, { threshold: 0.4 }).observe(end);
    }
    async function maybeComplete() {
      if (completed || T.blocked || !reachedEnd || active < T.minSecs) return;
      completed = true;
      const res = await send('complete');
      if (!res || !res.ok) { completed = false; if (res && res.error === 'tests') { T.blocked = true; gate(res.reason); } return; }
      handle(res);
      const row = document.querySelector('.lr-done-row');
      if (row) row.querySelector('form')?.replaceWith(Object.assign(document.createElement('span'), { className: 'lr-pill free', innerHTML: '<i class="fas fa-check"></i> Completed automatically' }));
      const item = document.querySelector('.lr-item.on');
      if (item && !item.querySelector('.fa-circle-check')) item.insertAdjacentHTML('beforeend', '<i class="fas fa-circle-check"></i>');
    }

    // 4. Exercises and quizzes
    // Tests gate: completing stays locked until every exercise and quiz in the lesson is passed.
    function gate(text) {
      const g = document.getElementById('lrGate');
      if (g && text) g.querySelector('span').textContent = text;
    }
    function testsUpdate(res) {
      const t = res && res.tests;
      if (!t) return;
      if (t.ok && T.prevDone) {
        T.blocked = false;
        document.getElementById('lrGate')?.remove();
        const f = document.getElementById('lrCompleteForm');
        if (f) f.hidden = false;
        maybeComplete();
      } else if (!t.ok) {
        gate('Finish the tests in this lesson to continue (' + t.done + ' of ' + t.total + ' done). Still to do: ' + t.missing.join(', ') + '.');
      }
    }
    document.addEventListener('sda:exercise', (e) => send('exercise', { n: e.detail.n }).then((res) => { handle(res); testsUpdate(res); }));
    document.addEventListener('sda:quiz', (e) => send('quiz', e.detail).then((res) => {
      handle(res);
      testsUpdate(res);
      if (res && res.ok && res.completed) setTimeout(() => location.reload(), 4000);
    }));
  }

  /* Course rating & review */
  const rv = document.getElementById('review');
  if (rv) {
    let value = rv.querySelectorAll('.lr-stars .on').length;
    const stars = [...rv.querySelectorAll('.lr-stars button')];
    const paint = (v) => stars.forEach((s, i) => { s.classList.toggle('on', i < v); s.setAttribute('aria-checked', String(i + 1 === v)); });
    stars.forEach((s) => {
      s.addEventListener('click', () => { value = Number(s.dataset.v); paint(value); });
      s.addEventListener('mouseenter', () => paint(Number(s.dataset.v)));
    });
    rv.querySelector('.lr-stars').addEventListener('mouseleave', () => paint(value));
    rv.querySelector('[data-act="save-review"]').addEventListener('click', async () => {
      const out = rv.querySelector('.lr-ex-result');
      if (!value) { out.className = 'lr-ex-result bad'; out.textContent = 'Pick a star rating first.'; return; }
      try {
        const r = await fetch('api/review', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': rv.dataset.csrf }, credentials: 'same-origin',
          body: JSON.stringify({ course_id: Number(rv.dataset.course), rating: value, review: rv.querySelector('textarea').value }) });
        const res = await r.json();
        if (!res.ok) { out.className = 'lr-ex-result bad'; out.textContent = res.error || 'Could not save.'; return; }
        out.className = 'lr-ex-result ok';
        out.textContent = 'Thanks for your review!';
        handle({ ok: true, xp: res.xp, badges: res.badges });
      } catch (e) { out.className = 'lr-ex-result bad'; out.textContent = 'Network problem – try again.'; }
    });
  }
})();

/* Lesson quizzes: no copying or right-click on quiz questions (practice for the real tests) */
['copy', 'cut', 'contextmenu', 'dragstart'].forEach((ev) => document.addEventListener(ev, (e) => {
  if (e.target && e.target.closest && e.target.closest('.lr-quiz')) e.preventDefault();
}, true));
