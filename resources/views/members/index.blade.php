@extends('adminlte::page')

@section('title', 'Members')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="mb-1">Member Management</h1>
            <p class="text-muted mb-0">Lifecycle, status, and financial exposure for all members.</p>
        </div>
        <div class="mt-2 mt-md-0">
            <a href="{{ route('members.export', request()->query()) }}" class="btn btn-outline-secondary">
                <i class="fas fa-file-csv mr-1"></i> Export CSV
            </a>
            <button type="button" class="btn btn-outline-primary" data-toggle="modal" data-target="#importMembersModal">
                <i class="fas fa-file-import mr-1"></i> Import CSV
            </button>
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#createMemberModal">
                <i class="fas fa-user-plus mr-1"></i> New Member
            </button>
        </div>
    </div>
@stop

@section('css')
<style>
    .member-kpi {
        border: 1px solid #e4ebf3;
        border-radius: .85rem;
        background: #fff;
        padding: .9rem;
        box-shadow: 0 8px 20px rgba(11,31,51,.05);
        height: 100%;
    }
    .member-kpi .label {
        font-size: .76rem;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #5b6c7d;
        font-weight: 700;
    }
    .member-kpi .value {
        font-size: 1.45rem;
        font-weight: 700;
        color: #0b1f33;
        line-height: 1.15;
    }
    .status-chip {
        border-radius: 999px;
        font-weight: 600;
        padding: .24rem .55rem;
        display: inline-block;
        font-size: .8rem;
    }
    .status-active { background: #eaf8ef; color: #1f7a3b; border: 1px solid #cdebd8; }
    .status-inactive { background: #f3f6f9; color: #5f6d7b; border: 1px solid #dfe6ed; }
    .status-suspended { background: #fff7e6; color: #9a6400; border: 1px solid #f3ddb0; }
    .status-exited { background: #f9f0ef; color: #9a3f38; border: 1px solid #efcec9; }
    .status-deceased { background: #f1f1f1; color: #505050; border: 1px solid #d7d7d7; }
    .select-col {
        width: 36px;
    }
    .donor-chip {
        border-radius: 999px;
        font-size: .72rem;
        font-weight: 700;
        padding: .18rem .5rem;
        display: inline-block;
        margin-top: .2rem;
    }
    .donor-bronze { background: #f5ece5; color: #8a4f22; border: 1px solid #e5ccb6; }
    .donor-silver { background: #eef2f6; color: #4d5968; border: 1px solid #d6dde7; }
    .donor-gold { background: #fff4db; color: #8a6400; border: 1px solid #efd9a1; }
    .donor-platinum { background: #eef5ff; color: #1f4d8e; border: 1px solid #d2e2fb; }
    .login-chip {
        border-radius: 999px;
        font-size: .72rem;
        font-weight: 700;
        padding: .18rem .5rem;
        display: inline-block;
        margin-left: .35rem;
        background: #eaf8ef;
        color: #1f7a3b;
        border: 1px solid #cdebd8;
    }
    .rating-chip {
        border-radius: 999px;
        font-size: .72rem;
        font-weight: 700;
        padding: .18rem .5rem;
        display: inline-block;
        margin-top: .2rem;
        margin-left: .2rem;
    }
    .rating-excellent { background: #e8f8ef; color: #1d7a3f; border: 1px solid #ccebd9; }
    .rating-good { background: #ecf6ff; color: #1f5f9e; border: 1px solid #d0e5fb; }
    .rating-watchlist { background: #fff7e6; color: #9a6400; border: 1px solid #f3ddb0; }
    .rating-high_risk { background: #fdeeee; color: #a0342d; border: 1px solid #f1c9c7; }
    .rating-progress {
        width: 165px;
        height: .42rem;
        background: #e8edf3;
        border-radius: 999px;
        overflow: hidden;
        margin-top: .28rem;
    }
    .rating-progress-bar {
        height: 100%;
        border-radius: 999px;
        transition: width .35s ease;
    }
    .rating-progress-good { background: linear-gradient(90deg, #10b981 0%, #34d399 100%); }
    .rating-progress-mid { background: linear-gradient(90deg, #f59e0b 0%, #fbbf24 100%); }
    .rating-progress-low { background: linear-gradient(90deg, #ef4444 0%, #f87171 100%); }
</style>
@stop

@section('content')
    @if(session('success'))
        <x-adminlte-alert theme="success" title="Success">
            {{ session('success') }}
        </x-adminlte-alert>
    @endif

    @if($errors->any())
        <x-adminlte-alert theme="danger" title="Please check the form">
            <ul class="mb-0 pl-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-adminlte-alert>
    @endif

    <div class="row mb-3">
        <div class="col-md-6 col-xl-2 mb-2 mb-xl-0">
            <div class="member-kpi">
                <div class="label">Total Members</div>
                <div class="value">{{ number_format((int)$stats['total_members']) }}</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-2 mb-2 mb-xl-0">
            <div class="member-kpi">
                <div class="label">Active</div>
                <div class="value text-success">{{ number_format((int)$stats['active_members']) }}</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-2 mb-2 mb-xl-0">
            <div class="member-kpi">
                <div class="label">Inactive</div>
                <div class="value">{{ number_format((int)$stats['inactive_members']) }}</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-2 mb-2 mb-xl-0">
            <div class="member-kpi">
                <div class="label">Suspended</div>
                <div class="value text-warning">{{ number_format((int)$stats['suspended_members']) }}</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-2 mb-2 mb-xl-0">
            <div class="member-kpi">
                <div class="label">Exited/Deceased</div>
                <div class="value">{{ number_format((int)$stats['exited_deceased_members']) }}</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-2">
            <div class="member-kpi">
                <div class="label">Members in Arrears</div>
                <div class="value text-danger">{{ number_format((int)$stats['arrears_members']) }}</div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-6 col-xl-3 mb-2 mb-xl-0">
            <div class="member-kpi">
                <div class="label">Voluntary Donors</div>
                <div class="value text-primary">{{ number_format((int)($stats['voluntary_donors'] ?? 0)) }}</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3 mb-2 mb-xl-0">
            <div class="member-kpi">
                <div class="label">Total Voluntary Contributions</div>
                <div class="value text-success">{{ number_format((float)($stats['voluntary_donations_total'] ?? 0), 2) }}</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3 mb-2 mb-xl-0">
            <div class="member-kpi">
                <div class="label">Benefit Eligible</div>
                <div class="value text-success">{{ number_format((int)($stats['benefit_eligible_members'] ?? 0)) }}</div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="member-kpi">
                <div class="label">Benefit Locked</div>
                <div class="value text-danger">{{ number_format((int)($stats['benefit_locked_members'] ?? 0)) }}</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <h3 class="card-title mb-2 mb-md-0">Member Directory</h3>
                <form class="form-inline" method="GET" action="{{ route('members.index') }}">
                    <div class="input-group input-group-sm mr-2 mb-2 mb-md-0" style="width: 230px;">
                        <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Search code, name, email, phone">
                    </div>
                    <div class="input-group input-group-sm mr-2 mb-2 mb-md-0" style="width: 170px;">
                        <select name="status" class="form-control">
                            <option value="">All statuses</option>
                            <option value="active" @selected($status === 'active')>Active</option>
                            <option value="inactive" @selected($status === 'inactive')>Inactive</option>
                            <option value="suspended" @selected($status === 'suspended')>Suspended</option>
                            <option value="exited" @selected($status === 'exited')>Exited</option>
                            <option value="deceased" @selected($status === 'deceased')>Deceased</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm mr-1">Apply</button>
                    <a href="{{ route('members.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                </form>
            </div>
        </div>
        <div class="card-body border-bottom">
            <form method="POST" action="{{ route('members.bulk-status', request()->query()) }}" id="bulkStatusForm">
                @csrf
                <input type="hidden" name="form_type" value="bulk_status">
                <div class="row align-items-end">
                    <div class="col-md-4">
                        <x-adminlte-select name="status" label="Bulk Status Action" required>
                            <option value="">Select target status</option>
                            <option value="active" @selected(old('status') === 'active')>Active</option>
                            <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                            <option value="suspended" @selected(old('status') === 'suspended')>Suspended</option>
                            <option value="exited" @selected(old('status') === 'exited')>Exited</option>
                            <option value="deceased" @selected(old('status') === 'deceased')>Deceased</option>
                        </x-adminlte-select>
                    </div>
                    <div class="col-md-5">
                        <x-adminlte-input name="status_reason" label="Reason (optional)" value="{{ old('status_reason') }}" />
                    </div>
                    <div class="col-md-3 mb-3">
                        <button type="submit" class="btn btn-warning btn-block">
                            <i class="fas fa-users-cog mr-1"></i> Apply to Selected
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                <tr>
                    <th class="select-col">
                        <input type="checkbox" id="selectAllMembers">
                    </th>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Contact</th>
                    <th>Joined</th>
                    <th>Status</th>
                    <th class="text-right">Outstanding</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($members as $member)
                    @php
                        $statusClass = match($member->status) {
                            'active' => 'status-active',
                            'inactive' => 'status-inactive',
                            'suspended' => 'status-suspended',
                            'exited' => 'status-exited',
                            'deceased' => 'status-deceased',
                            default => 'status-inactive'
                        };
                    @endphp
                    @php
                        $ratingPct = max(0, min(100, (float)($member->rating_score ?? 100)));
                        $ratingBarClass = $ratingPct >= 80 ? 'rating-progress-good' : ($ratingPct >= 65 ? 'rating-progress-mid' : 'rating-progress-low');
                    @endphp
                    <tr>
                        <td class="select-col">
                            <input
                                type="checkbox"
                                class="js-member-checkbox"
                                value="{{ $member->id }}"
                                @checked(in_array((string)$member->id, array_map('strval', old('member_ids', [])), true))
                            >
                        </td>
                        <td class="font-weight-bold">{{ $member->member_code }}</td>
                        <td>
                            <div>
                                <a href="{{ route('members.show', $member) }}" class="font-weight-bold">
                                    {{ $member->first_name }} {{ $member->last_name }}
                                </a>
                                @if($member->user)
                                    <span class="login-chip">Portal User</span>
                                @endif
                            </div>
                            @if(($member->donor_tier ?? 'none') !== 'none')
                                <div class="donor-chip donor-{{ $member->donor_tier }}">
                                    <i class="fas fa-award mr-1"></i>{{ $member->donor_label }}
                                </div>
                                @if((int)($member->voluntary_skipped_count_recent ?? 0) > 0)
                                    <div><small class="text-muted">Recent skips: {{ (int)$member->voluntary_skipped_count_recent }}</small></div>
                                @endif
                            @elseif((int)($member->voluntary_skipped_count_recent ?? 0) > 0)
                                <div><small class="text-muted">Badge paused (recent skips: {{ (int)$member->voluntary_skipped_count_recent }})</small></div>
                            @endif
                            <div class="rating-chip rating-{{ $member->rating_band ?? 'excellent' }}">
                                <i class="fas fa-shield-alt mr-1"></i>Rating {{ number_format($ratingPct, 2) }}%
                                @if(!($member->rating_eligible_for_benefit ?? true))
                                    <span class="ml-1">Locked</span>
                                @endif
                            </div>
                            <div class="rating-progress" title="Benefit threshold: {{ number_format((float)($member->rating_minimum_required ?? 80), 2) }}%">
                                <div class="rating-progress-bar {{ $ratingBarClass }}" style="width: {{ number_format($ratingPct, 2, '.', '') }}%;"></div>
                            </div>
                            @if($member->email)
                                <small class="text-muted">{{ $member->email }}</small>
                            @endif
                        </td>
                        <td>{{ $member->phone ?: '-' }}</td>
                        <td>{{ optional($member->date_joined)->format('Y-m-d') }}</td>
                        <td>
                            <span class="status-chip {{ $statusClass }}">{{ ucfirst($member->status) }}</span>
                            @if($member->status_reason)
                                <div><small class="text-muted">{{ $member->status_reason }}</small></div>
                            @endif
                        </td>
                        <td class="text-right {{ (float)$member->outstanding_balance > 0 ? 'text-danger font-weight-bold' : '' }}">
                            {{ number_format((float)$member->outstanding_balance, 2) }}
                        </td>
                        <td>
                            <a href="{{ route('members.show', $member) }}" class="btn btn-xs btn-outline-secondary">
                                Profile
                            </a>
                            @if(!$member->user && auth()->user()?->hasRole('Administrator'))
                                <button
                                    type="button"
                                    class="btn btn-xs btn-outline-success js-open-create-user-modal"
                                    data-toggle="modal"
                                    data-target="#createMemberUserModal"
                                    data-member-id="{{ $member->id }}"
                                    data-member-code="{{ $member->member_code }}"
                                    data-member-name="{{ $member->first_name }} {{ $member->last_name }}"
                                    data-member-email="{{ $member->email }}"
                                    data-member-phone="{{ $member->phone }}"
                                >
                                    Create User
                                </button>
                            @endif
                            <button
                                type="button"
                                class="btn btn-xs btn-outline-primary js-open-status-modal"
                                data-toggle="modal"
                                data-target="#updateMemberStatusModal"
                                data-member-id="{{ $member->id }}"
                                data-member-name="{{ $member->first_name }} {{ $member->last_name }}"
                                data-member-status="{{ $member->status }}"
                                data-member-reason="{{ $member->status_reason }}"
                            >
                                Update Status
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center py-3">No members found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer clearfix">
            {{ $members->links() }}
        </div>
    </div>

    <div class="modal fade" id="createMemberModal" tabindex="-1" role="dialog" aria-labelledby="createMemberModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <form method="POST" action="{{ route('members.store') }}" class="modal-content">
                @csrf
                <input type="hidden" name="form_type" value="create_member">
                <div class="modal-header">
                    <h5 class="modal-title" id="createMemberModalLabel">Create Member</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <x-adminlte-input name="first_name" label="First Name" value="{{ old('first_name') }}" required />
                        </div>
                        <div class="col-md-6">
                            <x-adminlte-input name="last_name" label="Last Name" value="{{ old('last_name') }}" required />
                        </div>
                        <div class="col-md-6">
                            <x-adminlte-input name="phone" label="Phone" value="{{ old('phone') }}" />
                        </div>
                        <div class="col-md-6">
                            <x-adminlte-input name="email" label="Email" type="email" value="{{ old('email') }}" />
                        </div>
                        <div class="col-md-6">
                            <x-adminlte-input name="date_joined" label="Date Joined" type="date" value="{{ old('date_joined') }}" required />
                        </div>
                        <div class="col-md-6">
                            <x-adminlte-select name="status" label="Status" required>
                                <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                                <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                                <option value="suspended" @selected(old('status') === 'suspended')>Suspended</option>
                                <option value="exited" @selected(old('status') === 'exited')>Exited</option>
                                <option value="deceased" @selected(old('status') === 'deceased')>Deceased</option>
                            </x-adminlte-select>
                        </div>
                        <div class="col-12">
                            <x-adminlte-input name="status_reason" label="Status Reason (optional)" value="{{ old('status_reason') }}" />
                        </div>
                        @if(auth()->user()?->hasRole('Administrator'))
                            <div class="col-12">
                                <div class="custom-control custom-switch mt-2">
                                    <input type="checkbox" class="custom-control-input" id="auto_create_user_member" name="auto_create_user" value="1" @checked(old('auto_create_user', '1') === '1')>
                                    <label class="custom-control-label" for="auto_create_user_member">
                                        Auto-create linked portal user account
                                    </label>
                                </div>
                                <small class="text-muted">System will generate username and temporary password automatically.</small>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Member</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="importMembersModal" tabindex="-1" role="dialog" aria-labelledby="importMembersModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="POST" action="{{ route('members.import') }}" enctype="multipart/form-data" class="modal-content">
                @csrf
                <input type="hidden" name="form_type" value="import_members">
                <div class="modal-header">
                    <h5 class="modal-title" id="importMembersModalLabel">Import Members (CSV)</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <x-adminlte-select name="import_mode" label="Import Mode" required>
                        <option value="create_only" @selected(old('import_mode') === 'create_only')>Create only</option>
                        <option value="upsert_by_code" @selected(old('import_mode') === 'upsert_by_code')>Upsert by member_code</option>
                    </x-adminlte-select>
                    @if(auth()->user()?->hasRole('Administrator'))
                        <div class="custom-control custom-switch mt-1 mb-2">
                            <input type="checkbox" class="custom-control-input" id="auto_create_user_import" name="auto_create_user" value="1" @checked(old('auto_create_user', '1') === '1')>
                            <label class="custom-control-label" for="auto_create_user_import">
                                Auto-create linked portal users
                            </label>
                        </div>
                    @endif
                    <x-adminlte-input name="csv_file" label="CSV File" type="file" required />
                    <small class="text-muted d-block mt-2">
                        Required columns: <code>first_name</code>, <code>last_name</code>, <code>date_joined</code>, <code>status</code>.
                        Optional: <code>member_code</code>, <code>phone</code>, <code>email</code>, <code>status_reason</code>.
                    </small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Import CSV</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="updateMemberStatusModal" tabindex="-1" role="dialog" aria-labelledby="updateMemberStatusModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="POST" id="updateMemberStatusForm" class="modal-content">
                @csrf
                <input type="hidden" name="form_type" value="update_member_status">
                <input type="hidden" name="member_id_for_status" id="memberIdForStatus" value="{{ old('member_id_for_status') }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateMemberStatusModalLabel">Update Member Status</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Member: <strong id="statusModalMemberName">-</strong></p>
                    <x-adminlte-select name="status" id="statusModalStatus" label="Status" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                        <option value="exited">Exited</option>
                        <option value="deceased">Deceased</option>
                    </x-adminlte-select>
                    <x-adminlte-input name="status_reason" id="statusModalReason" label="Status Reason (optional)" />
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>

    @if(auth()->user()?->hasRole('Administrator'))
    <div class="modal fade" id="createMemberUserModal" tabindex="-1" role="dialog" aria-labelledby="createMemberUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <form method="POST" id="createMemberUserForm" class="modal-content">
                @csrf
                <input type="hidden" name="form_type" value="create_member_user">
                <input type="hidden" name="member_id_for_user" id="memberIdForUser" value="{{ old('member_id_for_user') }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="createMemberUserModalLabel">Create User for Member</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Member: <strong id="memberUserTargetName">-</strong></p>
                    <div class="row">
                        <div class="col-md-6">
                            <x-adminlte-input name="username" id="memberUserUsername" label="Username" value="{{ old('username') }}" required />
                        </div>
                        <div class="col-md-6">
                            <x-adminlte-input name="email" id="memberUserEmail" type="email" label="Email" value="{{ old('email') }}" required />
                        </div>
                        <div class="col-md-6">
                            <x-adminlte-input name="phone" id="memberUserPhone" label="Phone" value="{{ old('phone') }}" />
                        </div>
                        <div class="col-md-6">
                            <x-adminlte-select name="status" label="User Status" required>
                                <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                                <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                                <option value="suspended" @selected(old('status') === 'suspended')>Suspended</option>
                                <option value="locked" @selected(old('status') === 'locked')>Locked</option>
                            </x-adminlte-select>
                        </div>
                        <div class="col-md-6">
                            <x-adminlte-select name="role_id" label="Role" required>
                                @if($memberRole)
                                    <option value="{{ $memberRole->id }}" @selected((int)old('role_id', $memberRole->id) === (int)$memberRole->id)>{{ $memberRole->name }}</option>
                                @endif
                                @foreach(($roles ?? collect()) as $role)
                                    @if(!$memberRole || (int)$role->id !== (int)$memberRole->id)
                                        <option value="{{ $role->id }}" @selected((int)old('role_id') === (int)$role->id)>{{ $role->name }}</option>
                                    @endif
                                @endforeach
                            </x-adminlte-select>
                        </div>
                        <div class="col-md-6">
                            <x-adminlte-input name="password" type="password" label="Password" required />
                        </div>
                        <div class="col-md-6">
                            <x-adminlte-input name="password_confirmation" type="password" label="Confirm Password" required />
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Create User Account</button>
                </div>
            </form>
        </div>
    </div>
    @endif
@stop

@section('js')
<script>
(() => {
    const statusModal = document.getElementById('updateMemberStatusModal');
    const statusForm = document.getElementById('updateMemberStatusForm');
    const statusModalName = document.getElementById('statusModalMemberName');
    const statusModalStatus = document.getElementById('statusModalStatus');
    const statusModalReason = document.getElementById('statusModalReason');
    const memberIdForStatus = document.getElementById('memberIdForStatus');
    const createMemberUserForm = document.getElementById('createMemberUserForm');
    const memberUserTargetName = document.getElementById('memberUserTargetName');
    const memberIdForUser = document.getElementById('memberIdForUser');
    const memberUserUsername = document.getElementById('memberUserUsername');
    const memberUserEmail = document.getElementById('memberUserEmail');
    const memberUserPhone = document.getElementById('memberUserPhone');
    const queryString = @json(http_build_query(request()->query()));
    const selectAll = document.getElementById('selectAllMembers');
    const memberCheckboxes = Array.from(document.querySelectorAll('.js-member-checkbox'));
    const bulkStatusForm = document.getElementById('bulkStatusForm');

    document.querySelectorAll('.js-open-status-modal').forEach((btn) => {
        btn.addEventListener('click', () => {
            const memberId = btn.getAttribute('data-member-id');
            const memberName = btn.getAttribute('data-member-name');
            const memberStatus = btn.getAttribute('data-member-status');
            const memberReason = btn.getAttribute('data-member-reason');

            if (statusModalName) statusModalName.textContent = memberName || '-';
            if (statusModalStatus) statusModalStatus.value = memberStatus || 'active';
            if (statusModalReason) statusModalReason.value = memberReason || '';
            if (memberIdForStatus) memberIdForStatus.value = memberId || '';

            const action = `{{ url('/members') }}/${memberId}/status${queryString ? `?${queryString}` : ''}`;
            statusForm.setAttribute('action', action);
        });
    });

    document.querySelectorAll('.js-open-create-user-modal').forEach((btn) => {
        btn.addEventListener('click', () => {
            const memberId = btn.getAttribute('data-member-id');
            const memberCode = btn.getAttribute('data-member-code') || '';
            const memberName = btn.getAttribute('data-member-name') || '';
            const memberEmail = btn.getAttribute('data-member-email') || '';
            const memberPhone = btn.getAttribute('data-member-phone') || '';

            if (memberUserTargetName) {
                memberUserTargetName.textContent = `${memberCode} - ${memberName}`.trim();
            }
            if (memberUserUsername && !memberUserUsername.value) {
                const base = `${memberCode}`.toLowerCase().replace(/[^a-z0-9]/g, '');
                memberUserUsername.value = base || '';
            }
            if (memberUserEmail && !memberUserEmail.value) {
                memberUserEmail.value = memberEmail;
            }
            if (memberUserPhone && !memberUserPhone.value) {
                memberUserPhone.value = memberPhone;
            }
            if (createMemberUserForm) {
                createMemberUserForm.setAttribute('action', `{{ url('/members') }}/${memberId}/user`);
            }
            if (memberIdForUser) {
                memberIdForUser.value = memberId || '';
            }
        });
    });

    const syncBulkHiddenIds = () => {
        if (!bulkStatusForm) return;
        bulkStatusForm.querySelectorAll('input[name="member_ids[]"]').forEach((el) => el.remove());
        memberCheckboxes.forEach((cb) => {
            if (cb.checked) {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'member_ids[]';
                hidden.value = cb.value;
                bulkStatusForm.appendChild(hidden);
            }
        });
    };

    if (selectAll) {
        selectAll.addEventListener('change', () => {
            memberCheckboxes.forEach((cb) => {
                cb.checked = selectAll.checked;
            });
            syncBulkHiddenIds();
        });
    }
    memberCheckboxes.forEach((cb) => {
        cb.addEventListener('change', () => {
            if (selectAll) {
                selectAll.checked = memberCheckboxes.length > 0 && memberCheckboxes.every((item) => item.checked);
            }
            syncBulkHiddenIds();
        });
    });
    syncBulkHiddenIds();

    const oldFormType = @json(old('form_type', ''));
    if (oldFormType === 'create_member') {
        $('#createMemberModal').modal('show');
    } else if (oldFormType === 'bulk_status') {
        syncBulkHiddenIds();
    } else if (oldFormType === 'import_members') {
        $('#importMembersModal').modal('show');
    } else if (oldFormType === 'update_member_status') {
        const oldMemberId = @json(old('member_id_for_status'));
        if (oldMemberId) {
            const action = `{{ url('/members') }}/${oldMemberId}/status${queryString ? `?${queryString}` : ''}`;
            statusForm.setAttribute('action', action);
        }
        $('#updateMemberStatusModal').modal('show');
    } else if (oldFormType === 'create_member_user') {
        const oldMemberIdForUser = @json(old('member_id_for_user'));
        if (oldMemberIdForUser && createMemberUserForm) {
            createMemberUserForm.setAttribute('action', `{{ url('/members') }}/${oldMemberIdForUser}/user`);
        }
        $('#createMemberUserModal').modal('show');
    }
})();
</script>
@stop
