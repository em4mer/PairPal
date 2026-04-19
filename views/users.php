<?php
// views/users.php - staff account management (admin only)
$userRepo = new UserRepository();
$users    = $userRepo->getAll();
?>
<div class="page-controls">
  <span class="page-title-inline">Staff Accounts</span>
  <button class="btn btn-primary" onclick="openAddUser()">+ Add Staff</button>
</div>

<div class="card">
  <div class="table-wrap">
  <table class="data-table">
    <thead>
      <tr><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Last Login</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach ($users as $u): $isMe = $u['id'] === ($_SESSION['user_id'] ?? ''); ?>
      <tr>
        <td>
          <strong><?= htmlspecialchars($u['name']) ?></strong>
          <?php if ($isMe): ?><span class="type-badge type-update" style="margin-left:.375rem">You</span><?php endif; ?>
        </td>
        <td><code><?= htmlspecialchars($u['username']) ?></code></td>
        <td class="text-muted"><?= htmlspecialchars($u['email'] ?? '—') ?></td>
        <td><span class="type-badge <?= $u['role']==='admin'?'type-add':'type-update' ?>"><?= ucfirst($u['role']==='user'?'Cashier':$u['role']) ?></span></td>
        <td class="text-muted" style="font-size:.8rem"><?= $u['last_login'] ? date('M j, Y g:i a', strtotime($u['last_login'])) : 'Never' ?></td>
        <td>
          <button class="btn btn-sm btn-ghost" onclick='openEditUser(<?= json_encode($u) ?>)'>Edit</button>
          <?php if (!$isMe): ?>
          <button class="btn btn-sm btn-danger" onclick="deleteUser('<?= $u['id'] ?>','<?= htmlspecialchars(addslashes($u['name'])) ?>')">Delete</button>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($users)): ?>
      <tr><td colspan="6" class="empty">No staff accounts found.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<div id="userModal" class="modal-overlay" style="display:none" onclick="if(event.target===this)closeUserModal()">
  <div class="modal" onclick="event.stopPropagation()" style="max-width:480px">
    <div class="modal-header">
      <h2 id="userModalTitle">Add Staff Account</h2>
      <button class="modal-close" onclick="closeUserModal()">✕</button>
    </div>
    <div class="modal-body" style="padding:1.25rem;display:flex;flex-direction:column;gap:.875rem">
      <div id="userFormError" class="alert alert-error" style="display:none"></div>
      <input type="hidden" id="userId" value="">
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Full Name *</label>
          <input type="text" id="userName" placeholder="e.g. Juan dela Cruz" maxlength="80">
        </div>
        <div class="form-group">
          <label class="form-label">Username *</label>
          <input type="text" id="userUsername" placeholder="e.g. juan" maxlength="40" autocomplete="off">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Email</label>
        <input type="email" id="userEmail" placeholder="juan@pairpal.com" maxlength="120">
      </div>
      <div class="form-group">
        <label class="form-label">Role *</label>
        <select id="userRole">
          <option value="user">Cashier</option>
          <option value="admin">Admin</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label" id="passwordLabel">Password *</label>
        <input type="password" id="userPassword" placeholder="Minimum 6 characters" maxlength="120" autocomplete="new-password">
        <small id="passwordHint" class="text-muted" style="font-size:.78rem;display:none">Leave blank to keep current password.</small>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeUserModal()">Cancel</button>
      <button class="btn btn-primary" onclick="saveUser()" id="userSaveBtn">Save</button>
    </div>
  </div>
</div>

