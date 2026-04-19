<?php // views/login.php ?>
<div class="login-wrapper">
  <div class="login-art">
    <div class="art-brand">
      <div class="art-icon">◈</div>
      <div class="art-name">PairPal</div>
      <div class="art-tagline">Sales Manager</div>
    </div>
  </div>
  <div class="login-panel">
    <div class="login-box">
      <h1 class="login-title">Welcome back.</h1>
      <p class="login-sub">Sign in to your workspace</p>

      <div id="loginError" class="alert alert-error" style="display:none"></div>
      <div id="loginSuccess" class="alert alert-success" style="display:none">Login successful — redirecting…</div>

      <form id="loginForm" onsubmit="doLogin(event)" novalidate>
        <div class="form-group">
          <label for="loginUsername">Username</label>
          <input type="text" name="username" id="loginUsername"
                 placeholder="your username" autocomplete="username" required>
        </div>
        <div class="form-group">
          <label for="loginPassword">Password</label>
          <input type="password" name="password" id="loginPassword"
                 placeholder="••••••••" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn btn-primary btn-full" id="loginBtn">
          Sign In
        </button>
      </form>

      <div class="login-hint">
        <small>Demo credentials — <strong>admin</strong> / password &nbsp;|&nbsp; <strong>cashier</strong> / password</small>
      </div>

      <div class="login-store-link">
        <a href="index.php">← Back to Store</a>
      </div>
    </div>
  </div>
</div>

<script>
async function doLogin(e) {
  e.preventDefault();

  const btn     = document.getElementById('loginBtn');
  const errEl   = document.getElementById('loginError');
  const succEl  = document.getElementById('loginSuccess');
  const username = document.getElementById('loginUsername').value.trim();
  const password = document.getElementById('loginPassword').value;

  // Client-side validation
  errEl.style.display  = 'none';
  succEl.style.display = 'none';

  if (!username || !password) {
    errEl.textContent    = 'Please enter both username and password.';
    errEl.style.display  = 'block';
    return;
  }

  btn.disabled    = true;
  btn.textContent = 'Signing in…';

  try {
    const fd = new FormData();
    fd.append('action',   'login');
    fd.append('username', username);
    fd.append('password', password);

    const res = await fetch('index.php', {
      method: 'POST',
      body: fd
    });

    // Check if response is actually JSON
    const contentType = res.headers.get('content-type') || '';
    if (!contentType.includes('application/json')) {
      throw new Error('Server returned unexpected response (not JSON). Check server configuration.');
    }

    const data = await res.json();

    if (data.success) {
      succEl.style.display = 'block';
      // Small delay so user sees the success state
      setTimeout(() => {
        window.location.href = 'index.php?page=dashboard';
      }, 400);
    } else {
      errEl.textContent   = data.message || 'Login failed. Please try again.';
      errEl.style.display = 'block';
      btn.disabled        = false;
      btn.textContent     = 'Sign In';
      document.getElementById('loginPassword').value = '';
      document.getElementById('loginPassword').focus();
    }
  } catch (err) {
    errEl.textContent   = 'Connection error: ' + err.message;
    errEl.style.display = 'block';
    btn.disabled        = false;
    btn.textContent     = 'Sign In';
  }
}

// Allow Enter key in username field to move to password
document.getElementById('loginUsername').addEventListener('keydown', function(e) {
  if (e.key === 'Enter') {
    e.preventDefault();
    document.getElementById('loginPassword').focus();
  }
});
</script>
