@extends('adminlte::page')

@section('title', 'Reconciliation')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <h1 class="mb-0">Reconciliation</h1>
        <div class="mt-2 mt-md-0">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#openBatchModal">
                <i class="fas fa-folder-open mr-1"></i> Open Batch
            </button>
            <button type="button" class="btn btn-success" data-toggle="modal" data-target="#addBatchItemModal">
                <i class="fas fa-plus-circle mr-1"></i> Add Item
            </button>
            <button type="button" class="btn btn-warning" data-toggle="modal" data-target="#closeBatchModal">
                <i class="fas fa-check-circle mr-1"></i> Close Batch
            </button>
        </div>
    </div>
@stop

@section('content')
    @if(session('success'))
        <x-adminlte-alert theme="success" title="Success">{{ session('success') }}</x-adminlte-alert>
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

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Open Batches</h3>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped">
                <thead>
                <tr>
                    <th>Ref</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Expected</th>
                    <th>Recorded</th>
                    <th>Discrepancy</th>
                    <th>Items</th>
                </tr>
                </thead>
                <tbody>
                @forelse($openBatches as $batch)
                    <tr>
                        <td>{{ $batch->reconciliation_reference }}</td>
                        <td>{{ $batch->reconciliation_type }}</td>
                        <td>{{ $batch->status }}</td>
                        <td>{{ number_format((float)$batch->expected_total, 2) }}</td>
                        <td>{{ number_format((float)$batch->recorded_total, 2) }}</td>
                        <td>{{ number_format((float)$batch->discrepancy_total, 2) }}</td>
                        <td>{{ $batch->items->count() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center">No open batches.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="openBatchModal" tabindex="-1" role="dialog" aria-labelledby="openBatchModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="POST" action="{{ route('reconciliation.open') }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="openBatchModalLabel">Open Batch</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <x-adminlte-select name="reconciliation_type" label="Type" required>
                        <option value="cash_end_of_day" @selected(old('reconciliation_type') === 'cash_end_of_day')>Cash End of Day</option>
                        <option value="cash_mid_day" @selected(old('reconciliation_type') === 'cash_mid_day')>Cash Mid Day</option>
                        <option value="digital_auto" @selected(old('reconciliation_type') === 'digital_auto')>Digital Auto</option>
                        <option value="manual" @selected(old('reconciliation_type') === 'manual')>Manual</option>
                    </x-adminlte-select>
                    <x-adminlte-input name="period_start" type="date" label="Start Date" value="{{ old('period_start') }}" required />
                    <x-adminlte-input name="period_end" type="date" label="End Date" value="{{ old('period_end') }}" required />
                    <x-adminlte-textarea name="notes" label="Notes">{{ old('notes') }}</x-adminlte-textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary" type="submit">Open Batch</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="addBatchItemModal" tabindex="-1" role="dialog" aria-labelledby="addBatchItemModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <form method="POST" action="{{ route('reconciliation.add-item') }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addBatchItemModalLabel">Add Batch Item</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <x-adminlte-input name="batch_id" label="Batch ID" type="number" value="{{ old('batch_id') }}" required />
                        </div>
                        <div class="col-md-6">
                            <x-adminlte-select name="action" label="Action" required>
                                <option value="include" @selected(old('action') === 'include')>Include</option>
                                <option value="exclude" @selected(old('action') === 'exclude')>Exclude</option>
                                <option value="flag_review" @selected(old('action') === 'flag_review')>Flag Review</option>
                                <option value="correct_amount" @selected(old('action') === 'correct_amount')>Correct Amount</option>
                            </x-adminlte-select>
                        </div>
                        <div class="col-12">
                            <x-adminlte-select name="payment_id" label="Payment" required>
                                @foreach($payments as $payment)
                                    <option value="{{ $payment->id }}" @selected((int)old('payment_id') === (int)$payment->id)>
                                        #{{ $payment->id }} {{ $payment->payment_reference }} ({{ number_format((float)$payment->amount,2) }})
                                    </option>
                                @endforeach
                            </x-adminlte-select>
                        </div>
                        <div class="col-md-6">
                            <x-adminlte-input name="recorded_amount" label="Recorded Amount" type="number" step="0.01" value="{{ old('recorded_amount') }}" />
                        </div>
                        <div class="col-md-6">
                            <x-adminlte-input name="corrected_amount" label="Corrected Amount" type="number" step="0.01" value="{{ old('corrected_amount') }}" />
                        </div>
                        <div class="col-12">
                            <x-adminlte-input name="discrepancy_reason" label="Reason" value="{{ old('discrepancy_reason') }}" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button class="btn btn-success" type="submit">Add Item</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="closeBatchModal" tabindex="-1" role="dialog" aria-labelledby="closeBatchModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="POST" action="{{ route('reconciliation.close') }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="closeBatchModalLabel">Close Batch</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <x-adminlte-input name="batch_id" label="Batch ID" type="number" value="{{ old('batch_id') }}" required />
                    <x-adminlte-input name="recorded_total" label="Recorded Total" type="number" step="0.01" value="{{ old('recorded_total') }}" required />
                    <x-adminlte-textarea name="notes" label="Notes">{{ old('notes') }}</x-adminlte-textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                    <button class="btn btn-warning" type="submit">Close Batch</button>
                </div>
            </form>
        </div>
    </div>
@stop

@section('js')
<script>
(() => {
    const hasOpenBatchErrors = @json($errors->has('reconciliation_type') || $errors->has('period_start') || $errors->has('period_end'));
    const hasAddItemErrors = @json($errors->has('payment_id') || $errors->has('action') || $errors->has('recorded_amount') || $errors->has('corrected_amount') || $errors->has('discrepancy_reason'));
    const hasCloseBatchErrors = @json($errors->has('recorded_total'));

    if (hasOpenBatchErrors) {
        $('#openBatchModal').modal('show');
    } else if (hasAddItemErrors) {
        $('#addBatchItemModal').modal('show');
    } else if (hasCloseBatchErrors) {
        $('#closeBatchModal').modal('show');
    }
})();
</script>
@stop
