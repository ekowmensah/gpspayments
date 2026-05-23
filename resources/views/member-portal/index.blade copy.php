@extends('layouts.member-portal')

@section('title', 'Member Portal')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="mb-1">Member Portal</h1>
            <p class="text-muted mb-0">Welcome, {{ $member->first_name }} {{ $member->last_name }} ({{ $member->member_code }})</p>
        </div>
        <div class="mt-2 mt-md-0">
            <a href="{{ route('member-portal.statement.export') }}" class="btn btn-outline-primary">
                <i class="fas fa-file-csv mr-1"></i> Download Statement
            </a>
        </div>
    </div>
@stop

@section('css')
<style>
    .portal-kpi {
        border: 1px solid #e4ebf3;
        border-radius: .85rem;
        background: #fff;
        padding: .9rem;
        box-shadow: 0 8px 20px rgba(11,31,51,.05);
        height: 100%;
    }
    .portal-kpi .label {
        font-size: .76rem;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #5b6c7d;
        font-weight: 700;
    }
    .portal-kpi .value {
        font-size: 1.45rem;
        font-weight: 700;
        color: #0b1f33;
        line-height: 1.15;
    }
    .portal-card {
        border: 1px solid #e4ebf3;
        border-radius: .9rem;
        overflow: hidden;
        box-shadow: 0 10px 24px rgba(11,31,51,.05);
    }
    .portal-card .card-header {
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        border-bottom: 1px solid #e4ebf3;
    }
    .statement-table td, .statement-table th {
        vertical-align: middle;
        padding-top: .55rem;
        padding-bottom: .55rem;
    }
    .rating-pill {
        display: inline-block;
        border-radius: 999px;
        font-size: .78rem;
        font-weight: 700;
        padding: .24rem .62rem;
    }
    .rating-excellent { background: #e8f8ef; color: #1d7a3f; border: 1px solid #ccebd9; }
    .rating-good { background: #ecf6ff; color: #1f5f9e; border: 1px solid #d0e5fb; }
    .rating-watchlist { background: #fff7e6; color: #9a6400; border: 1px solid #f3ddb0; }
    .rating-high_risk { background: #fdeeee; color: #a0342d; border: 1px solid #f1c9c7; }
    .rating-gauge {
        --score: 100;
        width: 78px;
        height: 78px;
        border-radius: 50%;
        background: conic-gradient(#16a34a calc(var(--score) * 1%), #e6edf5 0);
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .rating-gauge::after {
        content: '';
        position: absolute;
        width: 58px;
        height: 58px;
        background: #fff;
        border-radius: 50%;
        box-shadow: inset 0 0 0 1px #e6edf5;
    }
    .rating-gauge-value {
        position: relative;
        z-index: 2;
        font-size: .68rem;
        font-weight: 700;
        color: #0b1f33;
    }
</style>
@stop

@section('content')
    @php
        $ratingPct = max(0, min(100, (float)($summary['rating_score'] ?? 100)));
        $ratingMin = max(0, min(100, (float)($summary['rating_minimum_required'] ?? 80)));
    @endphp
    @if(session('success'))
        <x-adminlte-alert theme="success" title="Success">
            {{ session('success') }}
        </x-adminlte-alert>
    @endif

    @if($errors->has('voluntary'))
        <x-adminlte-alert theme="danger" title="Action failed">
            {{ $errors->first('voluntary') }}
        </x-adminlte-alert>
    @endif

    <div class="alert {{ ($summary['rating_eligible_for_benefit'] ?? true) ? 'alert-success' : 'alert-danger' }} mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <strong>Payment Rating:</strong>
                <span class="rating-pill rating-{{ $summary['rating_band'] ?? 'excellent' }}">
                    {{ number_format($ratingPct, 2) }}%
                </span>
                <span class="ml-1">
                    {{ ($summary['rating_eligible_for_benefit'] ?? true) ? 'Eligible for benefits' : 'Benefits temporarily locked' }}
                </span>
            </div>
            <small>
                Required: {{ number_format($ratingMin, 2) }}%
                | Overdue: {{ number_format((int)(($summary['rating_metrics']['overdue_count'] ?? 0))) }}
                | Max late days: {{ number_format((int)(($summary['rating_metrics']['max_overdue_days'] ?? 0))) }}
            </small>
        </div>
        <div class="d-flex align-items-center mt-2 flex-wrap">
            <div class="rating-gauge mr-3" style="--score: {{ number_format($ratingPct, 2, '.', '') }};">
                <div class="rating-gauge-value">{{ number_format($ratingPct, 2) }}%</div>
            </div>
            <div style="min-width:260px;max-width:420px;flex:1;">
                <div class="progress" style="height:.55rem;border-radius:999px;">
                    <div class="progress-bar {{ $ratingPct >= $ratingMin ? 'bg-success' : 'bg-danger' }}" style="width: {{ number_format($ratingPct, 2, '.', '') }}%"></div>
                </div>
                <div class="d-flex justify-content-between mt-1">
                    <small class="text-muted">0%</small>
                    <small class="text-muted">Threshold {{ number_format($ratingMin, 2) }}%</small>
                    <small class="text-muted">100%</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-6 col-xl-3 mb-2 mb-xl-0">
            <div class="portal-kpi">
                <div class="label">Total Expected</div>
                <div class="value">{{ number_format((float)$summary['total_expected'], 2) }}</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3 mb-2 mb-xl-0">
            <div class="portal-kpi">
                <div class="label">Total Paid</div>
                <div class="value text-success">{{ number_format((float)$summary['total_paid'], 2) }}</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3 mb-2 mb-xl-0">
            <div class="portal-kpi">
                <div class="label">Outstanding Balance</div>
                <div class="value text-danger">{{ number_format((float)$summary['outstanding_balance'], 2) }}</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="portal-kpi">
                <div class="label">Benefits Received</div>
                <div class="value text-primary">{{ number_format((float)($summary['benefits_received_total'] ?? 0), 2) }}</div>
                <small class="text-muted">{{ number_format((int)($summary['benefits_received_count'] ?? 0)) }} disbursements</small>
            </div>
        </div>
    </div>

    <div class="card portal-card mb-3">
        <div class="card-header">
            <h3 class="card-title mb-0">Notifications</h3>
        </div>
        <div class="card-body">
            @forelse(($notifications ?? []) as $notice)
                <div class="alert alert-{{ $notice['type'] }} mb-2">
                    <i class="{{ $notice['icon'] }} mr-1"></i>
                    <strong>{{ $notice['title'] }}</strong>
                    <div>{{ $notice['message'] }}</div>
                </div>
            @empty
                <div class="text-muted">No alerts at the moment.</div>
            @endforelse
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 mb-3 mb-lg-0">
            <div class="card portal-card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                    <h3 class="card-title mb-2 mb-md-0">My Statement</h3>
                    <div class="d-flex align-items-center">
                        <small class="text-muted mr-2">Entries: {{ number_format((int)$summary['statement_rows']) }}</small>
                        <a href="{{ route('member-portal.statement') }}" class="btn btn-xs btn-outline-primary">View Full Statement</a>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped mb-0 statement-table">
                        <thead>
                        <tr>
                            <th>Date</th>
                            <th>Reference</th>
                            <th>Description</th>
                            <th class="text-right">Debit</th>
                            <th class="text-right">Credit</th>
                            <th class="text-right">Balance</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($statementPager as $row)
                            <tr>
                                <td>{{ \Illuminate\Support\Carbon::parse((string)$row->entry_date)->format('Y-m-d') }}</td>
                                <td>{{ $row->reference }}</td>
                                <td>{{ $row->description }}</td>
                                <td class="text-right text-danger">{{ (float)$row->debit > 0 ? number_format((float)$row->debit, 2) : '-' }}</td>
                                <td class="text-right text-success">{{ (float)$row->credit > 0 ? number_format((float)$row->credit, 2) : '-' }}</td>
                                <td class="text-right font-weight-bold">{{ number_format((float)$row->running_balance, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center py-3">No statement entries available.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <small class="text-muted">Preview statement rows. Use Full Statement or CSV for complete ledger.</small>
                        {{ $statementPager->links() }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card portal-card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0">Pending Voluntary Contributions</h3>
                </div>
                <div class="card-body p-0 table-responsive" style="max-height: 260px;">
                    <table class="table table-sm mb-0">
                        <thead>
                        <tr>
                            <th>Collection</th>
                            <th class="text-right">Suggested</th>
                            <th>Cycle</th>
                            <th class="text-right">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse(($pendingVoluntaryContributions ?? collect()) as $pending)
                            <tr>
                                <td>
                                    <div class="font-weight-bold">{{ $pending->collection_name }}</div>
                                    <small class="text-muted">{{ $pending->category_name }}</small>
                                </td>
                                <td class="text-right">{{ number_format((float)$pending->suggested_amount, 2) }}</td>
                                <td>{{ $pending->cycle_label }}</td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('member-portal.voluntary.skip', $pending->collection_item_id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-outline-secondary">
                                            Skip for now
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center py-2">No pending voluntary contributions.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card portal-card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0">Skipped Voluntary Contributions</h3>
                </div>
                <div class="card-body p-0 table-responsive" style="max-height: 240px;">
                    <table class="table table-sm mb-0">
                        <thead>
                        <tr>
                            <th>Collection</th>
                            <th>Cycle</th>
                            <th>Skipped On</th>
                            <th class="text-right">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse(($skippedVoluntaryContributions ?? collect()) as $skipped)
                            <tr>
                                <td>
                                    <div class="font-weight-bold">{{ $skipped->collection_name }}</div>
                                    <small class="text-muted">{{ $skipped->category_name }}</small>
                                </td>
                                <td>{{ $skipped->cycle_label }}</td>
                                <td>
                                    {{ !empty($skipped->actioned_at) ? \Illuminate\Support\Carbon::parse((string)$skipped->actioned_at)->format('Y-m-d') : '-' }}
                                </td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('member-portal.voluntary.unskip', $skipped->collection_item_id) }}">
                                        @csrf
                                        <input type="hidden" name="cycle_key" value="{{ $skipped->cycle_key }}">
                                        <button type="submit" class="btn btn-xs btn-outline-success">
                                            Reverse Skip
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center py-2">No skipped voluntary contributions.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card portal-card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0">Benefits Received</h3>
                </div>
                <div class="card-body p-0 table-responsive" style="max-height: 220px;">
                    <table class="table table-sm mb-0">
                        <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Date</th>
                            <th class="text-right">Amount</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse(($benefitDisbursements ?? collect()) as $benefit)
                            <tr>
                                <td>{{ $benefit->reference }}</td>
                                <td>{{ \Illuminate\Support\Carbon::parse((string)$benefit->entry_date)->format('Y-m-d') }}</td>
                                <td class="text-right text-primary font-weight-bold">{{ number_format((float)$benefit->amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center py-2">No benefit disbursements.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card portal-card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0">Upcoming / Open Charges</h3>
                </div>
                <div class="card-body p-0 table-responsive" style="max-height: 280px;">
                    <table class="table table-sm mb-0">
                        <thead>
                        <tr>
                            <th>Collection</th>
                            <th>Due</th>
                            <th class="text-right">Outstanding</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($upcomingCharges as $charge)
                            <tr>
                                <td>{{ $charge->collection_name }}</td>
                                <td>{{ \Illuminate\Support\Carbon::parse((string)$charge->due_date)->format('Y-m-d') }}</td>
                                <td class="text-right text-danger font-weight-bold">{{ number_format((float)$charge->outstanding_amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center py-2">No open charges.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card portal-card">
                <div class="card-header">
                    <h3 class="card-title mb-0">Recent Payments</h3>
                </div>
                <div class="card-body p-0 table-responsive" style="max-height: 280px;">
                    <table class="table table-sm mb-0">
                        <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Date</th>
                            <th class="text-right">Amount</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($recentPayments as $payment)
                            <tr>
                                <td>{{ $payment->payment_reference }}</td>
                                <td>{{ optional($payment->posting_date)->format('Y-m-d') }}</td>
                                <td class="text-right">{{ number_format((float)$payment->amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center py-2">No payments yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@stop
