<?php
$basePath = $base_path ?? '';
$csrfToken = $csrf_token ?? '';
ob_start();
?>
<div class="page-shell">
    <header class="topbar">
        <div class="title-block">
            <h1>Member Registry</h1>
            <p>Create, review, and maintain active association membership records.</p>
        </div>
    </header>

    <div class="grid-two">
        <section class="panel">
            <h2>Create Member</h2>
            <form id="memberForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <label>Member ID</label>
                <input name="member_id" required>
                <label>First Name</label>
                <input name="first_name" required>
                <label>Last Name</label>
                <input name="last_name" required>
                <label>Phone</label>
                <input name="phone" required>
                <label>Email</label>
                <input name="email" type="email">
                <label>Date Joined</label>
                <input name="date_joined" type="date" value="<?php echo date('Y-m-d'); ?>" required>
                <button class="btn" type="submit">Create Member</button>
            </form>
            <div id="status" class="mono"></div>
        </section>

        <section class="panel">
            <div class="toolbar">
                <input id="search" placeholder="Search by first name">
                <button class="btn alt row" type="button" id="refresh">Refresh</button>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="rows"></tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<script>
const base = <?php echo json_encode($basePath); ?>;
const rows = document.getElementById('rows');
const statusBox = document.getElementById('status');
const searchInput = document.getElementById('search');

async function loadMembers() {
  const search = encodeURIComponent(searchInput.value.trim());
  const res = await fetch(base + '/members?search=' + search, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
  const payload = await res.json();
  const members = payload?.data?.members || [];
  rows.innerHTML = members.map(m => `
    <tr>
      <td>${m.member_id ?? ''}</td>
      <td>${(m.first_name ?? '') + ' ' + (m.last_name ?? '')}</td>
      <td>${m.phone ?? ''}</td>
      <td>${m.status ?? ''}</td>
    </tr>
  `).join('');
}

document.getElementById('refresh').addEventListener('click', loadMembers);
searchInput.addEventListener('input', () => {
  clearTimeout(window._membersTimer);
  window._membersTimer = setTimeout(loadMembers, 260);
});

document.getElementById('memberForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const payload = new URLSearchParams(new FormData(e.target));
  const res = await fetch(base + '/members', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: payload });
  const text = await res.text();
  statusBox.textContent = text;
  await loadMembers();
});

loadMembers();
</script>
<?php
$layoutContent = ob_get_clean();
$layoutTitle = 'Members';
$layoutSubtitle = 'Member directory and onboarding';
$layoutActions = [
    ['href' => $basePath . '/dashboard', 'label' => 'Back to Dashboard'],
];
$layoutNavLinks = [
    ['href' => $basePath . '/dashboard', 'label' => 'Dashboard'],
    ['href' => $basePath . '/members/page', 'label' => 'Members'],
    ['href' => $basePath . '/collections/page', 'label' => 'Collections'],
    ['href' => $basePath . '/payments/page', 'label' => 'Payments'],
    ['href' => $basePath . '/reconciliation/page', 'label' => 'Reconciliation'],
    ['href' => $basePath . '/reports/page', 'label' => 'Reports'],
    ['href' => $basePath . '/audit/page', 'label' => 'Audit'],
];
require __DIR__ . '/../layouts/base.php';
