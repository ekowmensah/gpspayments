@extends('layouts.member-portal')

@section('title', 'Full Statement')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>
            <h1 class="mb-1">Full Statement</h1>
            <p class="text-muted mb-0">Complete ledger of charges and payments.</p>
        </div>
        <div class="mt-2 mt-md-0">
            <a href="{{ route('member-portal.statement.export') }}" class="btn btn-outline-primary mr-1">
                <i class="fas fa-file-csv mr-1"></i> Export CSV
            </a>
            <a href="{{ route('member-portal.statement.print') }}" class="btn btn-outline-dark mr-1" target="_blank">
                <i class="fas fa-print mr-1"></i> Print / PDF
            </a>
            <a href="{{ route('member-portal.index') }}" class="btn btn-primary">
                <i class="fas fa-arrow-left mr-1"></i> Overview
            </a>
        </div>
    </div>
@stop

@section('content')
    <div class="card portal-card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
            <h3 class="card-title mb-1 mb-md-0">Statement Entries</h3>
            <small class="text-muted">Total entries: {{ number_format((int)$summary['statement_rows']) }}</small>
        </div>
        <div class="px-3 py-2 border-bottom text-muted" style="font-size:.85rem;">
            Outstanding tracks dues-related charges and allocations only. Voluntary, unallocated, and benefit entries are shown for visibility.
        </div>
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Reference</th>
                    <th>Description</th>
                    <th class="text-right">Debit</th>
                    <th class="text-right">Credit</th>
                    <th class="text-right">Outstanding</th>
                </tr>
                </thead>
                <tbody>
                @forelse($statementPager as $row)
                    @php $affectsOutstanding = (bool)($row->affects_outstanding ?? false); @endphp
                    <tr>
                        <td>{{ \Illuminate\Support\Carbon::parse((string)$row->entry_date)->format('Y-m-d') }}</td>
                        <td>{{ $row->reference }}</td>
                        <td>
                            {{ $row->description }}
                            @if(!$affectsOutstanding)
                                <span class="badge badge-light border ml-1">Info</span>
                            @endif
                        </td>
                        <td class="text-right text-danger">{{ (float)$row->debit > 0 ? number_format((float)$row->debit, 2) : '-' }}</td>
                        <td class="text-right text-success">{{ (float)$row->credit > 0 ? number_format((float)$row->credit, 2) : '-' }}</td>
                        <td class="text-right font-weight-bold">{{ $affectsOutstanding ? number_format((float)$row->running_balance, 2) : '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-3">No statement entries available.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-end">
            {{ $statementPager->links() }}
        </div>
    </div>
@stop
