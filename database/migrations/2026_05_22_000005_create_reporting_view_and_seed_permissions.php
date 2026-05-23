<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private array $permissionRows = [
        ['code' => 'members.view', 'description' => 'View members'],
        ['code' => 'members.create', 'description' => 'Create members'],
        ['code' => 'members.edit', 'description' => 'Edit member records'],
        ['code' => 'collections.view', 'description' => 'View collection items'],
        ['code' => 'collections.create', 'description' => 'Create collection items'],
        ['code' => 'collections.assign', 'description' => 'Assign collection items'],
        ['code' => 'payments.record', 'description' => 'Record payments'],
        ['code' => 'payments.verify', 'description' => 'Verify and approve payments'],
        ['code' => 'payments.void', 'description' => 'Void posted payments'],
        ['code' => 'payments.refund', 'description' => 'Process refunds'],
        ['code' => 'receipts.view', 'description' => 'View receipts'],
        ['code' => 'reports.view', 'description' => 'View reports'],
        ['code' => 'reports.export', 'description' => 'Export reports'],
        ['code' => 'arrears.view', 'description' => 'View arrears'],
        ['code' => 'reconciliation.manage', 'description' => 'Manage reconciliation'],
        ['code' => 'users.manage', 'description' => 'Manage users and roles'],
        ['code' => 'settings.manage', 'description' => 'Manage system settings'],
        ['code' => 'audit.view', 'description' => 'View audit logs'],
        ['code' => 'notifications.send', 'description' => 'Send reminders and notices'],
    ];

    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE OR REPLACE VIEW v_member_balances AS
SELECT
    mc.association_id,
    mc.member_id,
    m.member_code,
    m.full_name,
    SUM(mc.expected_amount + mc.penalty_amount - mc.discount_amount - mc.waived_amount) AS total_expected,
    COALESCE(SUM(pa.total_paid), 0.00) AS total_paid,
    SUM(mc.expected_amount + mc.penalty_amount - mc.discount_amount - mc.waived_amount) - COALESCE(SUM(pa.total_paid), 0.00) AS outstanding_balance
FROM member_charges mc
JOIN members m ON m.id = mc.member_id
LEFT JOIN (
    SELECT member_charge_id, SUM(allocated_amount) AS total_paid
    FROM payment_allocations
    GROUP BY member_charge_id
) pa ON pa.member_charge_id = mc.id
WHERE mc.status IN ('open', 'partial', 'paid')
GROUP BY mc.association_id, mc.member_id, m.member_code, m.full_name
SQL);

        $now = now();
        $rows = array_map(function (array $row) use ($now): array {
            $row['created_at'] = $now;
            return $row;
        }, $this->permissionRows);

        DB::table('permissions')->insertOrIgnore($rows);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_member_balances');

        $codes = array_map(static fn (array $row): string => $row['code'], $this->permissionRows);
        DB::table('permissions')->whereIn('code', $codes)->delete();
    }
};

