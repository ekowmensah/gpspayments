@extends('layouts.member-portal')

@section('title', 'My Profile')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="mb-1">My Profile</h1>
            <p class="text-muted mb-0">Identity, portal access, and payment health overview.</p>
        </div>
        <div class="mt-2 mt-md-0 d-flex align-items-center flex-wrap">
            <a href="{{ route('member-portal.index') }}" class="btn btn-outline-secondary mr-2 mb-1 mb-md-0">
                <i class="fas fa-id-card mr-1"></i> Member Portal
            </a>
            <a href="{{ route('member-portal.statement') }}" class="btn btn-primary mb-1 mb-md-0">
                <i class="fas fa-book-open mr-1"></i> Full Statement
            </a>
        </div>
    </div>
@stop

@section('css')
<style>
    .mp-profile-shell {
        display: grid;
        gap: 1rem;
    }
    .mp-hero {
        border: 1px solid #d8e4f0;
        border-radius: 1rem;
        background: linear-gradient(120deg, #f8fbff 0%, #edf5ff 58%, #e9f4ef 100%);
        box-shadow: 0 14px 30px rgba(12, 42, 70, .1);
        padding: 1rem;
    }
    .mp-hero-title {
        font-size: 1.12rem;
        font-weight: 800;
        color: #0f2b46;
        margin: 0;
    }
    .mp-hero-sub {
        font-size: .88rem;
        color: #5d7389;
        margin-top: .22rem;
    }

    .mp-badge {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        border-radius: 999px;
        padding: .3rem .72rem;
        font-size: .75rem;
        font-weight: 700;
    }
    .mp-badge-ok { background: #e8f8ef; color: #1d7a3f; border: 1px solid #ccebd9; }
    .mp-badge-lock { background: #fdeeee; color: #a0342d; border: 1px solid #f1c9c7; }
    .mp-badge-neutral { background: #eef4fb; color: #37526b; border: 1px solid #d8e4f0; }

    .mp-gauge {
        --score: 100;
        width: 90px;
        height: 90px;
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
        width: 66px;
        height: 66px;
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

    .mp-card {
        border: 1px solid #dde8f2;
        border-radius: 1rem;
        background: #fff;
        box-shadow: 0 10px 22px rgba(12, 42, 70, .07);
        overflow: hidden;
    }
    .mp-card .head {
        padding: .82rem .95rem;
        border-bottom: 1px solid #e3edf6;
        background: linear-gradient(180deg, #ffffff 0%, #f6faff 100%);
    }
    .mp-card .head h3 {
        margin: 0;
        font-size: .98rem;
        font-weight: 700;
        color: #13314f;
    }
    .mp-card .body {
        padding: .95rem;
    }

    .mp-list {
        display: grid;
        gap: .65rem;
    }
    .mp-list .item {
        display: grid;
        grid-template-columns: 130px minmax(0, 1fr);
        gap: .6rem;
        align-items: center;
        padding-bottom: .4rem;
        border-bottom: 1px dashed #e6eef6;
    }
    .mp-list .item:last-child {
        border-bottom: 0;
        padding-bottom: 0;
    }
    .mp-list .k {
        font-size: .76rem;
        text-transform: uppercase;
        letter-spacing: .045em;
        color: #667e95;
        font-weight: 700;
    }
    .mp-list .v {
        color: #173652;
        font-weight: 600;
        word-break: break-word;
    }

    .mp-kpi-grid {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: .68rem;
    }
    .mp-kpi {
        border: 1px solid #dde8f2;
        border-radius: .8rem;
        background: #fbfdff;
        padding: .72rem;
    }
    .mp-kpi .label {
        font-size: .7rem;
        text-transform: uppercase;
        color: #5f778f;
        letter-spacing: .05em;
        font-weight: 700;
    }
    .mp-kpi .value {
        margin-top: .25rem;
        font-size: 1.06rem;
        font-weight: 800;
        color: #102b45;
        line-height: 1.1;
    }

    .mp-threshold .progress {
        height: .6rem;
        border-radius: 999px;
        background: #dfe9f3;
    }

    @media (max-width: 1199px) {
        .mp-kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 767px) {
        .mp-kpi-grid { grid-template-columns: 1fr; }
        .mp-list .item { grid-template-columns: 1fr; }
    }
</style>
@stop

@section('content')
    @php
        $ratingPct = max(0, min(100, (float)($summary['rating_score'] ?? 100)));
        $ratingMin = max(0, min(100, (float)($summary['rating_minimum_required'] ?? 80)));
        $ratingEligible = (bool)($summary['rating_eligible_for_benefit'] ?? true);
    @endphp

    <div class="mp-profile-shell">
        <section class="mp-hero">
            <div class="d-flex justify-content-between align-items-start flex-wrap">
                <div class="pr-2 mb-2 mb-md-0">
                    <h2 class="mp-hero-title">{{ $member->first_name }} {{ $member->last_name }}</h2>
                    <div class="mp-hero-sub">Member Code: {{ $member->member_code }} | Joined {{ optional($member->date_joined)->format('Y-m-d') ?: '-' }}</div>
                    <div class="mt-2 d-flex align-items-center flex-wrap" style="gap: .45rem;">
                        <span class="mp-badge mp-badge-neutral"><i class="fas fa-user-circle"></i>{{ ucfirst((string)$member->status) }}</span>
                        <span class="mp-badge {{ $ratingEligible ? 'mp-badge-ok' : 'mp-badge-lock' }}">
                            <i class="fas {{ $ratingEligible ? 'fa-check-circle' : 'fa-lock' }}"></i>
                            {{ $ratingEligible ? 'Benefit Eligible' : 'Benefit Locked' }}
                        </span>
                    </div>
                </div>
                <div class="d-flex align-items-center flex-wrap" style="gap: .85rem;">
                    <div class="mp-gauge" style="--score: {{ number_format($ratingPct, 2, '.', '') }};">
                        <div class="value">{{ number_format($ratingPct, 2) }}%</div>
                    </div>
                    <div class="mp-threshold" style="min-width:260px;max-width:420px;">
                        <div class="d-flex justify-content-between mb-1">
                            <small class="text-muted">Required {{ number_format($ratingMin, 2) }}%</small>
                            <small class="text-muted">Current {{ number_format($ratingPct, 2) }}%</small>
                        </div>
                        <div class="progress">
                            <div class="progress-bar {{ $ratingPct >= $ratingMin ? 'bg-success' : 'bg-danger' }}" style="width: {{ number_format($ratingPct, 2, '.', '') }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="row">
            <div class="col-lg-6 mb-3 mb-lg-0">
                <section class="mp-card mb-3">
                    <div class="head"><h3>Member Details</h3></div>
                    <div class="body">
                        <div class="mp-list">
                            <div class="item"><div class="k">Full Name</div><div class="v">{{ $member->first_name }} {{ $member->last_name }}</div></div>
                            <div class="item"><div class="k">Email</div><div class="v">{{ $member->email ?: '-' }}</div></div>
                            <div class="item"><div class="k">Phone</div><div class="v">{{ $member->phone ?: '-' }}</div></div>
                            <div class="item"><div class="k">Date Joined</div><div class="v">{{ optional($member->date_joined)->format('Y-m-d') ?: '-' }}</div></div>
                            <div class="item"><div class="k">Status</div><div class="v">{{ ucfirst((string)$member->status) }}</div></div>
                        </div>
                    </div>
                </section>

                <section class="mp-card">
                    <div class="head"><h3>Portal Account</h3></div>
                    <div class="body">
                        <div class="mp-list">
                            <div class="item"><div class="k">Username</div><div class="v">{{ $user->username }}</div></div>
                            <div class="item"><div class="k">Login Email</div><div class="v">{{ $user->email }}</div></div>
                            <div class="item"><div class="k">Account Status</div><div class="v">{{ ucfirst((string)$user->status) }}</div></div>
                            <div class="item"><div class="k">Last Login</div><div class="v">{{ optional($user->last_login_at)->format('Y-m-d H:i') ?: '-' }}</div></div>
                            <div class="item"><div class="k">MFA Enabled</div><div class="v">{{ $user->is_mfa_enabled ? 'Yes' : 'No' }}</div></div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-lg-6">
                <section class="mp-card mb-3">
                    <div class="head"><h3>Financial Snapshot</h3></div>
                    <div class="body">
                        <div class="mp-kpi-grid mb-3">
                            <div class="mp-kpi">
                                <div class="label">Total Expected</div>
                                <div class="value">{{ number_format((float)$summary['total_expected'], 2) }}</div>
                            </div>
                            <div class="mp-kpi">
                                <div class="label">Total Paid</div>
                                <div class="value text-success">{{ number_format((float)$summary['total_paid'], 2) }}</div>
                            </div>
                            <div class="mp-kpi">
                                <div class="label">Outstanding</div>
                                <div class="value text-danger">{{ number_format((float)$summary['outstanding_balance'], 2) }}</div>
                            </div>
                            <div class="mp-kpi">
                                <div class="label">Voluntary Total</div>
                                <div class="value text-primary">{{ number_format((float)$summary['voluntary_total'], 2) }}</div>
                            </div>
                            <div class="mp-kpi">
                                <div class="label">Rating</div>
                                <div class="value {{ $ratingEligible ? 'text-success' : 'text-danger' }}">{{ number_format($ratingPct, 2) }}%</div>
                            </div>
                        </div>

                        <div class="alert {{ $ratingEligible ? 'alert-success' : 'alert-danger' }} mb-0">
                            <strong>{{ $ratingEligible ? 'Eligible for benefit disbursements.' : 'Benefit disbursements currently locked.' }}</strong>
                            <div class="small mt-1">
                                Rating threshold: {{ number_format($ratingMin, 2) }}% | Overdue items: {{ number_format((int)(($summary['rating_metrics']['overdue_count'] ?? 0))) }} | Max late days: {{ number_format((int)(($summary['rating_metrics']['max_overdue_days'] ?? 0))) }}
                            </div>
                        </div>
                    </div>
                </section>

                <section class="mp-card">
                    <div class="head"><h3>Security & Password</h3></div>
                    <div class="body">
                        <form method="POST" action="{{ route('member-portal.password.update') }}">
                            @csrf
                            <div class="row">
                                <div class="col-md-12">
                                    <p class="text-muted mb-3">Use a strong password with letters, numbers, and symbols. Avoid reusing old passwords.</p>
                                </div>
                                <div class="col-md-4">
                                    <x-adminlte-input name="current_password" type="password" label="Current Password" required />
                                </div>
                                <div class="col-md-4">
                                    <x-adminlte-input name="new_password" type="password" label="New Password" required />
                                </div>
                                <div class="col-md-4">
                                    <x-adminlte-input name="new_password_confirmation" type="password" label="Confirm New Password" required />
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-key mr-1"></i> Update Password
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </div>
@stop
