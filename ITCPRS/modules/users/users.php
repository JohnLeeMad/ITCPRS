<?php
/**
 * modules/users/users.php
 * ─────────────────────────────────────────────────────────────
 * User Management — admin only.
 * Shows admin, secretary, and staff accounts only.
 * Drivers are managed via the Drivers module.
 * The currently logged-in admin can edit their own account here.
 * ─────────────────────────────────────────────────────────────
 */

require_once __DIR__ . '/../../includes/auth.php';
auth_session_start();
require_login();
require_role(['admin']);

$page_title  = 'User Management';
$active_menu = 'users';

// ── Consume session flash ─────────────────────────────────────
$flash_type    = $_SESSION['flash_type']    ?? '';
$flash_message = $_SESSION['flash_message'] ?? '';
$modal_reopen  = $_SESSION['modal_reopen']  ?? '';
$form_data     = $_SESSION['form_data']     ?? [];
unset($_SESSION['flash_type'], $_SESSION['flash_message'],
      $_SESSION['modal_reopen'], $_SESSION['form_data']);

$current_user_id = (int) ($_SESSION['user']['id'] ?? 0);

// ── Helpers ───────────────────────────────────────────────────
function e(mixed $v): string {
  return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function user_photo_url(?string $filename): string {
  if (!$filename) return '';
  return '/assets/uploads/users/' . rawurlencode($filename);
}

function initials(string $name): string {
  $words = array_filter(explode(' ', trim($name)));
  return strtoupper(implode('', array_map(
    fn($w) => mb_substr($w, 0, 1),
    array_slice($words, 0, 2)
  )));
}

function fmt_date(?string $dt): string {
  if (!$dt) return '—';
  return date('M j, Y', strtotime($dt));
}

function fmt_datetime(?string $dt): string {
  if (!$dt) return '—';
  $ts   = strtotime($dt);
  $date = date('M j, Y', $ts);
  $time = date('g:i A', $ts);
  return $date . '<br><span class="dt-time">' . $time . '</span>';
}

// ── Role config (no driver here) ──────────────────────────────
$role_config = [
  'admin'     => ['label' => 'Admin',     'color' => '#d32f2f'],
  'secretary' => ['label' => 'Secretary', 'color' => '#007bff'],
  'staff'     => ['label' => 'Staff',     'color' => '#495057'],
];

// ── Fetch users — admin, secretary, staff only ────────────────
$users  = [];
$result = mysqli_query($conn,
  "SELECT id, full_name, username, email, contact_number,
          photo, role, status, created_at, updated_at, last_login
   FROM users
   WHERE role IN ('admin','secretary','staff')
   ORDER BY
     FIELD(role,'admin','secretary','staff'),
     full_name ASC"
);
if ($result) {
  while ($row = mysqli_fetch_assoc($result)) $users[] = $row;
  mysqli_free_result($result);
}

// Stats
$stat_total     = count($users);
$stat_admin     = count(array_filter($users, fn($u) => $u['role'] === 'admin'));
$stat_secretary = count(array_filter($users, fn($u) => $u['role'] === 'secretary'));
$stat_staff     = count(array_filter($users, fn($u) => $u['role'] === 'staff'));
$stat_active    = count(array_filter($users, fn($u) => $u['status'] === 'active'));
$stat_inactive  = count(array_filter($users, fn($u) => $u['status'] !== 'active'));

// Pagination
$per_page     = 12;
$current_page = max(1, (int) ($_GET['page'] ?? 1));
$total_pages  = max(1, (int) ceil($stat_total / $per_page));
$current_page = min($current_page, $total_pages);
$offset       = ($current_page - 1) * $per_page;
$paged        = array_slice($users, $offset, $per_page);

require_once __DIR__ . '/../../includes/admin_nav.php';
?>

<link rel="stylesheet" href="/assets/css/users.css" />

<!-- ── Flash messages ───────────────────────────────────────── -->
<?php if ($flash_message): ?>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    <?php if ($flash_type === 'success'): ?>
    ITCAlert.toast({ title: <?php echo json_encode($flash_message); ?>, type: 'success' });
    <?php else: ?>
    ITCAlert.show({ title: 'Error', text: <?php echo json_encode($flash_message); ?>, type: 'error' });
    <?php endif; ?>
  });