<div class="card" style="margin-top:1.5rem">
  <div class="card-header">
    <span class="card-title">📦 Category Management</span>
    <span class="text-muted" style="font-size:.78rem">Rename or remove product categories across all products</span>
  </div>
  <div style="padding:1rem 1.25rem;display:flex;flex-direction:column;gap:.75rem">
    <?php
    $prodRepo   = new ProductRepository();
    $categories = $prodRepo->getCategories();
    $catCounts  = [];
    foreach ($prodRepo->getAll() as $p) {
        $c = $p['category'] ?? 'Uncategorised';
        $catCounts[$c] = ($catCounts[$c] ?? 0) + 1;
    }
    ?>
    <div id="catError" class="alert alert-error" style="display:none"></div>
    <?php if (empty($categories)): ?>
      <p class="text-muted">No categories found.</p>
    <?php else: ?>
    <table class="data-table compact">
      <thead><tr><th>Category</th><th>Products</th><th style="width:200px">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($categories as $cat): ?>
        <tr id="catrow_<?= htmlspecialchars(urlencode($cat)) ?>">
          <td>
            <span class="cat-label" id="catlabel_<?= htmlspecialchars(urlencode($cat)) ?>"><?= htmlspecialchars($cat) ?></span>
            <input type="text" class="cat-edit-input" id="catedit_<?= htmlspecialchars(urlencode($cat)) ?>" value="<?= htmlspecialchars($cat) ?>" maxlength="60" style="display:none;width:180px">
          </td>
          <td><span class="count-badge"><?= $catCounts[$cat] ?? 0 ?></span></td>
          <td>
            <button class="btn btn-sm btn-ghost" id="catbtn_edit_<?= htmlspecialchars(urlencode($cat)) ?>"
              onclick="startCatEdit('<?= htmlspecialchars(addslashes($cat)) ?>')">Rename</button>
            <button class="btn btn-sm btn-primary" id="catbtn_save_<?= htmlspecialchars(urlencode($cat)) ?>" style="display:none"
              onclick="saveCatRename('<?= htmlspecialchars(addslashes($cat)) ?>')">Save</button>
            <button class="btn btn-sm btn-ghost" id="catbtn_cancel_<?= htmlspecialchars(urlencode($cat)) ?>" style="display:none"
              onclick="cancelCatEdit('<?= htmlspecialchars(addslashes($cat)) ?>')">Cancel</button>
            <?php if (($catCounts[$cat] ?? 0) === 0): ?>
            <button class="btn btn-sm btn-danger" onclick="deleteCat('<?= htmlspecialchars(addslashes($cat)) ?>')">Delete</button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<script>

let editingUserId = '';

function openAddUser() {
    editingUserId = '';
    document.getElementById('userModalTitle').textContent = 'Add Staff Account';
    document.getElementById('userId').value       = '';
    document.getElementById('userName').value     = '';
    document.getElementById('userUsername').value = '';
    document.getElementById('userEmail').value    = '';
    document.getElementById('userRole').value     = 'user';
    document.getElementById('userPassword').value = '';
    document.getElementById('userUsername').disabled = false;
    document.getElementById('passwordLabel').textContent = 'Password *';
    document.getElementById('passwordHint').style.display = 'none';
    document.getElementById('userFormError').style.display = 'none';
    document.getElementById('userSaveBtn').textContent = 'Create Account';
    document.getElementById('userModal').style.display = 'flex';
}

function openEditUser(u) {
    editingUserId = u.id;
    document.getElementById('userModalTitle').textContent = 'Edit Staff Account';
    document.getElementById('userId').value       = u.id;
    document.getElementById('userName').value     = u.name;
    document.getElementById('userUsername').value = u.username;
    document.getElementById('userEmail').value    = u.email || '';
    document.getElementById('userRole').value     = u.role;
    document.getElementById('userPassword').value = '';
    document.getElementById('userUsername').disabled = false;
    document.getElementById('passwordLabel').textContent = 'New Password';
    document.getElementById('passwordHint').style.display = 'block';
    document.getElementById('userFormError').style.display = 'none';
    document.getElementById('userSaveBtn').textContent = 'Save Changes';
    document.getElementById('userModal').style.display = 'flex';
}

function closeUserModal() {
    document.getElementById('userModal').style.display = 'none';
}

