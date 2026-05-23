<?php
$basePath = $base_path ?? '';
$csrfToken = $csrf_token ?? '';
ob_start();
?>
<div class="page-shell">
    <header class="topbar">
        <div class="title-block">
            <h1>Batch Reconciliation</h1>
            <p>Open batches, attach payments, and close with discrepancy checks.</p>
        </div>
    </header>

    <div class="grid-three">
        <section class="panel">
            <h2>Open Batch</h2>
            <form id="openForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <label>Type</label>
                <select name="reconciliation_type">
                    <option value="Cash_End_of_Day">Cash End of Day</option>
                    <option value="Cash_Mid_Day">Cash Mid Day</option>
                    <option value="Manual">Manual</option>
                </select>
                <label>Date</label>
                <input type="date" name="reconciliation_date" value="<?php echo date('Y-m-d'); ?>">
                <label>Notes</label>
                <textarea name="notes"></textarea>
                <button class="btn" type="submit">Open Batch</button>
            </form>
        </section>

        <section class="panel">
            <h2>Add Item</h2>
            <form id="itemForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <label>Batch ID</label>
                <input type="number" name="batch_id" required>
                <label>Payment ID</label>
                <input type="number" name="payment_id" required>
                <label>Action</label>
                <select name="action">
                    <option value="Include">Include</option>
                    <option value="Exclude">Exclude</option>
                    <option value="Flag_For_Review">Flag For Review</option>
                    <option value="Correct_Amount">Correct Amount</option>
                </select>
                <label>Corrected Amount</label>
                <input type="number" step="0.01" name="corrected_amount">
                <button class="btn alt" type="submit">Attach Item</button>
            </form>
        </section>

        <section class="panel">
            <h2>Close Batch</h2>
            <form id="closeForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <label>Batch ID</label>
                <input type="number" name="batch_id" required>
                <label>Recorded Amount</label>
                <input type="number" name="recorded_amount" step="0.01" required>
                <label>Notes</label>
                <textarea name="notes"></textarea>
                <button class="btn" type="submit">Close Batch</button>
            </form>
        </section>
    </div>

    <section class="panel" style="margin-top:14px;">
        <h2>Response Stream</h2>
        <div class="mono" id="log"></div>
    </section>
</div>

<script>
const base = <?php echo json_encode($basePath); ?>;
const log = document.getElementById('log');

async function post(url, form) {
  const body = new URLSearchParams(new FormData(form));
  const res = await fetch(base + url, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body });
  const text = await res.text();
  log.textContent = `[${new Date().toISOString()}] ${url}\n${text}\n\n` + log.textContent;
}

document.getElementById('openForm').addEventListener('submit', (e) => { e.preventDefault(); post('/reconciliation/batches/open', e.target); });
document.getElementById('itemForm').addEventListener('submit', (e) => { e.preventDefault(); post('/reconciliation/batches/add-item', e.target); });
document.getElementById('closeForm').addEventListener('submit', (e) => { e.preventDefault(); post('/reconciliation/batches/close', e.target); });
</script>
<?php
$layoutContent = ob_get_clean();
$layoutTitle = 'Reconciliation';
$layoutSubtitle = 'Batch reconciliation workflow';
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
