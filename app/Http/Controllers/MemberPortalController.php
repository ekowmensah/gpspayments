<?php

namespace App\Http\Controllers;

use App\Models\CollectionItem;
use App\Models\Member;
use App\Models\MemberVoluntaryAction;
use App\Models\Payment;
use App\Services\MemberRatingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberPortalController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        [$user, $member] = $this->resolveMemberUser($request);

        $payload = $this->buildPortalPayload($member);
        $statementPager = $this->paginateCollection(
            items: $payload['statement'],
            request: $request,
            pageName: 'statement_page',
            perPage: 12
        );

        return view('member-portal.index', array_merge([
            'member' => $member,
            'user' => $user,
            'statementPager' => $statementPager,
        ], $payload));
    }

    public function profile(Request $request): View|RedirectResponse
    {
        [$user, $member] = $this->resolveMemberUser($request);
        $payload = $this->buildPortalPayload($member);

        return view('member-portal.profile', [
            'member' => $member,
            'user' => $user,
            'summary' => $payload['summary'],
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        [$user] = $this->resolveMemberUser($request);

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $storedHash = (string)$user->password_hash;
        $matches = false;
        try {
            $matches = Hash::check((string)$validated['current_password'], $storedHash);
        } catch (\RuntimeException) {
            $matches = password_verify((string)$validated['current_password'], $storedHash);
        }

        if (!$matches) {
            return redirect()
                ->route('member-portal.profile')
                ->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $user->update([
            'password_hash' => Hash::make((string)$validated['new_password']),
        ]);

        return redirect()
            ->route('member-portal.profile')
            ->with('success', 'Password updated successfully.');
    }

    public function statement(Request $request): View|RedirectResponse
    {
        [$user, $member] = $this->resolveMemberUser($request);
        $payload = $this->buildPortalPayload($member);

        $statementPager = $this->paginateCollection(
            items: $payload['statement'],
            request: $request,
            pageName: 'page',
            perPage: 25
        );

        return view('member-portal.statement', [
            'member' => $member,
            'user' => $user,
            'summary' => $payload['summary'],
            'statementPager' => $statementPager,
        ]);
    }

    public function statementPrint(Request $request): View|RedirectResponse
    {
        [, $member] = $this->resolveMemberUser($request);
        $payload = $this->buildPortalPayload($member);

        return view('member-portal.statement-print', [
            'member' => $member,
            'summary' => $payload['summary'],
            'statement' => $payload['statement'],
            'generatedAt' => now(),
        ]);
    }

    public function statementExport(Request $request): StreamedResponse
    {
        [, $member] = $this->resolveMemberUser($request);
        $payload = $this->buildPortalPayload($member);
        $statement = $payload['statement'];

        $filename = sprintf(
            'my_statement_%s_%s.csv',
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

    public function skipVoluntaryContribution(Request $request, CollectionItem $collectionItem): RedirectResponse
    {
        [, $member] = $this->resolveMemberUser($request);

        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        if ((int)$collectionItem->association_id !== (int)$member->association_id) {
            abort(403, 'Unauthorized collection context.');
        }

        if ((string)$collectionItem->status !== 'active') {
            return redirect()
                ->route('member-portal.index')
                ->withErrors(['voluntary' => 'This contribution is not active.']);
        }

        $isVoluntary = false;
        if ($collectionItem->relationLoaded('categoryConfig')) {
            $isVoluntary = strtolower((string)$collectionItem->categoryConfig?->payment_mode) === 'voluntary';
        }
        if (!$isVoluntary) {
            $categoryMode = DB::table('collection_categories')
                ->where('id', (int)$collectionItem->collection_category_id)
                ->value('payment_mode');
            $isVoluntary = strtolower((string)$categoryMode) === 'voluntary'
                || strtolower((string)$collectionItem->charge_type) === 'voluntary'
                || strtolower((string)$collectionItem->category) === 'donation';
        }

        if (!$isVoluntary) {
            return redirect()
                ->route('member-portal.index')
                ->withErrors(['voluntary' => 'This collection is not configured as voluntary.']);
        }

        $isAssigned = DB::table('collection_item_members')
            ->where('collection_item_id', (int)$collectionItem->id)
            ->where('member_id', (int)$member->id)
            ->exists();
        if (!$isAssigned) {
            return redirect()
                ->route('member-portal.index')
                ->withErrors(['voluntary' => 'You are not assigned to this voluntary collection.']);
        }

        $cycle = $this->resolveContributionCycle(
            frequency: (string)$collectionItem->frequency,
            startDate: Carbon::parse((string)$collectionItem->start_date),
            asOf: now()
        );

        MemberVoluntaryAction::query()->updateOrCreate(
            [
                'member_id' => (int)$member->id,
                'collection_item_id' => (int)$collectionItem->id,
                'cycle_key' => (string)$cycle['key'],
            ],
            [
                'association_id' => (int)$member->association_id,
                'action' => 'skipped',
                'notes' => $validated['notes'] ?? null,
                'actioned_at' => now(),
            ]
        );

        /** @var MemberRatingService $ratings */
        $ratings = app(MemberRatingService::class);
        $ratings->recalculateForMember((int)$member->id, (int)$member->association_id);

        return redirect()
            ->route('member-portal.index')
            ->with('success', sprintf('Voluntary contribution "%s" skipped for %s.', (string)$collectionItem->name, (string)$cycle['label']));
    }

    public function unskipVoluntaryContribution(Request $request, CollectionItem $collectionItem): RedirectResponse
    {
        [, $member] = $this->resolveMemberUser($request);

        $validated = $request->validate([
            'cycle_key' => ['nullable', 'string', 'max:50'],
        ]);

        if ((int)$collectionItem->association_id !== (int)$member->association_id) {
            abort(403, 'Unauthorized collection context.');
        }

        $isVoluntary = false;
        if ($collectionItem->relationLoaded('categoryConfig')) {
            $isVoluntary = strtolower((string)$collectionItem->categoryConfig?->payment_mode) === 'voluntary';
        }
        if (!$isVoluntary) {
            $categoryMode = DB::table('collection_categories')
                ->where('id', (int)$collectionItem->collection_category_id)
                ->value('payment_mode');
            $isVoluntary = strtolower((string)$categoryMode) === 'voluntary'
                || strtolower((string)$collectionItem->charge_type) === 'voluntary'
                || strtolower((string)$collectionItem->category) === 'donation';
        }

        if (!$isVoluntary) {
            return redirect()
                ->route('member-portal.index')
                ->withErrors(['voluntary' => 'This collection is not configured as voluntary.']);
        }

        $cycle = $this->resolveContributionCycle(
            frequency: (string)$collectionItem->frequency,
            startDate: Carbon::parse((string)$collectionItem->start_date),
            asOf: now()
        );
        $cycleKey = trim((string)($validated['cycle_key'] ?? ''));
        if ($cycleKey === '') {
            $cycleKey = (string)$cycle['key'];
        }

        $deleted = MemberVoluntaryAction::query()
            ->where('member_id', (int)$member->id)
            ->where('collection_item_id', (int)$collectionItem->id)
            ->where('action', 'skipped')
            ->where('cycle_key', $cycleKey)
            ->delete();

        if ($deleted <= 0) {
            return redirect()
                ->route('member-portal.index')
                ->withErrors(['voluntary' => 'No skipped voluntary contribution found for reversal.']);
        }

        /** @var MemberRatingService $ratings */
        $ratings = app(MemberRatingService::class);
        $ratings->recalculateForMember((int)$member->id, (int)$member->association_id);

        return redirect()
            ->route('member-portal.index')
            ->with('success', sprintf('Skip reversed for "%s" (%s).', (string)$collectionItem->name, $cycleKey));
    }

    private function resolveMemberUser(Request $request): array
    {
        $user = $request->user();
        if (!$user) {
            abort(403, 'Unauthorized.');
        }

        /** @var Member|null $member */
        $member = $user->member;
        if (!$member) {
            abort(403, 'Your user account is not linked to a member profile.');
        }

        return [$user, $member];
    }

    private function paginateCollection(Collection $items, Request $request, string $pageName = 'page', int $perPage = 15): LengthAwarePaginator
    {
        $page = max(1, (int)$request->query($pageName, 1));
        $total = $items->count();
        $results = $items->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            items: $results,
            total: $total,
            perPage: $perPage,
            currentPage: $page,
            options: [
                'path' => $request->url(),
                'pageName' => $pageName,
                'query' => $request->query(),
            ]
        );
    }

    private function buildPortalPayload(Member $member): array
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
                COALESCE(pa.allocated_amount, 0) as paid_amount,
                GREATEST((mc.expected_amount + mc.penalty_amount - mc.discount_amount - mc.waived_amount) - COALESCE(pa.allocated_amount,0), 0) as outstanding_amount,
                mc.status
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

        $upcomingCharges = $charges
            ->filter(fn ($charge) => in_array((string)$charge->status, ['open', 'partial'], true) && (float)$charge->outstanding_amount > 0)
            ->take(8)
            ->values();

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
            ->selectRaw('COUNT(*) as payment_count, COALESCE(SUM(p.amount),0) as total_amount, MAX(p.posting_date) as last_paid_at')
            ->first();

        $voluntaryCollections = DB::table('collection_item_members as cim')
            ->join('collection_items as ci', 'ci.id', '=', 'cim.collection_item_id')
            ->leftJoin('collection_categories as cc', 'cc.id', '=', 'ci.collection_category_id')
            ->where('cim.member_id', $member->id)
            ->where('ci.status', 'active')
            ->whereDate('ci.start_date', '<=', now()->toDateString())
            ->where(function ($query): void {
                $query->whereNull('ci.end_date')
                    ->orWhereDate('ci.end_date', '>=', now()->toDateString());
            })
            ->where(function ($query): void {
                $query->where('cc.payment_mode', 'voluntary')
                    ->orWhere('ci.charge_type', 'voluntary')
                    ->orWhere('ci.category', 'donation');
            })
            ->selectRaw('
                ci.id as collection_item_id,
                ci.name as collection_name,
                ci.frequency,
                ci.amount,
                ci.currency_code,
                ci.start_date,
                ci.end_date,
                cc.name as category_name
            ')
            ->orderBy('ci.name')
            ->get();

        $voluntaryCollectionIds = $voluntaryCollections
            ->pluck('collection_item_id')
            ->map(fn ($id) => (int)$id)
            ->values()
            ->all();

        $voluntaryPostedPaymentsByCollection = collect();
        $skippedVoluntaryActionsByCollection = collect();
        if (!empty($voluntaryCollectionIds)) {
            $voluntaryPostedPaymentsByCollection = Payment::query()
                ->where('member_id', $member->id)
                ->where('status', 'posted')
                ->whereIn('collection_item_id', $voluntaryCollectionIds)
                ->get(['collection_item_id', 'posting_date', 'amount'])
                ->groupBy('collection_item_id');

            $skippedVoluntaryActionsByCollection = MemberVoluntaryAction::query()
                ->where('member_id', $member->id)
                ->where('action', 'skipped')
                ->whereIn('collection_item_id', $voluntaryCollectionIds)
                ->get(['collection_item_id', 'cycle_key', 'actioned_at'])
                ->groupBy('collection_item_id');
        }

        $pendingVoluntaryContributions = collect();
        $skippedVoluntaryContributions = collect();
        foreach ($voluntaryCollections as $collection) {
            $collectionId = (int)$collection->collection_item_id;
            $frequency = (string)$collection->frequency;
            $startDate = Carbon::parse((string)$collection->start_date);
            $cycle = $this->resolveContributionCycle($frequency, $startDate, now());

            $paymentsForCollection = $voluntaryPostedPaymentsByCollection->get($collectionId, collect());
            $hasPaidInCycle = $this->hasVoluntaryPaymentInCycle(
                frequency: $frequency,
                cycleStart: $cycle['start'],
                cycleEnd: $cycle['end'],
                payments: $paymentsForCollection
            );
            if ($hasPaidInCycle) {
                continue;
            }

            $skipActionsForCollection = $skippedVoluntaryActionsByCollection->get($collectionId, collect());
            $isSkippedInCycle = $skipActionsForCollection
                ->contains(fn ($action) => (string)$action->cycle_key === (string)$cycle['key']);
            if ($isSkippedInCycle) {
                $skippedAction = $skipActionsForCollection
                    ->first(fn ($action) => (string)$action->cycle_key === (string)$cycle['key']);
                $skippedVoluntaryContributions->push((object)[
                    'collection_item_id' => $collectionId,
                    'collection_name' => (string)$collection->collection_name,
                    'category_name' => (string)($collection->category_name ?: 'Voluntary'),
                    'frequency' => $frequency,
                    'suggested_amount' => (float)($collection->amount ?? 0),
                    'currency_code' => (string)($collection->currency_code ?: 'GHS'),
                    'cycle_label' => (string)$cycle['label'],
                    'cycle_key' => (string)$cycle['key'],
                    'actioned_at' => $skippedAction?->actioned_at,
                ]);
                continue;
            }

            $pendingVoluntaryContributions->push((object)[
                'collection_item_id' => $collectionId,
                'collection_name' => (string)$collection->collection_name,
                'category_name' => (string)($collection->category_name ?: 'Voluntary'),
                'frequency' => $frequency,
                'suggested_amount' => (float)($collection->amount ?? 0),
                'currency_code' => (string)($collection->currency_code ?: 'GHS'),
                'cycle_label' => (string)$cycle['label'],
                'cycle_key' => (string)$cycle['key'],
            ]);
        }

        $summary = [
            'total_expected' => (float)($balance->total_expected ?? 0),
            'total_paid' => (float)($balance->total_paid ?? 0),
            'outstanding_balance' => (float)($balance->outstanding_balance ?? 0),
            'statement_rows' => (int)$statement->count(),
            'voluntary_total' => (float)($voluntarySummary->total_amount ?? 0),
            'voluntary_count' => (int)($voluntarySummary->payment_count ?? 0),
            'last_voluntary_date' => $voluntarySummary->last_paid_at ?? null,
            'pending_voluntary_count' => (int)$pendingVoluntaryContributions->count(),
            'skipped_voluntary_count' => (int)$skippedVoluntaryContributions->count(),
            'benefits_received_total' => (float)$benefitDisbursements->sum('amount'),
            'benefits_received_count' => (int)$benefitDisbursements->count(),
            'rating_score' => (float)$memberRating->score,
            'rating_band' => (string)$memberRating->band,
            'rating_eligible_for_benefit' => (bool)$memberRating->eligible_for_benefit,
            'rating_minimum_required' => (float)$memberRating->minimum_required_score,
            'rating_metrics' => (array)($memberRating->metrics ?? []),
        ];

        $notifications = [];
        $today = now()->toDateString();
        $dueSoon = $charges
            ->filter(function ($charge) use ($today) {
                return in_array((string)$charge->status, ['open', 'partial'], true)
                    && (float)$charge->outstanding_amount > 0
                    && (string)$charge->due_date >= $today
                    && Carbon::parse((string)$charge->due_date)->lte(now()->copy()->addDays(7));
            })
            ->sortBy('due_date')
            ->values();

        $overdue = $charges
            ->filter(function ($charge) use ($today) {
                return in_array((string)$charge->status, ['open', 'partial'], true)
                    && (float)$charge->outstanding_amount > 0
                    && (string)$charge->due_date < $today;
            })
            ->values();

        if ($overdue->count() > 0) {
            $notifications[] = [
                'type' => 'danger',
                'icon' => 'fas fa-exclamation-circle',
                'title' => 'Overdue balance requires attention',
                'message' => sprintf(
                    '%d overdue charge(s), total %.2f outstanding.',
                    (int)$overdue->count(),
                    (float)$overdue->sum(fn ($c) => (float)$c->outstanding_amount)
                ),
            ];
        }

        if ($dueSoon->count() > 0) {
            $nextDue = $dueSoon->first();
            $notifications[] = [
                'type' => 'warning',
                'icon' => 'fas fa-calendar-alt',
                'title' => 'Upcoming payment due soon',
                'message' => sprintf(
                    '%s due on %s (%.2f outstanding).',
                    (string)$nextDue->collection_name,
                    Carbon::parse((string)$nextDue->due_date)->format('Y-m-d'),
                    (float)$nextDue->outstanding_amount
                ),
            ];
        }

        $recentPosted = $recentPayments
            ->filter(function ($p) {
                if (!$p->posting_date) {
                    return false;
                }
                return Carbon::parse((string)$p->posting_date)->gte(now()->copy()->subDays(7));
            })
            ->count();
        if ($recentPosted > 0) {
            $notifications[] = [
                'type' => 'success',
                'icon' => 'fas fa-check-circle',
                'title' => 'Recent payments posted',
                'message' => sprintf('%d payment(s) were posted in the last 7 days.', (int)$recentPosted),
            ];
        }

        if ($summary['voluntary_count'] > 0) {
            $notifications[] = [
                'type' => 'info',
                'icon' => 'fas fa-hands-helping',
                'title' => 'Voluntary contributions recognized',
                'message' => sprintf(
                    'Thank you for %d voluntary contribution(s) totaling %.2f.',
                    (int)$summary['voluntary_count'],
                    (float)$summary['voluntary_total']
                ),
            ];
        }

        if ($summary['pending_voluntary_count'] > 0) {
            $notifications[] = [
                'type' => 'secondary',
                'icon' => 'fas fa-hand-holding-usd',
                'title' => 'Pending voluntary contributions',
                'message' => sprintf(
                    'You have %d pending voluntary contribution request(s). You can pay or skip for this cycle.',
                    (int)$summary['pending_voluntary_count']
                ),
            ];
        }

        if ($summary['skipped_voluntary_count'] > 0) {
            $notifications[] = [
                'type' => 'secondary',
                'icon' => 'fas fa-undo',
                'title' => 'Skipped voluntary items can be reversed',
                'message' => sprintf(
                    'You have %d skipped voluntary item(s) this cycle. You can reverse a skip any time before paying.',
                    (int)$summary['skipped_voluntary_count']
                ),
            ];
        }

        if ($summary['benefits_received_count'] > 0) {
            $notifications[] = [
                'type' => 'primary',
                'icon' => 'fas fa-hand-holding-heart',
                'title' => 'Benefits Received',
                'message' => sprintf(
                    'You have received %d benefit disbursement(s), totaling %.2f.',
                    (int)$summary['benefits_received_count'],
                    (float)$summary['benefits_received_total']
                ),
            ];
        }

        if (!(bool)($summary['rating_eligible_for_benefit'] ?? false)) {
            $minScore = (float)($summary['rating_minimum_required'] ?? 80);
            $notifications[] = [
                'type' => 'danger',
                'icon' => 'fas fa-shield-alt',
                'title' => 'Benefit eligibility is currently locked',
                'message' => sprintf(
                    'Your rating is %.1f. Minimum required rating is %.1f.',
                    (float)($summary['rating_score'] ?? 0),
                    $minScore
                ),
            ];
        }

        return [
            'summary' => $summary,
            'statement' => $statement,
            'benefitDisbursements' => $benefitDisbursements,
            'pendingVoluntaryContributions' => $pendingVoluntaryContributions,
            'skippedVoluntaryContributions' => $skippedVoluntaryContributions,
            'recentPayments' => $recentPayments,
            'upcomingCharges' => $upcomingCharges,
            'notifications' => $notifications,
        ];
    }

    private function resolveContributionCycle(string $frequency, Carbon $startDate, Carbon $asOf): array
    {
        $frequency = strtolower(trim($frequency));
        $asOf = $asOf->copy()->startOfDay();

        if ($frequency === 'one_time') {
            return [
                'key' => 'ONCE',
                'label' => 'One-time',
                'start' => $startDate->copy()->startOfDay(),
                'end' => null,
            ];
        }

        if ($frequency === 'yearly') {
            return [
                'key' => 'Y-' . $asOf->format('Y'),
                'label' => $asOf->format('Y'),
                'start' => $asOf->copy()->startOfYear(),
                'end' => $asOf->copy()->endOfYear(),
            ];
        }

        if ($frequency === 'quarterly') {
            $quarter = (int)ceil($asOf->month / 3);
            $quarterStartMonth = (($quarter - 1) * 3) + 1;
            $start = Carbon::createFromDate($asOf->year, $quarterStartMonth, 1)->startOfDay();
            $end = $start->copy()->addMonths(3)->subDay()->endOfDay();
            return [
                'key' => sprintf('Q-%d-Q%d', $asOf->year, $quarter),
                'label' => sprintf('Q%d %d', $quarter, $asOf->year),
                'start' => $start,
                'end' => $end,
            ];
        }

        // monthly and custom default to monthly cycle
        return [
            'key' => 'M-' . $asOf->format('Y-m'),
            'label' => $asOf->format('M Y'),
            'start' => $asOf->copy()->startOfMonth(),
            'end' => $asOf->copy()->endOfMonth(),
        ];
    }

    private function hasVoluntaryPaymentInCycle(string $frequency, Carbon $cycleStart, ?Carbon $cycleEnd, Collection $payments): bool
    {
        $frequency = strtolower(trim($frequency));
        if ($payments->isEmpty()) {
            return false;
        }

        if ($frequency === 'one_time') {
            return $payments->isNotEmpty();
        }

        foreach ($payments as $payment) {
            if (!$payment->posting_date) {
                continue;
            }
            $postingDate = Carbon::parse((string)$payment->posting_date)->startOfDay();
            if ($postingDate->lt($cycleStart)) {
                continue;
            }
            if ($cycleEnd && $postingDate->gt($cycleEnd)) {
                continue;
            }
            return true;
        }

        return false;
    }
}
