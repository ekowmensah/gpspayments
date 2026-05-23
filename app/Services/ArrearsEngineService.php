<?php

namespace App\Services;

use App\Models\CollectionItem;
use App\Models\Member;
use App\Models\MemberCharge;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ArrearsEngineService
{
    public function generateCharges(
        Carbon $asOfDate,
        int $associationId = 1,
        ?int $collectionItemId = null,
        ?array $memberIds = null
    ): array {
        $itemsQuery = CollectionItem::query()
            ->leftJoin('collection_categories as cc', 'cc.id', '=', 'collection_items.collection_category_id')
            ->select('collection_items.*')
            ->where('collection_items.association_id', $associationId)
            ->where('collection_items.status', 'active')
            ->whereIn('collection_items.charge_type', ['recurring', 'one_time']);
        $itemsQuery->where(function ($query): void {
            $query->whereNull('cc.payment_mode')
                ->orWhere('cc.payment_mode', 'compulsory');
        });

        if ($collectionItemId) {
            $itemsQuery->where('collection_items.id', $collectionItemId);
        }

        $items = $itemsQuery->get();
        $created = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $assignedMembers = $this->assignedMembersForItem($item->id, $memberIds);
            foreach ($assignedMembers as $assigned) {
                $member = $assigned['member'];
                $assignmentDate = Carbon::parse((string)$assigned['assigned_at'])->startOfDay();

                $result = $this->generateMemberItemCharges(
                    item: $item,
                    member: $member,
                    asOfDate: $asOfDate,
                    assignmentDate: $assignmentDate
                );

                $created += $result['created'];
                $skipped += $result['skipped'];
            }
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
        ];
    }

    public function allocatePayment(Payment $payment): array
    {
        if (!$payment->member_id || (float)$payment->amount <= 0) {
            return [
                'allocated' => 0.0,
                'allocated_now' => 0.0,
                'unallocated' => (float)$payment->amount,
                'count' => 0,
            ];
        }

        $existingAllocated = (float) PaymentAllocation::query()
            ->where('payment_id', $payment->id)
            ->sum('allocated_amount');

        $remaining = max(0.0, (float)$payment->amount - $existingAllocated);
        if ($remaining <= 0.0) {
            return [
                'allocated' => $existingAllocated,
                'allocated_now' => 0.0,
                'unallocated' => 0.0,
                'count' => 0,
            ];
        }

        $chargesQuery = MemberCharge::query()
            ->where('association_id', (int)$payment->association_id)
            ->where('member_id', (int)$payment->member_id)
            ->whereIn('status', ['open', 'partial'])
            ->orderBy('due_date')
            ->orderBy('id');

        if ($payment->collection_item_id) {
            $chargesQuery->where('collection_item_id', (int)$payment->collection_item_id);
        }

        $charges = $chargesQuery->get();
        $allocatedCount = 0;
        $allocationOrder = ((int)PaymentAllocation::query()->where('payment_id', $payment->id)->max('allocation_order')) + 1;
        $totalAllocatedNow = 0.0;

        DB::transaction(function () use (
            $charges,
            $payment,
            &$remaining,
            &$allocatedCount,
            &$allocationOrder,
            &$totalAllocatedNow
        ): void {
            foreach ($charges as $charge) {
                if ($remaining <= 0.0) {
                    break;
                }

                $alreadyPaid = (float)PaymentAllocation::query()
                    ->where('member_charge_id', $charge->id)
                    ->sum('allocated_amount');
                $chargeTotal = (float)$charge->expected_amount
                    + (float)$charge->penalty_amount
                    - (float)$charge->discount_amount
                    - (float)$charge->waived_amount;
                $outstanding = max(0.0, $chargeTotal - $alreadyPaid);

                if ($outstanding <= 0.0) {
                    $this->updateChargeStatus($charge->id, $chargeTotal, $alreadyPaid);
                    continue;
                }

                $toAllocate = min($remaining, $outstanding);
                if ($toAllocate <= 0.0) {
                    continue;
                }

                PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'member_charge_id' => $charge->id,
                    'allocated_amount' => $toAllocate,
                    'allocation_order' => $allocationOrder++,
                    'created_at' => now(),
                ]);

                $allocatedCount++;
                $remaining -= $toAllocate;
                $totalAllocatedNow += $toAllocate;

                $newPaid = $alreadyPaid + $toAllocate;
                $this->updateChargeStatus($charge->id, $chargeTotal, $newPaid);
            }
        });

        $totalAllocated = $existingAllocated + $totalAllocatedNow;
        return [
            'allocated' => $totalAllocated,
            'allocated_now' => $totalAllocatedNow,
            'unallocated' => max(0.0, (float)$payment->amount - $totalAllocated),
            'count' => $allocatedCount,
        ];
    }

    public function allocateUnallocatedPostedPayments(int $associationId = 1): array
    {
        $payments = Payment::query()
            ->where('association_id', $associationId)
            ->where('status', 'posted')
            ->whereNotNull('member_id')
            ->orderBy('id')
            ->get();

        $processed = 0;
        $allocated = 0.0;
        $unallocated = 0.0;

        foreach ($payments as $payment) {
            $result = $this->allocatePayment($payment);
            $processed++;
            $allocated += (float)($result['allocated_now'] ?? 0);
            $unallocated += (float)$result['unallocated'];
        }

        return [
            'processed' => $processed,
            'allocated_total' => $allocated,
            'unallocated_total' => $unallocated,
        ];
    }

    private function assignedMembersForItem(int $collectionItemId, ?array $memberIds = null): array
    {
        $query = DB::table('collection_item_members as cim')
            ->join('members as m', 'm.id', '=', 'cim.member_id')
            ->where('cim.collection_item_id', $collectionItemId)
            ->where('m.status', 'active')
            ->select('m.*', 'cim.created_at as assigned_at');

        if ($memberIds !== null) {
            $memberIds = array_values(array_filter(array_map('intval', $memberIds), static fn (int $id): bool => $id > 0));
            if (empty($memberIds)) {
                return [];
            }
            $query->whereIn('m.id', $memberIds);
        }

        $rows = $query->get();
        $result = [];
        foreach ($rows as $row) {
            $member = new Member((array)$row);
            $member->exists = true;
            $member->id = (int)$row->id;
            $result[] = ['member' => $member, 'assigned_at' => $row->assigned_at];
        }

        return $result;
    }

    private function generateMemberItemCharges(
        CollectionItem $item,
        Member $member,
        Carbon $asOfDate,
        Carbon $assignmentDate
    ): array {
        $created = 0;
        $skipped = 0;
        $todayLimit = $asOfDate->copy()->endOfDay();

        $startDate = Carbon::parse((string)$item->start_date)->startOfDay();
        $joinDate = Carbon::parse((string)$member->date_joined)->startOfDay();
        $baseStart = max(
            $startDate->timestamp,
            $joinDate->timestamp,
            $assignmentDate->timestamp
        );
        $eligibilityStart = Carbon::createFromTimestamp($baseStart)->startOfDay();
        $cursor = Carbon::createFromTimestamp($baseStart)->startOfDay();

        $endDate = $item->end_date ? Carbon::parse((string)$item->end_date)->endOfDay() : null;

        if ($item->charge_type === 'one_time') {
            $dueDate = $this->buildDueDate($cursor->copy(), (int)($item->due_day_of_month ?? 0));
            if ($dueDate->lt($eligibilityStart)) {
                $dueDate = $eligibilityStart->copy();
            }
            if ($dueDate->lte($todayLimit) && (!$endDate || $dueDate->lte($endDate))) {
                if ($this->createChargeIfMissing($item, $member, $dueDate)) {
                    $created++;
                } else {
                    $skipped++;
                }
            }

            return compact('created', 'skipped');
        }

        $monthsStep = $this->frequencyToMonthStep($item->frequency);
        if ($monthsStep <= 0) {
            $monthsStep = 1;
        }

        // Normalize cursor to first cycle boundary for predictable generation.
        $cursor = $cursor->copy()->startOfMonth();
        $dueDay = (int)($item->due_day_of_month ?: Carbon::parse((string)$item->start_date)->day);
        $dueDay = max(1, min(28, $dueDay));

        while (true) {
            $cycleDate = $cursor->copy();
            $dueDate = $cycleDate->copy()->day($dueDay)->startOfDay();

            if ($dueDate->lt($eligibilityStart)) {
                $cursor->addMonthsNoOverflow($monthsStep);
                continue;
            }
            if ($dueDate->gt($todayLimit)) {
                break;
            }
            if ($endDate && $dueDate->gt($endDate)) {
                break;
            }

            if ($this->createChargeIfMissing($item, $member, $dueDate)) {
                $created++;
            } else {
                $skipped++;
            }

            $cursor->addMonthsNoOverflow($monthsStep);
        }

        return compact('created', 'skipped');
    }

    private function createChargeIfMissing(CollectionItem $item, Member $member, Carbon $dueDate): bool
    {
        $associationId = (int)$item->association_id;
        $reference = sprintf(
            'CHG-%d-%d-%d-%s',
            $associationId,
            (int)$member->id,
            (int)$item->id,
            $dueDate->format('Ymd')
        );

        $exists = MemberCharge::query()
            ->where('association_id', $associationId)
            ->where('member_id', (int)$member->id)
            ->where('collection_item_id', (int)$item->id)
            ->whereDate('due_date', $dueDate->toDateString())
            ->exists();

        if ($exists) {
            return false;
        }

        MemberCharge::create([
            'association_id' => $associationId,
            'charge_reference' => $reference,
            'member_id' => (int)$member->id,
            'collection_item_id' => (int)$item->id,
            'charge_date' => $dueDate->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'expected_amount' => (float)($item->amount ?? 0),
            'penalty_amount' => 0,
            'discount_amount' => 0,
            'waived_amount' => 0,
            'status' => 'open',
            'status_updated_at' => now(),
            'notes' => 'Auto-generated by arrears engine',
        ]);

        return true;
    }

    private function buildDueDate(Carbon $baseDate, int $dueDay): Carbon
    {
        if ($dueDay <= 0) {
            return $baseDate->copy()->startOfDay();
        }
        $day = max(1, min(28, $dueDay));
        return $baseDate->copy()->day($day)->startOfDay();
    }

    private function frequencyToMonthStep(string $frequency): int
    {
        return match (strtolower($frequency)) {
            'monthly' => 1,
            'quarterly' => 3,
            'yearly' => 12,
            'one_time' => 0,
            'custom' => 1,
            default => 1,
        };
    }

    private function updateChargeStatus(int $chargeId, float $chargeTotal, float $paidTotal): void
    {
        $status = 'open';
        if ($paidTotal <= 0) {
            $status = 'open';
        } elseif ($paidTotal + 0.00001 >= $chargeTotal) {
            $status = 'paid';
        } else {
            $status = 'partial';
        }

        MemberCharge::query()
            ->where('id', $chargeId)
            ->update([
                'status' => $status,
                'status_updated_at' => now(),
            ]);
    }
}
