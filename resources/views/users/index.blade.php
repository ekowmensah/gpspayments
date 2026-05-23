@extends('adminlte::page')

@section('title', 'Users')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <h1 class="mb-0">Users</h1>
        <button type="button" class="btn btn-primary mt-2 mt-md-0" data-toggle="modal" data-target="#createUserModal">
            <i class="fas fa-user-plus mr-1"></i> New User
        </button>
    </div>
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

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">User Accounts & Member Links</h3>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-striped mb-0">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Member Profile</th>
                            <th>Role(s)</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($users as $user)
                            <tr>
                                <td>{{ $user->id }}</td>
                                <td>{{ $user->fullName() }}</td>
                                <td>{{ $user->username }}</td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    @if($user->member)
                                        <span class="badge badge-success">Linked</span>
                                        <div class="small text-muted">
                                            {{ $user->member->member_code }} - {{ $user->member->first_name }} {{ $user->member->last_name }}
                                        </div>
                                    @else
                                        <span class="badge badge-secondary">Not linked</span>
                                    @endif
                                </td>
                                <td>
                                    @foreach($user->roles as $role)
                                        <span class="badge badge-info">{{ $role->name }}</span>
                                    @endforeach
                                </td>
                                <td>
                                    <span class="badge badge-{{ $user->status === 'active' ? 'success' : 'secondary' }}">
                                        {{ $user->status }}
                                    </span>
                                </td>
                                <td>
                                    @if(!$user->member)
                                        <button
                                            type="button"
                                            class="btn btn-xs btn-outline-primary js-open-link-member"
                                            data-toggle="modal"
                                            data-target="#linkMemberModal"
                                            data-user-id="{{ $user->id }}"
                                            data-user-name="{{ $user->fullName() }}"
                                        >
                                            Link Member
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-xs btn-outline-success js-open-create-member"
                                            data-toggle="modal"
                                            data-target="#createMemberFromUserModal"
                                            data-user-id="{{ $user->id }}"
                                            data-user-name="{{ $user->fullName() }}"
                                        >
                                            Create Member
                                        </button>
                                    @else
                                        <a href="{{ route('members.show', $user->member) }}" class="btn btn-xs btn-outline-secondary">View Member</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center">No users found.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer clearfix">
                    {{ $users->links() }}
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="createUserModal" tabindex="-1" role="dialog" aria-labelledby="createUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <form method="POST" action="{{ route('users.store') }}" class="modal-content">
                @csrf
                <input type="hidden" name="form_type" value="create_user">
                <div class="modal-header">
                    <h5 class="modal-title" id="createUserModalLabel">Create User</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <x-adminlte-input name="username" label="Username" value="{{ old('username') }}" required />
                        </div>
                        <div class="col-md-6">
                            <x-adminlte-input name="email" type="email" label="Email" value="{{ old('email') }}" required />
                        </div>
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
                            <x-adminlte-select name="member_id" label="Link to Member (optional)">
                                <option value="">No member link</option>
                                @foreach($availableMembers as $member)
                                    <option value="{{ $member->id }}" @selected((int)old('member_id') === (int)$member->id)>
                                        {{ $member->member_code }} - {{ $member->first_name }} {{ $member->last_name }}
                                    </option>
                                @endforeach
                            </x-adminlte-select>
                        </div>
                        <div class="col-md-6">
                            <x-adminlte-select name="status" label="Status" required>
                                <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                                <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                                <option value="suspended" @selected(old('status') === 'suspended')>Suspended</option>
                                <option value="locked" @selected(old('status') === 'locked')>Locked</option>
                            </x-adminlte-select>
                        </div>
                        <div class="col-md-6">
                            <x-adminlte-select name="role_id" label="Role" required>
                                @foreach($roles as $role)
                                    <option value="{{ $role->id }}" @selected((int)old('role_id') === (int)$role->id)>{{ $role->name }}</option>
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
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="linkMemberModal" tabindex="-1" role="dialog" aria-labelledby="linkMemberModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="POST" id="linkMemberForm" class="modal-content">
                @csrf
                <input type="hidden" name="form_type" value="link_member">
                <input type="hidden" name="user_id_for_link" id="userIdForLink" value="{{ old('user_id_for_link') }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="linkMemberModalLabel">Link User to Existing Member</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">User: <strong id="linkMemberUserName">-</strong></p>
                    <x-adminlte-select name="member_id" label="Member" required>
                        <option value="">Select member</option>
                        @foreach($availableMembers as $member)
                            <option value="{{ $member->id }}" @selected((int)old('member_id') === (int)$member->id)>
                                {{ $member->member_code }} - {{ $member->first_name }} {{ $member->last_name }}
                            </option>
                        @endforeach
                    </x-adminlte-select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Link Member</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="createMemberFromUserModal" tabindex="-1" role="dialog" aria-labelledby="createMemberFromUserModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="POST" id="createMemberFromUserForm" class="modal-content">
                @csrf
                <input type="hidden" name="form_type" value="create_member_from_user">
                <input type="hidden" name="user_id_for_create_member" id="userIdForCreateMember" value="{{ old('user_id_for_create_member') }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="createMemberFromUserModalLabel">Create Member Profile From User</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">User: <strong id="createMemberUserName">-</strong></p>
                    <x-adminlte-input name="date_joined" label="Date Joined" type="date" value="{{ old('date_joined', now()->format('Y-m-d')) }}" required />
                    <x-adminlte-select name="status" label="Member Status" required>
                        <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                        <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                        <option value="suspended" @selected(old('status') === 'suspended')>Suspended</option>
                        <option value="exited" @selected(old('status') === 'exited')>Exited</option>
                        <option value="deceased" @selected(old('status') === 'deceased')>Deceased</option>
                    </x-adminlte-select>
                    <x-adminlte-input name="status_reason" label="Status Reason (optional)" value="{{ old('status_reason') }}" />
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Create & Link Member</button>
                </div>
            </form>
        </div>
    </div>
