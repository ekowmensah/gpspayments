<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Member Statement - {{ $member->member_code }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #13263b; margin: 24px; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
        .title { margin: 0; font-size: 24px; }
        .meta { color: #4f6276; font-size: 13px; }
        .kpis { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin: 14px 0 18px; }
        .kpi { border: 1px solid #d7e1ed; border-radius: 8px; padding: 8px; }
        .kpi .label { font-size: 11px; text-transform: uppercase; color: #5e7083; }
        .kpi .value { font-size: 17px; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d7e1ed; padding: 7px; font-size: 12px; }
        th { background: #f3f7fc; text-align: left; }
        .num { text-align: right; }
        .debit { color: #a32f2f; }
        .credit { color: #1e7a4d; }
        @media print {
            .no-print { display: none; }
            body { margin: 10mm; }
        }
    </style>
</head>
<body>
    <div class="head">
        <div>
            <h1 class="title">Member Statement</h1>
            <div class="meta">{{ $member->first_name }} {{ $member->last_name }} ({{ $member->member_code }})</div>
            <div class="meta">Generated: {{ $generatedAt->format('Y-m-d H:i') }}</div>
        </div>
        <button class="no-print" onclick="window.print()">Print / Save PDF</button>
    </div>

    <div class="kpis">
        <div class="kpi"><div class="label">Total Expected</div><div class="value">{{ number_format((float)$summary['total_expected'], 2) }}</div></div>
        <div class="kpi"><div class="label">Total Paid</div><div class="value">{{ number_format((float)$summary['total_paid'], 2) }}</div></div>
        <div class="kpi"><div class="label">Outstanding</div><div class="value">{{ number_format((float)$summary['outstanding_balance'], 2) }}</div></div>
        <div class="kpi"><div class="label">Entries</div><div class="value">{{ number_format((int)$summary['statement_rows']) }}</div></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Reference</th>
                <th>Description</th>
                <th class="num">Debit</th>
                <th class="num">Credit</th>
                <th class="num">Outstanding</th>
            </tr>
        </thead>
        <tbody>
            @forelse($statement as $row)
                @php $affectsOutstanding = (bool)($row->affects_outstanding ?? false); @endphp
                <tr>
                    <td>{{ \Illuminate\Support\Carbon::parse((string)$row->entry_date)->format('Y-m-d') }}</td>
                    <td>{{ $row->reference }}</td>
                    <td>{{ $row->description }}{{ $affectsOutstanding ? '' : ' [Info]' }}</td>
                    <td class="num debit">{{ (float)$row->debit > 0 ? number_format((float)$row->debit, 2) : '-' }}</td>
                    <td class="num credit">{{ (float)$row->credit > 0 ? number_format((float)$row->credit, 2) : '-' }}</td>
                    <td class="num"><strong>{{ $affectsOutstanding ? number_format((float)$row->running_balance, 2) : '-' }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;">No statement entries available.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
