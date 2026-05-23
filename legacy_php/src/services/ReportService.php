<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Report service for financial and arrears reports.
 */
class ReportService {
    private \mysqli $db;

    public function __construct(?\mysqli $db = null) {
        $this->db = $db ?? db();
    }

    public function daily(string $date): array {
        $query = "
            SELECT
                p.payment_method,
                COUNT(*) AS payment_count,
                COALESCE(SUM(p.amount), 0) AS total_amount
            FROM payments p
            WHERE p.payment_date = ? AND p.status = 'Confirmed'
            GROUP BY p.payment_method
            ORDER BY total_amount DESC
        ";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $total = 0.0;
        foreach ($rows as $row) {
            $total += (float)$row['total_amount'];
        }

        return [
            'date' => $date,
            'total_confirmed' => $total,
            'breakdown' => $rows
        ];
    }

    public function monthly(int $year, int $month): array {
        $monthStart = sprintf('%04d-%02d-01', $year, $month);
        $monthEnd = date('Y-m-t', strtotime($monthStart));

        $query = "
            SELECT
                p.payment_date,
                COUNT(*) AS payment_count,
                COALESCE(SUM(p.amount), 0) AS total_amount
            FROM payments p
            WHERE p.payment_date BETWEEN ? AND ? AND p.status = 'Confirmed'
            GROUP BY p.payment_date
            ORDER BY p.payment_date ASC
        ";
        $stmt = $this->db->prepare($query);
        $stmt->bind_param('ss', $monthStart, $monthEnd);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $total = 0.0;
        foreach ($rows as $row) {
            $total += (float)$row['total_amount'];
        }

        return [
            'year' => $year,
            'month' => $month,
            'from' => $monthStart,
            'to' => $monthEnd,
            'total_confirmed' => $total,
            'daily' => $rows
        ];
    }

    public function arrears(int $limit = 200): array {
        $limit = max(1, min($limit, 2000));
        $query = "
            SELECT
                member_id,
                member_id_number,
                full_name,
                collection_item_id,
                collection_name,
                amount,
                expected_payments_count,
                paid_count,
                total_paid,
                balance_owed,
                status
            FROM member_arrears
            WHERE balance_owed > 0
            ORDER BY balance_owed DESC
            LIMIT {$limit}
        ";
        $result = $this->db->query($query);
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

        return [
            'count' => count($rows),
            'rows' => $rows
        ];
    }
}

