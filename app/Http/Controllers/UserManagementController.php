<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->with(['roles:id,name', 'member:id,member_code,first_name,last_name'])
            ->orderByDesc('id')
            ->paginate(20);

        $roles = Role::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $availableMembers = Member::query()
            ->leftJoin('users as u', 'u.member_id', '=', 'members.id')
            ->whereNull('u.id')
            ->orderBy('members.first_name')
            ->orderBy('members.last_name')
            ->select([
                'members.id',
                'members.member_code',
                'members.first_name',
                'members.last_name',
                'members.email',
            ])
            ->get();

        return view('users.index', compact('users', 'roles', 'availableMembers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $associationId = 1;

        $validated = $request->validate([
            'username' => [
                'required',
                'string',
                'max:100',
                Rule::unique('users', 'username')->where(fn ($q) => $q->where('association_id', $associationId)),
            ],
            'email' => [
                'required',
                'email',
                'max:191',
                Rule::unique('users', 'email')->where(fn ($q) => $q->where('association_id', $associationId)),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', 'in:active,inactive,suspended,locked'],
            'role_id' => ['required', 'exists:roles,id'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'member_id' => [
                'nullable',
                'integer',
                Rule::exists('members', 'id')->where(fn ($q) => $q->where('association_id', $associationId)),
                'unique:users,member_id',
            ],
        ]);

        $role = Role::query()
            ->where('id', (int) $validated['role_id'])
            ->where('association_id', $associationId)
            ->firstOrFail();

        $user = User::create([
            'association_id' => $associationId,
            'member_id' => isset($validated['member_id']) ? (int)$validated['member_id'] : null,
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password_hash' => Hash::make($validated['password']),
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'phone' => $validated['phone'] ?? null,
            'status' => $validated['status'],
        ]);

        $user->roles()->sync([$role->id]);

        AuditLog::create([
            'association_id' => $associationId,
            'actor_user_id' => $request->user()?->id,
            'actor_role' => $request->user()?->roles()->value('name'),
            'action' => 'USER_CREATED',
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'change_summary' => 'User account created',
            'after_data' => [
                'username' => $user->username,
                'email' => $user->email,
                'role' => $role->name,
                'status' => $user->status,
                'member_id' => $user->member_id,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'status' => 'success',
        ]);

        return redirect()
            ->route('users.index')
            ->with('success', 'User created successfully.');
    }

    public function createFromMember(Request $request, Member $member): RedirectResponse
    {
        if ($member->user()->exists()) {
            return redirect()
                ->route('members.index')
                ->withErrors(['member_user' => 'This member already has a user account.']);
        }

        $associationId = 1;
        $validated = $request->validate([
            'username' => [
                'required',
                'string',
                'max:100',
                Rule::unique('users', 'username')->where(fn ($q) => $q->where('association_id', $associationId)),
            ],
            'email' => [
                'required',
                'email',
                'max:191',
                Rule::unique('users', 'email')->where(fn ($q) => $q->where('association_id', $associationId)),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', 'in:active,inactive,suspended,locked'],
            'role_id' => ['required', 'exists:roles,id'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $role = Role::query()
            ->where('id', (int)$validated['role_id'])
            ->where('association_id', $associationId)
            ->firstOrFail();

        $user = User::create([
            'association_id' => $associationId,
            'member_id' => (int)$member->id,
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password_hash' => Hash::make($validated['password']),
            'first_name' => (string)$member->first_name,
            'last_name' => (string)$member->last_name,
            'phone' => $validated['phone'] ?? $member->phone,
            'status' => $validated['status'],
        ]);

        $user->roles()->sync([$role->id]);

        AuditLog::create([
            'association_id' => $associationId,
            'actor_user_id' => $request->user()?->id,
            'actor_role' => $request->user()?->roles()->value('name'),
            'action' => 'USER_CREATED_FROM_MEMBER',
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'change_summary' => 'User account created from member profile',
            'after_data' => [
                'member_id' => $member->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $role->name,
                'status' => $user->status,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => (string)$request->userAgent(),
            'status' => 'success',
        ]);

        return redirect()
            ->route('members.index')
            ->with('success', 'User account created for member successfully.');
    }

    public function createMemberFromUser(Request $request, User $user): RedirectResponse
    {
        if ($user->member_id) {
            return redirect()
                ->route('users.index')
                ->withErrors(['user_member' => 'This user is already linked to a member profile.']);
        }

        $validated = $request->validate([
            'date_joined' => ['required', 'date'],
            'status' => ['required', 'in:active,inactive,suspended,exited,deceased'],
            'status_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $email = trim((string)($user->email ?? ''));
        if (
            $email !== '' &&
            Member::query()
                ->where('association_id', 1)
                ->where('email', $email)
                ->exists()
        ) {
            $email = null;
        }

        $phone = trim((string)($user->phone ?? ''));
        if (
            $phone !== '' &&
            Member::query()
                ->where('association_id', 1)
                ->where('phone', $phone)
                ->exists()
        ) {
            $phone = null;
        }

        $member = Member::create([
            'association_id' => 1,
            'member_code' => 'MBR-' . strtoupper(Str::random(8)),
            'first_name' => (string)($user->first_name ?: 'User'),
            'last_name' => (string)($user->last_name ?: $user->username),
            'phone' => $phone !== '' ? $phone : null,
            'email' => $email,
            'date_joined' => $validated['date_joined'],
            'status' => $validated['status'],
            'status_reason' => $validated['status_reason'] ?? null,
        ]);

        $user->update(['member_id' => (int)$member->id]);

        AuditLog::create([
            'association_id' => 1,
            'actor_user_id' => $request->user()?->id,
            'actor_role' => $request->user()?->roles()->value('name'),
            'action' => 'MEMBER_CREATED_FROM_USER',
            'entity_type' => 'Member',
            'entity_id' => $member->id,
            'change_summary' => 'Member profile created from user account',
            'after_data' => [
                'member_id' => $member->id,
                'user_id' => $user->id,
                'member_code' => $member->member_code,
                'status' => $member->status,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => (string)$request->userAgent(),
            'status' => 'success',
        ]);

        return redirect()
            ->route('users.index')
            ->with('success', 'Member profile created and linked to user.');
    }

    public function linkMember(Request $request, User $user): RedirectResponse
    {
        $associationId = 1;
        $validated = $request->validate([
            'member_id' => [
                'required',
                'integer',
                Rule::exists('members', 'id')->where(fn ($q) => $q->where('association_id', $associationId)),
                'unique:users,member_id',
            ],
        ]);

        $member = Member::query()->findOrFail((int)$validated['member_id']);
        $before = $user->member_id;
        $user->update(['member_id' => (int)$member->id]);

        AuditLog::create([
            'association_id' => 1,
            'actor_user_id' => $request->user()?->id,
            'actor_role' => $request->user()?->roles()->value('name'),
            'action' => 'USER_LINKED_TO_MEMBER',
            'entity_type' => 'User',
            'entity_id' => $user->id,
            'change_summary' => 'User linked to member profile',
            'before_data' => ['member_id' => $before],
            'after_data' => ['member_id' => $member->id, 'member_code' => $member->member_code],
            'ip_address' => $request->ip(),
            'user_agent' => (string)$request->userAgent(),
            'status' => 'success',
        ]);

        return redirect()
            ->route('users.index')
            ->with('success', 'User linked to member profile successfully.');
    }
}
