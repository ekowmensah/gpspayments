<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MemberRating;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MemberRatingService
{
    private const MINIMUM_BENEFIT_SCORE = 80.0;

    public function recalculateForMember(int $memberId, int $associationId = 1, ?Carbon $asOfDate = null): MemberRating
    {
        $asOf = ($asOfDate ?? now())->copy()->startOfDay();

        /** @var Member $member */
        $member = Member::query()
            ->where('association_id', $associationId)
            ->findOrFail($memberId);

        $metrics = $this->calculateMetrics($member, $asOf);
        [$score, $band] = $this->scoreMember($metrics);
        $eligible = $this->isEligibleForBenefit($score, $metrics);

        return MemberRating::query()->updateOrCreate(
            [
                'association_id' => $associationId,
                'member_id' => (int)$member->id,
            ],
            [
                'score' => $score,
                'minimum_required_score' => self::MINIMUM_BENEFIT_SCORE,
                'eligible_for_benefit' => $eligible,
                'band' => $band,
                'as_of_date' => $asOf->toDateString(),
                'metrics' => $metrics,
            ]
        );
    }

    public function recalculateForAssociation(int $associationId = 1, ?Carbon $asOfDate = null): array
    {
        $asOf = ($asOfDate ?? now())->copy()->startOfDay();
        $memberIds = Member::query()
            ->where('association_id', $associationId)
            ->pluck('id')
            ->map(fn ($id) => (int)$id)
            ->all();

        $processed = 0;
        $eligibleCount = 0;
        $ineligibleCount = 0;
        $scoreTotal = 0.0;

        foreach ($memberIds as $memberId) {
            $rating = $this->recalculateForMember($memberId, $associationId, $asOf);
            $processed++;
            $scoreTotal += (float)$rating->score;
            if ($rating->eligible_for_benefit) {
                $eligibleCount++;
            } else {
                $ineligibleCount++;
            }
        }

        return [
            'processed' => $processed,
            'eligible' => $eligibleCount,
            'ineligible' => $ineligibleCount,
            'average_score' => $processed > 0 ? round($scoreTotal / $processed, 2) : 0.0,
            'as_of' => $asOf->toDateString(),
        ];
    }

    public function getOrRecalculate(int $memberId, int $associationId = 1): MemberRating
    {
        $rating = MemberRating::query()
            ->where('association_id', $associationId)
            ->where('member_id', $memberId)
            ->first();

        if (!$rating) {
            return $this->recalculateForMember($memberId, $associationId, now());
        }

        // Keep portal/member views fresh even when payments are imported or posted outside
        // the main payment flow by forcing periodic recomputation.
        $isStale = $rating->updated_at === null
            || $rating->updated_at->lt(now()->subMinutes(5))
            || ($rating->as_of_date?->toDateString() !== now()->toDateString());
        if ($isStale) {
            return $this->recalculateForMember($memberId, $associationId, now());
        }

        return $rating;
    }

    private function calculateMetrics(Member $member, Carbon $asOf): array
    {
        $allocationByCharge = DB::table('payment_allocations')
            ->selectRaw('member_charge_id, SUM(allocated_amount) as allocated_amount')
            ->groupBy('member_charge_id');

        $charges = DB::table('member_charges as mc')
            ->join('collection_items as ci', 'ci.id', '=', 'mc.collection_item_id')
            ->leftJoin('collection_categories as cc', 'cc.id', '=', 'ci.collection_category_id')
            ->leftJoinSub($allocationByCharge, 'pa', function ($join): void {
                $join->on('pa.member_charge_id', '=', 'mc.id');
            })
            ->where('mc.association_id', (int)$member->association_id)
            ->where('mc.member_id', (int)$member->id)
            ->whereIn('mc.status', ['open', 'partial', 'paid'])
            ->where(function ($query): void {
                $query->whereNull('cc.payment_mode')
                    ->orWhere('cc.payment_mode', 'compulsory');
            })
            ->where('ci.charge_type', '!=', 'voluntary')
            ->where('ci.category', '!=', 'donation')
            ->selectRaw('
                mc.id as charge_id,
                mc.due_date,
                (mc.expected_amount + mc.penalty_amount - mc.discount_amount - mc.waived_amount) as charge_total,
                COALESCE(pa.allocated_amount, 0) as paid_amount
            ')
            ->get();

        $today = $asOf->toDateString();
        $windowStart = $asOf->copy()->subDays(180)->toDateString();
        $voluntaryWindowStart = $asOf->copy()->subMonths(12)->startOfDay()->toDateString();

        $expectedTotal = 0.0;
        $outstandingTotal = 0.0;
        $openChargeCount = 0;
        $overdueCount = 0;
        $overdueAmount = 0.0;
        $maxOverdueDays = 0;
        $dueLast180Count = 0;
        $onTimeCount = 0;

        foreach ($charges as $charge) {
            $chargeTotal = max(0.0, (float)$charge->charge_total);
            $paid = max(0.0, (float)$charge->paid_amount);
            $outstanding = max(0.0, $chargeTotal - $paid);
            $dueDate = Carbon::parse((string)$charge->due_date)->toDateString();

            $expectedTotal += $chargeTotal;
            $outstandingTotal += $outstanding;
            if ($outstanding > 0.0001) {
                $openChargeCount++;
            }

            if ($dueDate < $today && $outstanding > 0.0001) {
                $overdueCount++;
                $overdueAmount += $outstanding;
                $days = Carbon::parse($dueDate)->diffInDays($asOf);
                $maxOverdueDays = max($maxOverdueDays, (int)$days);
            }

            if ($dueDate >= $windowStart && $dueDate <= $today) {
                $dueLast180Count++;
                if ($outstanding <= 0.0001) {
                    $onTimeCount++;
                }
            }
        }

        $skippedVoluntary12m = $this->countOutstandingVoluntarySkips(
            memberId: (int)$member->id,
            associationId: (int)$member->association_id,
            windowStart: $voluntaryWindowStart
        );

        $voluntaryPaid12m = (int) Payment::query()
            ->join('collection_items as ci', 'ci.id', '=', 'payments.collection_item_id')
            ->leftJoin('collection_categories as cc', 'cc.id', '=', 'ci.collection_category_id')
            ->where('payments.association_id', (int)$member->association_id)
            ->where('payments.member_id', (int)$member->id)
            ->where('payments.status', 'posted')
            ->whereDate('payments.posting_date', '>=', $voluntaryWindowStart)
            ->where(function ($query): void {
                $query->where('cc.payment_mode', 'voluntary')
                    ->orWhere('ci.charge_type', 'voluntary')
                    ->orWhere('ci.category', 'donation');
            })
            ->count();

        // If no compulsory charges are due in the window, avoid auto-awarding full on-time bonus.
        $onTimeRatio = $dueLast180Count > 0 ? ($onTimeCount / $dueLast180Count) : 0.0;
        $overdueRatio = $expectedTotal > 0 ? ($overdueAmount / $expectedTotal) : 0.0;

        return [
            'member_status' => (string)$member->status,
            'expected_total' => round($expectedTotal, 2),
            'outstanding_total' => round($outstandingTotal, 2),
            'open_charge_count' => $openChargeCount,
            'overdue_count' => $overdueCount,
            'overdue_amount' => round($overdueAmount, 2),
            'max_overdue_days' => $maxOverdueDays,
            'due_last_180_count' => $dueLast180Count,
            'on_time_paid_count' => $onTimeCount,
            'on_time_ratio' => round($onTimeRatio, 4),
            'overdue_ratio' => round($overdueRatio, 4),
            'voluntary_skips_12m' => $skippedVoluntary12m,
            'voluntary_paid_12m' => $voluntaryPaid12m,
        ];
    }

    private function countOutstandingVoluntarySkips(int $memberId, int $associationId, string $windowStart): int
    {
        $skipActions = DB::table('member_voluntary_actions as mva')
            ->join('collection_items as ci', 'ci.id', '=', 'mva.collection_item_id')
            ->where('mva.association_id', $associationId)
            ->where('mva.member_id', $memberId)
            ->where('mva.action', 'skipped')
            ->whereDate('mva.actioned_at', '>=', $windowStart)
            ->get([
                'mva.collection_item_id',
                'mva.cycle_key',
                'ci.frequency',
                'ci.start_date',
            ]);

        if ($skipActions->isEmpty()) {
            return 0;
        }

        $collectionIds = $skipActions
            ->pluck('collection_item_id')
            ->map(fn ($id) => (int)$id)
            ->unique()
            ->values()
            ->all();

        $payments = DB::table('payments as p')
            ->join('collection_items as ci', 'ci.id', '=', 'p.collection_item_id')
            ->where('p.association_id', $associationId)
            ->where('p.member_id', $memberId)
            ->where('p.status', 'posted')
            ->whereIn('p.collection_item_id', $collectionIds)
            ->whereDate('p.posting_date', '>=', $windowStart)
            ->get([
                'p.collection_item_id',
                'p.posting_date',
                'ci.frequency',
                'ci.start_date',
            ]);

        $resolvedCycles = [];
        foreach ($payments as $payment) {
            $cycleKey = $this->buildContributionCycleKey(
                frequency: (string)$payment->frequency,
                startDate: Carbon::parse((string)$payment->start_date),
                asOf: Carbon::parse((string)$payment->posting_date)
            );

            $resolvedCycles[(int)$payment->collection_item_id . '|' . $cycleKey] = true;
        }

        $outstanding = 0;
        foreach ($skipActions as $skipAction) {
            $key = (int)$skipAction->collection_item_id . '|' . (string)$skipAction->cycle_key;
            if (!isset($resolvedCycles[$key])) {
                $outstanding++;
            }
        }

        return $outstanding;
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

    private function scoreMember(array $metrics): array
    {
        $score = 100.0;

        $overdueCountPenalty = min(45.0, ((int)$metrics['overdue_count']) * 3.5);
        $overdueAmountPenalty = min(25.0, ((float)$metrics['overdue_amount']) / 150.0);
        $overdueAgePenalty = min(15.0, ((int)$metrics['max_overdue_days']) / 20.0);
        $skipPenalty = min(10.0, ((int)$metrics['voluntary_skips_12m']) * 1.5);

        $score -= ($overdueCountPenalty + $overdueAmountPenalty + $overdueAgePenalty + $skipPenalty);

        $onTimeBonus = min(12.0, ((float)$metrics['on_time_ratio']) * 12.0);
        $voluntaryConsistencyBonus = min(6.0, ((int)$metrics['voluntary_paid_12m']) * 0.5);
        $score += ($onTimeBonus + $voluntaryConsistencyBonus);

        // Any recent voluntary skip must always have a visible impact.
        $skipCount = (int)($metrics['voluntary_skips_12m'] ?? 0);
        if ($skipCount > 0) {
            $hardCapAfterSkip = max(0.0, 99.0 - (($skipCount - 1) * 0.5));
            $score = min($score, $hardCapAfterSkip);
        }

        $score = max(0.0, min(100.0, $score));
        $score = round($score, 2);

        $band = 'high_risk';
        if ($score >= 90) {
            $band = 'excellent';
        } elseif ($score >= 80) {
            $band = 'good';
        } elseif ($score >= 65) {
            $band = 'watchlist';
        }

        return [$score, $band];
    }

    private function isEligibleForBenefit(float $score, array $metrics): bool
    {
        if (($metrics['member_status'] ?? 'inactive') !== 'active') {
            return false;
        }

        if ($score < self::MINIMUM_BENEFIT_SCORE) {
            return false;
        }

        if ((int)$metrics['overdue_count'] >= 10) {
            return false;
        }

        if ((int)$metrics['max_overdue_days'] > 60) {
            return false;
        }

        if ((float)$metrics['outstanding_total'] > 0 && (float)$metrics['overdue_ratio'] >= 0.55) {
            return false;
        }

        return true;
    }
}
