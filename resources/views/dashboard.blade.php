@extends('adminlte::page')

@section('title', 'Dashboard')
@section('plugins.Chartjs', true)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="mb-1">Executive Dashboard</h1>
            <p class="text-muted mb-0">Real-time collections intelligence, risk exposure, and payment velocity.</p>
        </div>
        <div class="mt-2 mt-md-0">
            <a href="{{ route('reports.index') }}" class="btn btn-outline-primary">
                <i class="fas fa-chart-line mr-1"></i> Full Reports
            </a>
            <a href="{{ route('payments.index') }}" class="btn btn-primary">
                <i class="fas fa-receipt mr-1"></i> Payments
            </a>
        </div>
    </div>
@stop

@section('css')
<style>
    :root {
        --dash-ink: #0b1f33;
        --dash-slate: #5d6f82;
        --dash-line: #e3ebf4;
        --dash-soft: #f5f8fc;
        --dash-primary: #0069d9;
        --dash-danger: #c0392b;
        --dash-success: #1e8449;
        --dash-warning: #c77d00;
    }
    .dash-card {
        border: 1px solid var(--dash-line);
        border-radius: .95rem;
        box-shadow: 0 10px 24px rgba(11, 31, 51, .05);
        height: 100%;
    }
    .dash-card .card-header {
        background: linear-gradient(180deg, #ffffff 0%, #f9fbfe 100%);
        border-bottom: 1px solid var(--dash-line);
    }
    .kpi-card {
        border: 1px solid var(--dash-line);
        border-radius: .95rem;
        background: #fff;
        padding: 1rem;
        height: 100%;
        box-shadow: 0 8px 18px rgba(11, 31, 51, .05);
    }
    .kpi-label {
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--dash-slate);
        font-weight: 700;
        margin-bottom: .35rem;
    }
    .kpi-value {
        font-size: 1.55rem;
        line-height: 1.15;
        color: var(--dash-ink);
        font-weight: 700;
        margin-bottom: .2rem;
    }
    .kpi-sub {
        color: var(--dash-slate);
        font-size: .86rem;
    }
    .mini-kpi {
        border: 1px solid var(--dash-line);
        border-radius: .8rem;
        background: #fff;
        padding: .7rem .8rem;
        height: 100%;
    }
    .mini-kpi .label {
        font-size: .72rem;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: var(--dash-slate);
        font-weight: 700;
    }
    .mini-kpi .value {
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--dash-ink);
        line-height: 1.2;
    }
    .ops-pill {
        border-radius: 999px;
        border: 1px solid var(--dash-line);
        background: var(--dash-soft);
        color: var(--dash-ink);
        font-weight: 600;
        display: inline-block;
        padding: .2rem .55rem;
        font-size: .82rem;
    }
    .alert-tile {
        border-radius: .8rem;
        padding: .75rem .85rem;
        border: 1px solid transparent;
        height: 100%;
    }
    .alert-tile h6 {
        margin: 0;
        font-weight: 700;
        font-size: .9rem;
    }
    .alert-tile p {
        margin: .2rem 0 0 0;
        font-size: .84rem;
    }
    .alert-danger-soft {
        background: #fff4f3;
        border-color: #f5c6c3;
        color: #8e2a21;
    }
    .alert-warning-soft {
        background: #fff8eb;
        border-color: #f4d9a6;
        color: #8a6116;
    }
    .alert-success-soft {
        background: #eefaf1;
        border-color: #c8e8d1;
        color: #1f6a37;
    }
    .table-tight td, .table-tight th {
        padding-top: .55rem;
        padding-bottom: .55rem;
        vertical-align: middle;
    }
    .chart-wrap {
        height: 300px;
        position: relative;
    }
    .dash-scroll {
        max-height: 320px;
        overflow: auto;
    }
    .tab-link {
        font-size: .86rem;
        font-weight: 600;
    }
</style>
@stop