@stop

@section('js')
<script>
(() => {
    const linkMemberForm = document.getElementById('linkMemberForm');
    const createMemberFromUserForm = document.getElementById('createMemberFromUserForm');
    const linkMemberUserName = document.getElementById('linkMemberUserName');
    const createMemberUserName = document.getElementById('createMemberUserName');
    const userIdForLink = document.getElementById('userIdForLink');
    const userIdForCreateMember = document.getElementById('userIdForCreateMember');

    document.querySelectorAll('.js-open-link-member').forEach((btn) => {
        btn.addEventListener('click', () => {
            const userId = btn.getAttribute('data-user-id');
            const userName = btn.getAttribute('data-user-name') || '-';
            if (linkMemberUserName) linkMemberUserName.textContent = userName;
            if (linkMemberForm) {
                linkMemberForm.setAttribute('action', `{{ url('/users') }}/${userId}/link-member`);
            }
            if (userIdForLink) {
                userIdForLink.value = userId || '';
            }
        });
    });

    document.querySelectorAll('.js-open-create-member').forEach((btn) => {
        btn.addEventListener('click', () => {
            const userId = btn.getAttribute('data-user-id');
            const userName = btn.getAttribute('data-user-name') || '-';
            if (createMemberUserName) createMemberUserName.textContent = userName;
            if (createMemberFromUserForm) {
                createMemberFromUserForm.setAttribute('action', `{{ url('/users') }}/${userId}/member`);
            }
            if (userIdForCreateMember) {
                userIdForCreateMember.value = userId || '';
            }
        });
    });

    const oldFormType = @json(old('form_type', ''));
    if (oldFormType === 'create_user') {
        $('#createUserModal').modal('show');
    } else if (oldFormType === 'link_member') {
        const oldUserIdForLink = @json(old('user_id_for_link'));
        if (oldUserIdForLink && linkMemberForm) {
            linkMemberForm.setAttribute('action', `{{ url('/users') }}/${oldUserIdForLink}/link-member`);
        }
        $('#linkMemberModal').modal('show');
    } else if (oldFormType === 'create_member_from_user') {
        const oldUserIdForCreateMember = @json(old('user_id_for_create_member'));
        if (oldUserIdForCreateMember && createMemberFromUserForm) {
            createMemberFromUserForm.setAttribute('action', `{{ url('/users') }}/${oldUserIdForCreateMember}/member`);
        }
        $('#createMemberFromUserModal').modal('show');
    }
})();
</script>
@stop
