/* Learning Lab extras (runs after learn.js). Workbook notes autosave, practice-task cards, mobile outline scrim. */
(function () {
  const L = window.LAB;

  // Close the outline on phones when the scrim is tapped
  const side = document.getElementById('lrSide');
  document.getElementById('labScrim')?.addEventListener('click', () => {
    side?.classList.remove('open');
    document.body.classList.remove('lr-menu-open');
  });

  const post = (body) => fetch(L.endpoint, {
    method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': L.csrf },
    body: JSON.stringify(Object.assign({ lesson_id: L.lesson }, body)),
  }).then((r) => r.json());

  // Turn the lesson's "Practice task" section into a task card with a tick box
  const content = document.getElementById('labContent');
  if (content) {
    const heads = [...content.querySelectorAll('h2, h3')].filter((h) => /practice task|your task|try this/i.test(h.textContent));
    heads.forEach((h) => {
      const card = document.createElement('section');
      card.className = 'lab-task';
      const top = document.createElement('div');
      top.className = 'lab-task-head';
      top.innerHTML = '<span class="lab-task-ico"><i class="fas fa-list-check"></i></span><div><small>Practice task</small><strong></strong></div>';
      top.querySelector('strong').textContent = h.textContent.replace(/^practice task:?\s*/i, '') || 'Put it into practice';
      h.parentNode.insertBefore(card, h);
      card.appendChild(top);
      const body = document.createElement('div');
      body.className = 'lab-task-body';
      card.appendChild(body);
      let n = h.nextElementSibling;
      h.remove();
      while (n && !/^H[12]$/.test(n.tagName)) { const nx = n.nextElementSibling; body.appendChild(n); n = nx; }

      if (L) {
        const foot = document.createElement('label');
        foot.className = 'lab-task-done';
        foot.innerHTML = '<input type="checkbox"> <span>I have done this task</span>';
        const cb = foot.querySelector('input');
        cb.checked = !!L.taskDone;
        card.classList.toggle('is-done', cb.checked);
        cb.addEventListener('change', () => {
          card.classList.toggle('is-done', cb.checked);
          post({ action: 'task', done: cb.checked }).then((r) => { if (!r.ok) { cb.checked = !cb.checked; card.classList.toggle('is-done', cb.checked); } })
            .catch(() => { cb.checked = !cb.checked; card.classList.toggle('is-done', cb.checked); });
        });
        const tip = document.createElement('p');
        tip.className = 'lab-task-tip';
        tip.innerHTML = '<i class="fas fa-pen-to-square"></i> Write your answer in <a href="#labNote">My notes</a>; it is saved in your workbook.';
        card.appendChild(tip);
        card.appendChild(foot);
      }
    });
  }

  if (!L) return;

  // Notes: save 1.2 s after typing stops, and when leaving the page
  const note = document.getElementById('labNote');
  const state = document.getElementById('noteState');
  if (!note) return;
  let timer = null, last = note.value, busy = false;
  const save = () => {
    if (note.value === last || busy) return;
    busy = true;
    const v = note.value;
    if (state) state.textContent = 'Saving…';
    post({ action: 'save_note', note: v })
      .then((r) => { if (r.ok) { last = v; if (state) state.textContent = 'Saved ' + r.saved_at; } else if (state) state.textContent = 'Not saved'; })
      .catch(() => { if (state) state.textContent = 'Not saved (offline?)'; })
      .finally(() => { busy = false; if (note.value !== last) { clearTimeout(timer); timer = setTimeout(save, 1200); } });
  };
  note.addEventListener('input', () => { if (state) state.textContent = 'Typing…'; clearTimeout(timer); timer = setTimeout(save, 1200); });
  note.addEventListener('blur', save);
  window.addEventListener('pagehide', () => {
    if (note.value === last) return;
    try {
      fetch(L.endpoint, { method: 'POST', credentials: 'same-origin', keepalive: true,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': L.csrf },
        body: JSON.stringify({ action: 'save_note', lesson_id: L.lesson, note: note.value }) });
    } catch (e) {}
  });
})();
