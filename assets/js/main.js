/**
 * Skope Digital Academy – Main JavaScript
 */

'use strict';

// ── Fade-in on scroll ──────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  const fadeEls = document.querySelectorAll('.card, .feature-card, .category-card, .course-card, .value-card, .mission-card, .scholarship-card, .stat-card');
  const fadeObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry, i) => {
      if (entry.isIntersecting) {
        setTimeout(() => {
          entry.target.classList.add('fade-in', 'visible');
        }, i * 60);
        fadeObserver.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

  fadeEls.forEach(el => {
    el.classList.add('fade-in');
    fadeObserver.observe(el);
  });
});

// ── Toast notification system ──────────────────────────────────
function showToast(message, type = 'info', duration = 5000) {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    document.body.appendChild(container);
  }

  const icons = {
    success: 'fas fa-check-circle',
    danger:  'fas fa-exclamation-circle',
    warning: 'fas fa-exclamation-triangle',
    info:    'fas fa-info-circle',
  };

  const titles = {
    success: 'Success',
    danger:  'Error',
    warning: 'Attention',
    info:    'Update',
  };

  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  
  toast.innerHTML = `
    <div class="toast-icon">
      <i class="${icons[type] || icons.info}"></i>
    </div>
    <div class="toast-content">
      <div class="toast-title">${titles[type] || 'Information'}</div>
      <div class="toast-msg">${message}</div>
    </div>
  `;

  container.appendChild(toast);

  // Trigger animation
  requestAnimationFrame(() => {
    setTimeout(() => {
      toast.classList.add('visible');
    }, 10);
  });

  // Systematic removal
  setTimeout(() => {
    toast.classList.remove('visible');
    setTimeout(() => toast.remove(), 400);
  }, duration);
}

// ── Confirm dialog ─────────────────────────────────────────────
function confirmAction(message, callback) {
  const overlay = document.createElement('div');
  overlay.className = 'modal-overlay open';
  overlay.innerHTML = `
    <div class="modal modal-sm">
      <div class="modal-body" style="text-align:center; padding: 48px 32px;">
        <div style="width: 80px; height: 80px; background: rgba(255,140,0,0.1); color: var(--secondary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.2rem; margin: 0 auto 24px;">
          <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3 style="font-family: 'Poppins', sans-serif; font-weight: 800; font-size: 1.4rem; color: var(--text-primary); margin-bottom: 12px;">Confirm Action</h3>
        <p style="color: var(--text-dim); line-height: 1.6; font-size: 0.95rem; margin-bottom: 32px;">${message}</p>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
          <button id="confirmNo" class="btn btn-ghost" style="height: 54px; border-radius: 14px;">Cancel</button>
          <button id="confirmYes" class="btn btn-primary" style="height: 54px; border-radius: 14px; background: var(--secondary); border-color: var(--secondary); box-shadow: 0 10px 20px rgba(255,140,0,0.2);">Confirm</button>
        </div>
      </div>
    </div>`;
  document.body.appendChild(overlay);
  
  // Fade in
  requestAnimationFrame(() => overlay.style.opacity = '1');

  document.getElementById('confirmNo').onclick  = () => {
    overlay.style.opacity = '0';
    setTimeout(() => overlay.remove(), 400);
  };
  
  document.getElementById('confirmYes').onclick = () => {
    overlay.style.opacity = '0';
    setTimeout(() => {
      overlay.remove();
      callback();
    }, 400);
  };
}

// ── Format file size ───────────────────────────────────────────
function formatFileSize(bytes) {
  if (bytes < 1024)       return bytes + ' B';
  if (bytes < 1048576)    return (bytes / 1024).toFixed(1) + ' KB';
  return (bytes / 1048576).toFixed(1) + ' MB';
}

// ── Debounce ──────────────────────────────────────────────────
function debounce(fn, wait = 300) {
  let timer;
  return (...args) => { clearTimeout(timer); timer = setTimeout(() => fn(...args), wait); };
}

// ── AJAX helper ───────────────────────────────────────────────
async function fetchJSON(url, options = {}) {
  try {
    const res = await fetch(url, {
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      ...options,
    });
    return await res.json();
  } catch (err) {
    console.error('fetchJSON error:', err);
    return { error: 'Network error. Please try again.' };
  }
}

// ── Progress bar animation ────────────────────────────────────
function animateProgressBars() {
  document.querySelectorAll('.progress-bar-fill[data-width]').forEach(bar => {
    const width = bar.dataset.width;
    setTimeout(() => { bar.style.width = width + '%'; }, 300);
  });
}
animateProgressBars();

// ── Sidebar Toggle — Unified for all Dashboards ──────────────────────────────
function toggleSidebar() {
    const sidebar = document.getElementById('dashSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggle  = document.getElementById('dashToggle');
    
    if (sidebar) {
        const isOpen = sidebar.classList.toggle('open');
        if (overlay) overlay.classList.toggle('open', isOpen);
        if (toggle) {
            toggle.innerHTML = isOpen ? '<i class="fas fa-times"></i>' : '<i class="fas fa-bars"></i>';
        }
        document.body.style.overflow = isOpen ? 'hidden' : '';
    }
}

// ── Expose globals ─────────────────────────────────────────────
window.SDAC = { showToast, confirmAction, fetchJSON, debounce, toggleSidebar };
window.SDA = window.SDAC; // Backwards compatibility for now
window.toggleSidebar = toggleSidebar;
