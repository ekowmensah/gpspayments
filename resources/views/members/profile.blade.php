@extends('adminlte::page')

@section('title', 'Member Profile')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="mb-1">Member Profile</h1>
            <p class="text-muted mb-0">{{ $member->member_code }} - {{ $member->first_name }} {{ $member->last_name }}</p>
        </div>
        <div class="mt-2 mt-md-0">
            @if(!empty(auth()->user()?->member_id))
                <a href="{{ route('member-portal.index') }}" class="btn btn-outline-info">
                    <i class="fas fa-id-card mr-1"></i> Member Portal
                </a>
            @endif
            <a href="{{ route('members.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left mr-1"></i> Back to Members
            </a>
            <a href="{{ route('members.statement.export', $member) }}" class="btn btn-outline-primary">
                <i class="fas fa-file-csv mr-1"></i> Statement CSV
            </a>
            <a href="{{ route('members.statement.print', $member) }}" class="btn btn-outline-dark" target="_blank">
                <i class="fas fa-print mr-1"></i> Print / PDF
            </a>
            <a href="{{ route('payments.index', ['member_id' => $member->id]) }}" class="btn btn-primary">
                <i class="fas fa-receipt mr-1"></i> Member Payments
            </a>
        </div>
    </div>
@stop

@section('css')
<style>
    .profile-kpi {
        border: 1px solid #e4ebf3;
        border-radius: .85rem;
        background: #fff;
        padding: .85rem;
        box-shadow: 0 8px 20px rgba(11,31,51,.05);
        height: 100%;
    }
    .profile-kpi .label {
        font-size: .74rem;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #5b6c7d;
        font-weight: 700;
    }
    .profile-kpi .value {
        font-size: 1.4rem;
        font-weight: 700;
        color: #0b1f33;
        line-height: 1.15;
    }
    .statement-wrap {
        border: 1px solid #e4ebf3;
        border-radius: .9rem;
        overflow: hidden;
    }
    .statement-head {
        background: linear-gradient(120deg, #0b1f33 0%, #11385c 100%);
        color: #fff;
        padding: .9rem 1rem;
    }
    .statement-meta {
        font-size: .85rem;
        opacity: .9;
    }
    .statement-table td, .statement-table th {
        vertical-align: middle;
        padding-top: .55rem;
        padding-bottom: .55rem;
        font-size: .92rem;
    }
    .donor-pill {
        display: inline-block;
        border-radius: 999px;
        font-size: .78rem;
        font-weight: 700;
        padding: .22rem .58rem;
    }
    .donor-bronze { background: #f5ece5; color: #8a4f22; border: 1px solid #e5ccb6; }
    .donor-silver { background: #eef2f6; color: #4d5968; border: 1px solid #d6dde7; }
    .donor-gold { background: #fff4db; color: #8a6400; border: 1px solid #efd9a1; }
    .donor-platinum { background: #eef5ff; color: #1f4d8e; border: 1px solid #d2e2fb; }
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
        width: 82px;
        height: 82px;
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
        width: 62px;
        height: 62px;
        background: #fff;
        border-radius: 50%;
        box-shadow: inset 0 0 0 1px #e6edf5;
    }
    .rating-gauge-value {
        position: relative;
        z-index: 2;
        font-size: .72rem;
        font-weight: 700;
        color: #0b1f33;
    }
</style>
@stop

@section('content')
    @php
        $ratingPct = max(0, min(100, (float)($ratingData['score'] ?? 100)));
        $ratingMin = max(0, min(100, (float)($ratingData['minimum_required_score'] ?? 80)));
    @endphp
    <div class="alert {{ ($ratingData['eligible_for_benefit'] ?? true) ? 'alert-success' : 'alert-danger' }} mb-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <strong>Member Rating:</strong>
                <span class="rating-pill rating-{{ $ratingData['band'] ?? 'excellent' }}">
                    {{ number_format($ratingPct, 2) }}%
                </span>
                <span class="ml-1">
                    {{ ($ratingData['eligible_for_benefit'] ?? true) ? 'Benefit Eligible' : 'Benefit Locked' }}
                </span>
            </div>
            <small>
                Minimum required score: {{ number_format($ratingMin, 2) }}%
                @if(!empty($ratingData['as_of_date']))
                    | As of {{ \Illuminate\Support\Carbon::parse((string)$ratingData['as_of_date'])->format('Y-m-d') }}
                @endif
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
            <div class="profile-kpi">
                <div class="label">Total Expected</div>
                <div class="value">{{ number_format((float)$summary['total_expected'], 2) }}</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3 mb-2 mb-xl-0">
            <div class="profile-kpi">
                <div class="label">Total Paid</div>
                <div class="value text-success">{{ number_format((float)$summary['total_paid'], 2) }}</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3 mb-2 mb-xl-0">
            <div class="profile-kpi">
                <div class="label">Outstanding</div>
                <div class="value text-danger">{{ number_format((float)$summary['outstanding_balance'], 2) }}</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="profile-kpi">
                <div class="label">Benefits Received</div>
                <div class="value text-primary">{{ number_format((float)($summary['benefits_received_total'] ?? 0), 2) }}</div>
                <small class="text-muted">{{ number_format((int)($summary['benefits_received_count'] ?? 0)) }} disbursements</small>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 mb-3 mb-lg-0">
            <div class="statement-wrap">
                <div class="statement-head d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h3 class="mb-1" style="font-size:1.06rem;">Member Statement</h3>
                        <div class="statement-meta">Ledger of charges and payments</div>
                    </div>
                    <div class="statement-meta">
                        Generated: {{ now()->format('Y-m-d H:i') }}
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
                            <th class="text-right">Outstanding</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($statement as $row)
                            @php $affectsOutstanding = (bool)($row->affects_outstanding ?? false); @endphp
                            <tr>
                                <td>{{ \Illuminate\Support\Carbon::parse((string)$row->entry_date)->format('Y-m-d') }}</td>
                                <td>{{ $row->reference }}</td>
                                <td>
                                    {{ $row->description }}
                                    @if(!$affectsOutstanding)
                                        <span class="badge badge-light border ml-1">Info</span>
                                    @endif
                                </td>
                                <td class="text-right text-danger">{{ (float)$row->debit > 0 ? number_format((float)$row->debit, 2) : '-' }}</td>
                                <td class="text-right text-success">{{ (float)$row->credit > 0 ? number_format((float)$row->credit, 2) : '-' }}</td>
                                <td class="text-right font-weight-bold">{{ $affectsOutstanding ? number_format((float)$row->running_balance, 2) : '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center py-3">No statement entries available.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title mb-0">Member Details</h3>
            </div>
            <div class="card-body">
                    @if(($voluntaryData['tier'] ?? 'none') !== 'none')
                        <div class="mb-2">
                            <span class="donor-pill donor-{{ $voluntaryData['tier'] }}">
                                <i class="fas fa-award mr-1"></i>{{ $voluntaryData['label'] }}
                            </span>
                        </div>
                    @endif
                    <dl class="row mb-0">
                        <dt class="col-5">Status</dt>
                        <dd class="col-7">{{ ucfirst((string)$member->status) }}</dd>
                        <dt class="col-5">Joined</dt>
                        <dd class="col-7">{{ optional($member->date_joined)->format('Y-m-d') }}</dd>
                        <dt class="col-5">Phone</dt>
                        <dd class="col-7">{{ $member->phone ?: '-' }}</dd>
                        <dt class="col-5">Email</dt>
                        <dd class="col-7">{{ $member->email ?: '-' }}</dd>
                        <dt class="col-5">Status Reason</dt>
                        <dd class="col-7">{{ $member->status_reason ?: '-' }}</dd>
                        <dt class="col-5">Rating Band</dt>
                        <dd class="col-7 text-capitalize">{{ str_replace('_', ' ', (string)($ratingData['band'] ?? 'excellent')) }}</dd>
                        <dt class="col-5">Overdue Charges</dt>
                        <dd class="col-7">{{ number_format((int)(($ratingData['metrics']['overdue_count'] ?? 0))) }}</dd>
                        <dt class="col-5">Max Overdue Days</dt>
                        <dd class="col-7">{{ number_format((int)(($ratingData['metrics']['max_overdue_days'] ?? 0))) }}</dd>
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0">Voluntary Giving</h3>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-6">Total Given</dt>
                        <dd class="col-6 text-right font-weight-bold text-success">
                            {{ number_format((float)($voluntaryData['total_amount'] ?? 0), 2) }}
                        </dd>
                        <dt class="col-6">No. of Payments</dt>
                        <dd class="col-6 text-right">{{ number_format((int)($voluntaryData['payment_count'] ?? 0)) }}</dd>
                        <dt class="col-6">Last Given</dt>
                        <dd class="col-6 text-right">
                            {{ !empty($voluntaryData['last_paid_at']) ? \Illuminate\Support\Carbon::parse((string)$voluntaryData['last_paid_at'])->format('Y-m-d') : '-' }}
                        </dd>
                        <dt class="col-6">Recent Skips (12m)</dt>
                        <dd class="col-6 text-right">{{ number_format((int)($voluntaryData['skipped_count_recent'] ?? 0)) }}</dd>
                        <dt class="col-6">Activity Score</dt>
                        <dd class="col-6 text-right">{{ number_format((float)($voluntaryData['donor_activity_score'] ?? 0), 1) }}</dd>
                    </dl>
                    @if(($voluntaryData['tier'] ?? 'none') === 'none' && (int)($voluntaryData['skipped_count_recent'] ?? 0) > 0)
                        <div class="alert alert-secondary mt-2 mb-0 py-2 px-3">
                            Donor badge is currently paused due to recent voluntary skips.
                        </div>
                    @endif
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0">Benefits Received</h3>
                </div>
                <div class="card-body p-0 table-responsive" style="max-height:240px;">
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

            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title mb-0">Recent Payments</h3>
                </div>
                <div class="card-body p-0 table-responsive" style="max-height:260px;">
                    <table class="table table-sm mb-0">
                        <thead>
                        <tr>
                            <th>Ref</th>
                            <th class="text-right">Amount</th>
                            <th>Date</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($recentPayments as $payment)
                            <tr>
                                <td>{{ $payment->payment_reference }}</td>
                                <td class="text-right">{{ number_format((float)$payment->amount, 2) }}</td>
                                <td>{{ optional($payment->posting_date)->format('Y-m-d') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center py-2">No recent payments.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">Audit Timeline</h3>
                </div>
                <div class="card-body p-0 table-responsive" style="max-height:260px;">
                    <table class="table table-sm mb-0">
                        <thead>
                        <tr>
                            <th>Action</th>
                            <th>Date</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($auditTrail as $event)
                            <tr>
                                <td>
                                    <div>{{ $event->change_summary ?: $event->action }}</div>
                                    <small class="text-muted">{{ $event->actor_role ?: '-' }}</small>
                                </td>
                                <td>{{ optional($event->created_at)->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-center py-2">No audit events.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@stop