</script>
<?php endif; ?>

<!-- ── Page Header ──────────────────────────────────────────── -->
<div class="page-header">
  <div class="page-header-left">
    <div class="page-header-eyebrow">Administration</div>
    <h1>User Management</h1>
    <div class="page-header-sub">
      <?php echo $stat_total; ?> system user<?php echo $stat_total !== 1 ? 's' : ''; ?>
      (admin, secretary, staff) &mdash;
      <?php echo $stat_active; ?> active
    </div>
  </div>
  <div class="page-header-actions">
    <button class="btn btn-gold" id="addUserBtn" type="button">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
      </svg>
      Add User
    </button>
  </div>
</div>

<!-- ── Stats (3 cards) ───────────────────────────────────────── -->
<div class="stats-grid users-stats-grid">

  <div class="stat-card stat-navy">
    <div class="stat-header">
      <div class="stat-icon navy">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
      </div>
      <div class="stat-trend flat">All roles</div>
    </div>
    <div class="stat-value"><?php echo $stat_total; ?></div>
    <div class="stat-label">Total Users</div>
  </div>

  <div class="stat-card stat-red">
    <div class="stat-header">
      <div class="stat-icon red">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
          <circle cx="12" cy="8" r="4"/>
          <path d="M4 20v-2a8 8 0 0 1 16 0v2"/>
          <line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/>
        </svg>
      </div>
      <div class="stat-trend flat">
        <?php echo $stat_admin; ?> admin<?php echo $stat_admin !== 1 ? 's' : ''; ?>
      </div>
    </div>
    <div class="stat-value"><?php echo $stat_secretary + $stat_staff; ?></div>
    <div class="stat-label">Secretary / Staff</div>
  </div>

  <div class="stat-card stat-green">
    <div class="stat-header">
      <div class="stat-icon green">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
          <polyline points="22,4 12,14.01 9,11.01"/>
        </svg>
      </div>
      <div class="stat-trend <?php echo $stat_inactive > 0 ? 'down' : 'up'; ?>">
        <?php if ($stat_inactive > 0): ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6,9 12,15 18,9"/></svg>
          <?php echo $stat_inactive; ?> inactive
        <?php else: ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="18,15 12,9 6,15"/></svg>
          All active
        <?php endif; ?>
      </div>
    </div>
    <div class="stat-value"><?php echo $stat_active; ?></div>
    <div class="stat-label">Active Accounts</div>
  </div>

</div>

