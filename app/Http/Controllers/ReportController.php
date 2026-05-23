<?php

namespace App\Http\Controllers;

use App\Models\CollectionItem;
use App\Models\Payment;
use App\Models\ReportView;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->resolveFilters($request);
        $metrics = $this->buildReportMetrics($filters);

        $daily = $metrics['daily'];
        $monthly = $metrics['monthly'];
        $arrears = $metrics['arrears'];

        $dailyTotal = (float) $daily->sum('total_amount');
        $dailyTransactions = (int) $daily->sum('payment_count');
        $monthlyTotal = (float) $monthly->sum('total_amount');
        $monthlyTransactions = (int) $monthly->sum('payment_count');
        $activeDays = max(1, (int) $monthly->count());
        $averageDaily = $monthlyTotal / $activeDays;

        $arrearsTotal = (float) ($metrics['arrears_summary']->outstanding_balance ?? 0);
        $arrearsMembersCount = (int) ($metrics['arrears_summary']->members_count ?? 0);
        $expectedTotal = (float) ($metrics['arrears_summary']->total_expected ?? 0);
        $paidTotal = (float) ($metrics['arrears_summary']->total_paid ?? 0);
        $collectionRate = $expectedTotal > 0 ? ($paidTotal / $expectedTotal) * 100 : 0;

        $monthStart = $filters['month_start'];
        $monthEnd = $filters['month_end'];
        $prevMonthStart = date('Y-m-01', strtotime($monthStart . ' -1 month'));
        $prevMonthEnd = date('Y-m-t', strtotime($prevMonthStart));
        $previousMonthQuery = Payment::query()
            ->whereBetween('posting_date', [$prevMonthStart, $prevMonthEnd])
            ->where('status', 'posted')
            ->when(($filters['collection_item_id'] ?? 0) > 0, function ($query) use ($filters) {
                $query->where('collection_item_id', (int) $filters['collection_item_id']);
            })
            ->when(($filters['payment_method'] ?? null) !== null, function ($query) use ($filters) {
                $query->where('payment_method', (string) $filters['payment_method']);
            });
        $this->applyContributionTypeFilter($previousMonthQuery, (string)($filters['contribution_type'] ?? ''));
        $previousMonthTotal = (float) $previousMonthQuery->sum('amount');
        $monthlyDeltaPct = $previousMonthTotal > 0
            ? (($monthlyTotal - $previousMonthTotal) / $previousMonthTotal) * 100
            : null;

        $dailyBreakdown = $daily->map(function ($row) use ($dailyTotal) {
            $amount = (float) $row->total_amount;
            $share = $dailyTotal > 0 ? ($amount / $dailyTotal) * 100 : 0;
            return (object) [
                'payment_method' => $row->payment_method,
                'payment_count' => (int) $row->payment_count,
                'total_amount' => $amount,
                'share' => $share,
            ];
        });

        $monthlySeries = $monthly->map(function ($row) {
            return (object) [
                'posting_date' => $row->posting_date,
                'date_label' => date('d M', strtotime((string) $row->posting_date)),
                'payment_count' => (int) $row->payment_count,
                'total_amount' => (float) $row->total_amount,
            ];
        });

        $kpis = [
            'daily_total' => $dailyTotal,
            'daily_transactions' => $dailyTransactions,
            'monthly_total' => $monthlyTotal,
            'monthly_transactions' => $monthlyTransactions,
            'average_daily' => $averageDaily,
            'collection_rate' => $collectionRate,
            'arrears_total' => $arrearsTotal,
            'arrears_members_count' => $arrearsMembersCount,
            'previous_month_total' => $previousMonthTotal,
            'monthly_delta_pct' => $monthlyDeltaPct,
        ];

        $savedViews = ReportView::query()
            ->where('user_id', $request->user()?->id)
            ->orderBy('name')
            ->get(['id', 'name', 'filters']);

        $collections = CollectionItem::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('reports.index', compact(
            'savedViews',
            'collections',
            'daily',
            'monthly',
            'filters',
            'dailyBreakdown',
            'monthlySeries',
            'arrears',
            'kpis'
        ));
    }

    public function saveView(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'date' => ['required', 'date'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'limit' => ['required', 'integer', 'min:1', 'max:500'],
            'payment_method' => ['nullable', 'in:cash,mobile_money,bank_transfer,ussd,card'],
            'collection_item_id' => ['nullable', 'integer', 'min:1', 'exists:collection_items,id'],
            'contribution_type' => ['nullable', 'in:compulsory,voluntary'],
        ]);

        ReportView::updateOrCreate(
            [
                'association_id' => 1,
                'user_id' => $request->user()?->id,
                'name' => $validated['name'],
            ],
            [
                'filters' => [
                    'date' => $validated['date'],
                    'year' => (int) $validated['year'],
                    'month' => (int) $validated['month'],
                    'limit' => (int) $validated['limit'],
                    'payment_method' => $validated['payment_method'] ?? null,
                    'collection_item_id' => isset($validated['collection_item_id']) && (int)$validated['collection_item_id'] > 0
                        ? (int)$validated['collection_item_id']
                        : null,
                    'contribution_type' => isset($validated['contribution_type']) && in_array((string)$validated['contribution_type'], ['compulsory', 'voluntary'], true)
                        ? (string)$validated['contribution_type']
                        : null,
                    'preset' => null,
                ],
            ]
        );

        return redirect()
            ->route('reports.index', $request->except(['name', '_token']))
            ->with('success', 'Report view saved.');
    }

    public function export(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:daily,monthly,arrears'],
            'date' => ['nullable', 'date'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
            'payment_method' => ['nullable', 'in:cash,mobile_money,bank_transfer,ussd,card'],
            'collection_item_id' => ['nullable', 'integer', 'min:1', 'exists:collection_items,id'],
            'contribution_type' => ['nullable', 'in:compulsory,voluntary'],
            'preset' => ['nullable', 'string'],
            'view_id' => ['nullable', 'integer'],
        ]);

        $filters = $this->resolveFilters($request);
        $metrics = $this->buildReportMetrics($filters);
        $type = $validated['type'];

        $filename = sprintf(
            'reports_%s_%s.csv',
            $type,
            now()->format('Ymd_His')
        );

        return response()->streamDownload(function () use ($metrics, $type): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            if ($type === 'daily') {
                fputcsv($out, ['Payment Method', 'Transaction Count', 'Total Amount']);
                foreach ($metrics['daily'] as $row) {
                    fputcsv($out, [$row->payment_method, $row->payment_count, $row->total_amount]);
                }
            } elseif ($type === 'monthly') {
                fputcsv($out, ['Posting Date', 'Transaction Count', 'Total Amount']);
                foreach ($metrics['monthly'] as $row) {
                    fputcsv($out, [$row->posting_date, $row->payment_count, $row->total_amount]);
                }
            } else {
                fputcsv($out, ['Member Code', 'Full Name', 'Total Expected', 'Total Paid', 'Outstanding Balance']);
                foreach ($metrics['arrears'] as $row) {
                    fputcsv($out, [
                        $row->member_code,
                        $row->full_name,
                        $row->total_expected,
                        $row->total_paid,
                        $row->outstanding_balance,
                    ]);
                }
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function resolveFilters(Request $request): array
    {
        $now = now();

        $date = (string) $request->query('date', $now->toDateString());
        $year = (int) $request->query('year', $now->year);
        $month = (int) $request->query('month', $now->month);
        $limit = max(1, min((int) $request->query('limit', 50), 500));
        $paymentMethod = $request->query('payment_method');
        $paymentMethod = is_string($paymentMethod) && $paymentMethod !== '' ? $paymentMethod : null;
        $collectionItemIdInput = $request->query('collection_item_id');
        $collectionItemId = is_numeric($collectionItemIdInput) && (int)$collectionItemIdInput > 0
            ? (int)$collectionItemIdInput
            : null;
        $contributionType = (string)$request->query('contribution_type', '');
        $contributionType = in_array($contributionType, ['compulsory', 'voluntary'], true) ? $contributionType : '';
        $preset = (string) $request->query('preset', '');
        $viewId = (int) $request->query('view_id', 0);

        if ($viewId > 0 && $request->user()) {
            $view = ReportView::query()
                ->where('id', $viewId)
                ->where('user_id', $request->user()->id)
                ->first();
            if ($view && is_array($view->filters)) {
                $vf = $view->filters;
                $date = (string)($vf['date'] ?? $date);
                $year = (int)($vf['year'] ?? $year);
                $month = (int)($vf['month'] ?? $month);
                $limit = max(1, min((int)($vf['limit'] ?? $limit), 500));
                $paymentMethod = isset($vf['payment_method']) && $vf['payment_method'] !== '' ? (string)$vf['payment_method'] : $paymentMethod;
                $collectionItemId = isset($vf['collection_item_id']) && (int)$vf['collection_item_id'] > 0
                    ? (int)$vf['collection_item_id']
                    : $collectionItemId;
                $contributionType = isset($vf['contribution_type']) && in_array((string)$vf['contribution_type'], ['compulsory', 'voluntary'], true)
                    ? (string)$vf['contribution_type']
                    : $contributionType;
            }
        }

        if ($preset !== '') {
            $preset = strtolower($preset);
            if ($preset === 'today') {
                $date = $now->toDateString();
                $year = $now->year;
                $month = $now->month;
            } elseif ($preset === 'this_month') {
                $year = $now->year;
                $month = $now->month;
            } elseif ($preset === 'last_month') {
                $lastMonth = $now->copy()->subMonthNoOverflow();
                $year = $lastMonth->year;
                $month = $lastMonth->month;
                $date = $lastMonth->toDateString();
            } elseif ($preset === 'last_30_days') {
                $date = $now->toDateString();
                $year = $now->year;
                $month = $now->month;
            }
        }

        $month = max(1, min($month, 12));
        $year = max(2000, min($year, 2100));
        $monthStart = Carbon::createFromDate($year, $month, 1)->format('Y-m-d');
        $monthEnd = Carbon::createFromDate($year, $month, 1)->endOfMonth()->format('Y-m-d');

        return [
            'date' => $date,
            'year' => $year,
            'month' => $month,
            'limit' => $limit,
            'payment_method' => $paymentMethod,
            'collection_item_id' => $collectionItemId,
            'contribution_type' => $contributionType,
            'preset' => $preset,
            'view_id' => $viewId,
            'month_start' => $monthStart,
            'month_end' => $monthEnd,
        ];
    }

    private function buildReportMetrics(array $filters): array
    {
        $date = $filters['date'];
        $monthStart = $filters['month_start'];
        $monthEnd = $filters['month_end'];
        $limit = $filters['limit'];
        $paymentMethod = $filters['payment_method'];
        $collectionItemId = (int) ($filters['collection_item_id'] ?? 0);
        $contributionType = (string)($filters['contribution_type'] ?? '');

        $dailyQuery = Payment::query()
            ->select('payment_method', DB::raw('COUNT(*) as payment_count'), DB::raw('COALESCE(SUM(amount),0) as total_amount'))
            ->whereDate('posting_date', $date)
            ->where('status', 'posted');
        if ($paymentMethod) {
            $dailyQuery->where('payment_method', $paymentMethod);
        }
        if ($collectionItemId > 0) {
            $dailyQuery->where('collection_item_id', $collectionItemId);
        }
        $this->applyContributionTypeFilter($dailyQuery, $contributionType);
        $daily = $dailyQuery
            ->groupBy('payment_method')
            ->orderByDesc('total_amount')
            ->get();

        $monthlyQuery = Payment::query()
            ->selectRaw('posting_date, COUNT(*) as payment_count, COALESCE(SUM(amount),0) as total_amount')
            ->whereBetween('posting_date', [$monthStart, $monthEnd])
            ->where('status', 'posted');
        if ($paymentMethod) {
            $monthlyQuery->where('payment_method', $paymentMethod);
        }
        if ($collectionItemId > 0) {
            $monthlyQuery->where('collection_item_id', $collectionItemId);
        }
        $this->applyContributionTypeFilter($monthlyQuery, $contributionType);
        $monthly = $monthlyQuery
            ->groupBy('posting_date')
            ->orderBy('posting_date')
            ->get();

        if ($contributionType === 'voluntary') {
            $arrears = collect();
            $arrearsSummary = (object) [
                'total_expected' => 0.0,
                'total_paid' => 0.0,
                'outstanding_balance' => 0.0,
                'members_count' => 0,
            ];
        } else {
            $arrearsMemberQuery = $this->buildArrearsMemberQuery($collectionItemId);
            $arrears = DB::query()
                ->fromSub($arrearsMemberQuery, 'member_balances')
                ->where('outstanding_balance', '>', 0)
                ->orderByDesc('outstanding_balance')
                ->limit($limit)
                ->get();

            $arrearsSummary = DB::query()
                ->fromSub($arrearsMemberQuery, 'member_balances')
                ->selectRaw('
                    COALESCE(SUM(total_expected),0) as total_expected,
                    COALESCE(SUM(total_paid),0) as total_paid,
                    COALESCE(SUM(outstanding_balance),0) as outstanding_balance,
                    COALESCE(SUM(CASE WHEN outstanding_balance > 0 THEN 1 ELSE 0 END),0) as members_count
                ')
                ->first();
        }

        return [
            'daily' => $daily,
            'monthly' => $monthly,
            'arrears' => $arrears,
            'arrears_summary' => $arrearsSummary,
        ];
    }

    private function buildArrearsMemberQuery(int $collectionItemId = 0)
    {
        $chargeTotalExpr = 'mc.expected_amount + mc.penalty_amount - mc.discount_amount - mc.waived_amount';

        $allocationSub = DB::table('payment_allocations')
            ->selectRaw('member_charge_id, SUM(allocated_amount) as allocated_amount')
            ->groupBy('member_charge_id');

        return DB::table('member_charges as mc')
            ->join('members as m', 'm.id', '=', 'mc.member_id')
            ->leftJoinSub($allocationSub, 'pa', function ($join): void {
                $join->on('pa.member_charge_id', '=', 'mc.id');
            })
            ->when($collectionItemId > 0, function ($query) use ($collectionItemId): void {
                $query->where('mc.collection_item_id', $collectionItemId);
            })
            ->whereIn('mc.status', ['open', 'partial', 'paid'])
            ->groupBy('m.id', 'm.member_code', 'm.first_name', 'm.middle_name', 'm.last_name')
            ->selectRaw("
                m.id as member_id,
                m.member_code,
                TRIM(CONCAT(COALESCE(m.first_name, ''), ' ', COALESCE(m.middle_name, ''), ' ', COALESCE(m.last_name, ''))) as full_name,
                COALESCE(SUM($chargeTotalExpr), 0) as total_expected,
                COALESCE(SUM(COALESCE(pa.allocated_amount, 0)), 0) as total_paid,
                GREATEST(COALESCE(SUM($chargeTotalExpr), 0) - COALESCE(SUM(COALESCE(pa.allocated_amount, 0)), 0), 0) as outstanding_balance
            ");
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
}
