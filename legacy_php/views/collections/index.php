<?php
$basePath = $base_path ?? '';
$csrfToken = $csrf_token ?? '';
$collectionTypes = $collection_types ?? [];
$collectionFrequencies = $collection_frequencies ?? [];
$members = $members ?? [];
$collections = $collections ?? [];
ob_start();
?>
<div class="page-shell">
    <header class="topbar">
        <div class="title-block">
            <h1>Collections</h1>
            <p>Create dues and levies, assign them to members, and review collection performance.</p>
        </div>
    </header>

    <div class="grid-two">
        <section class="panel">
            <h2>Create Collection Item</h2>
            <form id="collectionForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <label>Name</label>
                <input name="name" required>
                <label>Description</label>
                <textarea name="description"></textarea>
                <label>Amount</label>
                <input name="amount" type="number" min="0.01" step="0.01">
                <label>Type</label>
                <select name="type" required>
                    <?php foreach ($collectionTypes as $value => $label): ?>
                        <option value="<?php echo htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
                <label>Frequency</label>
                <select name="frequency" required>
                    <?php foreach ($collectionFrequencies as $value => $label): ?>
                        <option value="<?php echo htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
                <label>Start Date</label>
                <input name="start_date" type="date" value="<?php echo date('Y-m-d'); ?>" required>
                <label>Due Date</label>
                <input name="due_date" type="date">
                <label class="row-inline">
                    <input name="is_required" type="checkbox" value="1" checked> Required for assigned members
                </label>
                <button class="btn" type="submit">Create Collection</button>
            </form>
        </section>

        <section class="panel">
            <h2>Assign Collection</h2>
            <form id="assignForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <label>Collection Item</label>
                <select name="collection_item_id" id="assignCollectionId" required>
                    <option value="">Select collection item</option>
                    <?php foreach ($collections as $collection): ?>
                        <option value="<?php echo (int)$collection['id']; ?>">
                            <?php echo htmlspecialchars((string)($collection['name'] ?? 'Collection'), ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label>Assignment Mode</label>
                <select name="assign_mode" id="assignMode" required>
                    <option value="all">All active members</option>
                    <option value="selected">Selected members</option>
                </select>
                <div id="memberSelectWrap" style="display:none;">
                    <label>Members</label>
                    <select name="member_ids[]" id="memberIds" multiple size="8">
                        <?php foreach ($members as $member): ?>
                            <option value="<?php echo (int)$member['id']; ?>">
                                <?php echo htmlspecialchars((string)(($member['member_id'] ?? '') . ' - ' . ($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn alt" type="submit">Assign Collection</button>
            </form>
        </section>
    </div>

    <section class="panel" style="margin-top:14px;">
        <h2>Collection Items</h2>
        <div class="toolbar">
            <button class="btn alt row" type="button" id="refreshCollections">Refresh</button>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Frequency</th>
                        <th>Amount</th>
                        <th>Assigned</th>
                        <th>Collected</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="collectionRows">
                    <?php foreach ($collections as $collection): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string)($collection['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string)($collection['type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string)($collection['frequency'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string)($collection['amount'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo (int)($collection['assigned_members'] ?? 0); ?></td>
                            <td><?php echo htmlspecialchars((string)($collection['total_collected'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string)($collection['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel" style="margin-top:14px;">
        <h2>Member Statement</h2>
        <div class="toolbar">
            <select id="statementMemberId">
                <option value="">Select member</option>
                <?php foreach ($members as $member): ?>
                    <option value="<?php echo (int)$member['id']; ?>">
                        <?php echo htmlspecialchars((string)(($member['member_id'] ?? '') . ' - ' . ($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="btn row" type="button" id="loadStatement">Load Statement</button>
        </div>
        <div id="responseLog" class="mono"></div>
    </section>
</div>

<script>
const base = <?php echo json_encode($basePath); ?>;
const responseLog = document.getElementById('responseLog');
const collectionRows = document.getElementById('collectionRows');
const assignMode = document.getElementById('assignMode');
const memberSelectWrap = document.getElementById('memberSelectWrap');
const memberIds = document.getElementById('memberIds');

assignMode.addEventListener('change', () => {
  const selectedMode = assignMode.value;
  memberSelectWrap.style.display = selectedMode === 'selected' ? 'block' : 'none';
  memberIds.required = selectedMode === 'selected';
});

async function writeResponse(prefix, response) {
  const body = await response.text();
  responseLog.textContent = `[${new Date().toISOString()}] ${prefix}\n${body}\n\n` + responseLog.textContent;
}

async function refreshCollections() {
  const response = await fetch(base + '/collections', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
  const payload = await response.json();
  const rows = payload?.data?.collections || [];
  collectionRows.innerHTML = rows.map((item) => `
    <tr>
      <td>${item.name ?? ''}</td>
      <td>${item.type ?? ''}</td>
      <td>${item.frequency ?? ''}</td>
      <td>${item.amount ?? ''}</td>
      <td>${item.assigned_members ?? 0}</td>
      <td>${item.total_collected ?? 0}</td>
      <td>${item.status ?? ''}</td>
    </tr>
  `).join('');
}

document.getElementById('collectionForm').addEventListener('submit', async (event) => {
  event.preventDefault();
  const payload = new URLSearchParams(new FormData(event.target));
  const response = await fetch(base + '/collections', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: payload,
  });
  await writeResponse('/collections', response);
  await refreshCollections();
});

document.getElementById('assignForm').addEventListener('submit', async (event) => {
  event.preventDefault();
  const payload = new URLSearchParams(new FormData(event.target));
  const response = await fetch(base + '/collections/assign', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    body: payload,
  });
  await writeResponse('/collections/assign', response);
  await refreshCollections();
});

document.getElementById('loadStatement').addEventListener('click', async () => {
  const memberId = document.getElementById('statementMemberId').value;
  if (!memberId) {
    responseLog.textContent = 'Select a member first.\n\n' + responseLog.textContent;
    return;
  }
  const response = await fetch(base + '/collections/member-statement?member_id=' + encodeURIComponent(memberId), {
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
  });
  await writeResponse('/collections/member-statement', response);
});

document.getElementById('refreshCollections').addEventListener('click', refreshCollections);
</script>
<?php
$layoutContent = ob_get_clean();
$layoutTitle = 'Collections';
$layoutSubtitle = 'Dues and levy setup';
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

