@extends('adminlte::page')

@section('title', 'Payments')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="mb-1">Payments</h1>
            <p class="text-muted mb-0">Capture transactions, monitor liquidity, and reconcile collection performance.</p>
        </div>
        <button type="button" class="btn btn-primary mt-2 mt-md-0" data-toggle="modal" data-target="#recordPaymentModal">
            <i class="fas fa-plus mr-1"></i> Record Payment
        </button>
    </div>
@stop

@section('css')
<style>
    :root {
        --ops-blue: #0d6efd;
        --ops-ink: #0b1f33;
        --ops-soft: #f4f7fb;
        --ops-line: #e4ebf3;
        --ops-slate: #5a6a7a;
    }
    .ops-stat {
        border: 1px solid var(--ops-line);
        border-radius: .9rem;
        background: #fff;
        height: 100%;
        box-shadow: 0 8px 20px rgba(11, 31, 51, .05);
    }
    .ops-stat .label {
        color: var(--ops-slate);
        font-size: .78rem;
        text-transform: uppercase;
        letter-spacing: .06em;
        font-weight: 700;
    }
    .ops-stat .value {
        color: var(--ops-ink);
        font-size: 1.4rem;
        font-weight: 700;
        line-height: 1.15;
    }
    .ops-card {
        border: 1px solid var(--ops-line);
        border-radius: .9rem;
        box-shadow: 0 10px 24px rgba(11, 31, 51, .05);
    }
    .ops-card .card-header {
        background: linear-gradient(180deg, #ffffff 0%, #f9fbfe 100%);
        border-bottom: 1px solid var(--ops-line);
    }
    .method-chip {
        background: var(--ops-soft);
        border: 1px solid var(--ops-line);
        color: var(--ops-ink);
        border-radius: 999px;
        font-weight: 600;
        padding: .22rem .55rem;
        display: inline-block;
    }
    .payment-row td {
        vertical-align: middle;
    }
</style>
@stop

@section('content')
    @if(session('success'))
        <x-adminlte-alert theme="success" title="Success">
            {{ session('success') }}
        </x-adminlte-alert>
    @endif

    @if($errors->any())
        <x-adminlte-alert theme="danger" title="Please check the form">
            <ul class="mb-0 pl-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-adminlte-alert>
    @endif

    <div class="row mb-3">
        <div class="col-md-6 col-lg-3 mb-3 mb-lg-0">
            <div class="ops-stat p-3">
                <div class="label">Filtered Amount</div>
                <div class="value">{{ number_format((float)($stats['filtered_amount'] ?? 0), 2) }}</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3 mb-3 mb-lg-0">
            <div class="ops-stat p-3">
                <div class="label">Collected This Month</div>
                <div class="value">{{ number_format((float)($stats['month_amount'] ?? 0), 2) }}</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3 mb-3 mb-lg-0">
            <div class="ops-stat p-3">
                <div class="label">Collected Today</div>
                <div class="value">{{ number_format((float)($stats['today_amount'] ?? 0), 2) }}</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="ops-stat p-3">
                <div class="label">Unallocated (Compulsory Only)</div>
                <div class="value">{{ number_format((float)($stats['unallocated_amount'] ?? 0), 2) }}</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3 mt-3 mt-lg-0">
            <div class="ops-stat p-3">
                <div class="label">Voluntary Contributions</div>
                <div class="value text-primary">{{ number_format((float)($stats['voluntary_amount'] ?? 0), 2) }}</div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 mb-3">
            <div class="card ops-card">
                <div class="card-header">
                    <h3 class="card-title mb-0">Payment Filters</h3>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('payments.index') }}">
                        <div class="row align-items-end">
                            <div class="col-md-3">
                                <x-adminlte-input name="date_from" label="From" type="date" value="{{ $filters['date_from'] ?? '' }}" />
                            </div>
                            <div class="col-md-3">
                                <x-adminlte-input name="date_to" label="To" type="date" value="{{ $filters['date_to'] ?? '' }}" />
                            </div>
                            <div class="col-md-3">
                                <x-adminlte-select name="payment_method" label="Method">
                                    <option value="">All methods</option>
                                    <option value="cash" @selected(($filters['payment_method'] ?? '') === 'cash')>Cash</option>
                                    <option value="mobile_money" @selected(($filters['payment_method'] ?? '') === 'mobile_money')>Mobile Money</option>
                                    <option value="bank_transfer" @selected(($filters['payment_method'] ?? '') === 'bank_transfer')>Bank Transfer</option>
                                    <option value="ussd" @selected(($filters['payment_method'] ?? '') === 'ussd')>USSD</option>
                                    <option value="card" @selected(($filters['payment_method'] ?? '') === 'card')>Card</option>
                                </x-adminlte-select>
                            </div>
                            <div class="col-md-3">
                                <x-adminlte-select name="member_id" label="Member">
                                    <option value="">All members</option>
                                    @foreach($members as $member)
                                        <option value="{{ $member->id }}" @selected((int)($filters['member_id'] ?? 0) === (int)$member->id)>
                                            {{ $member->member_code }} - {{ $member->first_name }} {{ $member->last_name }}
                                        </option>
                                    @endforeach
                                </x-adminlte-select>
                            </div>
                        </div>
                        <div class="row align-items-end">
                            <div class="col-md-6">
                                <x-adminlte-select name="collection_item_id" label="Collection Item">
                                    <option value="">All collections</option>
                                    @foreach($collections as $item)
                                        <option value="{{ $item->id }}" @selected((int)($filters['collection_item_id'] ?? 0) === (int)$item->id)>
                                            {{ $item->name }}
                                        </option>
                                    @endforeach
                                </x-adminlte-select>
                            </div>
                            <div class="col-md-3">
                                <x-adminlte-select name="contribution_type" label="Contribution Type">
                                    <option value="">All types</option>
                                    <option value="compulsory" @selected(($filters['contribution_type'] ?? '') === 'compulsory')>Compulsory</option>
                                    <option value="voluntary" @selected(($filters['contribution_type'] ?? '') === 'voluntary')>Voluntary / Donation</option>
                                </x-adminlte-select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <button type="submit" class="btn btn-primary btn-block">
                                    <i class="fas fa-filter mr-1"></i> Apply
                                </button>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="{{ route('payments.index') }}" class="btn btn-outline-secondary btn-block">
                                    Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card ops-card">
                <div class="card-header">
                    <h3 class="card-title mb-0">Method Mix (Current Filter)</h3>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap">
                        @forelse($methodBreakdown as $row)
                            <div class="mr-2 mb-2">
                                <span class="method-chip">
                                    {{ ucfirst(str_replace('_', ' ', $row->payment_method)) }}:
                                    {{ number_format((float)$row->total_amount, 2) }}
                                </span>
                            </div>
                        @empty
                            <span class="text-muted">No data in current filter.</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="recordPaymentModal" tabindex="-1" role="dialog" aria-labelledby="recordPaymentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <form method="POST" action="{{ route('payments.store') }}" class="modal-content" id="recordPaymentForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="recordPaymentModalLabel">Record Payment</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <x-adminlte-select name="member_id" id="record_member_id" label="Member" required>
                                @foreach($members as $member)
                                    <option value="{{ $member->id }}" @selected((int)old('member_id') === (int)$member->id)>
                                        {{ $member->member_code }} - {{ $member->first_name }} {{ $member->last_name }}
                                    </option>
                                @endforeach
                            </x-adminlte-select>
                        </div>
                        <div class="col-md-6">
                            <x-adminlte-select name="collection_item_id" id="record_collection_item_id" label="Collection Item">
                                <option value="">General payment</option>
                                @foreach($collections as $item)
                                    @php
                                        $isVoluntary = (
                                            strtolower((string)($item->categoryConfig?->payment_mode ?? '')) === 'voluntary'
                                            || strtolower((string)$item->charge_type) === 'voluntary'
                                            || strtolower((string)$item->category) === 'donation'
                                        );
                                    @endphp
                                    <option
                                        value="{{ $item->id }}"
                                        data-default-amount="{{ $item->amount !== null ? number_format((float)$item->amount, 2, '.', '') : '' }}"
                                        data-is-required="{{ (int)($item->is_required ? 1 : 0) }}"
                                        data-is-voluntary="{{ $isVoluntary ? '1' : '0' }}"
                                        @selected((int)old('collection_item_id') === (int)$item->id)
                                    >
                                        {{ $item->name }}
                                    </option>
                                @endforeach
                            </x-adminlte-select>
                        </div>
                        <div class="col-md-6">
                            <x-adminlte-input name="amount" id="record_amount" label="Amount (GHS)" type="number" step="0.01" min="0.01" value="{{ old('amount') }}" required />
                            <small id="record_amount_hint" class="text-muted d-block mt-1">Enter amount.</small>
                        </div>
                        <div class="col-md-6">
                            <x-adminlte-select name="payment_method" label="Payment Method" required>
                                <option value="cash" @selected(old('payment_method') === 'cash')>Cash</option>
                                <option value="mobile_money" @selected(old('payment_method') === 'mobile_money')>Mobile Money</option>
                                <option value="bank_transfer" @selected(old('payment_method') === 'bank_transfer')>Bank Transfer</option>
                                <option value="ussd" @selected(old('payment_method') === 'ussd')>USSD</option>
                                <option value="card" @selected(old('payment_method') === 'card')>Card</option>
                            </x-adminlte-select>
                        </div>
                        <div class="col-md-6">
                            <x-adminlte-input name="payment_date" id="record_payment_date" label="Payment Date" type="date" value="{{ old('payment_date', now()->toDateString()) }}" required />
                        </div>
                        <div class="col-12">
                            <x-adminlte-textarea name="notes" label="Notes">{{ old('notes') }}</x-adminlte-textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="recordPaymentSubmit">
                        <i class="fas fa-receipt mr-1"></i> Record Payment
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card ops-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Payment History</h3>
            <small class="text-muted">{{ number_format((int)($stats['filtered_count'] ?? 0)) }} records in current filter</small>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead>
                <tr>
                    <th>Reference</th>
                    <th>Member</th>
                    <th>Collection</th>
                    <th>Method</th>
                    <th class="text-right">Amount</th>
                    <th>Date</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                @forelse($payments as $payment)
                    <tr class="payment-row">
                        <td class="font-weight-bold">{{ $payment->payment_reference }}</td>
                        <td>
                            {{ $payment->member?->member_code }} -
                            {{ trim(($payment->member?->first_name ?? '') . ' ' . ($payment->member?->last_name ?? '')) }}
                        </td>
                        <td>
                            {{ $payment->collectionItem?->name ?? 'General' }}
                            @if(
                                $payment->collectionItem
                                && (
                                    ($payment->collectionItem->categoryConfig?->payment_mode ?? '') === 'voluntary'
                                    || ($payment->collectionItem->charge_type ?? '') === 'voluntary'
                                    || ($payment->collectionItem->category ?? '') === 'donation'
                                )
                            )
                                <span class="badge badge-info ml-1">Voluntary</span>
                            @endif
                        </td>
                        <td>
                            <span class="method-chip">{{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}</span>
                        </td>
                        <td class="text-right font-weight-bold">{{ number_format((float)$payment->amount, 2) }}</td>
                        <td>{{ optional($payment->posting_date)->format('Y-m-d') }}</td>
                        <td>
                            <span class="badge badge-success">{{ ucfirst($payment->status) }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center py-4 text-muted">No payments found for current filter.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $payments->links() }}</div>
    </div>
@stop

@section('js')
<script>
(() => {
    const amountSuggestionEndpoint = @json(route('payments.amount-suggestion'));
    const recordPaymentForm = document.getElementById('recordPaymentForm');
    const collectionSelect = document.getElementById('record_collection_item_id');
    const memberSelect = document.getElementById('record_member_id');
    const paymentDateInput = document.getElementById('record_payment_date');
    const amountInput = document.getElementById('record_amount');
    const amountHint = document.getElementById('record_amount_hint');
    const submitBtn = document.getElementById('recordPaymentSubmit');
    let suggestionRequestSeq = 0;

    const setAmountEditable = (editable) => {
        if (!amountInput) return;
        if (editable) {
            amountInput.removeAttribute('readonly');
            amountInput.removeAttribute('disabled');
            amountInput.removeAttribute('tabindex');
            amountInput.style.pointerEvents = '';
            amountInput.classList.remove('bg-light');
            amountInput.dataset.autoLocked = '0';
            return;
        }
        amountInput.setAttribute('readonly', 'readonly');
        amountInput.setAttribute('tabindex', '-1');
        amountInput.style.pointerEvents = 'none';
        amountInput.classList.add('bg-light');
        amountInput.dataset.autoLocked = '1';
    };

    const syncSubmitState = (isRequired, isVoluntary) => {
        if (!submitBtn || !amountInput) return;
        const amountValue = Number.parseFloat(amountInput.value || '0');
        const blockZeroRequired = isRequired && !isVoluntary && Number.isFinite(amountValue) && amountValue <= 0;
        submitBtn.disabled = blockZeroRequired;
        if (amountHint && blockZeroRequired) {
            amountHint.textContent = 'No outstanding balance. Amount is 0.00, so payment cannot be recorded.';
        }
    };

    const syncPaymentAmountMode = async () => {
        if (!collectionSelect || !amountInput) return;
        const selected = collectionSelect.options[collectionSelect.selectedIndex];
        const collectionId = selected && selected.value ? selected.value : '';
        const isVoluntary = selected && selected.getAttribute('data-is-voluntary') === '1';
        const isRequired = selected && selected.getAttribute('data-is-required') === '1';
        const defaultAmountRaw = selected ? (selected.getAttribute('data-default-amount') || '') : '';
        const defaultAmount = defaultAmountRaw !== '' ? Number.parseFloat(defaultAmountRaw) : NaN;

        if (collectionId === '') {
            setAmountEditable(true);
            if (amountHint) {
                amountHint.textContent = 'General payment: enter amount manually.';
            }
            syncSubmitState(false, false);
            return;
        }

        if (isVoluntary) {
            setAmountEditable(true);
            if (amountInput.dataset.autoLocked === '1') {
                amountInput.value = '';
            }
            if (amountHint) {
                amountHint.textContent = 'Voluntary collection: enter amount manually.';
            }
            syncSubmitState(false, true);
            return;
        }

        const memberId = memberSelect && memberSelect.value ? memberSelect.value : '';
        const paymentDate = paymentDateInput && paymentDateInput.value ? paymentDateInput.value : '';
        if (memberId !== '' && collectionId !== '') {
            const currentSeq = ++suggestionRequestSeq;
            if (amountHint) {
                amountHint.textContent = 'Checking member outstanding balance...';
            }

            try {
                const url = `${amountSuggestionEndpoint}?${new URLSearchParams({
                    member_id: memberId,
                    collection_item_id: collectionId,
                    payment_date: paymentDate,
                }).toString()}`;
                const response = await fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const payload = await response.json();
                if (currentSeq !== suggestionRequestSeq) {
                    return;
                }

                const suggestedAmount = Number.parseFloat(payload.suggested_amount);
                if (Number.isFinite(suggestedAmount) && suggestedAmount >= 0) {
                    amountInput.value = suggestedAmount.toFixed(2);
                } else if (isRequired && Number.isFinite(defaultAmount) && defaultAmount > 0) {
                    amountInput.value = defaultAmount.toFixed(2);
                }

                const lockAmount = payload.lock_amount === true || payload.lock_amount === 1 || payload.lock_amount === '1';
                setAmountEditable(!lockAmount);
                if (amountHint) {
                    amountHint.textContent = payload.reason || (lockAmount
                        ? 'Required collection: amount auto-filled.'
                        : 'Enter amount manually.');
                }
                syncSubmitState(isRequired, false);
                return;
            } catch (error) {
                // fallback to local behavior if suggestion endpoint fails
            }
        }

        if (isRequired && Number.isFinite(defaultAmount) && defaultAmount > 0) {
            amountInput.value = defaultAmount.toFixed(2);
            setAmountEditable(false);
            if (amountHint) {
                amountHint.textContent = 'Required collection: amount auto-filled from collection default.';
            }
            syncSubmitState(isRequired, false);
            return;
        }

        setAmountEditable(true);
        if (amountHint) {
            amountHint.textContent = 'Collection has no default amount configured. Enter amount manually.';
        }
        syncSubmitState(isRequired, false);
    };

    if (collectionSelect) {
        collectionSelect.addEventListener('change', syncPaymentAmountMode);
    }
    if (memberSelect) {
        memberSelect.addEventListener('change', syncPaymentAmountMode);
    }
    if (paymentDateInput) {
        paymentDateInput.addEventListener('change', syncPaymentAmountMode);
    }
    if (amountInput) {
        amountInput.addEventListener('input', () => {
            const selected = collectionSelect ? collectionSelect.options[collectionSelect.selectedIndex] : null;
            const isRequired = selected && selected.getAttribute('data-is-required') === '1';
            const isVoluntary = selected && selected.getAttribute('data-is-voluntary') === '1';
            syncSubmitState(Boolean(isRequired), Boolean(isVoluntary));
        });
    }
    if (recordPaymentForm) {
        recordPaymentForm.addEventListener('submit', (event) => {
            const amountValue = Number.parseFloat(amountInput && amountInput.value ? amountInput.value : '0');
            if (!Number.isFinite(amountValue) || amountValue <= 0) {
                event.preventDefault();
                if (amountHint) {
                    amountHint.textContent = 'Amount must be greater than zero.';
                }
            }
        });
    }

    const hasPaymentCreateErrors = @json($errors->has('member_id') || $errors->has('collection_item_id') || $errors->has('amount') || $errors->has('payment_method') || $errors->has('payment_date') || $errors->has('notes'));
    syncPaymentAmountMode();
    if (hasPaymentCreateErrors) {
        $('#recordPaymentModal').modal('show');
    }
})();
</script>
@stop
