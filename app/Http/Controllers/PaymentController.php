<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CollectionItem;
use App\Models\Member;
use App\Models\MemberVoluntaryAction;
use App\Models\Payment;
use App\Services\ArrearsEngineService;
use App\Services\MemberRatingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = [
            'date_from' => (string)$request->query('date_from', ''),
            'date_to' => (string)$request->query('date_to', ''),
            'payment_method' => (string)$request->query('payment_method', ''),
            'member_id' => (int)$request->query('member_id', 0),
            'collection_item_id' => (int)$request->query('collection_item_id', 0),
            'contribution_type' => in_array((string)$request->query('contribution_type', ''), ['compulsory', 'voluntary'], true)
                ? (string)$request->query('contribution_type')
                : '',
        ];

        $paymentsQuery = Payment::query()
            ->with([
                'member:id,member_code,first_name,last_name',
                'collectionItem:id,collection_category_id,name,charge_type,category',
                'collectionItem.categoryConfig:id,name,payment_mode',
            ])
            ->orderByDesc('id');

        if ($filters['date_from'] !== '') {
            $paymentsQuery->whereDate('posting_date', '>=', $filters['date_from']);
        }
        if ($filters['date_to'] !== '') {
            $paymentsQuery->whereDate('posting_date', '<=', $filters['date_to']);
        }
        if ($filters['payment_method'] !== '') {
            $paymentsQuery->where('payment_method', $filters['payment_method']);
        }
        if ($filters['member_id'] > 0) {
            $paymentsQuery->where('member_id', $filters['member_id']);
        }
        if ($filters['collection_item_id'] > 0) {
            $paymentsQuery->where('collection_item_id', $filters['collection_item_id']);
        }
        $this->applyContributionTypeFilter($paymentsQuery, $filters['contribution_type']);

        $filteredCount = (int) (clone $paymentsQuery)->count();
        $filteredAmount = (float) (clone $paymentsQuery)->sum('amount');

        $payments = $paymentsQuery
            ->paginate(20)
            ->withQueryString();

        $methodBreakdownQuery = Payment::query()
            ->selectRaw('payment_method, COUNT(*) as payment_count, COALESCE(SUM(amount),0) as total_amount')
            ->where('status', 'posted')
            ->when($filters['date_from'] !== '', fn ($q) => $q->whereDate('posting_date', '>=', $filters['date_from']))
            ->when($filters['date_to'] !== '', fn ($q) => $q->whereDate('posting_date', '<=', $filters['date_to']))
            ->when($filters['member_id'] > 0, fn ($q) => $q->where('member_id', $filters['member_id']))
            ->when($filters['collection_item_id'] > 0, fn ($q) => $q->where('collection_item_id', $filters['collection_item_id']))
            ->groupBy('payment_method')
            ->orderByDesc('total_amount');
        $this->applyContributionTypeFilter($methodBreakdownQuery, $filters['contribution_type']);
        $methodBreakdown = $methodBreakdownQuery->get();

        $members = Member::query()
            ->where('status', 'active')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'member_code', 'first_name', 'last_name']);

        $collections = CollectionItem::query()
            ->with('categoryConfig:id,payment_mode')
            ->where('status', 'active')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'amount',
                'is_required',
                'charge_type',
                'category',
                'collection_category_id',
            ]);

        $today = now()->toDateString();
        $monthStart = now()->copy()->startOfMonth()->toDateString();
        $monthEnd = now()->copy()->endOfMonth()->toDateString();

        $stats = [
            'filtered_count' => $filteredCount,
            'filtered_amount' => $filteredAmount,
            'today_amount' => (float) Payment::query()
                ->where('status', 'posted')
                ->whereDate('posting_date', $today)
                ->sum('amount'),
            'month_amount' => (float) Payment::query()
                ->where('status', 'posted')
                ->whereBetween('posting_date', [$monthStart, $monthEnd])
                ->sum('amount'),
            'voluntary_amount' => (float) DB::table('payments as p')
                ->join('collection_items as ci', 'ci.id', '=', 'p.collection_item_id')
                ->leftJoin('collection_categories as cc', 'cc.id', '=', 'ci.collection_category_id')
                ->where('p.status', 'posted')
                ->where(function ($query): void {
                    $query->where('cc.payment_mode', 'voluntary')
                        ->orWhere('ci.charge_type', 'voluntary')
                        ->orWhere('ci.category', 'donation');
                })
                ->selectRaw('COALESCE(SUM(p.amount),0) as value')
                ->value('value'),
            'unallocated_amount' => (float) DB::table('payments as p')
                ->leftJoin(
                    DB::raw('(SELECT payment_id, SUM(allocated_amount) as allocated_total FROM payment_allocations GROUP BY payment_id) pa'),
                    'pa.payment_id',
                    '=',
                    'p.id'
                )
                ->leftJoin('collection_items as ci', 'ci.id', '=', 'p.collection_item_id')
                ->leftJoin('collection_categories as cc', 'cc.id', '=', 'ci.collection_category_id')
                ->where('p.status', 'posted')
                ->where(function ($query): void {
                    $query->whereNull('p.collection_item_id')
                        ->orWhere(function ($query): void {
                            $query->where(function ($qq): void {
                                $qq->where('cc.payment_mode', '!=', 'voluntary')
                                    ->orWhereNull('cc.payment_mode');
                            })->where('ci.charge_type', '!=', 'voluntary')
                                ->where('ci.category', '!=', 'donation');
                        });
                })
                ->selectRaw('COALESCE(SUM(GREATEST(p.amount - COALESCE(pa.allocated_total, 0), 0)),0) as value')
                ->value('value'),
        ];

        return view('payments.index', compact('payments', 'members', 'collections', 'filters', 'stats', 'methodBreakdown'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'collection_item_id' => ['nullable', 'exists:collection_items,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,mobile_money,bank_transfer,ussd,card'],
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ((float)$validated['amount'] <= 0) {
            return redirect()
                ->route('payments.index')
                ->withErrors(['amount' => 'Amount must be greater than zero.'])
                ->withInput();
        }

        $paymentDate = $validated['payment_date'] . ' 00:00:00';
        $ref = 'PAY-' . strtoupper(Str::random(10));
        $idem = 'IDM-' . strtoupper(Str::random(12));

        $payment = Payment::create([
            'association_id' => 1,
            'payment_reference' => $ref,
            'member_id' => $validated['member_id'],
            'collection_item_id' => $validated['collection_item_id'] ?? null,
            'amount' => $validated['amount'],
            'currency_code' => 'GHS',
            'payment_method' => $validated['payment_method'],
            'source' => 'manual_entry',
            'transaction_reference' => $ref,
            'idempotency_key' => $idem,
            'payment_date' => $paymentDate,
            'posting_date' => $validated['payment_date'],
            'status' => 'posted',
            'notes' => $validated['notes'] ?? null,
        ]);

        /** @var ArrearsEngineService $arrears */
        $arrears = app(ArrearsEngineService::class);
        $isVoluntary = false;
        $resolvedSkippedActionCount = 0;
        $item = null;
        if ($payment->collection_item_id) {
            $item = CollectionItem::query()
                ->with('categoryConfig:id,payment_mode')
                ->find((int)$payment->collection_item_id);
            $isVoluntary = $item
                && (
                    strtolower((string)$item->categoryConfig?->payment_mode) === 'voluntary'
                    ||
                    strtolower((string)$item->charge_type) === 'voluntary'
                    || strtolower((string)$item->category) === 'donation'
                );
        }

        if ($isVoluntary) {
            if ($item instanceof CollectionItem) {
                $cycleKey = $this->buildContributionCycleKey(
                    frequency: (string)$item->frequency,
                    startDate: Carbon::parse((string)$item->start_date),
                    asOf: Carbon::parse((string)$validated['payment_date'])
                );

                $resolvedSkippedActionCount = (int) MemberVoluntaryAction::query()
                    ->where('association_id', (int)$payment->association_id)
                    ->where('member_id', (int)$payment->member_id)
                    ->where('collection_item_id', (int)$item->id)
                    ->where('action', 'skipped')
                    ->where('cycle_key', $cycleKey)
                    ->delete();
            }

            $allocation = [
                'allocated' => 0.0,
                'allocated_now' => 0.0,
                'unallocated' => 0.0,
                'count' => 0,
                'mode' => 'voluntary',
            ];
        } else {
            $arrears->generateCharges(
                asOfDate: Carbon::parse((string)$validated['payment_date'])->endOfDay(),
                associationId: 1,
                collectionItemId: $payment->collection_item_id ? (int)$payment->collection_item_id : null,
                memberIds: [(int)$payment->member_id]
            );
            $allocation = $arrears->allocatePayment($payment);
        }

        $ratingData = null;
        try {
            /** @var MemberRatingService $ratings */
            $ratings = app(MemberRatingService::class);
            $rating = $ratings->recalculateForMember((int)$payment->member_id, (int)$payment->association_id);
            $ratingData = [
                'score' => (float)$rating->score,
                'eligible_for_benefit' => (bool)$rating->eligible_for_benefit,
                'band' => (string)$rating->band,
            ];
        } catch (\Throwable $e) {
            Log::warning('Member rating recalculation failed after payment recording.', [
                'payment_id' => (int)$payment->id,
                'member_id' => (int)$payment->member_id,
                'association_id' => (int)$payment->association_id,
                'error' => $e->getMessage(),
            ]);
        }

        AuditLog::create([
            'association_id' => 1,
            'actor_user_id' => $request->user()?->id,
            'actor_role' => $request->user()?->roles()->value('name'),
            'action' => 'PAYMENT_RECORDED',
            'entity_type' => 'Payment',
            'entity_id' => $payment->id,
            'change_summary' => 'Payment recorded manually',
            'after_data' => array_merge($payment->toArray(), [
                'allocation' => $allocation,
                'resolved_skipped_actions' => $resolvedSkippedActionCount,
                'member_rating' => $ratingData,
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => (string)$request->userAgent(),
            'status' => 'success',
        ]);

        $message = $isVoluntary
            ? 'Voluntary contribution recorded successfully. It is tracked under donations/voluntary contributions and does not create arrears.'
            : sprintf(
                'Payment recorded. Allocated: %.2f, Unallocated: %.2f.',
                (float)($allocation['allocated_now'] ?? 0),
                (float)($allocation['unallocated'] ?? 0)
            );
        if ($resolvedSkippedActionCount > 0) {
            $message .= ' Previous skip for this cycle was reversed automatically.';
        }
        if (is_array($ratingData)) {
            $message .= sprintf(
                ' Member rating: %.1f (%s).',
                (float)$ratingData['score'],
                ((bool)$ratingData['eligible_for_benefit']) ? 'benefit-eligible' : 'not eligible'
            );
        } else {
            $message .= ' Member rating update is temporarily unavailable.';
        }

        return redirect()
            ->route('payments.index')
            ->with('success', $message);
    }

    public function amountSuggestion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'collection_item_id' => ['required', 'integer', 'exists:collection_items,id'],
            'payment_date' => ['nullable', 'date'],
        ]);

        $memberId = (int)$validated['member_id'];
        $collectionItemId = (int)$validated['collection_item_id'];
        $asOfDate = !empty($validated['payment_date'])
            ? Carbon::parse((string)$validated['payment_date'])->endOfDay()
            : now()->endOfDay();

        /** @var CollectionItem $item */
        $item = CollectionItem::query()
            ->with('categoryConfig:id,payment_mode')
            ->findOrFail($collectionItemId);

        $isVoluntary = (
            strtolower((string)$item->categoryConfig?->payment_mode) === 'voluntary'
            || strtolower((string)$item->charge_type) === 'voluntary'
            || strtolower((string)$item->category) === 'donation'
        );
        $isRequired = (bool)$item->is_required;
        $defaultAmount = $item->amount !== null ? (float)$item->amount : null;

        if ($isVoluntary) {
            return response()->json([
                'collection_item_id' => $collectionItemId,
                'member_id' => $memberId,
                'is_voluntary' => true,
                'is_required' => $isRequired,
                'default_amount' => $defaultAmount,
                'outstanding_amount' => 0.0,
                'suggested_amount' => null,
                'lock_amount' => false,
                'reason' => 'Voluntary collection uses manual amount entry.',
            ]);
        }

        /** @var ArrearsEngineService $arrears */
        $arrears = app(ArrearsEngineService::class);
        $arrears->generateCharges(
            asOfDate: $asOfDate,
            associationId: 1,
            collectionItemId: $collectionItemId,
            memberIds: [$memberId]
        );

        $allocationSub = DB::table('payment_allocations')
            ->selectRaw('member_charge_id, SUM(allocated_amount) as allocated_amount')
            ->groupBy('member_charge_id');

        $outstandingAmount = (float) DB::table('member_charges as mc')
            ->leftJoinSub($allocationSub, 'pa', function ($join): void {
                $join->on('pa.member_charge_id', '=', 'mc.id');
            })
            ->where('mc.member_id', $memberId)
            ->where('mc.collection_item_id', $collectionItemId)
            ->whereIn('mc.status', ['open', 'partial', 'paid'])
            ->selectRaw(
                'COALESCE(SUM(GREATEST((mc.expected_amount + mc.penalty_amount - mc.discount_amount - mc.waived_amount) - COALESCE(pa.allocated_amount, 0), 0)), 0) as outstanding'
            )
            ->value('outstanding');

        $suggestedAmount = 0.0;
        $reason = 'No outstanding balance for this member/collection as of selected date.';
        if ($outstandingAmount > 0) {
            $suggestedAmount = $outstandingAmount;
            $reason = 'Outstanding balance detected for this member/collection.';
        }

        return response()->json([
            'collection_item_id' => $collectionItemId,
            'member_id' => $memberId,
            'is_voluntary' => false,
            'is_required' => $isRequired,
            'default_amount' => $defaultAmount !== null ? round($defaultAmount, 2) : null,
            'outstanding_amount' => round($outstandingAmount, 2),
            'suggested_amount' => round($suggestedAmount, 2),
            'lock_amount' => $isRequired,
            'reason' => $reason,
        ]);
    }

    private function applyContributionTypeFilter($query, string $contributionType): void
    {
        if ($contributionType === 'voluntary') {
            $query->whereHas('collectionItem', function ($q): void {
                $q->where(function ($qq): void {
                    $qq->whereHas('categoryConfig', function ($qcc): void {
                        $qcc->where('payment_mode', 'voluntary');
                    })->orWhere('charge_type', 'voluntary')
                        ->orWhere('category', 'donation');
                });
            });
            return;
        }

        if ($contributionType === 'compulsory') {
            $query->where(function ($q): void {
                $q->whereNull('collection_item_id')
                    ->orWhereHas('collectionItem', function ($qq): void {
                        $qq->where(function ($qMode): void {
                            $qMode->whereHas('categoryConfig', function ($qcc): void {
                                $qcc->where('payment_mode', 'compulsory');
                            })->orWhereDoesntHave('categoryConfig');
                        })->where('charge_type', '!=', 'voluntary')
                            ->where('category', '!=', 'donation');
                    });
            });
        }
    }

    private function buildContributionCycleKey(string $frequency, Carbon $startDate, Carbon $asOf): string
    {
        $frequency = strtolower(trim($frequency));
        $asOf = $asOf->copy()->startOfDay();

        if ($frequency === 'one_time') {
            return 'ONCE';
        }

        if ($frequency === 'yearly') {
            return 'Y-' . $asOf->format('Y');
        }

        if ($frequency === 'quarterly') {
            $quarter = (int)ceil($asOf->month / 3);
            return sprintf('Q-%d-Q%d', $asOf->year, $quarter);
        }

        return 'M-' . $asOf->format('Y-m');
    }
}
