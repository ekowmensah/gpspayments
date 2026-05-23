<?php
$basePath = $base_path ?? '';
ob_start();
?>
<div class="page-shell">
    <header class="topbar">
        <div class="title-block">
            <h1>Financial Reports</h1>
            <p>Generate daily, monthly, and arrears visibility snapshots.</p>
        </div>
    </header>

    <div class="grid-three">
        <section class="panel">
            <h2>Daily Report</h2>
            <label>Date</label>
            <input id="dailyDate" type="date" value="<?php echo date('Y-m-d'); ?>">
            <button class="btn" onclick="runDaily()">Run Daily</button>
        </section>
        <section class="panel">
            <h2>Monthly Report</h2>
            <label>Year</label>
            <input id="monthlyYear" type="number" value="<?php echo date('Y'); ?>">
            <label>Month</label>
            <input id="monthlyMonth" type="number" min="1" max="12" value="<?php echo date('m'); ?>">
            <button class="btn alt" onclick="runMonthly()">Run Monthly</button>
        </section>
        <section class="panel">
            <h2>Arrears Report</h2>
            <label>Limit</label>
            <input id="arrearsLimit" type="number" min="1" max="2000" value="100">
            <button class="btn" onclick="runArrears()">Run Arrears</button>
        </section>
    </div>

    <section class="panel" style="margin-top:14px;">
        <h2>Report Output</h2>
        <div id="out" class="mono"></div>
    </section>
</div>
<script>
const base = <?php echo json_encode($basePath); ?>;
const out = document.getElementById('out');

async function fetchReport(path) {
  const res = await fetch(base + path, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
  out.textContent = await res.text();
}

function runDaily() {
  const d = document.getElementById('dailyDate').value;
  fetchReport('/reports/daily?date=' + encodeURIComponent(d));
}

function runMonthly() {
  const y = document.getElementById('monthlyYear').value;
  const m = document.getElementById('monthlyMonth').value;
  fetchReport('/reports/monthly?year=' + encodeURIComponent(y) + '&month=' + encodeURIComponent(m));
}

function runArrears() {
  const l = document.getElementById('arrearsLimit').value;
  fetchReport('/reports/arrears?limit=' + encodeURIComponent(l));
}
</script>
<?php
$layoutContent = ob_get_clean();
$layoutTitle = 'Reports';
$layoutSubtitle = 'Financial and arrears analytics';
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