<!-- ── Users Table ────────────────────────────────────────────── -->
<div class="card">
  <div class="card-head">
    <div>
      <h2>System Users</h2>
      <div class="card-head-sub">
        Admin, secretary &amp; staff accounts &mdash; drivers are managed in the
        <a href="/modules/drivers/drivers.php" class="card-link">Drivers</a> page
      </div>
    </div>
    <div class="users-toolbar">
      <div class="search-box">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input type="text" id="userSearch" placeholder="Search name, username, email…" autocomplete="off" />
      </div>
      <select class="filter-select" id="roleFilter">
        <option value="">All Roles</option>
        <option value="admin">Admin</option>
        <option value="secretary">Secretary</option>
        <option value="staff">Staff</option>
      </select>
      <select class="filter-select" id="statusFilter">
        <option value="">All Statuses</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
        <option value="suspended">Suspended</option>
      </select>
    </div>
  </div>

  <div class="card-body">
    <table class="data-table users-table">
      <thead>
        <tr>
          <th>User</th>
          <th>Username</th>
          <th>Contact</th>
          <th>Role</th>
          <th>Status</th>
          <th>Created</th>
          <th>Last Updated</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody id="usersTableBody">

        <?php if (empty($paged)): ?>
        <tr>
          <td colspan="8" class="users-empty-cell">
            <div class="users-empty">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
              </svg>
              <p>No users found.</p>
            </div>
          </td>
        </tr>

        <?php else: foreach ($paged as $u):
          $photo_url = user_photo_url($u['photo']);
          $ini       = initials($u['full_name']);
          $rc        = $role_config[$u['role']] ?? ['label' => ucfirst($u['role']), 'color' => '#495057'];
          $is_me     = ($u['id'] === $current_user_id);

          $status_pill = ['active' => 'pill-green', 'inactive' => 'pill-amber', 'suspended' => 'pill-red'];
          $s_pill      = $status_pill[$u['status']] ?? 'pill-navy';
        ?>

        <tr data-row
            data-name="<?php echo e($u['full_name']); ?>"
            data-username="<?php echo e($u['username']); ?>"
            data-email="<?php echo e($u['email'] ?? ''); ?>"
            data-role="<?php echo e($u['role']); ?>"
            data-status="<?php echo e($u['status']); ?>">

          <!-- User identity -->
          <td data-label="User">
            <div class="user-identity">
              <?php if ($photo_url): ?>
                <img class="user-avatar-img" src="<?php echo e($photo_url); ?>"
                     alt="<?php echo e($u['full_name']); ?>" />
              <?php else: ?>
                <div class="user-avatar-initials"
                     style="background:<?php echo e($rc['color']); ?>;">
                  <?php echo e($ini); ?>
                </div>
              <?php endif; ?>
              <div class="user-name-wrap">
                <span class="user-fullname">
                  <?php echo e($u['full_name']); ?>
                  <?php if ($is_me): ?>
                    <span class="user-me-badge">You</span>
                  <?php endif; ?>
                </span>
                <?php if ($u['email']): ?>
                <span class="user-email"><?php echo e($u['email']); ?></span>
                <?php endif; ?>
              </div>
            </div>
          </td>

          <td data-label="Username">
            <span class="user-username">@<?php echo e($u['username']); ?></span>
          </td>

          <td data-label="Contact">
            <?php echo $u['contact_number']
              ? e($u['contact_number'])
              : '<span class="muted">—</span>'; ?>
          </td>

          <td data-label="Role">
            <span class="role-badge role-<?php echo e($u['role']); ?>">
              <?php echo e($rc['label']); ?>
            </span>
          </td>

          <td data-label="Status">
            <span class="pill <?php echo $s_pill; ?>"><?php echo ucfirst(e($u['status'])); ?></span>
          </td>

          <td data-label="Created" class="dt-cell">
            <?php echo fmt_datetime($u['created_at']); ?>
          </td>

          <td data-label="Last Updated" class="dt-cell">
            <?php echo fmt_datetime($u['updated_at']); ?>
          </td>

          <td data-label="Actions">
            <div class="row-actions">
              <!-- Every user (including self) has an Edit button -->
              <button type="button"
                class="btn btn-outline btn-sm btn-edit-user"
                title="<?php echo $is_me ? 'Edit your account' : 'Edit user'; ?>"
                data-id="<?php echo (int)$u['id']; ?>"
                data-full-name="<?php echo e($u['full_name']); ?>"
                data-username="<?php echo e($u['username']); ?>"
                data-email="<?php echo e($u['email'] ?? ''); ?>"
                data-contact="<?php echo e($u['contact_number'] ?? ''); ?>"
                data-role="<?php echo e($u['role']); ?>"
                data-status="<?php echo e($u['status']); ?>"
                data-photo="<?php echo e($photo_url); ?>"
                data-is-me="<?php echo $is_me ? '1' : '0'; ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
                <?php echo $is_me ? 'Edit Me' : 'Edit'; ?>
              </button>

              <!-- Cannot delete yourself -->
              <?php if (!$is_me): ?>
              <button type="button"
                class="btn btn-outline btn-sm btn-delete-user"
                title="Delete user"
                data-id="<?php echo (int)$u['id']; ?>"
                data-name="<?php echo e($u['full_name']); ?>"
                data-role="<?php echo e($u['role']); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                  <polyline points="3,6 5,6 21,6"/>
                  <path d="M19 6l-1 14H6L5 6"/>
                  <path d="M10 11v6"/><path d="M14 11v6"/>
                  <path d="M9 6V4h6v2"/>
                </svg>
                Delete
              </button>
              <?php endif; ?>
            </div>
          </td>

        </tr>
        <?php endforeach; endif; ?>

      </tbody>
    </table>
  </div>

  <!-- Empty state (JS filter) -->
  <div id="emptyState" class="users-empty" style="display:none;padding:48px 20px;">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round">
      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
      <circle cx="9" cy="7" r="4"/>
    </svg>
    <p>No users match your search or filter.</p>
  </div>

  <!-- Pagination -->
  <?php if ($total_pages > 1 || $stat_total > 0): ?>
  <div class="card-foot users-pagination">
    <span class="pagination-info" id="countInfo">
      <?php if ($stat_total === 0): ?>No users found<?php else: ?>
        Showing <?php echo $offset + 1; ?>–<?php echo min($offset + $per_page, $stat_total); ?>
        of <?php echo $stat_total; ?> user<?php echo $stat_total !== 1 ? 's' : ''; ?>
      <?php endif; ?>
    </span>
    <?php if ($total_pages > 1): ?>
    <div class="pagination-controls">
      <?php if ($current_page > 1): ?>
        <a class="page-btn" href="?page=<?php echo $current_page - 1; ?>">
          <svg width="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15,18 9,12 15,6"/></svg>
        </a>
      <?php else: ?>
        <span class="page-btn disabled"><svg width="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15,18 9,12 15,6"/></svg></span>
      <?php endif; ?>
      <?php for ($p = 1; $p <= $total_pages; $p++): ?>
        <a class="page-btn <?php echo $p === $current_page ? 'active' : ''; ?>" href="?page=<?php echo $p; ?>"><?php echo $p; ?></a>
      <?php endfor; ?>
      <?php if ($current_page < $total_pages): ?>
        <a class="page-btn" href="?page=<?php echo $current_page + 1; ?>">
          <svg width="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9,18 15,12 9,6"/></svg>
        </a>
      <?php else: ?>
        <span class="page-btn disabled"><svg width="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9,18 15,12 9,6"/></svg></span>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ── Hidden delete form ─────────────────────────────────────── -->
