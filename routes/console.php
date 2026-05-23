<?php

use App\Services\ArrearsEngineService;
use App\Services\MemberRatingService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Carbon;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command(
    'arrears:sync
    {--association=1 : Association ID}
    {--as-of= : Generate charges up to this date (YYYY-MM-DD)}
    {--collection-item= : Optional collection item ID}
    {--member=* : Optional member IDs (repeat option)}
    {--allocate : Allocate posted payments after generating charges}',
    function (ArrearsEngineService $arrears): int {
        $associationId = (int)$this->option('association');
        $asOfOption = (string)$this->option('as-of');
        $asOfDate = $asOfOption !== ''
            ? Carbon::parse($asOfOption)->endOfDay()
            : now()->endOfDay();
        $collectionItemId = $this->option('collection-item');
        $collectionItemId = $collectionItemId !== null && $collectionItemId !== ''
            ? (int)$collectionItemId
            : null;
        $memberOptions = $this->option('member');
        $memberIds = is_array($memberOptions) && !empty($memberOptions)
            ? array_map('intval', $memberOptions)
            : null;

        $this->info(sprintf(
            'Generating charges up to %s (association=%d)...',
            $asOfDate->toDateString(),
            $associationId
        ));
        $generated = $arrears->generateCharges(
            asOfDate: $asOfDate,
            associationId: $associationId,
            collectionItemId: $collectionItemId,
            memberIds: $memberIds
        );
        $this->info(sprintf(
            'Charge generation complete: created=%d, skipped=%d',
            (int)($generated['created'] ?? 0),
            (int)($generated['skipped'] ?? 0)
        ));

        if ($this->option('allocate')) {
            $this->info('Allocating posted payments...');
            $allocation = $arrears->allocateUnallocatedPostedPayments($associationId);
            $this->info(sprintf(
                'Allocation complete: processed=%d, allocated_now=%.2f, remaining_unallocated=%.2f',
                (int)($allocation['processed'] ?? 0),
                (float)($allocation['allocated_total'] ?? 0),
                (float)($allocation['unallocated_total'] ?? 0)
            ));
        }

        return 0;
    }
)->purpose('Generate member charges and optionally allocate posted payments');

Artisan::command(
    'ratings:recalculate
    {--association=1 : Association ID}
    {--member=* : Optional member IDs (repeat option)}
    {--as-of= : Optional as-of date (YYYY-MM-DD)}',
    function (MemberRatingService $ratings): int {
        $associationId = (int)$this->option('association');
        $asOfOption = (string)$this->option('as-of');
        $asOfDate = $asOfOption !== ''
            ? Carbon::parse($asOfOption)->startOfDay()
            : now()->startOfDay();

        $memberOptions = $this->option('member');
        $memberIds = is_array($memberOptions)
            ? array_values(array_filter(array_map('intval', $memberOptions), static fn (int $id): bool => $id > 0))
            : [];

        if (!empty($memberIds)) {
            $processed = 0;
            $eligible = 0;
            $ineligible = 0;
            $scoreTotal = 0.0;

            foreach ($memberIds as $memberId) {
                $rating = $ratings->recalculateForMember($memberId, $associationId, $asOfDate);
                $processed++;
                $scoreTotal += (float)$rating->score;
                if ($rating->eligible_for_benefit) {
                    $eligible++;
                } else {
                    $ineligible++;
                }
            }

            $averageScore = $processed > 0 ? ($scoreTotal / $processed) : 0.0;
            $this->info(sprintf(
                'Ratings recalculated: processed=%d, eligible=%d, ineligible=%d, average_score=%.2f',
                $processed,
                $eligible,
                $ineligible,
                $averageScore
            ));
            return 0;
        }

        $summary = $ratings->recalculateForAssociation($associationId, $asOfDate);
        $this->info(sprintf(
            'Ratings recalculated: processed=%d, eligible=%d, ineligible=%d, average_score=%.2f',
            (int)($summary['processed'] ?? 0),
            (int)($summary['eligible'] ?? 0),
            (int)($summary['ineligible'] ?? 0),
            (float)($summary['average_score'] ?? 0)
        ));
        return 0;
    }
)->purpose('Recalculate member payment ratings and benefit eligibility');
