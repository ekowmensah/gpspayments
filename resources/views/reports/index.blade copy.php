@extends('adminlte::page')

@section('title', 'Reports')

@section('content_header')
    <h1>Reports</h1>
@stop

@section('content')
    <div class="row">
        <div class="col-md-4">
            <div class="card card-primary">
                <div class="card-header"><h3 class="card-title">Filters</h3></div>
                <form method="GET" action="{{ route('reports.index') }}">
                    <div class="card-body">
                        <x-adminlte-input name="date" label="Daily Date" type="date" value="{{ $date }}" />
                        <x-adminlte-input name="year" label="Year" type="number" value="{{ $year }}" />
                        <x-adminlte-input name="month" label="Month" type="number" min="1" max="12" value="{{ $month }}" />
                        <x-adminlte-input name="limit" label="Arrears Limit" type="number" min="1" max="500" value="{{ $limit }}" />
                    </div>
                    <div class="card-footer">
                        <button class="btn btn-primary" type="submit">Run Reports</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Daily Collections</h3></div>
                <div class="card-body p-0">
                    <table class="table table-striped">
                        <thead><tr><th>Method</th><th>Count</th><th>Total</th></tr></thead>
                        <tbody>
                        @forelse($daily as $row)
                            <tr>
                                <td>{{ $row->payment_method }}</td>
                                <td>{{ $row->payment_count }}</td>
                                <td>{{ number_format((float)$row->total_amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center">No data.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title">Monthly Trend</h3></div>
                <div class="card-body p-0">
                    <table class="table table-striped">
                        <thead><tr><th>Date</th><th>Count</th><th>Total</th></tr></thead>
                        <tbody>
                        @forelse($monthly as $row)
                            <tr>
                                <td>{{ $row->posting_date }}</td>
                                <td>{{ $row->payment_count }}</td>
                                <td>{{ number_format((float)$row->total_amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center">No data.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Arrears Snapshot</h3></div>
        <div class="card-body p-0">
            <table class="table table-striped">
                <thead>
                <tr>
                    <th>Member Code</th>
                    <th>Full Name</th>
                    <th>Total Expected</th>
                    <th>Total Paid</th>
                    <th>Outstanding</th>
                </tr>
                </thead>
                <tbody>
                @forelse($arrears as $row)
                    <tr>
                        <td>{{ $row->member_code }}</td>
                        <td>{{ $row->full_name }}</td>
                        <td>{{ number_format((float)$row->total_expected, 2) }}</td>
                        <td>{{ number_format((float)$row->total_paid, 2) }}</td>
                        <td>{{ number_format((float)$row->outstanding_balance, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center">No arrears found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@stop

