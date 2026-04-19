// assets/js/main.js

// ── CSRF protection ────────────────────────────────────────────────
// The token is rendered into a meta tag by layout.php.
// pairpalPost() is a drop-in wrapper for fetch() that auto-injects it.
function getCsrfToken() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta ? meta.content : '';
}

/**
 * Send a CSRF-protected POST to index.php.
 * Usage: const data = await pairpalPost(formData)
 */
async function pairpalPost(fd) {
  fd.append('csrf_token', getCsrfToken());
  const res = await fetch('index.php', { method: 'POST', body: fd });
  return res.json();
}

// ── Toast notification ─────────────────────────────────────────────
let toastTimer;
function showToast(msg, type = 'info') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = `toast show ${type}`;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => t.classList.remove('show'), 3400);
}

// ── Generic modal ──────────────────────────────────────────────────
function openModal(html) {
  const m  = document.getElementById('modal');
  const ov = document.getElementById('modal-overlay');
  m.innerHTML = html;
  m.style.display = 'block';
  ov.style.display = 'block';
}
function closeModal() {
  document.getElementById('modal').style.display       = 'none';
  document.getElementById('modal-overlay').style.display = 'none';
}

// ── Sidebar ────────────────────────────────────────────────────────
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
}
document.addEventListener('click', (e) => {
  const sb  = document.getElementById('sidebar');
  const btn = document.querySelector('.menu-toggle');
  if (sb?.classList.contains('open') && !sb.contains(e.target) && e.target !== btn) {
    sb.classList.remove('open');
  }
});

// ── Auth ───────────────────────────────────────────────────────────
async function logout() {
  if (!confirm('Sign out?')) return;
  const fd = new FormData(); fd.append('action', 'logout');
  await fetch('index.php', { method: 'POST', body: fd });
  window.location = 'index.php?page=login';
}

// ── Escape key closes modals ───────────────────────────────────────
document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  closeModal();
  ['productModal','stockModal','bulkModal','receiptModal','detailModal','stockQuickModal'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.style.display = 'none';
  });
});

// ── Page load animations ───────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.stat-card, .card, .intel-card').forEach((el, i) => {
    el.style.opacity      = '0';
    el.style.transform    = 'translateY(12px)';
    el.style.transition   = `opacity 0.35s ease ${i * 0.04}s, transform 0.35s ease ${i * 0.04}s`;
    requestAnimationFrame(() => {
      el.style.opacity   = '1';
      el.style.transform = 'translateY(0)';
    });
  });
});
