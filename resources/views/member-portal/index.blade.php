@extends('layouts.member-portal')

@section('title', 'Member Portal')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="mb-1">Member Portal</h1>
            <p class="text-muted mb-0">Welcome back, {{ $member->first_name }} {{ $member->last_name }} ({{ $member->member_code }})</p>
        </div>
        <div class="mt-2 mt-md-0 d-flex align-items-center flex-wrap">
            <a href="{{ route('member-portal.statement') }}" class="btn btn-outline-secondary mr-2 mb-1 mb-md-0">
                <i class="fas fa-book-open mr-1"></i> Full Statement
            </a>
            <a href="{{ route('member-portal.statement.export') }}" class="btn btn-primary mb-1 mb-md-0">
                <i class="fas fa-file-csv mr-1"></i> Download Statement
            </a>
        </div>
    </div>
@stop

@section('css')
<style>
    .mp-shell {
        display: grid;
        gap: 1rem;
    }
    .mp-rating-hero {
        border: 1px solid #d8e4f0;
        border-radius: 1rem;
        background: linear-gradient(120deg, #f8fbff 0%, #eef5ff 55%, #e7f4f1 100%);
        box-shadow: 0 14px 30px rgba(12, 42, 70, .1);
        padding: 1rem;
    }
    .mp-rating-hero h4 {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 700;
        color: #0f2b46;
    }
    .mp-rating-note {
        color: #5a6f85;
        font-size: .88rem;
        margin-top: .2rem;
    }
    .mp-pill {
        border-radius: 999px;
        padding: .28rem .72rem;
        font-size: .76rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: .3rem;
    }
    .mp-pill-success { background: #e8f8ef; color: #1d7a3f; border: 1px solid #ccebd9; }
    .mp-pill-danger { background: #fdeeee; color: #a0342d; border: 1px solid #f1c9c7; }

    .mp-gauge {
        --score: 100;
        width: 92px;
        height: 92px;
        border-radius: 50%;
        background: conic-gradient(#0f8f5c calc(var(--score) * 1%), #dfe9f3 0);
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
    }
    .mp-gauge::after {
        content: '';
        position: absolute;
        width: 68px;
        height: 68px;
        border-radius: 50%;
        background: #fff;
        box-shadow: inset 0 0 0 1px #e2eaf3;
    }
    .mp-gauge .value {
        position: relative;
        z-index: 2;
        font-size: .72rem;
        font-weight: 800;
        color: #0f2b46;
    }

    .mp-threshold {
        min-width: 260px;
        flex: 1;
    }
    .mp-threshold .progress {
        height: .62rem;
        border-radius: 999px;
        background: #dfe9f3;
    }

    .mp-kpi-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: .85rem;
    }
    .mp-kpi {
        border: 1px solid #dde8f2;
        border-radius: .95rem;
        background: #fff;
        box-shadow: 0 10px 22px rgba(12, 42, 70, .07);
        padding: .9rem;
    }
    .mp-kpi .label {
        font-size: .74rem;
        color: #61788f;
        text-transform: uppercase;
        letter-spacing: .05em;
        font-weight: 700;
    }
    .mp-kpi .value {
        font-size: 1.42rem;
        line-height: 1.1;
        font-weight: 800;
        color: #102b45;
        margin-top: .35rem;
    }

    .mp-panel {
        border: 1px solid #dde8f2;
        border-radius: 1rem;
        background: #fff;
        box-shadow: 0 10px 22px rgba(12, 42, 70, .07);
        overflow: hidden;
    }
    .mp-panel .head {
        padding: .82rem .95rem;
        border-bottom: 1px solid #e3edf6;
        background: linear-gradient(180deg, #ffffff 0%, #f6faff 100%);
    }
    .mp-panel .head h3 {
        margin: 0;
        font-size: .98rem;
        font-weight: 700;
        color: #13314f;
    }
    .mp-panel .body {
        padding: .92rem;
    }

    .mp-statement th,
    .mp-statement td {
        padding-top: .56rem;
        padding-bottom: .56rem;
        vertical-align: middle;
    }

    .mp-quick-table thead th {
        background: #f7fbff;
        color: #57718a;
        font-size: .74rem;
        text-transform: uppercase;
        letter-spacing: .04em;
        border-top: 0;
    }

    .mp-notice {
        border-radius: .72rem;
        border: 1px solid #dce9f5;
        background: #f7fbff;
        padding: .7rem .78rem;
        margin-bottom: .58rem;
    }
    .mp-notice:last-child { margin-bottom: 0; }
    .mp-notice .title {
        font-size: .86rem;
        font-weight: 700;
        color: #153350;
    }
    .mp-notice .msg {
        font-size: .82rem;
        color: #60788f;
        margin-top: .18rem;
    }

    @media (max-width: 1199px) {
        .mp-kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 767px) {
        .mp-kpi-grid { grid-template-columns: 1fr; }
        .mp-rating-hero .d-flex.align-items-center { align-items: flex-start !important; }
    }
</style>
@stop

@section('content')
    @php
        $ratingPct = max(0, min(100, (float)($summary['rating_score'] ?? 100)));
        $ratingMin = max(0, min(100, (float)($summary['rating_minimum_required'] ?? 80)));
        $ratingEligible = (bool)($summary['rating_eligible_for_benefit'] ?? true);
    @endphp

    <div class="mp-shell">
        <section class="mp-rating-hero">
            <div class="d-flex justify-content-between align-items-start flex-wrap">
                <div class="pr-2 mb-2 mb-md-0">
                    <h4>Payment Rating & Benefit Eligibility</h4>
                    <div class="mp-rating-note">Rating is based on overdue behavior, outstanding balances, and voluntary skip actions.</div>
                    <div class="mt-2">
                        <span class="mp-pill {{ $ratingEligible ? 'mp-pill-success' : 'mp-pill-danger' }}">
                            <i class="fas {{ $ratingEligible ? 'fa-check-circle' : 'fa-lock' }}"></i>
                            {{ $ratingEligible ? 'Eligible For Benefits' : 'Benefits Temporarily Locked' }}
                        </span>
                    </div>
                </div>
                <div class="d-flex align-items-center flex-wrap" style="gap: .85rem;">
                    <div class="mp-gauge" style="--score: {{ number_format($ratingPct, 2, '.', '') }};">
                        <div class="value">{{ number_format($ratingPct, 2) }}%</div>
                    </div>
                    <div class="mp-threshold">
                        <div class="d-flex justify-content-between mb-1">
                            <small class="text-muted">Required: {{ number_format($ratingMin, 2) }}%</small>
                            <small class="text-muted">Overdue: {{ number_format((int)(($summary['rating_metrics']['overdue_count'] ?? 0))) }}</small>
                            <small class="text-muted">Max late days: {{ number_format((int)(($summary['rating_metrics']['max_overdue_days'] ?? 0))) }}</small>
                        </div>
                        <div class="progress">
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
        </section>

        <section class="mp-kpi-grid">
            <article class="mp-kpi">
                <div class="label">Total Expected</div>
                <div class="value">{{ number_format((float)$summary['total_expected'], 2) }}</div>
            </article>
            <article class="mp-kpi">
                <div class="label">Total Paid</div>
                <div class="value text-success">{{ number_format((float)$summary['total_paid'], 2) }}</div>
            </article>
            <article class="mp-kpi">
                <div class="label">Outstanding Balance</div>
                <div class="value text-danger">{{ number_format((float)$summary['outstanding_balance'], 2) }}</div>
            </article>
            <article class="mp-kpi">
                <div class="label">Benefits Received</div>
                <div class="value text-primary">{{ number_format((float)($summary['benefits_received_total'] ?? 0), 2) }}</div>
                <small class="text-muted">{{ number_format((int)($summary['benefits_received_count'] ?? 0)) }} disbursements</small>
            </article>
        </section>

        <div class="row">
            <div class="col-lg-8 mb-3 mb-lg-0">
                <section class="mp-panel mb-3">
                    <div class="head d-flex justify-content-between align-items-center flex-wrap">
                        <h3>Notifications</h3>
                    </div>
                    <div class="body">
                        @forelse(($notifications ?? []) as $notice)
                            <div class="mp-notice">
                                <div class="title"><i class="{{ $notice['icon'] }} mr-1"></i>{{ $notice['title'] }}</div>
                                <div class="msg">{{ $notice['message'] }}</div>
                            </div>
                        @empty
                            <div class="text-muted">No alerts at the moment.</div>
                        @endforelse
                    </div>
                </section>

                <section class="mp-panel">
                    <div class="head d-flex justify-content-between align-items-center flex-wrap">
                        <h3>My Statement Preview</h3>
                        <div class="d-flex align-items-center">
                            <small class="text-muted mr-2">Entries: {{ number_format((int)$summary['statement_rows']) }}</small>
                            <a href="{{ route('member-portal.statement') }}" class="btn btn-xs btn-outline-primary">View Full Statement</a>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped mb-0 mp-statement">
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
                            @forelse($statementPager as $row)
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
                                    <td class="text-right font-weight-bold">
                                        {{ $affectsOutstanding ? number_format((float)$row->running_balance, 2) : '-' }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center py-3">No statement entries available.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="head border-top-0">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <small class="text-muted">Running outstanding reflects dues only. Voluntary and unallocated entries are informational.</small>
                            {{ $statementPager->links() }}
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-lg-4">
                <section class="mp-panel mb-3">
                    <div class="head"><h3>Pending Voluntary Contributions</h3></div>
                    <div class="table-responsive" style="max-height: 250px;">
                        <table class="table table-sm mb-0 mp-quick-table">
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
                                            <button type="submit" class="btn btn-xs btn-outline-secondary">Skip</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center py-2">No pending voluntary contributions.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="mp-panel mb-3">
                    <div class="head"><h3>Skipped Voluntary Contributions</h3></div>
                    <div class="table-responsive" style="max-height: 230px;">
                        <table class="table table-sm mb-0 mp-quick-table">
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
                                    <td>{{ !empty($skipped->actioned_at) ? \Illuminate\Support\Carbon::parse((string)$skipped->actioned_at)->format('Y-m-d') : '-' }}</td>
                                    <td class="text-right">
                                        <form method="POST" action="{{ route('member-portal.voluntary.unskip', $skipped->collection_item_id) }}">
                                            @csrf
                                            <input type="hidden" name="cycle_key" value="{{ $skipped->cycle_key }}">
                                            <button type="submit" class="btn btn-xs btn-outline-success">Reverse</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center py-2">No skipped voluntary contributions.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="mp-panel mb-3">
                    <div class="head"><h3>Upcoming / Open Charges</h3></div>
                    <div class="table-responsive" style="max-height: 250px;">
                        <table class="table table-sm mb-0 mp-quick-table">
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
                </section>

                <section class="mp-panel mb-3">
                    <div class="head"><h3>Benefits Received</h3></div>
                    <div class="table-responsive" style="max-height: 220px;">
                        <table class="table table-sm mb-0 mp-quick-table">
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
                </section>

                <section class="mp-panel">
                    <div class="head"><h3>Recent Payments</h3></div>
                    <div class="table-responsive" style="max-height: 230px;">
                        <table class="table table-sm mb-0 mp-quick-table">
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
                </section>
            </div>
        </div>
    </div>
@stop

@section('js')
<script>
(() => {
    const reverseForms = document.querySelectorAll('form[action*="/member-portal/voluntary/"][action*="/unskip"]');
    reverseForms.forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!confirm('Reverse this skipped voluntary contribution?')) {
                event.preventDefault();
            }
        });
    });
})();
</script>
@stop
