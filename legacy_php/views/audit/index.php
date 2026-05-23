<?php
$basePath = $base_path ?? '';
ob_start();
?>
<div class="page-shell">
    <header class="topbar">
        <div class="title-block">
            <h1>Audit Log Viewer</h1>
            <p>Compliance trail across members, payments, reconciliation, and reports.</p>
        </div>
    </header>

    <section class="panel">
        <div class="grid-three">
            <div>
                <label>Action</label>
                <input id="action" placeholder="PAYMENT_RECORDED">
            </div>
            <div>
                <label>User ID</label>
                <input id="userId" type="number" placeholder="1">
            </div>
            <div>
                <label>Date Range</label>
                <div class="toolbar">
                    <input id="start" type="date">
                    <input id="end" type="date">
                </div>
            </div>
        </div>
        <button class="btn row" onclick="loadLogs()">Load Logs</button>
        <div id="out" class="mono"></div>
    </section>
</div>
<script>
const base = <?php echo json_encode($basePath); ?>;
const out = document.getElementById('out');

async function loadLogs() {
  const params = new URLSearchParams();
  const action = document.getElementById('action').value.trim();
  const userId = document.getElementById('userId').value.trim();
  const start = document.getElementById('start').value.trim();
  const end = document.getElementById('end').value.trim();
  if (action) params.set('action', action);
  if (userId) params.set('user_id', userId);
  if (start) params.set('start', start);
  if (end) params.set('end', end);
  const res = await fetch(base + '/audit/logs?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
  out.textContent = await res.text();
}

loadLogs();
</script>
<?php
$layoutContent = ob_get_clean();
$layoutTitle = 'Audit';
$layoutSubtitle = 'Compliance and action tracing';
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