async function saveUser() {
    const id       = document.getElementById('userId').value;
    const name     = document.getElementById('userName').value.trim();
    const username = document.getElementById('userUsername').value.trim();
    const email    = document.getElementById('userEmail').value.trim();
    const role     = document.getElementById('userRole').value;
    const password = document.getElementById('userPassword').value;
    const errEl    = document.getElementById('userFormError');
    const btn      = document.getElementById('userSaveBtn');

    errEl.style.display = 'none';
    if (!name)     { errEl.textContent = 'Full name is required.';  errEl.style.display = 'block'; return; }
    if (!username) { errEl.textContent = 'Username is required.';   errEl.style.display = 'block'; return; }
    if (!id && password.length < 6) { errEl.textContent = 'Password must be at least 6 characters.'; errEl.style.display = 'block'; return; }

    btn.disabled = true; btn.textContent = 'Saving…';
    const fd = new FormData();
    fd.append('action',   id ? 'user_update' : 'user_create');
    fd.append('id',       id);
    fd.append('name',     name);
    fd.append('username', username);
    fd.append('email',    email);
    fd.append('role',     role);
    fd.append('password', password);

    const data = await pairpalPost(fd);
    btn.disabled = false; btn.textContent = id ? 'Save Changes' : 'Create Account';

    if (data.success) {
        closeUserModal();
        showToast(data.message, 'success');
        setTimeout(() => location.reload(), 600);
    } else {
        errEl.textContent = data.message || 'Failed.';
        errEl.style.display = 'block';
    }
}

async function deleteUser(id, name) {
    if (!confirm(`Delete staff account "${name}"? This cannot be undone.`)) return;
    const fd = new FormData();
    fd.append('action', 'user_delete');
    fd.append('id', id);
    const data = await pairpalPost(fd);
    if (data.success) {
        showToast('Account deleted.', 'success');
        setTimeout(() => location.reload(), 500);
    } else {
        showToast(data.message || 'Failed to delete.', 'error');
    }
}

function encCat(name) { return encodeURIComponent(name); }

function startCatEdit(cat) {
    const key = encCat(cat);
    document.getElementById('catlabel_'  + key).style.display = 'none';
    document.getElementById('catedit_'   + key).style.display = 'inline-block';
    document.getElementById('catbtn_edit_'   + key).style.display = 'none';
    document.getElementById('catbtn_save_'   + key).style.display = 'inline-block';
    document.getElementById('catbtn_cancel_' + key).style.display = 'inline-block';
    document.getElementById('catedit_' + key).focus();
}

function cancelCatEdit(cat) {
    const key = encCat(cat);
    document.getElementById('catlabel_'  + key).style.display = '';
    document.getElementById('catedit_'   + key).style.display = 'none';
    document.getElementById('catbtn_edit_'   + key).style.display = '';
    document.getElementById('catbtn_save_'   + key).style.display = 'none';
    document.getElementById('catbtn_cancel_' + key).style.display = 'none';
}

async function saveCatRename(oldName) {
    const key     = encCat(oldName);
    const newName = document.getElementById('catedit_' + key).value.trim();
    if (!newName || newName === oldName) { cancelCatEdit(oldName); return; }
    const fd = new FormData();
    fd.append('action',   'category_rename');
    fd.append('old_name', oldName);
    fd.append('new_name', newName);
    const data = await pairpalPost(fd);
    if (data.success) {
        showToast(`Renamed to "${newName}" (${data.count} product(s) updated).`, 'success');
        setTimeout(() => location.reload(), 600);
    } else {
        document.getElementById('catError').textContent = data.message || 'Failed.';
        document.getElementById('catError').style.display = 'block';
    }
}

async function deleteCat(name) {
    if (!confirm(`Move all products in "${name}" to Uncategorised and delete this category?`)) return;
    const fd = new FormData();
    fd.append('action',   'category_rename');
    fd.append('old_name', name);
    fd.append('new_name', 'Uncategorised');
    const data = await pairpalPost(fd);
    if (data.success) {
        showToast(`Category deleted.`, 'success');
        setTimeout(() => location.reload(), 500);
    } else {
        showToast(data.message || 'Failed.', 'error');
    }
}
</script>
