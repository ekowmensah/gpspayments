<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\MemberRatingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function index(Request $request): View
    {
        $donorWindowStart = now()->copy()->subMonths(12)->startOfDay()->toDateString();
        $q = trim((string) $request->query('q', ''));
        $status = trim((string) $request->query('status', ''));
        $status = in_array($status, ['active', 'inactive', 'suspended', 'exited', 'deceased'], true) ? $status : '';

        $members = Member::query()
            ->with('user:id,member_id,username,status')
            ->when($status !== '', function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->when($q !== '', function ($query) use ($q): void {
                $query->where('member_code', 'like', "%{$q}%")
                    ->orWhere('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $statusCounts = Member::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalMembers = (int) Member::query()->count();
        $activeMembers = (int) ($statusCounts['active'] ?? 0);
        $inactiveMembers = (int) ($statusCounts['inactive'] ?? 0);
        $suspendedMembers = (int) ($statusCounts['suspended'] ?? 0);
        $exitedMembers = (int) ($statusCounts['exited'] ?? 0);
        $deceasedMembers = (int) ($statusCounts['deceased'] ?? 0);
        $arrearsMembers = (int) DB::table('v_member_balances')
            ->where('outstanding_balance', '>', 0)
            ->count();

        $balanceMap = DB::table('v_member_balances')
            ->whereIn('member_id', $members->pluck('id'))
            ->get(['member_id', 'total_expected', 'total_paid', 'outstanding_balance'])
            ->keyBy('member_id');

        $voluntaryMap = DB::table('payments as p')
            ->join('collection_items as ci', 'ci.id', '=', 'p.collection_item_id')
            ->leftJoin('collection_categories as cc', 'cc.id', '=', 'ci.collection_category_id')
            ->where('p.status', 'posted')
            ->whereIn('p.member_id', $members->pluck('id'))
            ->where(function ($query): void {
                $query->where('cc.payment_mode', 'voluntary')
                    ->orWhere('ci.charge_type', 'voluntary')
                    ->orWhere('ci.category', 'donation');
            })
            ->groupBy('p.member_id')
            ->selectRaw('p.member_id, COUNT(*) as voluntary_payment_count, COALESCE(SUM(p.amount),0) as voluntary_total')
            ->get()
            ->keyBy('member_id');

        $voluntaryRecentMap = DB::table('payments as p')
            ->join('collection_items as ci', 'ci.id', '=', 'p.collection_item_id')
            ->leftJoin('collection_categories as cc', 'cc.id', '=', 'ci.collection_category_id')
            ->where('p.status', 'posted')
            ->whereDate('p.posting_date', '>=', $donorWindowStart)
            ->whereIn('p.member_id', $members->pluck('id'))
            ->where(function ($query): void {
                $query->where('cc.payment_mode', 'voluntary')
                    ->orWhere('ci.charge_type', 'voluntary')
                    ->orWhere('ci.category', 'donation');
            })
            ->groupBy('p.member_id')
            ->selectRaw('p.member_id, COUNT(*) as voluntary_payment_count_recent, COALESCE(SUM(p.amount),0) as voluntary_total_recent')
            ->get()
            ->keyBy('member_id');

        $skipRecentMap = DB::table('member_voluntary_actions')
            ->where('action', 'skipped')
            ->whereDate('actioned_at', '>=', $donorWindowStart)
            ->whereIn('member_id', $members->pluck('id'))
            ->groupBy('member_id')
            ->selectRaw('member_id, COUNT(*) as skipped_count_recent')
            ->get()
            ->keyBy('member_id');

        /** @var MemberRatingService $ratings */
        $ratings = app(MemberRatingService::class);
        $memberIds = $members->pluck('id')->map(fn ($id) => (int)$id)->all();
        $ratingRows = DB::table('member_ratings')
            ->where('association_id', 1)
            ->whereIn('member_id', $memberIds)
            ->get()
            ->keyBy('member_id');
        foreach ($memberIds as $memberId) {
            if ($ratingRows->has($memberId)) {
                continue;
            }
            $createdRating = $ratings->recalculateForMember($memberId, 1);
            $ratingRows->put($memberId, (object)[
                'member_id' => (int)$createdRating->member_id,
                'score' => (float)$createdRating->score,
                'eligible_for_benefit' => (bool)$createdRating->eligible_for_benefit,
                'band' => (string)$createdRating->band,
                'minimum_required_score' => (float)$createdRating->minimum_required_score,
            ]);
        }

        $members->getCollection()->transform(function (Member $member) use ($balanceMap): Member {
            $balance = $balanceMap->get($member->id);
            $member->total_expected = (float)($balance->total_expected ?? 0);
            $member->total_paid = (float)($balance->total_paid ?? 0);
            $member->outstanding_balance = (float)($balance->outstanding_balance ?? 0);
            return $member;
        });

        $members->getCollection()->transform(function (Member $member) use ($voluntaryMap, $voluntaryRecentMap, $skipRecentMap, $ratingRows): Member {
            $voluntary = $voluntaryMap->get($member->id);
            $count = (int)($voluntary->voluntary_payment_count ?? 0);
            $total = (float)($voluntary->voluntary_total ?? 0);
            $recent = $voluntaryRecentMap->get($member->id);
            $recentCount = (int)($recent->voluntary_payment_count_recent ?? 0);
            $recentTotal = (float)($recent->voluntary_total_recent ?? 0);
            $skip = $skipRecentMap->get($member->id);
            $recentSkips = (int)($skip->skipped_count_recent ?? 0);

            $tier = $this->resolveDonorTier($recentCount, $recentTotal, $recentSkips, $count, $total);
            $member->voluntary_payment_count = $count;
            $member->voluntary_total = $total;
            $member->voluntary_payment_count_recent = $recentCount;
            $member->voluntary_total_recent = $recentTotal;
            $member->voluntary_skipped_count_recent = $recentSkips;
            $member->donor_activity_score = (float)($tier['score'] ?? 0);
            $member->donor_tier = $tier['tier'];
            $member->donor_label = $tier['label'];
            $rating = $ratingRows->get($member->id);
            $member->rating_score = (float)($rating->score ?? 100);
            $member->rating_band = (string)($rating->band ?? 'excellent');
            $member->rating_eligible_for_benefit = (bool)($rating->eligible_for_benefit ?? true);
            $member->rating_minimum_required = (float)($rating->minimum_required_score ?? 80);
            return $member;
        });

        $voluntarySummary = DB::table('payments as p')
            ->join('collection_items as ci', 'ci.id', '=', 'p.collection_item_id')
            ->leftJoin('collection_categories as cc', 'cc.id', '=', 'ci.collection_category_id')
            ->where('p.status', 'posted')
            ->where(function ($query): void {
                $query->where('cc.payment_mode', 'voluntary')
                    ->orWhere('ci.charge_type', 'voluntary')
                    ->orWhere('ci.category', 'donation');
            })
            ->selectRaw('COUNT(DISTINCT p.member_id) as donor_count, COALESCE(SUM(p.amount),0) as donor_amount')
            ->first();

        $memberRole = Role::query()
            ->where('association_id', 1)
            ->where('name', 'Member')
            ->first(['id', 'name']);
        $roles = Role::query()
            ->where('association_id', 1)
            ->orderBy('name')
            ->get(['id', 'name']);

        $stats = [
            'total_members' => $totalMembers,
            'active_members' => $activeMembers,
            'inactive_members' => $inactiveMembers,
            'suspended_members' => $suspendedMembers,
            'exited_deceased_members' => $exitedMembers + $deceasedMembers,
            'arrears_members' => $arrearsMembers,
            'voluntary_donors' => (int)($voluntarySummary->donor_count ?? 0),
            'voluntary_donations_total' => (float)($voluntarySummary->donor_amount ?? 0),
            'benefit_eligible_members' => (int) DB::table('member_ratings')
                ->where('association_id', 1)
                ->where('eligible_for_benefit', true)
                ->count(),
            'benefit_locked_members' => (int) DB::table('member_ratings')
                ->where('association_id', 1)
                ->where('eligible_for_benefit', false)
                ->count(),
        ];

        return view('members.index', compact('members', 'q', 'status', 'stats', 'memberRole', 'roles'));
    }

    public function store(Request $request): RedirectResponse
    {
        $associationId = 1;
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('members', 'phone')->where(fn ($q) => $q->where('association_id', $associationId)),
            ],
            'email' => [
                'nullable',
                'email',
                'max:191',
                Rule::unique('members', 'email')->where(fn ($q) => $q->where('association_id', $associationId)),
            ],
            'date_joined' => ['required', 'date'],
            'status' => ['required', 'in:active,inactive,suspended,exited,deceased'],
            'status_reason' => ['nullable', 'string', 'max:255'],
            'auto_create_user' => ['nullable', 'boolean'],
        ]);

        if (!$this->canAssignStatus($request, (string)$validated['status'])) {
            return redirect()
                ->route('members.index')
                ->withErrors(['status' => 'You are not allowed to assign this status.'])
                ->withInput();
        }

        $memberCode = 'MBR-' . strtoupper(Str::random(8));

        $member = Member::create([
            'association_id' => $associationId,
            'member_code' => $memberCode,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'date_joined' => $validated['date_joined'],
            'status' => $validated['status'],
            'status_reason' => $validated['status_reason'] ?? null,
        ]);

        /** @var MemberRatingService $ratings */
        $ratings = app(MemberRatingService::class);
        $memberRating = $ratings->recalculateForMember((int)$member->id, $associationId);

        $autoCreateUser = (bool)($validated['auto_create_user'] ?? true);
        $autoCreateUser = $autoCreateUser && (bool)$request->user()?->hasRole('Administrator');
        $userProvision = null;
        if ($autoCreateUser) {
            $userProvision = $this->provisionPortalUserForMember($member);
        }

        AuditLog::create([
            'association_id' => 1,
            'actor_user_id' => $request->user()?->id,
            'actor_role' => $request->user()?->roles()->value('name'),
            'action' => 'MEMBER_CREATED',
            'entity_type' => 'Member',
            'entity_id' => $member->id,
            'change_summary' => 'Member created',
            'after_data' => array_merge($member->toArray(), [
                'member_rating' => [
                    'score' => (float)$memberRating->score,
                    'eligible_for_benefit' => (bool)$memberRating->eligible_for_benefit,
                    'band' => (string)$memberRating->band,
                ],
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => (string)$request->userAgent(),
            'status' => 'success',
        ]);

        return redirect()
            ->route('members.index')
            ->with(
                'success',
                $this->buildMemberCreateSuccessMessage($member, $userProvision, $autoCreateUser)
            );
    }

    public function updateStatus(Request $request, Member $member): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:active,inactive,suspended,exited,deceased'],
            'status_reason' => ['nullable', 'string', 'max:255'],
            'member_id_for_status' => ['nullable', 'integer'],
        ]);

        if (!$this->canAssignStatus($request, (string)$validated['status'])) {
            return redirect()
                ->route('members.index', $request->query())
                ->withErrors(['status' => 'You are not allowed to set this status.'])
                ->withInput();
        }

        $before = $member->only(['status', 'status_reason']);

        $member->update([
            'status' => $validated['status'],
            'status_reason' => $validated['status_reason'] ?? null,
        ]);

        AuditLog::create([
            'association_id' => 1,
            'actor_user_id' => $request->user()?->id,
            'actor_role' => $request->user()?->roles()->value('name'),
            'action' => 'MEMBER_STATUS_UPDATED',
            'entity_type' => 'Member',
            'entity_id' => $member->id,
            'change_summary' => 'Member status updated',
            'before_data' => $before,
            'after_data' => $member->only(['status', 'status_reason']),
            'ip_address' => $request->ip(),
            'user_agent' => (string)$request->userAgent(),
            'status' => 'success',
        ]);

        return redirect()
            ->route('members.index', $request->query())
            ->with('success', 'Member status updated.');
    }

    public function bulkStatus(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'member_ids' => ['required', 'array', 'min:1'],
            'member_ids.*' => ['integer', 'exists:members,id'],
            'status' => ['required', 'in:active,inactive,suspended,exited,deceased'],
            'status_reason' => ['nullable', 'string', 'max:255'],
        ]);

        if (!$this->canAssignStatus($request, (string)$validated['status'])) {
            return redirect()
                ->route('members.index', $request->query())
                ->withErrors(['status' => 'You are not allowed to set this status in bulk.'])
                ->withInput();
        }

        $memberIds = array_map('intval', $validated['member_ids']);
        $members = Member::query()->whereIn('id', $memberIds)->get();

        $updated = 0;
        foreach ($members as $member) {
            $before = $member->only(['status', 'status_reason']);
            $member->update([
                'status' => $validated['status'],
                'status_reason' => $validated['status_reason'] ?? null,
            ]);
            $updated++;

            AuditLog::create([
                'association_id' => 1,
                'actor_user_id' => $request->user()?->id,
                'actor_role' => $request->user()?->roles()->value('name'),
                'action' => 'MEMBER_STATUS_UPDATED',
                'entity_type' => 'Member',
                'entity_id' => $member->id,
                'change_summary' => 'Member status updated (bulk)',
                'before_data' => $before,
                'after_data' => $member->only(['status', 'status_reason']),
                'ip_address' => $request->ip(),
                'user_agent' => (string)$request->userAgent(),
                'status' => 'success',
            ]);
        }

        return redirect()
            ->route('members.index', $request->query())
            ->with('success', sprintf('Bulk status update complete. %d member(s) updated.', $updated));
    }

    public function export(Request $request): StreamedResponse
    {
        $q = trim((string)$request->query('q', ''));
        $status = trim((string)$request->query('status', ''));
        $status = in_array($status, ['active', 'inactive', 'suspended', 'exited', 'deceased'], true) ? $status : '';

        $members = Member::query()
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($q !== '', function ($query) use ($q): void {
                $query->where('member_code', 'like', "%{$q}%")
                    ->orWhere('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            })
            ->orderBy('id')
            ->get();

        $filename = 'members_' . now()->format('Ymd_His') . '.csv';
        return response()->streamDownload(function () use ($members): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, ['Member Code', 'First Name', 'Last Name', 'Phone', 'Email', 'Date Joined', 'Status', 'Status Reason']);
            foreach ($members as $member) {
                fputcsv($out, [
                    $member->member_code,
                    $member->first_name,
                    $member->last_name,
                    $member->phone,
                    $member->email,
                    optional($member->date_joined)->format('Y-m-d'),
                    $member->status,
                    $member->status_reason,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(Request $request): RedirectResponse
    {
        $associationId = 1;
        $validated = $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'import_mode' => ['required', 'in:create_only,upsert_by_code'],
            'auto_create_user' => ['nullable', 'boolean'],
        ]);
        $autoCreateUser = (bool)($validated['auto_create_user'] ?? true);
        $autoCreateUser = $autoCreateUser && (bool)$request->user()?->hasRole('Administrator');

        /** @var UploadedFile $file */
        $file = $validated['csv_file'];
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return redirect()
                ->route('members.index')
                ->withErrors(['csv_file' => 'Unable to read CSV file.']);
        }

        $header = fgetcsv($handle);
        if (!is_array($header)) {
            fclose($handle);
            return redirect()
                ->route('members.index')
                ->withErrors(['csv_file' => 'CSV appears empty or invalid.']);
        }

        $map = [];
        foreach ($header as $idx => $col) {
            $map[strtolower(trim((string)$col))] = $idx;
        }

        $required = ['first_name', 'last_name', 'date_joined', 'status'];
        foreach ($required as $field) {
            if (!array_key_exists($field, $map)) {
                fclose($handle);
                return redirect()
                    ->route('members.index')
                    ->withErrors(['csv_file' => "Missing required column: {$field}"]);
            }
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $usersCreated = 0;
        $credentialSamples = [];
        $errors = [];
        $line = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            $firstName = trim((string)($row[$map['first_name']] ?? ''));
            $lastName = trim((string)($row[$map['last_name']] ?? ''));
            $dateJoined = trim((string)($row[$map['date_joined']] ?? ''));
            $status = strtolower(trim((string)($row[$map['status']] ?? 'active')));
            $phone = isset($map['phone']) ? trim((string)($row[$map['phone']] ?? '')) : null;
            $email = isset($map['email']) ? trim((string)($row[$map['email']] ?? '')) : null;
            $statusReason = isset($map['status_reason']) ? trim((string)($row[$map['status_reason']] ?? '')) : null;
            $memberCode = isset($map['member_code']) ? trim((string)($row[$map['member_code']] ?? '')) : '';

            if ($firstName === '' || $lastName === '' || $dateJoined === '' || !in_array($status, ['active', 'inactive', 'suspended', 'exited', 'deceased'], true)) {
                $skipped++;
                $errors[] = "Line {$line}: invalid required fields.";
                continue;
            }
            if (!$this->canAssignStatus($request, $status)) {
                $skipped++;
                $errors[] = "Line {$line}: unauthorized status '{$status}'.";
                continue;
            }

            try {
                $parsedDate = Carbon::parse($dateJoined)->format('Y-m-d');
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = "Line {$line}: invalid date_joined '{$dateJoined}'.";
                continue;
            }

            $payload = [
                'association_id' => $associationId,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phone ?: null,
                'email' => $email ?: null,
                'date_joined' => $parsedDate,
                'status' => $status,
                'status_reason' => $statusReason ?: null,
            ];

            if ($validated['import_mode'] === 'upsert_by_code' && $memberCode !== '') {
                $member = Member::query()->where('member_code', $memberCode)->first();
                if ($member) {
                    if (!$this->canUseMemberEmail($payload['email'], $associationId, (int)$member->id)) {
                        $skipped++;
                        $errors[] = "Line {$line}: email '{$payload['email']}' already belongs to another member.";
                        continue;
                    }
                    if (!$this->canUseMemberPhone($payload['phone'], $associationId, (int)$member->id)) {
                        $skipped++;
                        $errors[] = "Line {$line}: phone '{$payload['phone']}' already belongs to another member.";
                        continue;
                    }

                    $before = $member->toArray();
                    $member->update($payload);
                    $updated++;
                    if ($autoCreateUser && !$member->user()->exists()) {
                        $provision = $this->provisionPortalUserForMember($member);
                        if ((bool)($provision['created'] ?? false)) {
                            $usersCreated++;
                            if (count($credentialSamples) < 5 && !empty($provision['username']) && !empty($provision['temp_password'])) {
                                $credentialSamples[] = sprintf('%s:%s', (string)$provision['username'], (string)$provision['temp_password']);
                            }
                        }
                    }

                    AuditLog::create([
                        'association_id' => 1,
                        'actor_user_id' => $request->user()?->id,
                        'actor_role' => $request->user()?->roles()->value('name'),
                        'action' => 'MEMBER_UPDATED_IMPORT',
                        'entity_type' => 'Member',
                        'entity_id' => $member->id,
                        'change_summary' => 'Member updated via CSV import',
                        'before_data' => $before,
                        'after_data' => $member->toArray(),
                        'ip_address' => $request->ip(),
                        'user_agent' => (string)$request->userAgent(),
                        'status' => 'success',
                    ]);
                    continue;
                }
            }

            if (!$this->canUseMemberEmail($payload['email'], $associationId, null)) {
                $skipped++;
                $errors[] = "Line {$line}: email '{$payload['email']}' already exists for another member.";
                continue;
            }
            if (!$this->canUseMemberPhone($payload['phone'], $associationId, null)) {
                $skipped++;
                $errors[] = "Line {$line}: phone '{$payload['phone']}' already exists for another member.";
                continue;
            }

            $payload['member_code'] = $memberCode !== '' ? $memberCode : 'MBR-' . strtoupper(Str::random(8));
            $member = Member::create($payload);
            $created++;
            if ($autoCreateUser) {
                $provision = $this->provisionPortalUserForMember($member);
                if ((bool)($provision['created'] ?? false)) {
                    $usersCreated++;
                    if (count($credentialSamples) < 5 && !empty($provision['username']) && !empty($provision['temp_password'])) {
                        $credentialSamples[] = sprintf('%s:%s', (string)$provision['username'], (string)$provision['temp_password']);
                    }
                }
            }

            AuditLog::create([
                'association_id' => 1,
                'actor_user_id' => $request->user()?->id,
                'actor_role' => $request->user()?->roles()->value('name'),
                'action' => 'MEMBER_CREATED_IMPORT',
                'entity_type' => 'Member',
                'entity_id' => $member->id,
                'change_summary' => 'Member created via CSV import',
                'after_data' => $member->toArray(),
                'ip_address' => $request->ip(),
                'user_agent' => (string)$request->userAgent(),
                'status' => 'success',
            ]);
        }
        fclose($handle);

        $summary = sprintf('Import complete. Created: %d, Updated: %d, Skipped: %d.', $created, $updated, $skipped);
        if ($autoCreateUser) {
            $summary .= sprintf(' Portal users auto-created: %d.', $usersCreated);
            if (!empty($credentialSamples)) {
                $summary .= ' Sample credentials (username:password): ' . implode(' | ', $credentialSamples);
            }
        }
        if (!empty($errors)) {
            $summary .= ' Sample issues: ' . implode(' | ', array_slice($errors, 0, 3));
        }

        return redirect()
            ->route('members.index')
            ->with('success', $summary);
    }

    public function show(Member $member): View
    {
        $payload = $this->buildStatementPayload($member);
        return view('members.profile', array_merge(['member' => $member], $payload));
    }

    public function statementExport(Member $member): StreamedResponse
    {
        $payload = $this->buildStatementPayload($member);
        $statement = $payload['statement'];

        $filename = sprintf(
            'member_statement_%s_%s.csv',
            strtolower((string)$member->member_code),
            now()->format('Ymd_His')
        );

        return response()->streamDownload(function () use ($statement): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, ['Date', 'Reference', 'Description', 'Debit', 'Credit', 'Outstanding', 'Entry Type']);
            foreach ($statement as $row) {
                $affectsOutstanding = (bool)($row->affects_outstanding ?? false);
                fputcsv($out, [
                    Carbon::parse((string)$row->entry_date)->format('Y-m-d'),
                    $row->reference,
                    $row->description,
                    number_format((float)$row->debit, 2, '.', ''),
                    number_format((float)$row->credit, 2, '.', ''),
                    $affectsOutstanding ? number_format((float)$row->running_balance, 2, '.', '') : '',
                    $affectsOutstanding ? 'Dues' : 'Informational',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function statementPrint(Member $member): View
    {
        $payload = $this->buildStatementPayload($member);
        return view('members.statement-print', array_merge(['member' => $member], $payload));
    }

    private function canAssignStatus(Request $request, string $status): bool
    {
        $status = strtolower(trim($status));
        $roleNames = $request->user()?->roles()->pluck('name')->map(fn ($name) => strtolower((string)$name))->all() ?? [];

        $isAdmin = in_array('administrator', $roleNames, true);
        $isSecretary = in_array('secretary', $roleNames, true);

        if ($isAdmin) {
            return true;
        }
        if ($isSecretary) {
            return in_array($status, ['active', 'inactive', 'suspended'], true);
        }
        return false;
    }

    private function resolveDonorTier(
        int $recentCount,
        float $recentAmount,
        int $recentSkips = 0,
        int $allTimeCount = 0,
        float $allTimeAmount = 0
    ): array
    {
        // Recent activity (last 12 months) is weighted highest; repeated skips reduce motivation badge level.
        $score = ($recentCount * 2.0) + ($recentAmount / 120.0) - ($recentSkips * 2.5);
        $score += min($allTimeCount, 12) * 0.15;
        $score += min($allTimeAmount / 2000.0, 1.5);

        if ($recentSkips >= max(2, $recentCount + 1) && $recentAmount < 150) {
            return ['tier' => 'none', 'label' => '', 'score' => $score];
        }

        if (($recentCount >= 24 || $recentAmount >= 2000) && $recentSkips <= 4 && $score >= 44) {
            return ['tier' => 'platinum', 'label' => 'Platinum Donor', 'score' => $score];
        }
        if (($recentCount >= 12 || $recentAmount >= 1000) && $recentSkips <= 5 && $score >= 26) {
            return ['tier' => 'gold', 'label' => 'Gold Donor', 'score' => $score];
        }
        if (($recentCount >= 6 || $recentAmount >= 400) && $recentSkips <= 6 && $score >= 14) {
            return ['tier' => 'silver', 'label' => 'Silver Donor', 'score' => $score];
        }
        if (($recentCount > 0 || $recentAmount > 0 || $allTimeCount > 0 || $allTimeAmount > 0) && $score >= 3) {
            return ['tier' => 'bronze', 'label' => 'Bronze Donor', 'score' => $score];
        }

        return ['tier' => 'none', 'label' => '', 'score' => $score];
    }

    private function canUseMemberEmail(?string $email, int $associationId, ?int $ignoreMemberId): bool
    {
        $email = trim((string)$email);
        if ($email === '') {
            return true;
        }

        $query = Member::query()
            ->where('association_id', $associationId)
            ->where('email', $email);

        if ($ignoreMemberId !== null) {
            $query->where('id', '!=', $ignoreMemberId);
        }

        return !$query->exists();
    }

    private function canUseMemberPhone(?string $phone, int $associationId, ?int $ignoreMemberId): bool
    {
        $phone = trim((string)$phone);
        if ($phone === '') {
            return true;
        }

        $query = Member::query()
            ->where('association_id', $associationId)
            ->where('phone', $phone);

        if ($ignoreMemberId !== null) {
            $query->where('id', '!=', $ignoreMemberId);
        }

        return !$query->exists();
    }

    private function provisionPortalUserForMember(Member $member): array
    {
        if ($member->user()->exists()) {
            return ['created' => false, 'reason' => 'already_linked'];
        }

        $associationId = (int)$member->association_id;
        $username = $this->buildUniqueUsernameForMember($member);
        $email = $this->buildUniqueEmailForMember($member, $username);
        $tempPassword = $this->generateTempPassword();
        $userStatus = match ((string)$member->status) {
            'active' => 'active',
            'suspended' => 'suspended',
            'inactive' => 'inactive',
            default => 'inactive',
        };

        $user = User::create([
            'association_id' => $associationId,
            'member_id' => (int)$member->id,
            'username' => $username,
            'email' => $email,
            'password_hash' => Hash::make($tempPassword),
            'first_name' => (string)$member->first_name,
            'last_name' => (string)$member->last_name,
            'phone' => $member->phone,
            'status' => $userStatus,
        ]);

        $memberRole = Role::query()
            ->where('association_id', $associationId)
            ->where('name', 'Member')
            ->first();
        if ($memberRole) {
            $user->roles()->syncWithoutDetaching([$memberRole->id]);
        }

        return [
            'created' => true,
            'user_id' => $user->id,
            'username' => $username,
            'email' => $email,
            'temp_password' => $tempPassword,
        ];
    }

    private function buildUniqueUsernameForMember(Member $member): string
    {
        $associationId = (int)$member->association_id;
        $base = strtolower(preg_replace('/[^a-z0-9]/i', '', (string)$member->member_code));
        if ($base === '') {
            $base = 'member' . (int)$member->id;
        }

        $username = $base;
        $i = 1;
        while (User::query()->where('association_id', $associationId)->where('username', $username)->exists()) {
            $username = $base . $i;
            $i++;
        }

        return $username;
    }

    private function buildUniqueEmailForMember(Member $member, string $username): string
    {
        $associationId = (int)$member->association_id;
        $memberEmail = trim((string)($member->email ?? ''));
        if (
            $memberEmail !== '' &&
            !User::query()->where('association_id', $associationId)->where('email', $memberEmail)->exists()
        ) {
            return $memberEmail;
        }

        $email = $username . '@members.local';
        $i = 1;
        while (User::query()->where('association_id', $associationId)->where('email', $email)->exists()) {
            $email = $username . $i . '@members.local';
            $i++;
        }
        return $email;
    }

    private function generateTempPassword(): string
    {
        return 'Mbr#' . strtoupper(Str::random(8)) . '9!';
    }

    private function buildMemberCreateSuccessMessage(Member $member, ?array $userProvision, bool $autoCreateUser): string
    {
        $message = 'Member created successfully.';
        if (!$autoCreateUser) {
            return $message;
        }
        if ((bool)($userProvision['created'] ?? false) && !empty($userProvision['username']) && !empty($userProvision['temp_password'])) {
            return $message . sprintf(
                ' Portal user created: %s. Temporary password: %s',
                (string)$userProvision['username'],
                (string)$userProvision['temp_password']
            );
        }
        return $message . ' Portal user was not auto-created.';
    }

    private function buildStatementPayload(Member $member): array
    {
        /** @var MemberRatingService $ratings */
        $ratings = app(MemberRatingService::class);
        $memberRating = $ratings->getOrRecalculate((int)$member->id, (int)$member->association_id);

        $balance = DB::table('v_member_balances')->where('member_id', $member->id)->first();

        $allocationByCharge = DB::table('payment_allocations')
            ->selectRaw('member_charge_id, SUM(allocated_amount) as allocated_amount')
            ->groupBy('member_charge_id');

        $charges = DB::table('member_charges as mc')
            ->join('collection_items as ci', 'ci.id', '=', 'mc.collection_item_id')
            ->leftJoinSub($allocationByCharge, 'pa', function ($join): void {
                $join->on('pa.member_charge_id', '=', 'mc.id');
            })
            ->where('mc.member_id', $member->id)
            ->selectRaw('
                mc.id as charge_id,
                mc.charge_reference,
                mc.charge_date,
                mc.due_date,
                ci.name as collection_name,
                (mc.expected_amount + mc.penalty_amount - mc.discount_amount - mc.waived_amount) as charge_amount,
                COALESCE(pa.allocated_amount, 0) as paid_amount
            ')
            ->orderBy('mc.due_date')
            ->orderBy('mc.id')
            ->get();

        $allocations = DB::table('payment_allocations as pa')
            ->join('payments as p', 'p.id', '=', 'pa.payment_id')
            ->join('member_charges as mc', 'mc.id', '=', 'pa.member_charge_id')
            ->join('collection_items as ci_alloc', 'ci_alloc.id', '=', 'mc.collection_item_id')
            ->where('mc.member_id', $member->id)
            ->where('p.status', 'posted')
            ->selectRaw('
                p.id as payment_id,
                p.payment_reference,
                p.posting_date as entry_date,
                p.payment_method,
                COALESCE(SUM(pa.allocated_amount), 0) as amount,
                GROUP_CONCAT(DISTINCT ci_alloc.name ORDER BY ci_alloc.name SEPARATOR ", ") as allocated_collections
            ')
            ->groupBy('p.id', 'p.payment_reference', 'p.posting_date', 'p.payment_method')
            ->orderBy('p.posting_date')
            ->orderBy('p.id')
            ->get();

        $allocationByPayment = DB::table('payment_allocations')
            ->selectRaw('payment_id, SUM(allocated_amount) as allocated_total')
            ->groupBy('payment_id');

        $unallocatedPayments = Payment::query()
            ->leftJoinSub($allocationByPayment, 'pa', function ($join): void {
                $join->on('pa.payment_id', '=', 'payments.id');
            })
            ->leftJoin('collection_items as ci', 'ci.id', '=', 'payments.collection_item_id')
            ->leftJoin('collection_categories as cc', 'cc.id', '=', 'ci.collection_category_id')
            ->where('payments.member_id', $member->id)
            ->where('payments.status', 'posted')
            ->where(function ($query): void {
                $query->whereNull('payments.collection_item_id')
                    ->orWhere(function ($query): void {
                        $query->where(function ($qq): void {
                            $qq->where('cc.payment_mode', '!=', 'voluntary')
                                ->orWhereNull('cc.payment_mode');
                        })->where('ci.charge_type', '!=', 'voluntary')
                            ->where('ci.category', '!=', 'donation');
                    });
            })
            ->whereRaw('GREATEST(payments.amount - COALESCE(pa.allocated_total, 0), 0) > 0')
            ->selectRaw('
                payments.id as payment_id,
                payments.payment_reference,
                payments.posting_date as entry_date,
                payments.payment_method,
                GREATEST(payments.amount - COALESCE(pa.allocated_total, 0), 0) as amount
            ')
            ->orderBy('payments.posting_date')
            ->orderBy('payments.id')
            ->get();

        $voluntaryPayments = Payment::query()
            ->join('collection_items as ci', 'ci.id', '=', 'payments.collection_item_id')
            ->leftJoin('collection_categories as cc', 'cc.id', '=', 'ci.collection_category_id')
            ->leftJoinSub($allocationByPayment, 'pa', function ($join): void {
                $join->on('pa.payment_id', '=', 'payments.id');
            })
            ->where('payments.member_id', $member->id)
            ->where('payments.status', 'posted')
            ->where(function ($query): void {
                $query->where('cc.payment_mode', 'voluntary')
                    ->orWhere('ci.charge_type', 'voluntary')
                    ->orWhere('ci.category', 'donation');
            })
            ->whereRaw('COALESCE(pa.allocated_total, 0) = 0')
            ->selectRaw('
                payments.id as payment_id,
                payments.payment_reference,
                payments.posting_date as entry_date,
                payments.payment_method,
                ci.name as collection_name,
                payments.amount as amount
            ')
            ->orderBy('payments.posting_date')
            ->orderBy('payments.id')
            ->get();

        $benefitDisbursements = DB::table('member_benefit_disbursements as mbd')
            ->join('collection_items as ci', 'ci.id', '=', 'mbd.collection_item_id')
            ->where('mbd.member_id', $member->id)
            ->where('mbd.status', 'posted')
            ->selectRaw('
                mbd.id as disbursement_id,
                mbd.reference,
                mbd.disbursed_date as entry_date,
                mbd.disbursed_amount as amount,
                ci.name as collection_name
            ')
            ->orderBy('mbd.disbursed_date')
            ->orderBy('mbd.id')
            ->get();

        $ledger = collect();
        foreach ($charges as $charge) {
            $ledger->push((object)[
                'entry_date' => $charge->due_date ?? $charge->charge_date,
                'type' => 'debit',
                'sort_order' => 10,
                'affects_outstanding' => true,
                'reference' => $charge->charge_reference,
                'description' => 'Charge: ' . $charge->collection_name,
                'debit' => (float)$charge->charge_amount,
                'credit' => 0.0,
            ]);
        }
        foreach ($allocations as $allocation) {
            $allocatedCollections = trim((string)($allocation->allocated_collections ?? ''));
            $ledger->push((object)[
                'entry_date' => $allocation->entry_date,
                'type' => 'credit',
                'sort_order' => 20,
                'affects_outstanding' => true,
                'reference' => $allocation->payment_reference,
                'description' => 'Payment Allocation (' . str_replace('_', ' ', (string)$allocation->payment_method) . ')' . ($allocatedCollections !== '' ? ' - ' . $allocatedCollections : ''),
                'debit' => 0.0,
                'credit' => (float)$allocation->amount,
            ]);
        }
        foreach ($unallocatedPayments as $payment) {
            $ledger->push((object)[
                'entry_date' => $payment->entry_date,
                'type' => 'credit',
                'sort_order' => 30,
                'affects_outstanding' => false,
                'reference' => $payment->payment_reference,
                'description' => 'Unallocated Payment (' . str_replace('_', ' ', (string)$payment->payment_method) . ') - not applied to dues',
                'debit' => 0.0,
                'credit' => (float)$payment->amount,
            ]);
        }
        foreach ($voluntaryPayments as $payment) {
            $ledger->push((object)[
                'entry_date' => $payment->entry_date,
                'type' => 'credit',
                'sort_order' => 40,
                'affects_outstanding' => false,
                'reference' => $payment->payment_reference,
                'description' => 'Voluntary Contribution (' . str_replace('_', ' ', (string)$payment->payment_method) . ') - ' . $payment->collection_name . ' (not applied to dues)',
                'debit' => 0.0,
                'credit' => (float)$payment->amount,
            ]);
        }
        foreach ($benefitDisbursements as $disbursement) {
            $ledger->push((object)[
                'entry_date' => $disbursement->entry_date,
                'type' => 'credit',
                'sort_order' => 50,
                'affects_outstanding' => false,
                'reference' => $disbursement->reference,
                'description' => 'Benefit Disbursement - ' . $disbursement->collection_name . ' (informational)',
                'debit' => 0.0,
                'credit' => (float)$disbursement->amount,
            ]);
        }

        $statement = $ledger
            ->sortBy([
                ['entry_date', 'asc'],
                ['sort_order', 'asc'],
                ['reference', 'asc'],
            ])
            ->values();

        $runningOutstanding = 0.0;
        $statement = $statement->map(function ($row) use (&$runningOutstanding) {
            if ((bool)($row->affects_outstanding ?? false)) {
                $runningOutstanding += ((float)$row->debit - (float)$row->credit);
            }
            $row->running_balance = max(0.0, $runningOutstanding);
            return $row;
        });

        $recentPayments = Payment::query()
            ->where('member_id', $member->id)
            ->where('status', 'posted')
            ->orderByDesc('posting_date')
            ->orderByDesc('id')
            ->limit(8)
            ->get(['payment_reference', 'payment_method', 'amount', 'posting_date']);

        $auditTrail = AuditLog::query()
            ->where('entity_type', 'Member')
            ->where('entity_id', $member->id)
            ->orderByDesc('id')
            ->limit(10)
            ->get(['action', 'change_summary', 'created_at', 'actor_role', 'status']);

        $summary = [
            'total_expected' => (float)($balance->total_expected ?? 0),
            'total_paid' => (float)($balance->total_paid ?? 0),
            'outstanding_balance' => (float)($balance->outstanding_balance ?? 0),
            'statement_rows' => (int)$statement->count(),
            'benefits_received_total' => (float)$benefitDisbursements->sum('amount'),
            'benefits_received_count' => (int)$benefitDisbursements->count(),
            'rating_score' => (float)$memberRating->score,
            'rating_band' => (string)$memberRating->band,
            'rating_eligible_for_benefit' => (bool)$memberRating->eligible_for_benefit,
            'rating_minimum_required' => (float)$memberRating->minimum_required_score,
        ];

        $voluntarySummary = DB::table('payments as p')
            ->join('collection_items as ci', 'ci.id', '=', 'p.collection_item_id')
            ->leftJoin('collection_categories as cc', 'cc.id', '=', 'ci.collection_category_id')
            ->where('p.member_id', $member->id)
            ->where('p.status', 'posted')
            ->where(function ($query): void {
                $query->where('cc.payment_mode', 'voluntary')
                    ->orWhere('ci.charge_type', 'voluntary')
                    ->orWhere('ci.category', 'donation');
            })
            ->selectRaw('
                COUNT(*) as payment_count,
                COALESCE(SUM(p.amount),0) as total_amount,
                MAX(p.posting_date) as last_paid_at
            ')
            ->first();

        $donorWindowStart = now()->copy()->subMonths(12)->startOfDay()->toDateString();
        $voluntaryRecentSummary = DB::table('payments as p')
            ->join('collection_items as ci', 'ci.id', '=', 'p.collection_item_id')
            ->leftJoin('collection_categories as cc', 'cc.id', '=', 'ci.collection_category_id')
            ->where('p.member_id', $member->id)
            ->where('p.status', 'posted')
            ->whereDate('p.posting_date', '>=', $donorWindowStart)
            ->where(function ($query): void {
                $query->where('cc.payment_mode', 'voluntary')
                    ->orWhere('ci.charge_type', 'voluntary')
                    ->orWhere('ci.category', 'donation');
            })
            ->selectRaw('COUNT(*) as payment_count_recent, COALESCE(SUM(p.amount),0) as total_amount_recent')
            ->first();

        $voluntarySkipRecentCount = (int) DB::table('member_voluntary_actions')
            ->where('member_id', $member->id)
            ->where('action', 'skipped')
            ->whereDate('actioned_at', '>=', $donorWindowStart)
            ->count();

        $tier = $this->resolveDonorTier(
            (int)($voluntaryRecentSummary->payment_count_recent ?? 0),
            (float)($voluntaryRecentSummary->total_amount_recent ?? 0),
            $voluntarySkipRecentCount,
            (int)($voluntarySummary->payment_count ?? 0),
            (float)($voluntarySummary->total_amount ?? 0)
        );

        $voluntaryData = [
            'payment_count' => (int)($voluntarySummary->payment_count ?? 0),
            'total_amount' => (float)($voluntarySummary->total_amount ?? 0),
            'last_paid_at' => $voluntarySummary->last_paid_at,
            'payment_count_recent' => (int)($voluntaryRecentSummary->payment_count_recent ?? 0),
            'total_amount_recent' => (float)($voluntaryRecentSummary->total_amount_recent ?? 0),
            'skipped_count_recent' => $voluntarySkipRecentCount,
            'donor_activity_score' => (float)($tier['score'] ?? 0),
            'tier' => $tier['tier'],
            'label' => $tier['label'],
        ];

        $ratingData = [
            'score' => (float)$memberRating->score,
            'band' => (string)$memberRating->band,
            'eligible_for_benefit' => (bool)$memberRating->eligible_for_benefit,
            'minimum_required_score' => (float)$memberRating->minimum_required_score,
            'metrics' => (array)($memberRating->metrics ?? []),
            'as_of_date' => $memberRating->as_of_date?->toDateString(),
        ];

        return [
            'summary' => $summary,
            'voluntaryData' => $voluntaryData,
            'ratingData' => $ratingData,
            'statement' => $statement,
            'benefitDisbursements' => $benefitDisbursements,
            'recentPayments' => $recentPayments,
            'auditTrail' => $auditTrail,
        ];
    }
}
