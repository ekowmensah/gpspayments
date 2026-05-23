<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\ReconciliationBatch;
use App\Models\ReconciliationItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReconciliationController extends Controller
{
    public function index(): View
    {
        $openBatches = ReconciliationBatch::query()
            ->whereIn('status', ['open', 'pending_review'])
            ->orderByDesc('id')
            ->with('items')
            ->get();

        $payments = Payment::query()
            ->where('status', 'posted')
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'payment_reference', 'amount', 'posting_date']);

        return view('reconciliation.index', compact('openBatches', 'payments'));
    }

    public function openBatch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'reconciliation_type' => ['required', 'in:cash_end_of_day,cash_mid_day,digital_auto,manual'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $batch = ReconciliationBatch::create([
            'association_id' => 1,
            'reconciliation_reference' => 'REC-' . strtoupper(Str::random(8)),
            'reconciliation_type' => $validated['reconciliation_type'],
            'period_start' => $validated['period_start'] . ' 00:00:00',
            'period_end' => $validated['period_end'] . ' 23:59:59',
            'expected_total' => 0,
            'recorded_total' => 0,
            'discrepancy_total' => 0,
            'status' => 'open',
            'reconciled_by' => $request->user()?->id,
            'notes' => $validated['notes'] ?? null,
        ]);

        AuditLog::create([
            'association_id' => 1,
            'actor_user_id' => $request->user()?->id,
            'actor_role' => $request->user()?->roles()->value('name'),
            'action' => 'RECONCILIATION_OPENED',
            'entity_type' => 'ReconciliationBatch',
            'entity_id' => $batch->id,
            'change_summary' => 'Reconciliation batch opened',
            'status' => 'success',
        ]);

        return back()->with('success', 'Reconciliation batch opened.');
    }

    public function addItem(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'batch_id' => ['required', 'exists:reconciliation_batches,id'],
            'payment_id' => ['required', 'exists:payments,id'],
            'action' => ['required', 'in:include,exclude,flag_review,correct_amount'],
            'recorded_amount' => ['nullable', 'numeric', 'min:0'],
            'corrected_amount' => ['nullable', 'numeric', 'min:0'],
            'discrepancy_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $payment = Payment::findOrFail((int) $validated['payment_id']);

        ReconciliationItem::create([
            'batch_id' => (int) $validated['batch_id'],
            'payment_id' => $payment->id,
            'action' => $validated['action'],
            'expected_amount' => $payment->amount,
            'recorded_amount' => $validated['recorded_amount'] ?? $payment->amount,
            'corrected_amount' => $validated['corrected_amount'] ?? null,
            'discrepancy_reason' => $validated['discrepancy_reason'] ?? null,
            'created_at' => now(),
        ]);

        return back()->with('success', 'Reconciliation item added.');
    }

    public function closeBatch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'batch_id' => ['required', 'exists:reconciliation_batches,id'],
            'recorded_total' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $batch = ReconciliationBatch::query()->with('items')->findOrFail((int) $validated['batch_id']);
        $expected = (float) $batch->items->sum(fn ($i) => (float) ($i->expected_amount ?? 0));
        $recorded = (float) $validated['recorded_total'];
        $discrepancy = $recorded - $expected;

        $batch->update([
            'expected_total' => $expected,
            'recorded_total' => $recorded,
            'discrepancy_total' => $discrepancy,
            'status' => abs($discrepancy) < 0.01 ? 'closed' : 'pending_review',
            'closed_by' => $request->user()?->id,
            'closed_at' => now(),
            'notes' => $validated['notes'] ?? $batch->notes,
        ]);

        AuditLog::create([
            'association_id' => 1,
            'actor_user_id' => $request->user()?->id,
            'actor_role' => $request->user()?->roles()->value('name'),
            'action' => 'RECONCILIATION_CLOSED',
            'entity_type' => 'ReconciliationBatch',
            'entity_id' => $batch->id,
            'change_summary' => 'Reconciliation batch closed',
            'status' => 'success',
        ]);

        return back()->with('success', 'Reconciliation batch updated.');
    }
}

