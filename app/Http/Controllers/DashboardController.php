<?php

namespace App\Http\Controllers;

use App\Models\CollectionItem;
use App\Models\Member;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): View|RedirectResponse
    {
        $isAdminPanelUser = (bool)request()->user()?->hasRole('Administrator', 'Treasurer', 'Secretary', 'Auditor');
        if (!empty(request()->user()?->member_id) && !$isAdminPanelUser) {
            return redirect()->route('member-portal.index');
        }

        $today = now()->toDateString();
        $yesterday = now()->copy()->subDay()->toDateString();
        $monthStart = now()->copy()->startOfMonth()->toDateString();
        $monthEnd = now()->copy()->endOfMonth()->toDateString();
        $thirtyDaysStart = now()->copy()->subDays(29)->toDateString();

        $todayAmount = (float) Payment::query()
            ->whereDate('posting_date', $today)
            ->where('status', 'posted')
            ->sum('amount');
        $yesterdayAmount = (float) Payment::query()
            ->whereDate('posting_date', $yesterday)
            ->where('status', 'posted')
            ->sum('amount');
        $monthAmount = (float) Payment::query()
            ->whereBetween('posting_date', [$monthStart, $monthEnd])
            ->where('status', 'posted')
            ->sum('amount');
        $monthTransactions = (int) Payment::query()
            ->whereBetween('posting_date', [$monthStart, $monthEnd])
            ->where('status', 'posted')
            ->count();

        $balanceAggregate = DB::table('v_member_balances')
            ->selectRaw('COALESCE(SUM(total_expected),0) as total_expected, COALESCE(SUM(total_paid),0) as total_paid, COALESCE(SUM(outstanding_balance),0) as outstanding_balance')
            ->first();
        $totalExpected = (float) ($balanceAggregate->total_expected ?? 0);
        $totalPaid = (float) ($balanceAggregate->total_paid ?? 0);
        $outstandingBalance = (float) ($balanceAggregate->outstanding_balance ?? 0);
        $collectionRate = $totalExpected > 0 ? ($totalPaid / $totalExpected) * 100 : 0.0;

        $unallocatedAmount = (float) DB::table('payments as p')
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
            ->value('value');

        $overdueSummary = DB::table('member_charges as mc')
            ->leftJoin(
                DB::raw('(SELECT member_charge_id, SUM(allocated_amount) as allocated_total FROM payment_allocations GROUP BY member_charge_id) pa'),
                'pa.member_charge_id',
                '=',
                'mc.id'
            )
            ->where('mc.due_date', '<', $today)
            ->whereIn('mc.status', ['open', 'partial'])
            ->selectRaw('
                COUNT(mc.id) as overdue_count,
                COALESCE(SUM(GREATEST(
                    (mc.expected_amount + mc.penalty_amount - mc.discount_amount - mc.waived_amount) - COALESCE(pa.allocated_total, 0),
                    0
                )), 0) as overdue_amount
            ')
            ->first();

        $openBatches = (int) DB::table('reconciliation_batches')
            ->whereIn('status', ['open', 'pending_review'])
            ->count();

        $totalMembers = (int) Member::count();
        $activeMembers = (int) Member::where('status', 'active')->count();
        $inactiveMembers = (int) Member::where('status', 'inactive')->count();
        $suspendedMembers = (int) Member::where('status', 'suspended')->count();
        $exitedMembers = (int) Member::where('status', 'exited')->count();
        $deceasedMembers = (int) Member::where('status', 'deceased')->count();
        $arrearsMembers = (int) DB::table('v_member_balances')->where('outstanding_balance', '>', 0)->count();
        $activeCoveragePct = $totalMembers > 0 ? ($activeMembers / $totalMembers) * 100 : 0.0;
        $arrearsRatePct = $activeMembers > 0 ? ($arrearsMembers / $activeMembers) * 100 : 0.0;

        $stats = [
            'total_members' => $totalMembers,
            'active_members' => $activeMembers,
            'inactive_members' => $inactiveMembers,
            'suspended_members' => $suspendedMembers,
            'exited_members' => $exitedMembers,
            'deceased_members' => $deceasedMembers,
            'active_coverage_pct' => $activeCoveragePct,
            'arrears_rate_pct' => $arrearsRatePct,
            'active_collections' => (int) CollectionItem::where('status', 'active')->count(),
            'today_payments' => (int) Payment::whereDate('posting_date', $today)->where('status', 'posted')->count(),
            'month_transactions' => $monthTransactions,
            'today_amount' => $todayAmount,
            'yesterday_amount' => $yesterdayAmount,
            'month_amount' => $monthAmount,
            'outstanding_balance' => $outstandingBalance,
            'collection_rate' => $collectionRate,
            'arrears_members' => $arrearsMembers,
            'high_risk_members' => (int) DB::table('v_member_balances')->where('outstanding_balance', '>=', 1000)->count(),
            'unallocated_amount' => $unallocatedAmount,
            'overdue_count' => (int) ($overdueSummary->overdue_count ?? 0),
            'overdue_amount' => (float) ($overdueSummary->overdue_amount ?? 0),
            'open_batches' => $openBatches,
        ];

        $todayDeltaPct = $yesterdayAmount > 0
            ? (($todayAmount - $yesterdayAmount) / $yesterdayAmount) * 100
            : null;

        $recentPayments = Payment::query()
            ->with(['member:id,member_code,first_name,last_name', 'collectionItem:id,name'])
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $rawDailyTrend = Payment::query()
            ->selectRaw('posting_date, COUNT(*) as payment_count, COALESCE(SUM(amount),0) as total_amount')
            ->whereBetween('posting_date', [$thirtyDaysStart, $today])
            ->where('status', 'posted')
            ->groupBy('posting_date')
            ->orderBy('posting_date')
            ->get()
            ->keyBy(fn ($row) => (string)$row->posting_date);

        $dailyTrend = collect();
        $cursor = Carbon::parse($thirtyDaysStart);
        $end = Carbon::parse($today);
        $runningTotal = 0.0;
        while ($cursor->lte($end)) {
            $dateKey = $cursor->toDateString();
            $row = $rawDailyTrend->get($dateKey);
            $amount = (float)($row->total_amount ?? 0);
            $count = (int)($row->payment_count ?? 0);
            $runningTotal += $amount;

            $dailyTrend->push((object) [
                'posting_date' => $dateKey,
                'label' => $cursor->format('d M'),
                'payment_count' => $count,
                'total_amount' => $amount,
                'cumulative_amount' => $runningTotal,
            ]);

            $cursor->addDay();
        }

        $methodMix = Payment::query()
            ->selectRaw('payment_method, COUNT(*) as payment_count, COALESCE(SUM(amount),0) as total_amount')
            ->whereBetween('posting_date', [$monthStart, $monthEnd])
            ->where('status', 'posted')
            ->groupBy('payment_method')
            ->orderByDesc('total_amount')
            ->get()
            ->map(fn ($row) => (object) [
                'payment_method' => (string)$row->payment_method,
                'payment_count' => (int)$row->payment_count,
                'total_amount' => (float)$row->total_amount,
            ]);

        $topArrears = DB::table('v_member_balances')
            ->where('outstanding_balance', '>', 0)
            ->orderByDesc('outstanding_balance')
            ->limit(8)
            ->get();

        $collectionPerformance = DB::table('member_charges as mc')
            ->join('collection_items as ci', 'ci.id', '=', 'mc.collection_item_id')
            ->leftJoin(
                DB::raw('(SELECT member_charge_id, SUM(allocated_amount) as allocated_total FROM payment_allocations GROUP BY member_charge_id) pa'),
                'pa.member_charge_id',
                '=',
                'mc.id'
            )
            ->selectRaw('
                ci.id as collection_item_id,
                ci.name as collection_name,
                COALESCE(SUM(mc.expected_amount + mc.penalty_amount - mc.discount_amount - mc.waived_amount), 0) as total_expected,
                COALESCE(SUM(COALESCE(pa.allocated_total, 0)), 0) as total_paid,
                COALESCE(SUM(GREATEST(
                    (mc.expected_amount + mc.penalty_amount - mc.discount_amount - mc.waived_amount) - COALESCE(pa.allocated_total, 0),
                    0
                )),0) as outstanding_balance
            ')
            ->groupBy('ci.id', 'ci.name')
            ->orderByDesc('outstanding_balance')
            ->limit(8)
            ->get();

        return view('dashboard', compact(
            'stats',
            'recentPayments',
            'dailyTrend',
            'methodMix',
            'topArrears',
            'collectionPerformance',
            'todayDeltaPct'
        ));
    }
}