@section('content')
    <div class="row mb-3">
        <div class="col-md-6 col-xl-3 mb-3 mb-xl-0">
            <div class="kpi-card">
                <div class="kpi-label">Collected Today</div>
                <div class="kpi-value">{{ number_format((float)$stats['today_amount'], 2) }}</div>
                <div class="kpi-sub">
                    {{ number_format((int)$stats['today_payments']) }} transactions
                    @if($todayDeltaPct !== null)
                        | <span class="{{ $todayDeltaPct >= 0 ? 'text-success' : 'text-danger' }}">{{ $todayDeltaPct >= 0 ? '+' : '' }}{{ number_format((float)$todayDeltaPct, 1) }}%</span> vs yesterday
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3 mb-3 mb-xl-0">
            <div class="kpi-card">
                <div class="kpi-label">Collected This Month</div>
                <div class="kpi-value">{{ number_format((float)$stats['month_amount'], 2) }}</div>
                <div class="kpi-sub">{{ number_format((int)$stats['month_transactions']) }} posted transactions</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3 mb-3 mb-xl-0">
            <div class="kpi-card">
                <div class="kpi-label">Collection Rate</div>
                <div class="kpi-value">{{ number_format((float)$stats['collection_rate'], 2) }}%</div>
                <div class="kpi-sub">Across all generated charges</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="kpi-card">
                <div class="kpi-label">Outstanding Exposure</div>
                <div class="kpi-value text-danger">{{ number_format((float)$stats['outstanding_balance'], 2) }}</div>
                <div class="kpi-sub">{{ number_format((int)$stats['arrears_members']) }} members in arrears</div>
            </div>
        </div>
    </div>

    <div class="card dash-card mb-3">
        <div class="card-header border-0 d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Membership Health</h3>
            <small class="text-muted">
                Active Coverage: {{ number_format((float)$stats['active_coverage_pct'], 1) }}%
                | Arrears Rate: {{ number_format((float)$stats['arrears_rate_pct'], 1) }}%
            </small>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 col-xl-2 mb-2 mb-xl-0">
                    <div class="mini-kpi">
                        <div class="label">Total Members</div>
                        <div class="value">{{ number_format((int)$stats['total_members']) }}</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-2 mb-2 mb-xl-0">
                    <div class="mini-kpi">
                        <div class="label">Active</div>
                        <div class="value text-success">{{ number_format((int)$stats['active_members']) }}</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-2 mb-2 mb-xl-0">
                    <div class="mini-kpi">
                        <div class="label">Inactive</div>
                        <div class="value text-secondary">{{ number_format((int)$stats['inactive_members']) }}</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-2 mb-2 mb-xl-0">
                    <div class="mini-kpi">
                        <div class="label">Suspended</div>
                        <div class="value text-warning">{{ number_format((int)$stats['suspended_members']) }}</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-2 mb-2 mb-xl-0">
                    <div class="mini-kpi">
                        <div class="label">Exited/Deceased</div>
                        <div class="value">{{ number_format((int)$stats['exited_members'] + (int)$stats['deceased_members']) }}</div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-2">
                    <div class="mini-kpi">
                        <div class="label">Members in Arrears</div>
                        <div class="value text-danger">{{ number_format((int)$stats['arrears_members']) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-lg-8 mb-3 mb-lg-0">
            <div class="card dash-card">
                <div class="card-header border-0">
                    <h3 class="card-title mb-0">30-Day Collections (Daily + Cumulative)</h3>
                </div>
                <div class="card-body">
                    <div class="chart-wrap">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card dash-card">
                <div class="card-header border-0">
                    <h3 class="card-title mb-0">Payment Method Mix (Month)</h3>
                </div>
                <div class="card-body">
                    <div class="chart-wrap">
                        <canvas id="methodMixChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-4 mb-3 mb-md-0">
            <div class="alert-tile alert-danger-soft">
                <h6>High Risk Arrears</h6>
                <p>{{ number_format((int)$stats['high_risk_members']) }} members at critical balance levels.</p>
            </div>
        </div>
        <div class="col-md-4 mb-3 mb-md-0">
            <div class="alert-tile alert-warning-soft">
                <h6>Overdue Charges</h6>
                <p>{{ number_format((int)$stats['overdue_count']) }} overdue charges totaling {{ number_format((float)$stats['overdue_amount'], 2) }}.</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="alert-tile alert-success-soft">
                <h6>Reconciliation & Unallocated</h6>
                <p>{{ number_format((int)$stats['open_batches']) }} open batches | Unallocated {{ number_format((float)$stats['unallocated_amount'], 2) }}.</p>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-5 mb-3 mb-lg-0">
            <div class="card dash-card">
                <div class="card-header border-0 d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0">Top Arrears Exposure</h3>
                    <a href="{{ route('reports.index', ['preset' => 'this_month']) }}" class="btn btn-xs btn-outline-primary">Open Report</a>
                </div>
                <div class="card-body p-0 table-responsive dash-scroll">
                    <table class="table table-hover table-tight mb-0">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th class="text-right">Outstanding</th>
                                <th class="text-right">Expected</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($topArrears as $row)
                                <tr>
                                    <td>
                                        <div class="font-weight-bold">{{ $row->member_code }}</div>
                                        <small class="text-muted">{{ $row->full_name }}</small>
                                    </td>
                                    <td class="text-right text-danger font-weight-bold">{{ number_format((float)$row->outstanding_balance, 2) }}</td>
                                    <td class="text-right">{{ number_format((float)$row->total_expected, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-3">No arrears data yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card dash-card">
                <div class="card-header border-0 d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0">Operations Feed</h3>
                    <ul class="nav nav-pills">
                        <li class="nav-item">
                            <a class="nav-link active tab-link" href="#tabRecentPayments" data-toggle="tab">Recent Payments</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link tab-link" href="#tabCollectionPerformance" data-toggle="tab">Collection Performance</a>
                        </li>
                    </ul>
                </div>
                <div class="card-body p-0">
                    <div class="tab-content">
                        <div class="tab-pane active" id="tabRecentPayments">
                            <div class="table-responsive dash-scroll">
                                <table class="table table-hover table-tight mb-0">
                                    <thead>
                                    <tr>
                                        <th>Reference</th>
                                        <th>Member</th>
                                        <th>Collection</th>
                                        <th>Method</th>
                                        <th class="text-right">Amount</th>
                                        <th>Status</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($recentPayments as $payment)
                                        <tr>
                                            <td class="font-weight-bold">{{ $payment->payment_reference }}</td>
                                            <td>
                                                {{ $payment->member?->member_code }} -
                                                {{ trim(($payment->member?->first_name ?? '') . ' ' . ($payment->member?->last_name ?? '')) }}
                                            </td>
                                            <td>{{ $payment->collectionItem?->name ?? 'General' }}</td>
                                            <td>{{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}</td>
                                            <td class="text-right">{{ number_format((float)$payment->amount, 2) }}</td>
                                            <td>
                                                <span class="badge badge-{{ $payment->status === 'posted' ? 'success' : 'secondary' }}">
                                                    {{ ucfirst($payment->status) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="text-center text-muted py-3">No recent payments found.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane" id="tabCollectionPerformance">
                            <div class="table-responsive dash-scroll">
                                <table class="table table-hover table-tight mb-0">
                                    <thead>
                                    <tr>
                                        <th>Collection</th>
                                        <th class="text-right">Expected</th>
                                        <th class="text-right">Paid</th>
                                        <th class="text-right">Outstanding</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($collectionPerformance as $row)
                                        <tr>
                                            <td>{{ $row->collection_name }}</td>
                                            <td class="text-right">{{ number_format((float)$row->total_expected, 2) }}</td>
                                            <td class="text-right">{{ number_format((float)$row->total_paid, 2) }}</td>
                                            <td class="text-right font-weight-bold {{ (float)$row->outstanding_balance > 0 ? 'text-danger' : 'text-success' }}">
                                                {{ number_format((float)$row->outstanding_balance, 2) }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center text-muted py-3">No charge data available.</td></tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@section('js')
<script>
(() => {
    const trendLabels = @json($dailyTrend->pluck('label')->values());
    const trendValues = @json($dailyTrend->pluck('total_amount')->values());
    const cumulativeValues = @json($dailyTrend->pluck('cumulative_amount')->values());
    const methodLabels = @json($methodMix->pluck('payment_method')->map(fn ($m) => ucfirst(str_replace('_', ' ', $m)))->values());
    const methodValues = @json($methodMix->pluck('total_amount')->values());

    const trendCtx = document.getElementById('trendChart');
    if (trendCtx) {
        new Chart(trendCtx, {
            type: 'bar',
            data: {
                labels: trendLabels,
                datasets: [
                    {
                        type: 'bar',
                        label: 'Daily Amount',
                        data: trendValues,
                        borderColor: '#79aef5',
                        backgroundColor: 'rgba(0,105,217,0.22)',
                        borderWidth: 1,
                        yAxisID: 'y-axis-daily',
                    },
                    {
                        type: 'line',
                        label: 'Cumulative Amount',
                        data: cumulativeValues,
                        borderColor: '#0069d9',
                        backgroundColor: 'rgba(0,105,217,0.10)',
                        fill: false,
                        tension: 0.25,
                        pointRadius: 2.2,
                        pointHoverRadius: 4,
                        yAxisID: 'y-axis-cumulative',
                    }
                ]
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { display: true } },
                scales: {
                    yAxes: [
                        {
                            id: 'y-axis-daily',
                            type: 'linear',
                            position: 'left',
                            ticks: { beginAtZero: true },
                            scaleLabel: {
                                display: true,
                                labelString: 'Daily'
                            }
                        },
                        {
                            id: 'y-axis-cumulative',
                            type: 'linear',
                            position: 'right',
                            ticks: { beginAtZero: true },
                            gridLines: { drawOnChartArea: false },
                            scaleLabel: {
                                display: true,
                                labelString: 'Cumulative'
                            }
                        }
                    ]
                },
                legend: { display: true }
            }
        });
    }

    const methodCtx = document.getElementById('methodMixChart');
    if (methodCtx) {
        new Chart(methodCtx, {
            type: 'doughnut',
            data: {
                labels: methodLabels,
                datasets: [{
                    data: methodValues,
                    backgroundColor: ['#0069d9', '#17a673', '#f39c12', '#c0392b', '#6f42c1'],
                    borderWidth: 0
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }
})();
</script>
@stop