<form id="deleteForm" method="POST" action="users_handler.php" style="display:none;">
  <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
  <input type="hidden" name="action"     value="delete">
  <input type="hidden" name="id"         id="deleteFormId">
</form>

<!-- ══════════════════════════════════════════════════════════
     ADD USER MODAL
═══════════════════════════════════════════════════════════ -->
<div class="itc-modal-overlay" id="addUserModal">
  <div class="itc-modal" role="dialog" aria-modal="true">
    <div class="itc-modal-header">
      <div class="itc-modal-title">Add New User</div>
      <button type="button" class="itc-modal-close" data-close-modal>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>

    <form method="POST" action="users_handler.php" enctype="multipart/form-data" id="addUserForm">
      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
      <input type="hidden" name="action"     value="create">

      <div class="itc-modal-body">
        <div class="form-grid">

          <div class="form-group full">
            <label class="form-label" for="a_full_name">Full Name <span class="req">*</span></label>
            <input class="form-control <?php echo ($modal_reopen==='add' && ($form_data['full_name']??'')==='') ? 'is-error':''; ?>"
                   id="a_full_name" name="full_name" type="text"
                   placeholder="e.g. Maria Santos" maxlength="150"
                   value="<?php echo $modal_reopen==='add' ? e($form_data['full_name']??'') : ''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="a_username">Username <span class="req">*</span></label>
            <input class="form-control" id="a_username" name="username" type="text"
                   placeholder="e.g. maria_s" maxlength="60" autocomplete="off"
                   value="<?php echo $modal_reopen==='add' ? e($form_data['username']??'') : ''; ?>" />
            <span class="form-hint">3–60 chars: letters, numbers, dots, hyphens, underscores</span>
          </div>

          <div class="form-group">
            <label class="form-label" for="a_email">Email Address <span class="req">*</span></label>
            <input class="form-control" id="a_email" name="email" type="email"
                  placeholder="e.g. user@example.com" maxlength="180" required
                  value="<?php echo $modal_reopen==='add' ? e($form_data['email']??'') : ''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="a_contact">Contact Number</label>
            <input class="form-control" id="a_contact" name="contact_number" type="tel"
                   placeholder="e.g. 09171234567" maxlength="20"
                   value="<?php echo $modal_reopen==='add' ? e($form_data['contact_number']??'') : ''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="a_role">Role <span class="req">*</span></label>
            <select class="form-control" id="a_role" name="role">
              <?php foreach (['admin'=>'Admin','secretary'=>'Secretary','staff'=>'Staff'] as $v=>$l): ?>
              <option value="<?php echo $v; ?>"
                <?php echo ($modal_reopen==='add' && ($form_data['role']??'staff')===$v) ? 'selected':''; ?>>
                <?php echo $l; ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label" for="a_status">Status</label>
            <select class="form-control" id="a_status" name="status">
              <?php foreach (['active'=>'Active','inactive'=>'Inactive','suspended'=>'Suspended'] as $v=>$l): ?>
              <option value="<?php echo $v; ?>"
                <?php echo ($modal_reopen==='add' && ($form_data['status']??'active')===$v) ? 'selected':''; ?>>
                <?php echo $l; ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group full">
            <label class="form-label" for="a_password">Password <span class="req">*</span></label>
            <div class="password-wrap">
              <input class="form-control" id="a_password" name="password" type="password"
                     placeholder="Minimum 8 characters" maxlength="100" autocomplete="new-password" />
              <button type="button" class="password-toggle" data-target="a_password">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                </svg>
              </button>
            </div>
          </div>

          <div class="form-divider full"><span>Profile Photo</span></div>

          <div class="form-group full">
            <div class="photo-upload-row">
              <div class="photo-preview-wrap">
                <div class="photo-preview-placeholder" id="a_photo_placeholder">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
                    <circle cx="12" cy="8" r="4"/><path d="M4 20v-2a8 8 0 0 1 16 0v2"/>
                  </svg>
                </div>
                <img id="a_photo_preview" class="photo-preview-img" src="" alt="" style="display:none;" />
              </div>
              <div class="photo-upload-controls">
                <label class="file-upload-btn" for="a_photo">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" width="15" height="15">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="17,8 12,3 7,8"/><line x1="12" y1="3" x2="12" y2="15"/>
                  </svg>
                  <span id="a_photo_label">Upload photo…</span>
                </label>
                <input type="file" id="a_photo" name="photo" accept="image/*"
                       class="file-upload-input"
                       data-label-id="a_photo_label"
                       data-preview-id="a_photo_preview"
                       data-placeholder-id="a_photo_placeholder" />
                <span class="form-hint">JPG, PNG, WebP — max 5 MB. Optional.</span>
              </div>
            </div>
          </div>

        </div>
      </div>

      <div class="itc-modal-footer">
        <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn-gold">Add User</button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     EDIT USER MODAL
