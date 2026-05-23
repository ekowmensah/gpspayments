@extends('adminlte::page')

@section('title', 'Reports')
@section('plugins.Chartjs', true)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="mb-1">Reports & Analytics</h1>
            <p class="text-muted mb-0">Operational finance insights with arrears and collection performance.</p>
        </div>
        <div class="text-muted small mt-2 mt-md-0">
            Generated: {{ now()->format('d M Y, H:i') }}
        </div>
    </div>
@stop

@section('content')
    <div class="card card-outline card-primary">
        <div class="card-body">
            <form method="GET" action="{{ route('reports.index') }}">
                <div class="row align-items-end">
                    <div class="col-md-3">
                        <x-adminlte-input name="date" label="Daily Date" type="date" value="{{ $filters['date'] }}" />
                    </div>
                    <div class="col-md-2">
                        <x-adminlte-input name="year" label="Year" type="number" value="{{ $filters['year'] }}" />
                    </div>
                    <div class="col-md-2">
                        <x-adminlte-input name="month" label="Month" type="number" min="1" max="12" value="{{ $filters['month'] }}" />
                    </div>
                    <div class="col-md-2">
                        <x-adminlte-input name="limit" label="Arrears Rows" type="number" min="1" max="500" value="{{ $filters['limit'] }}" />
                    </div>
                    <div class="col-md-3 mb-3">
                        <button class="btn btn-primary btn-block" type="submit">
                            <i class="fas fa-sync-alt mr-1"></i> Refresh Report
                        </button>
                    </div>
                </div>
                <div class="row align-items-end">
                    <div class="col-md-3">
                        <x-adminlte-select name="payment_method" label="Payment Method">
                            <option value="">All methods</option>
                            <option value="cash" @selected(($filters['payment_method'] ?? '') === 'cash')>Cash</option>
                            <option value="mobile_money" @selected(($filters['payment_method'] ?? '') === 'mobile_money')>Mobile Money</option>
                            <option value="bank_transfer" @selected(($filters['payment_method'] ?? '') === 'bank_transfer')>Bank Transfer</option>
                            <option value="ussd" @selected(($filters['payment_method'] ?? '') === 'ussd')>USSD</option>
                            <option value="card" @selected(($filters['payment_method'] ?? '') === 'card')>Card</option>
                        </x-adminlte-select>
                    </div>
                    <div class="col-md-3">
                        <x-adminlte-select name="collection_item_id" label="Collection">
                            <option value="">All collections</option>
                            @foreach($collections as $collection)
                                <option value="{{ $collection->id }}" @selected((int)($filters['collection_item_id'] ?? 0) === (int)$collection->id)>
                                    {{ $collection->name }}
                                </option>
                            @endforeach
                        </x-adminlte-select>
                    </div>
                    <div class="col-md-3">
                        <x-adminlte-select name="contribution_type" label="Contribution Type">
                            <option value="">All types</option>
                            <option value="compulsory" @selected(($filters['contribution_type'] ?? '') === 'compulsory')>Compulsory</option>
                            <option value="voluntary" @selected(($filters['contribution_type'] ?? '') === 'voluntary')>Voluntary / Donation</option>
                        </x-adminlte-select>
                    </div>
                    <div class="col-md-3">
                        <x-adminlte-select name="preset" label="Quick Range">
                            <option value="">Manual</option>
                            <option value="today" @selected(($filters['preset'] ?? '') === 'today')>Today</option>
                            <option value="this_month" @selected(($filters['preset'] ?? '') === 'this_month')>This Month</option>
                            <option value="last_month" @selected(($filters['preset'] ?? '') === 'last_month')>Last Month</option>
                            <option value="last_30_days" @selected(($filters['preset'] ?? '') === 'last_30_days')>Last 30 Days</option>
                        </x-adminlte-select>
                    </div>
                    <div class="col-md-3">
                        <x-adminlte-select name="view_id" label="Saved View">
                            <option value="">None</option>
                            @foreach($savedViews as $view)
                                <option value="{{ $view->id }}" @selected((int)($filters['view_id'] ?? 0) === (int)$view->id)>{{ $view->name }}</option>
                            @endforeach
                        </x-adminlte-select>
                    </div>
                </div>
                <div class="row align-items-end">
                    <div class="col-md-3 mb-3">
                        <a class="btn btn-outline-secondary btn-block"
                           href="{{ route('reports.export', array_merge($filters, ['type' => 'monthly'])) }}">
                            <i class="fas fa-file-csv mr-1"></i> Export Monthly CSV
                        </a>
                    </div>
                </div>
            </form>
            <form method="POST" action="{{ route('reports.views.save') }}" class="mt-2">
                @csrf
                <input type="hidden" name="date" value="{{ $filters['date'] }}">
                <input type="hidden" name="year" value="{{ $filters['year'] }}">
                <input type="hidden" name="month" value="{{ $filters['month'] }}">
                <input type="hidden" name="limit" value="{{ $filters['limit'] }}">
                <input type="hidden" name="payment_method" value="{{ $filters['payment_method'] ?? '' }}">
                <input type="hidden" name="collection_item_id" value="{{ (int)($filters['collection_item_id'] ?? 0) > 0 ? (int)$filters['collection_item_id'] : '' }}">
                <input type="hidden" name="contribution_type" value="{{ $filters['contribution_type'] ?? '' }}">
                <div class="row align-items-end">
                    <div class="col-md-6">
                        <x-adminlte-input name="name" label="Save Current View As" placeholder="e.g. Month-end Finance Snapshot" required />
                    </div>
                    <div class="col-md-3 mb-3">
                        <button type="submit" class="btn btn-outline-primary btn-block">
                            <i class="fas fa-save mr-1"></i> Save View
                        </button>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a class="btn btn-outline-secondary btn-block"
                           href="{{ route('reports.export', array_merge($filters, ['type' => 'arrears'])) }}">
                            <i class="fas fa-file-export mr-1"></i> Export Arrears CSV
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-gradient-info">
                <div class="inner">
                    <h3>{{ number_format((float)$kpis['daily_total'], 2) }}</h3>
                    <p>Collected (Selected Day)</p>
                </div>
                <div class="icon"><i class="fas fa-wallet"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-gradient-success">
                <div class="inner">
                    <h3>{{ number_format((int)$kpis['daily_transactions']) }}</h3>
                    <p>Transactions (Selected Day)</p>
                </div>
                <div class="icon"><i class="fas fa-receipt"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-gradient-primary">
                <div class="inner">
                    <h3>{{ number_format((float)$kpis['monthly_total'], 2) }}</h3>
                    <p>Collected (Selected Month)</p>
                </div>
                <div class="icon"><i class="fas fa-chart-line"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="small-box bg-gradient-warning">
                <div class="inner">
                    <h3>{{ number_format((float)$kpis['arrears_total'], 2) }}</h3>
                    <p>Outstanding Arrears</p>
                </div>
                <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header border-0">
                    <h3 class="card-title">KPI Summary</h3>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-7">Collection Rate</dt>
                        <dd class="col-5 text-right">{{ number_format((float)$kpis['collection_rate'], 2) }}%</dd>
                        <dt class="col-7">Avg Daily (Month)</dt>
                        <dd class="col-5 text-right">{{ number_format((float)$kpis['average_daily'], 2) }}</dd>
                        <dt class="col-7">Monthly Transactions</dt>
                        <dd class="col-5 text-right">{{ number_format((int)$kpis['monthly_transactions']) }}</dd>
                        <dt class="col-7">Members in Arrears</dt>
                        <dd class="col-5 text-right">{{ number_format((int)$kpis['arrears_members_count']) }}</dd>
                        <dt class="col-7">Previous Month</dt>
                        <dd class="col-5 text-right">{{ number_format((float)$kpis['previous_month_total'], 2) }}</dd>
                        <dt class="col-7">MoM Change</dt>
                        <dd class="col-5 text-right">
                            @if($kpis['monthly_delta_pct'] === null)
                                <span class="text-muted">N/A</span>
                            @else
                                <span class="{{ $kpis['monthly_delta_pct'] >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ $kpis['monthly_delta_pct'] >= 0 ? '+' : '' }}{{ number_format((float)$kpis['monthly_delta_pct'], 2) }}%
                                </span>
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header border-0">
                    <h3 class="card-title">Monthly Collection Trend</h3>
                </div>
                <div class="card-body">
                    <canvas id="monthlyTrendChart" height="110"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-5">
            <div class="card">
                <div class="card-header border-0">
                    <h3 class="card-title">Daily Method Breakdown</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped">
                        <thead><tr><th>Method</th><th class="text-right">Count</th><th class="text-right">Amount</th><th>Share</th></tr></thead>
                        <tbody>
                        @forelse($dailyBreakdown as $row)
                            <tr>
                                <td>
                                    <a href="{{ route('payments.index', [
                                        'date_from' => $filters['date'],
                                        'date_to' => $filters['date'],
                                        'payment_method' => $row->payment_method,
                                        'collection_item_id' => (int)($filters['collection_item_id'] ?? 0) > 0 ? (int)$filters['collection_item_id'] : '',
                                        'contribution_type' => $filters['contribution_type'] ?? '',
                                    ]) }}">
                                        {{ $row->payment_method }}
                                    </a>
                                </td>
                                <td class="text-right">{{ number_format((int)$row->payment_count) }}</td>
                                <td class="text-right">{{ number_format((float)$row->total_amount, 2) }}</td>
                                <td style="min-width:140px;">
                                    <div class="progress progress-xs">
                                        <div class="progress-bar bg-primary" style="width: {{ min(100, max(0, $row->share)) }}%"></div>
                                    </div>
                                    <small class="text-muted">{{ number_format((float)$row->share, 1) }}%</small>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">No data for selected day.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <canvas id="dailyMethodChart" height="160"></canvas>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card">
                <div class="card-header border-0">
                    <h3 class="card-title">Daily Performance in Selected Month</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped">
                        <thead><tr><th>Date</th><th class="text-right">Count</th><th class="text-right">Total</th></tr></thead>
                        <tbody>
                        @forelse($monthlySeries as $row)
                            <tr>
                                <td>
                                    <a href="{{ route('payments.index', [
                                        'date_from' => $row->posting_date,
                                        'date_to' => $row->posting_date,
                                        'payment_method' => $filters['payment_method'] ?? '',
                                        'collection_item_id' => (int)($filters['collection_item_id'] ?? 0) > 0 ? (int)$filters['collection_item_id'] : '',
                                        'contribution_type' => $filters['contribution_type'] ?? '',
                                    ]) }}">
                                        {{ $row->posting_date }}
                                    </a>
                                </td>
                                <td class="text-right">{{ number_format((int)$row->payment_count) }}</td>
                                <td class="text-right">{{ number_format((float)$row->total_amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted">No monthly transactions found.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header border-0 d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Arrears Snapshot</h3>
            <small class="text-muted">Top {{ $filters['limit'] }} by outstanding balance</small>
        </div>
        @if(($filters['contribution_type'] ?? '') === 'voluntary')
            <div class="card-body pb-0">
                <div class="alert alert-info mb-0">
                    Voluntary / donation contributions do not create arrears. Arrears are shown only for compulsory collections.
                </div>
            </div>
        @endif
        <div class="card-body p-0">
            <table class="table table-striped">
                <thead>
                <tr>
                    <th>Member Code</th>
                    <th>Full Name</th>
                    <th>Total Expected</th>
                    <th>Total Paid</th>
                    <th>Outstanding</th>
                </tr>
                </thead>
                <tbody>
                @forelse($arrears as $row)
                    @php
                        $risk = (float)$row->outstanding_balance >= 1000 ? 'High' : ((float)$row->outstanding_balance >= 300 ? 'Medium' : 'Low');
                        $riskClass = $risk === 'High' ? 'danger' : ($risk === 'Medium' ? 'warning' : 'success');
                    @endphp
                    <tr>
                        <td>{{ $row->member_code }}</td>
                        <td>{{ $row->full_name }}</td>
                        <td>{{ number_format((float)$row->total_expected, 2) }}</td>
                        <td>{{ number_format((float)$row->total_paid, 2) }}</td>
                        <td>
                            {{ number_format((float)$row->outstanding_balance, 2) }}
                            <span class="badge badge-{{ $riskClass }} ml-1">{{ $risk }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center">No arrears found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@stop

@section('js')
<script>
(() => {
    const monthlyLabels = @json($monthlySeries->pluck('date_label')->values());
    const monthlyValues = @json($monthlySeries->pluck('total_amount')->values());
    const dailyLabels = @json($dailyBreakdown->pluck('payment_method')->values());
    const dailyValues = @json($dailyBreakdown->pluck('total_amount')->values());

    const monthlyCtx = document.getElementById('monthlyTrendChart');
    if (monthlyCtx) {
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: monthlyLabels,
                datasets: [{
                    label: 'Daily Collected',
                    data: monthlyValues,
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0,123,255,0.12)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    const dailyCtx = document.getElementById('dailyMethodChart');
    if (dailyCtx) {
        new Chart(dailyCtx, {
            type: 'doughnut',
            data: {
                labels: dailyLabels,
                datasets: [{
                    data: dailyValues,
                    backgroundColor: ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6610f2', '#20c997'],
                    borderWidth: 0
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }
})();
</script>
@stop
