<?php
$basePath = $base_path ?? '';
$csrfToken = $csrf_token ?? '';
ob_start();
?>
<div class="page-shell">
    <header class="topbar">
        <div class="title-block">
            <h1>Payment Operations</h1>
            <p>Record cash and digital collections with verification-ready metadata.</p>
        </div>
    </header>

    <div class="grid-two">
        <section class="panel">
            <h2>Cash Payment</h2>
            <form id="cashForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <label>Member</label>
                <select name="member_id" required>
                    <option value="">Select Member</option>
                    <?php foreach (($members ?? []) as $member): ?>
                        <option value="<?php echo (int)$member['id']; ?>">
                            <?php echo htmlspecialchars(($member['member_id'] ?? '') . ' - ' . ($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label>Amount</label>
                <input name="amount" type="number" min="0.01" step="0.01" required>
                <label>Collection Item</label>
                <select name="collection_item_id">
                    <option value="">General Payment (Unassigned)</option>
                    <?php foreach (($collection_items ?? []) as $item): ?>
                        <option value="<?php echo (int)$item['id']; ?>">
                            <?php echo htmlspecialchars((string)(($item['name'] ?? '') . ' - ' . ($item['amount'] ?? '0.00')), ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label>Payment Date</label>
                <input name="payment_date" type="date" value="<?php echo date('Y-m-d'); ?>" required>
                <label>Notes</label>
                <textarea name="notes"></textarea>
                <button class="btn" type="submit">Record Cash</button>
            </form>
        </section>

        <section class="panel">
            <h2>Digital Payment</h2>
            <form id="digitalForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <label>Member</label>
                <select name="member_id" required>
                    <option value="">Select Member</option>
                    <?php foreach (($members ?? []) as $member): ?>
                        <option value="<?php echo (int)$member['id']; ?>">
                            <?php echo htmlspecialchars(($member['member_id'] ?? '') . ' - ' . ($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label>Amount</label>
                <input name="amount" type="number" min="0.01" step="0.01" required>
                <label>Collection Item</label>
                <select name="collection_item_id">
                    <option value="">General Payment (Unassigned)</option>
                    <?php foreach (($collection_items ?? []) as $item): ?>
                        <option value="<?php echo (int)$item['id']; ?>">
                            <?php echo htmlspecialchars((string)(($item['name'] ?? '') . ' - ' . ($item['amount'] ?? '0.00')), ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label>Method</label>
                <select name="payment_method" required>
                    <option value="Mobile Money">Mobile Money</option>
                    <option value="USSD">USSD</option>
                    <option value="Card">Card</option>
                </select>
                <label>Transaction ID</label>
                <input name="transaction_id" required>
                <label>Notes</label>
                <textarea name="notes"></textarea>
                <button class="btn alt" type="submit">Record Digital</button>
            </form>
        </section>
    </div>

    <section class="panel" style="margin-top:14px;">
        <h2>Response Stream</h2>
        <div id="log" class="mono"></div>
    </section>
</div>

<script>
const base = <?php echo json_encode($basePath); ?>;
const log = document.getElementById('log');

const show = async (url, form) => {
  const payload = new URLSearchParams(new FormData(form));
  const res = await fetch(base + url, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: payload });
  const text = await res.text();
  log.textContent = `[${new Date().toISOString()}] ${url}\n${text}\n\n` + log.textContent;
};

document.getElementById('cashForm').addEventListener('submit', (e) => {
  e.preventDefault();
  show('/payments/cash', e.target);
});

document.getElementById('digitalForm').addEventListener('submit', (e) => {
  e.preventDefault();
  show('/payments/digital', e.target);
});
</script>
<?php
$layoutContent = ob_get_clean();
$layoutTitle = 'Payments';
$layoutSubtitle = 'Collection recording and verification';
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