═══════════════════════════════════════════════════════════ -->
<div class="itc-modal-overlay" id="editUserModal">
  <div class="itc-modal" role="dialog" aria-modal="true">
    <div class="itc-modal-header">
      <div class="itc-modal-title" id="editModalTitle">Edit User</div>
      <button type="button" class="itc-modal-close" data-close-modal>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>

    <form method="POST" action="users_handler.php" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
      <input type="hidden" name="action"     value="update">
      <input type="hidden" name="id"         id="edit_id"
             value="<?php echo $modal_reopen==='edit' ? (int)($form_data['id']??0) : ''; ?>">

      <div class="itc-modal-body">
        <div class="form-grid">

          <div class="form-group full">
            <label class="form-label" for="edit_full_name">Full Name <span class="req">*</span></label>
            <input class="form-control" id="edit_full_name" name="full_name" type="text"
                   placeholder="e.g. Maria Santos" maxlength="150"
                   value="<?php echo $modal_reopen==='edit' ? e($form_data['full_name']??'') : ''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_username">Username <span class="req">*</span></label>
            <input class="form-control" id="edit_username" name="username" type="text"
                   placeholder="e.g. maria_s" maxlength="60" autocomplete="off"
                   value="<?php echo $modal_reopen==='edit' ? e($form_data['username']??'') : ''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_email">Email Address <span class="req">*</span></label>
            <input class="form-control" id="edit_email" name="email" type="email"
                  placeholder="e.g. user@example.com" maxlength="180" required
                  value="<?php echo $modal_reopen==='edit' ? e($form_data['email']??'') : ''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_contact">Contact Number</label>
            <input class="form-control" id="edit_contact" name="contact_number" type="tel"
                   placeholder="e.g. 09171234567" maxlength="20"
                   value="<?php echo $modal_reopen==='edit' ? e($form_data['contact_number']??'') : ''; ?>" />
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_role">Role <span class="req">*</span></label>
            <select class="form-control" id="edit_role" name="role">
              <?php foreach (['admin'=>'Admin','secretary'=>'Secretary','staff'=>'Staff'] as $v=>$l): ?>
              <option value="<?php echo $v; ?>"
                <?php echo ($modal_reopen==='edit' && ($form_data['role']??'')===$v) ? 'selected':''; ?>>
                <?php echo $l; ?>
              </option>
              <?php endforeach; ?>
            </select>
            <p class="form-hint edit-role-lock-note" id="edit_role_locked_note" style="display:none;">
              You cannot change your own role.
            </p>
          </div>

          <div class="form-group">
            <label class="form-label" for="edit_status">Status</label>
            <select class="form-control" id="edit_status" name="status">
              <?php foreach (['active'=>'Active','inactive'=>'Inactive','suspended'=>'Suspended'] as $v=>$l): ?>
              <option value="<?php echo $v; ?>"
                <?php echo ($modal_reopen==='edit' && ($form_data['status']??'')===$v) ? 'selected':''; ?>>
                <?php echo $l; ?>
              </option>
              <?php endforeach; ?>
            </select>
            <p class="form-hint edit-status-lock-note" id="edit_status_locked_note" style="display:none;">
              You cannot deactivate your own account.
            </p>
          </div>

          <div class="form-group full">
            <label class="form-label" for="edit_new_password">New Password</label>
            <div class="password-wrap">
              <input class="form-control" id="edit_new_password" name="new_password" type="password"
                     placeholder="Leave blank to keep current" maxlength="100" autocomplete="new-password" />
              <button type="button" class="password-toggle" data-target="edit_new_password">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                </svg>
              </button>
            </div>
            <span class="form-hint">Leave blank to keep the existing password.</span>
          </div>

          <div class="form-divider full"><span>Profile Photo</span></div>

          <div class="form-group full">
            <div class="photo-upload-row">
              <div class="photo-preview-wrap">
                <div class="photo-preview-placeholder" id="edit_photo_placeholder">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
                    <circle cx="12" cy="8" r="4"/><path d="M4 20v-2a8 8 0 0 1 16 0v2"/>
                  </svg>
                </div>
                <img id="edit_photo_current" class="photo-preview-img" src="" alt="" style="display:none;" />
                <img id="edit_photo_new"     class="photo-preview-img" src="" alt="" style="display:none;" />
              </div>
              <div class="photo-upload-controls">
                <label class="current-photo-remove" id="edit_remove_wrap" style="display:none;">
                  <input type="checkbox" name="remove_photo" value="1" id="edit_remove_photo" />
                  Remove current photo
                </label>
                <label class="file-upload-btn" for="edit_photo">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" width="15" height="15">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="17,8 12,3 7,8"/><line x1="12" y1="3" x2="12" y2="15"/>
                  </svg>
                  <span id="edit_photo_label">Replace photo…</span>
                </label>
                <input type="file" id="edit_photo" name="photo" accept="image/*"
                       class="file-upload-input"
                       data-label-id="edit_photo_label"
                       data-preview-id="edit_photo_new"
                       data-placeholder-id="edit_photo_placeholder" />
                <span class="form-hint">Leave empty to keep existing photo.</span>
              </div>
            </div>
          </div>

        </div>
      </div>

      <div class="itc-modal-footer">
        <button type="button" class="btn btn-outline" data-close-modal>Cancel</button>
        <button type="submit" class="btn btn-gold">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<?php if ($modal_reopen): ?>
<script>
  document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('<?php echo $modal_reopen === 'edit' ? 'editUserModal' : 'addUserModal'; ?>')
      ?.classList.add('open');
  });
</script>
<?php endif; ?>

<script src="/assets/js/users.js"></script>
<?php require_once __DIR__ . '/../../includes/admin_nav_end.php'; ?>