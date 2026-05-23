@extends('layouts.member-portal')

@section('title', 'My Profile')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="mb-1">My Profile</h1>
            <p class="text-muted mb-0">Member identity and account-linked details.</p>
        </div>
        <div class="mt-2 mt-md-0">
            <a href="{{ route('member-portal.index') }}" class="btn btn-outline-secondary mr-1">
                <i class="fas fa-id-card mr-1"></i> Member Portal
            </a>
            <a href="{{ route('member-portal.index') }}" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left mr-1"></i> Back to Overview
            </a>
        </div>
    </div>
@stop

@section('css')
<style>
    .rating-gauge {
        --score: 100;
        width: 86px;
        height: 86px;
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
        width: 64px;
        height: 64px;
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
        $ratingPct = max(0, min(100, (float)($summary['rating_score'] ?? 100)));
        $ratingMin = max(0, min(100, (float)($summary['rating_minimum_required'] ?? 80)));
    @endphp
    <div class="row">
        <div class="col-lg-6 mb-3">
            <div class="card portal-card">
                <div class="card-header"><h3 class="card-title mb-0">Member Details</h3></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Member Code</dt><dd class="col-sm-7">{{ $member->member_code }}</dd>
                        <dt class="col-sm-5">Full Name</dt><dd class="col-sm-7">{{ $member->first_name }} {{ $member->last_name }}</dd>
                        <dt class="col-sm-5">Email</dt><dd class="col-sm-7">{{ $member->email ?: '-' }}</dd>
                        <dt class="col-sm-5">Phone</dt><dd class="col-sm-7">{{ $member->phone ?: '-' }}</dd>
                        <dt class="col-sm-5">Joined</dt><dd class="col-sm-7">{{ optional($member->date_joined)->format('Y-m-d') }}</dd>
                        <dt class="col-sm-5">Status</dt><dd class="col-sm-7">{{ ucfirst((string)$member->status) }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-3">
            <div class="card portal-card">
                <div class="card-header"><h3 class="card-title mb-0">Portal Account</h3></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Username</dt><dd class="col-sm-7">{{ $user->username }}</dd>
                        <dt class="col-sm-5">Login Email</dt><dd class="col-sm-7">{{ $user->email }}</dd>
                        <dt class="col-sm-5">Account Status</dt><dd class="col-sm-7">{{ ucfirst((string)$user->status) }}</dd>
                        <dt class="col-sm-5">Last Login</dt><dd class="col-sm-7">{{ optional($user->last_login_at)->format('Y-m-d H:i') ?: '-' }}</dd>
                        <dt class="col-sm-5">MFA Enabled</dt><dd class="col-sm-7">{{ $user->is_mfa_enabled ? 'Yes' : 'No' }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-12 mb-3">
            <div class="card portal-card">
                <div class="card-header"><h3 class="card-title mb-0">Change Password</h3></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('member-portal.password.update') }}">
                        @csrf
                        <div class="row">
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
            </div>
        </div>

        <div class="col-12">
            <div class="card portal-card">
                <div class="card-header"><h3 class="card-title mb-0">Financial Snapshot</h3></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-2 mb-md-0">
                            <div class="small text-uppercase text-muted">Total Expected</div>
                            <div class="h5 mb-0">{{ number_format((float)$summary['total_expected'], 2) }}</div>
                        </div>
                        <div class="col-md-3 mb-2 mb-md-0">
                            <div class="small text-uppercase text-muted">Total Paid</div>
                            <div class="h5 mb-0 text-success">{{ number_format((float)$summary['total_paid'], 2) }}</div>
                        </div>
                        <div class="col-md-3 mb-2 mb-md-0">
                            <div class="small text-uppercase text-muted">Outstanding</div>
                            <div class="h5 mb-0 text-danger">{{ number_format((float)$summary['outstanding_balance'], 2) }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="small text-uppercase text-muted">Voluntary Total</div>
                            <div class="h5 mb-0 text-primary">{{ number_format((float)$summary['voluntary_total'], 2) }}</div>
                        </div>
                        <div class="col-md-3 mt-2 mt-md-0">
                            <div class="small text-uppercase text-muted">Payment Rating</div>
                            <div class="h5 mb-0 {{ ($summary['rating_eligible_for_benefit'] ?? true) ? 'text-success' : 'text-danger' }}">
                                {{ number_format($ratingPct, 2) }}%
                            </div>
                            <small class="text-muted">{{ ($summary['rating_eligible_for_benefit'] ?? true) ? 'Benefit eligible' : 'Benefit locked' }}</small>
                        </div>
                    </div>
                    <div class="d-flex align-items-center mt-3 flex-wrap">
                        <div class="rating-gauge mr-3" style="--score: {{ number_format($ratingPct, 2, '.', '') }};">
                            <div class="rating-gauge-value">{{ number_format($ratingPct, 2) }}%</div>
                        </div>
                        <div style="min-width:260px;max-width:460px;flex:1;">
                            <div class="progress" style="height:.58rem;border-radius:999px;">
                                <div class="progress-bar {{ $ratingPct >= $ratingMin ? 'bg-success' : 'bg-danger' }}" style="width: {{ number_format($ratingPct, 2, '.', '') }}%"></div>
                            </div>
                            <div class="d-flex justify-content-between mt-1">
                                <small class="text-muted">0%</small>
                                <small class="text-muted">Required {{ number_format($ratingMin, 2) }}%</small>
                                <small class="text-muted">100%</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
