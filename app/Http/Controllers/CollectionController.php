<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CollectionCategory;
use App\Models\CollectionItem;
use App\Models\Member;
use App\Models\MemberBenefitDisbursement;
use App\Services\ArrearsEngineService;
use App\Services\MemberRatingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CollectionController extends Controller
{
    public function index(): View
    {
        $collections = CollectionItem::query()
            ->select('collection_items.*')
            ->selectSub(
                DB::table('member_charges')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('member_charges.collection_item_id', 'collection_items.id'),
                'member_charges_count'
            )
            ->with('categoryConfig:id,name,payment_mode')
            ->with('beneficiary:id,member_code,first_name,last_name')
            ->withCount('members')
            ->withCount('payments')
            ->withCount('benefitDisbursements')
            ->withSum([
                'payments as collected_total' => function ($query): void {
                    $query->where('status', 'posted');
                }
            ], 'amount')
            ->withSum([
                'benefitDisbursements as disbursed_total' => function ($query): void {
                    $query->where('status', 'posted');
                }
            ], 'disbursed_amount')
            ->orderByDesc('id')
            ->paginate(15);
        $collections->getCollection()->transform(function (CollectionItem $item): CollectionItem {
            $collected = (float)($item->collected_total ?? 0);
            $disbursed = (float)($item->disbursed_total ?? 0);
            $item->available_for_disbursement = max(0, $collected - $disbursed);
            $item->has_financial_history = ((int)($item->member_charges_count ?? 0) > 0)
                || ((int)($item->payments_count ?? 0) > 0)
                || ((int)($item->benefit_disbursements_count ?? 0) > 0);
            return $item;
        });

        $collectionOptions = CollectionItem::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        $categories = CollectionCategory::query()
            ->where('association_id', 1)
            ->where('status', 'active')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'code',
                'payment_mode',
                'default_charge_type',
                'default_is_required',
                'default_allow_partial_payment',
            ]);

        $members = Member::query()
            ->where('status', 'active')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'member_code', 'first_name', 'last_name']);

        $benefitCollectionOptions = CollectionItem::query()
            ->where('status', 'active')
            ->where('is_benefit_collection', true)
            ->whereNotNull('beneficiary_member_id')
            ->with('beneficiary:id,member_code,first_name,last_name')
            ->orderBy('name')
            ->get(['id', 'name', 'beneficiary_member_id']);

        $stats = [
            'total_collections' => (int) CollectionItem::query()->count(),
            'active_collections' => (int) CollectionItem::query()->where('status', 'active')->count(),
            'assigned_links' => (int) DB::table('collection_item_members')->count(),
            'outstanding_balance' => (float) DB::table('v_member_balances')->sum('outstanding_balance'),
            'benefit_collections' => (int) CollectionItem::query()->where('is_benefit_collection', true)->count(),
            'benefits_disbursed_total' => (float) MemberBenefitDisbursement::query()
                ->where('status', 'posted')
                ->sum('disbursed_amount'),
        ];

        return view('collections.index', compact('collections', 'collectionOptions', 'benefitCollectionOptions', 'members', 'categories', 'stats'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'collection_category_id' => ['required', 'integer', 'exists:collection_categories,id'],
            'amount' => [
                Rule::requiredIf(function () use ($request): bool {
                    $categoryId = (int)$request->input('collection_category_id', 0);
                    if ($categoryId <= 0) {
                        return true;
                    }
                    $category = CollectionCategory::query()->find($categoryId);
                    if (!$category) {
                        return true;
                    }
                    return strtolower((string)$category->payment_mode) !== 'voluntary';
                }),
                'nullable',
                'numeric',
                'min:0',
            ],
            'frequency' => ['required', 'in:monthly,quarterly,yearly,one_time,custom'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'due_day_of_month' => ['nullable', 'integer', 'between:1,28'],
            'status' => ['nullable', 'in:draft,active,paused'],
            'is_benefit_collection' => ['nullable', 'boolean'],
            'beneficiary_member_id' => [
                'nullable',
                'integer',
                'exists:members,id',
                Rule::requiredIf(fn () => $request->boolean('is_benefit_collection')),
            ],
            'auto_assign_mode' => ['nullable', 'in:none,all,selected'],
            'member_ids' => ['required_if:auto_assign_mode,selected', 'array', 'min:1'],
            'member_ids.*' => ['integer', 'exists:members,id'],
        ]);

        $selectedCategory = CollectionCategory::query()
            ->where('association_id', 1)
            ->where('id', (int)$validated['collection_category_id'])
            ->firstOrFail();

        $derivedChargeType = (string)$selectedCategory->default_charge_type;
        $derivedIsRequired = (bool)$selectedCategory->default_is_required;
        $derivedAllowPartial = (bool)$selectedCategory->default_allow_partial_payment;
        $derivedCategoryCode = (string)$selectedCategory->code;
        $isVoluntaryCategory = strtolower((string)$selectedCategory->payment_mode) === 'voluntary';

        if ($derivedChargeType === 'one_time') {
            $validated['frequency'] = 'one_time';
        }
        if ($isVoluntaryCategory) {
            $derivedChargeType = 'voluntary';
            $validated['amount'] = $validated['amount'] ?? 0;
            $derivedIsRequired = false;
        }

        $autoAssignMode = (string)($validated['auto_assign_mode'] ?? 'none');
        $memberIds = [];
        if ($autoAssignMode === 'all') {
            $memberIds = Member::query()
                ->where('status', 'active')
                ->pluck('id')
                ->map(fn ($id) => (int)$id)
                ->all();
        } elseif ($autoAssignMode === 'selected') {
            $memberIds = array_map('intval', $validated['member_ids'] ?? []);
        }

        $isBenefitCollection = $request->boolean('is_benefit_collection');
        $item = null;
        $assignment = ['created' => 0, 'skipped' => 0, 'assigned_members' => 0];
        DB::transaction(function () use (
            $validated,
            $autoAssignMode,
            $memberIds,
            $isBenefitCollection,
            $selectedCategory,
            $derivedCategoryCode,
            $derivedChargeType,
            $derivedIsRequired,
            $derivedAllowPartial,
            &$item,
            &$assignment
        ): void {
            $item = CollectionItem::create([
                'association_id' => 1,
                'collection_category_id' => (int)$selectedCategory->id,
                'code' => 'COL-' . strtoupper(Str::random(6)),
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'amount' => $validated['amount'] ?? null,
                'category' => $derivedCategoryCode,
                'charge_type' => $derivedChargeType,
                'frequency' => $validated['frequency'],
                'currency_code' => 'GHS',
                'is_required' => $derivedIsRequired,
                'allow_partial_payment' => $derivedAllowPartial,
                'is_benefit_collection' => $isBenefitCollection,
                'beneficiary_member_id' => $validated['beneficiary_member_id'] ?? null,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'] ?? null,
                'due_day_of_month' => $validated['due_day_of_month'] ?? null,
                'applies_scope' => match ($autoAssignMode) {
                    'all' => 'all_members',
                    'selected' => 'selected_members',
                    default => 'all_members',
                },
                'status' => $validated['status'] ?? 'active',
            ]);

            if ($autoAssignMode !== 'none' && !empty($memberIds)) {
                $assignment = $this->assignMembersAndGenerateCharges($item, $memberIds);
            }
        });

        if (!$item instanceof CollectionItem) {
            return redirect()
                ->route('collections.index')
                ->withErrors(['name' => 'Collection could not be created. Please try again.'])
                ->withInput();
        }

        AuditLog::create([
            'association_id' => 1,
            'actor_user_id' => $request->user()?->id,
            'actor_role' => $request->user()?->roles()->value('name'),
            'action' => 'COLLECTION_CREATED',
            'entity_type' => 'CollectionItem',
            'entity_id' => $item->id,
            'change_summary' => 'Collection created',
            'after_data' => array_merge($item->toArray(), [
                'auto_assign_mode' => $autoAssignMode,
                'category' => [
                    'id' => (int)$selectedCategory->id,
                    'name' => (string)$selectedCategory->name,
                    'mode' => (string)$selectedCategory->payment_mode,
                    'default_charge_type' => $derivedChargeType,
                ],
                'assigned_members' => (int)($assignment['assigned_members'] ?? 0),
                'charges_created' => (int)($assignment['created'] ?? 0),
                'charges_skipped' => (int)($assignment['skipped'] ?? 0),
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => (string)$request->userAgent(),
            'status' => 'success',
        ]);

        $message = 'Collection item created.';
        if ($item->is_benefit_collection && $item->beneficiary) {
            $message .= sprintf(
                ' Beneficiary: %s %s.',
                (string)$item->beneficiary->first_name,
                (string)$item->beneficiary->last_name
            );
        }
        if ($autoAssignMode !== 'none') {
            $message .= sprintf(
                ' Assignment completed for %d member(s). Charges created: %d, skipped: %d.',
                (int)($assignment['assigned_members'] ?? 0),
                (int)($assignment['created'] ?? 0),
                (int)($assignment['skipped'] ?? 0)
            );
        }

        return redirect()
            ->route('collections.index')
            ->with('success', $message);
    }

    public function update(Request $request, CollectionItem $collectionItem): RedirectResponse
    {
        if ((int)$collectionItem->association_id !== 1) {
            abort(403);
        }

        $validated = $request->validate([
            'update_collection_item_id' => ['required', 'integer'],
            'update_name' => ['required', 'string', 'max:120'],
            'update_description' => ['nullable', 'string'],
            'update_collection_category_id' => ['required', 'integer', 'exists:collection_categories,id'],
            'update_amount' => ['nullable', 'numeric', 'min:0'],
            'update_frequency' => ['required', 'in:monthly,quarterly,yearly,one_time,custom'],
            'update_start_date' => ['required', 'date'],
            'update_end_date' => ['nullable', 'date', 'after_or_equal:update_start_date'],
            'update_due_day_of_month' => ['nullable', 'integer', 'between:1,28'],
            'update_status' => ['required', 'in:draft,active,paused,archived'],
            'update_is_benefit_collection' => ['nullable', 'boolean'],
            'update_beneficiary_member_id' => [
                'nullable',
                'integer',
                'exists:members,id',
                Rule::requiredIf(fn () => $request->boolean('update_is_benefit_collection')),
            ],
        ]);

        if ((int)$validated['update_collection_item_id'] !== (int)$collectionItem->id) {
            return redirect()
                ->route('collections.index')
                ->withErrors(['update_collection_item_id' => 'Collection context mismatch. Please retry.'])
                ->withInput();
        }

        $selectedCategory = CollectionCategory::query()
            ->where('association_id', 1)
            ->where('id', (int)$validated['update_collection_category_id'])
            ->firstOrFail();

        $isVoluntaryCategory = strtolower((string)$selectedCategory->payment_mode) === 'voluntary';
        $derivedChargeType = $isVoluntaryCategory ? 'voluntary' : (string)$selectedCategory->default_charge_type;
        $derivedIsRequired = $isVoluntaryCategory ? false : (bool)$selectedCategory->default_is_required;
        $derivedAllowPartial = (bool)$selectedCategory->default_allow_partial_payment;

        $hasFinancialHistory = $this->collectionHasFinancialHistory($collectionItem);
        if (!$isVoluntaryCategory && !$hasFinancialHistory && (($validated['update_amount'] ?? null) === null)) {
            return redirect()
                ->route('collections.index')
                ->withErrors(['update_amount' => 'Amount is required for compulsory collections.'])
                ->withInput();
        }

        $before = $collectionItem->toArray();
        $lockedFieldsChanged = [];
        if ($hasFinancialHistory) {
            $currentAmount = (float)($collectionItem->amount ?? 0);
            $incomingAmount = (float)($validated['update_amount'] ?? $collectionItem->amount ?? 0);

            if ((int)$collectionItem->collection_category_id !== (int)$validated['update_collection_category_id']) {
                $lockedFieldsChanged[] = 'category';
            }
            if (abs($incomingAmount - $currentAmount) >= 0.00001) {
                $lockedFieldsChanged[] = 'amount';
            }
            if ((string)$collectionItem->frequency !== (string)$validated['update_frequency']) {
                $lockedFieldsChanged[] = 'frequency';
            }
            if (optional($collectionItem->start_date)->toDateString() !== Carbon::parse((string)$validated['update_start_date'])->toDateString()) {
                $lockedFieldsChanged[] = 'start date';
            }
            $currentDueDay = $collectionItem->due_day_of_month !== null ? (int)$collectionItem->due_day_of_month : null;
            $incomingDueDay = array_key_exists('update_due_day_of_month', $validated) && $validated['update_due_day_of_month'] !== null
                ? (int)$validated['update_due_day_of_month']
                : null;
            if ($currentDueDay !== $incomingDueDay) {
                $lockedFieldsChanged[] = 'due day';
            }
            $currentBenefit = (bool)$collectionItem->is_benefit_collection;
            $incomingBenefit = $request->boolean('update_is_benefit_collection');
            if ($currentBenefit !== $incomingBenefit) {
                $lockedFieldsChanged[] = 'benefit flag';
            }
            $currentBeneficiary = $collectionItem->beneficiary_member_id !== null ? (int)$collectionItem->beneficiary_member_id : null;
            $incomingBeneficiary = array_key_exists('update_beneficiary_member_id', $validated) && $validated['update_beneficiary_member_id'] !== null
                ? (int)$validated['update_beneficiary_member_id']
                : null;
            if ($currentBeneficiary !== $incomingBeneficiary) {
                $lockedFieldsChanged[] = 'beneficiary';
            }
        }

        if (!empty($lockedFieldsChanged)) {
            return redirect()
                ->route('collections.index')
                ->withErrors([
                    'update_locked' => sprintf(
                        'This collection already has member charges/payments. You can only edit name, description, status, and end date. Blocked change(s): %s.',
                        implode(', ', $lockedFieldsChanged)
                    )
                ])
                ->withInput();
        }

        $payload = [
            'name' => $validated['update_name'],
            'description' => $validated['update_description'] ?? null,
            'end_date' => $validated['update_end_date'] ?? null,
            'status' => $validated['update_status'],
        ];

        if (!$hasFinancialHistory) {
            $amount = $validated['update_amount'] ?? null;
            if ($isVoluntaryCategory && $amount === null) {
                $amount = 0;
            }

            $payload = array_merge($payload, [
                'collection_category_id' => (int)$selectedCategory->id,
                'category' => (string)$selectedCategory->code,
                'charge_type' => $derivedChargeType,
                'frequency' => $derivedChargeType === 'one_time' ? 'one_time' : $validated['update_frequency'],
                'amount' => $amount,
                'is_required' => $derivedIsRequired,
                'allow_partial_payment' => $derivedAllowPartial,
                'is_benefit_collection' => $request->boolean('update_is_benefit_collection'),
                'beneficiary_member_id' => $request->boolean('update_is_benefit_collection')
                    ? ($validated['update_beneficiary_member_id'] ?? null)
                    : null,
                'start_date' => $validated['update_start_date'],
                'due_day_of_month' => $validated['update_due_day_of_month'] ?? null,
            ]);
        }

        $collectionItem->update($payload);

        AuditLog::create([
            'association_id' => 1,
            'actor_user_id' => $request->user()?->id,
            'actor_role' => $request->user()?->roles()->value('name'),
            'action' => 'COLLECTION_UPDATED',
            'entity_type' => 'CollectionItem',
            'entity_id' => $collectionItem->id,
            'change_summary' => $hasFinancialHistory
                ? 'Collection updated (history-safe fields only)'
                : 'Collection updated',
            'before_data' => $before,
            'after_data' => $collectionItem->fresh()?->toArray() ?? [],
            'ip_address' => $request->ip(),
            'user_agent' => (string)$request->userAgent(),
            'status' => 'success',
        ]);

        return redirect()
            ->route('collections.index')
            ->with(
                'success',
                $hasFinancialHistory
                    ? 'Collection updated. Historical payment/charge fields were protected.'
                    : 'Collection updated successfully.'
            );
    }

    public function assign(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'collection_item_id' => ['required', 'exists:collection_items,id'],
            'assign_mode' => ['required', 'in:all,selected'],
            'member_ids' => ['required_if:assign_mode,selected', 'array', 'min:1'],
            'member_ids.*' => ['integer', 'exists:members,id'],
        ]);

        /** @var CollectionItem $collection */
        $collection = CollectionItem::findOrFail($validated['collection_item_id']);

        if ($validated['assign_mode'] === 'all') {
            $memberIds = Member::query()
                ->where('status', 'active')
                ->pluck('id')
                ->map(fn ($id) => (int)$id)
                ->all();
        } else {
            $memberIds = array_map('intval', $validated['member_ids'] ?? []);
        }

        $generation = $this->assignMembersAndGenerateCharges($collection, $memberIds);

        AuditLog::create([
            'association_id' => 1,
            'actor_user_id' => $request->user()?->id,
            'actor_role' => $request->user()?->roles()->value('name'),
            'action' => 'COLLECTION_ASSIGNED',
            'entity_type' => 'CollectionItem',
            'entity_id' => $collection->id,
            'change_summary' => 'Collection assigned to members',
            'after_data' => [
                'assign_mode' => $validated['assign_mode'],
                'member_count' => (int)($generation['assigned_members'] ?? 0),
                'charges_created' => (int)($generation['created'] ?? 0),
                'charges_skipped' => (int)($generation['skipped'] ?? 0),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => (string)$request->userAgent(),
            'status' => 'success',
        ]);

        return redirect()
            ->route('collections.index')
            ->with(
                'success',
                sprintf(
                    'Collection assignment completed. Charges created: %d, skipped existing: %d.',
                    (int)($generation['created'] ?? 0),
                    (int)($generation['skipped'] ?? 0)
                )
            );
    }

    public function disburseBenefit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'benefit_collection_item_id' => ['required', 'integer', 'exists:collection_items,id'],
            'disbursed_amount' => ['required', 'numeric', 'min:0.01'],
            'disbursed_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var CollectionItem $collection */
        $collection = CollectionItem::query()
            ->with('beneficiary:id,member_code,first_name,last_name')
            ->findOrFail((int)$validated['benefit_collection_item_id']);

        if (!$collection->is_benefit_collection || !$collection->beneficiary_member_id) {
            return redirect()
                ->route('collections.index')
                ->withErrors(['benefit_collection_item_id' => 'Selected collection is not configured as a member benefit collection.'])
                ->withInput();
        }

        /** @var MemberRatingService $ratings */
        $ratings = app(MemberRatingService::class);
        $beneficiaryRating = $ratings->recalculateForMember(
            (int)$collection->beneficiary_member_id,
            (int)$collection->association_id
        );
        if (!$beneficiaryRating->eligible_for_benefit) {
            return redirect()
                ->route('collections.index')
                ->withErrors([
                    'benefit_collection_item_id' => sprintf(
                        'Beneficiary is not eligible. Rating %.1f is below policy threshold %.1f.',
                        (float)$beneficiaryRating->score,
                        (float)$beneficiaryRating->minimum_required_score
                    )
                ])
                ->withInput();
        }

        $collected = (float) DB::table('payments')
            ->where('collection_item_id', $collection->id)
            ->where('status', 'posted')
            ->sum('amount');
        $disbursedAlready = (float) MemberBenefitDisbursement::query()
            ->where('collection_item_id', $collection->id)
            ->where('status', 'posted')
            ->sum('disbursed_amount');
        $available = max(0.0, $collected - $disbursedAlready);
        $disbursedAmount = (float)$validated['disbursed_amount'];

        if ($disbursedAmount > $available + 0.0001) {
            return redirect()
                ->route('collections.index')
                ->withErrors([
                    'disbursed_amount' => sprintf(
                        'Disbursement exceeds available realized amount. Available: %.2f.',
                        $available
                    )
                ])
                ->withInput();
        }

        $disbursement = MemberBenefitDisbursement::create([
            'association_id' => 1,
            'collection_item_id' => $collection->id,
            'member_id' => (int)$collection->beneficiary_member_id,
            'disbursed_amount' => $disbursedAmount,
            'disbursed_date' => Carbon::parse((string)$validated['disbursed_date'])->toDateString(),
            'reference' => 'BEN-' . strtoupper(Str::random(10)),
            'status' => 'posted',
            'notes' => $validated['notes'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        AuditLog::create([
            'association_id' => 1,
            'actor_user_id' => $request->user()?->id,
            'actor_role' => $request->user()?->roles()->value('name'),
            'action' => 'BENEFIT_DISBURSED',
            'entity_type' => 'MemberBenefitDisbursement',
            'entity_id' => $disbursement->id,
            'change_summary' => 'Benefit disbursement posted',
            'after_data' => [
                'collection_item_id' => $collection->id,
                'collection_name' => $collection->name,
                'beneficiary_member_id' => (int)$collection->beneficiary_member_id,
                'disbursed_amount' => $disbursedAmount,
                'disbursed_date' => $validated['disbursed_date'],
                'beneficiary_rating' => [
                    'score' => (float)$beneficiaryRating->score,
                    'eligible_for_benefit' => (bool)$beneficiaryRating->eligible_for_benefit,
                    'band' => (string)$beneficiaryRating->band,
                ],
            ],
            'ip_address' => $request->ip(),
            'user_agent' => (string)$request->userAgent(),
            'status' => 'success',
        ]);

        return redirect()
            ->route('collections.index')
            ->with(
                'success',
                sprintf(
                    'Benefit disbursement posted for %s %s: %.2f.',
                    (string)$collection->beneficiary?->first_name,
                    (string)$collection->beneficiary?->last_name,
                    $disbursedAmount
                )
            );
    }

    public function memberStatement(Member $member): JsonResponse
    {
        /** @var ArrearsEngineService $arrears */
        $arrears = app(ArrearsEngineService::class);
        $arrears->generateCharges(now(), 1, null, [(int)$member->id]);

        $today = now()->toDateString();

        $allocationSub = DB::table('payment_allocations')
            ->selectRaw('member_charge_id, SUM(allocated_amount) as allocated_amount')
            ->groupBy('member_charge_id');

        $items = DB::table('member_charges as mc')
            ->join('collection_items as ci', 'ci.id', '=', 'mc.collection_item_id')
            ->leftJoinSub($allocationSub, 'pa', function ($join): void {
                $join->on('pa.member_charge_id', '=', 'mc.id');
            })
            ->where('mc.member_id', $member->id)
            ->selectRaw(
                '
                ci.id as collection_item_id,
                ci.name as collection_name,
                ci.charge_type,
                ci.frequency,
                COUNT(mc.id) as charges_count,
                MAX(mc.due_date) as last_due_date,
                COALESCE(SUM(mc.expected_amount + mc.penalty_amount - mc.discount_amount - mc.waived_amount), 0) as total_expected,
                COALESCE(SUM(COALESCE(pa.allocated_amount, 0)), 0) as total_paid,
                GREATEST(
                    COALESCE(SUM(mc.expected_amount + mc.penalty_amount - mc.discount_amount - mc.waived_amount), 0)
                    - COALESCE(SUM(COALESCE(pa.allocated_amount, 0)), 0),
                    0
                ) as balance,
                COALESCE(SUM(
                    CASE
                        WHEN mc.due_date < ? THEN GREATEST(
                            (mc.expected_amount + mc.penalty_amount - mc.discount_amount - mc.waived_amount)
                            - COALESCE(pa.allocated_amount, 0),
                            0
                        )
                        ELSE 0
                    END
                ), 0) as overdue_balance
                ',
                [$today]
            )
            ->groupBy('ci.id', 'ci.name', 'ci.charge_type', 'ci.frequency')
            ->orderBy('ci.name')
            ->get();

        return response()->json([
            'member' => [
                'id' => $member->id,
                'member_code' => $member->member_code,
                'full_name' => trim($member->first_name . ' ' . $member->last_name),
            ],
            'items' => $items,
        ]);
    }

    private function assignMembersAndGenerateCharges(CollectionItem $collection, array $memberIds): array
    {
        $memberIds = array_values(array_unique(array_map('intval', $memberIds)));
        if (empty($memberIds)) {
            return ['created' => 0, 'skipped' => 0, 'assigned_members' => 0];
        }

        $rows = [];
        $now = now();
        foreach ($memberIds as $memberId) {
            if ($memberId <= 0) {
                continue;
            }
            $rows[] = [
                'collection_item_id' => $collection->id,
                'member_id' => $memberId,
                'created_at' => $now,
            ];
        }

        if (!empty($rows)) {
            // Keep original assignment timestamp for existing rows;
            // assignment date anchors recurring charge backfill logic.
            DB::table('collection_item_members')->insertOrIgnore($rows);
        }

        /** @var ArrearsEngineService $arrears */
        $arrears = app(ArrearsEngineService::class);
        $generation = $arrears->generateCharges(
            asOfDate: now(),
            associationId: 1,
            collectionItemId: (int)$collection->id,
            memberIds: $memberIds
        );

        return [
            'created' => (int)($generation['created'] ?? 0),
            'skipped' => (int)($generation['skipped'] ?? 0),
            'assigned_members' => count($memberIds),
        ];
    }

    private function collectionHasFinancialHistory(CollectionItem $collection): bool
    {
        if ((int)$collection->id <= 0) {
            return false;
        }

        $hasCharges = DB::table('member_charges')
            ->where('collection_item_id', (int)$collection->id)
            ->exists();
        if ($hasCharges) {
            return true;
        }

        $hasPayments = DB::table('payments')
            ->where('collection_item_id', (int)$collection->id)
            ->exists();
        if ($hasPayments) {
            return true;
        }

        return DB::table('member_benefit_disbursements')
            ->where('collection_item_id', (int)$collection->id)
            ->exists();
    }
}
