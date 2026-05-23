<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Statement - {{ $member->member_code }}</title>
    <style>
        body {
            font-family: "Segoe UI", Tahoma, Arial, sans-serif;
            color: #0b1f33;
            margin: 20px;
        }
        .sheet {
            max-width: 980px;
            margin: 0 auto;
        }
        .top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #102f4a;
            padding-bottom: 10px;
            margin-bottom: 16px;
        }
        .title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .muted {
            color: #5a6c7d;
            font-size: 12px;
        }
        .summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
            margin-bottom: 14px;
        }
        .box {
            border: 1px solid #d9e4ef;
            border-radius: 8px;
            padding: 8px 10px;
        }
        .box .label {
            color: #5a6c7d;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .04em;
            font-weight: 700;
        }
        .box .value {
            font-size: 18px;
            font-weight: 700;
            margin-top: 2px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        thead th {
            background: #eff4fa;
            border: 1px solid #d9e4ef;
            padding: 6px;
            text-align: left;
        }
        tbody td {
            border: 1px solid #e4ecf4;
            padding: 6px;
        }
        .right {
            text-align: right;
        }
        .debit {
            color: #b23a2f;
            font-weight: 600;
        }
        .credit {
            color: #237748;
            font-weight: 600;
        }
        .balance {
            font-weight: 700;
        }
        .footer {
            margin-top: 10px;
            font-size: 11px;
            color: #66798d;
        }
        @media print {
            body {
                margin: 0;
            }
            .no-print {
                display: none;
            }
            .sheet {
                max-width: none;
                padding: 8mm;
            }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="top">
            <div>
                <div class="title">Member Statement</div>
                <div>{{ $member->first_name }} {{ $member->last_name }} ({{ $member->member_code }})</div>
                <div class="muted">Status: {{ ucfirst((string)$member->status) }} | Joined: {{ optional($member->date_joined)->format('Y-m-d') }}</div>
            </div>
            <div class="muted">
                Generated: {{ now()->format('Y-m-d H:i') }}
            </div>
        </div>

        <div class="summary">
            <div class="box">
                <div class="label">Total Expected</div>
                <div class="value">{{ number_format((float)$summary['total_expected'], 2) }}</div>
            </div>
            <div class="box">
                <div class="label">Total Paid</div>
                <div class="value">{{ number_format((float)$summary['total_paid'], 2) }}</div>
            </div>
            <div class="box">
                <div class="label">Outstanding Balance</div>
                <div class="value">{{ number_format((float)$summary['outstanding_balance'], 2) }}</div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Reference</th>
                    <th>Description</th>
                    <th class="right">Debit</th>
                    <th class="right">Credit</th>
                    <th class="right">Outstanding</th>
                </tr>
            </thead>
            <tbody>
                @forelse($statement as $row)
                    @php $affectsOutstanding = (bool)($row->affects_outstanding ?? false); @endphp
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse((string)$row->entry_date)->format('Y-m-d') }}</td>
                        <td>{{ $row->reference }}</td>
                        <td>{{ $row->description }}{{ $affectsOutstanding ? '' : ' [Info]' }}</td>
                        <td class="right debit">{{ (float)$row->debit > 0 ? number_format((float)$row->debit, 2) : '-' }}</td>
                        <td class="right credit">{{ (float)$row->credit > 0 ? number_format((float)$row->credit, 2) : '-' }}</td>
                        <td class="right balance">{{ $affectsOutstanding ? number_format((float)$row->running_balance, 2) : '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="right">No statement entries.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="footer">
            This statement is system-generated and intended for member account reconciliation.
        </div>

        <div class="no-print" style="margin-top:12px;">
            <button onclick="window.print()">Print / Save as PDF</button>
        </div>
    </div>
</body>
</html>
