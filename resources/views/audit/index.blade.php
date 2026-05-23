@extends('adminlte::page')

@section('title', 'Audit Logs')

@section('content_header')
    <h1>Audit Logs</h1>
@stop

@section('content')
    <div class="card">
        <div class="card-header"><h3 class="card-title">Filters</h3></div>
        <div class="card-body">
            <form method="GET" action="{{ route('audit.index') }}">
                <div class="row">
                    <div class="col-md-4">
                        <x-adminlte-input name="action" label="Action" value="{{ $action }}" />
                    </div>
                    <div class="col-md-4">
                        <x-adminlte-input name="entity_type" label="Entity Type" value="{{ $entityType }}" />
                    </div>
                    <div class="col-md-4">
                        <x-adminlte-select name="status" label="Status">
                            <option value="">All</option>
                            <option value="success" @selected($status === 'success')>Success</option>
                            <option value="failed" @selected($status === 'failed')>Failed</option>
                            <option value="attempted" @selected($status === 'attempted')>Attempted</option>
                        </x-adminlte-select>
                    </div>
                </div>
                <button class="btn btn-primary" type="submit">Apply</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Entries</h3></div>
        <div class="card-body p-0">
            <table class="table table-striped">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Action</th>
                    <th>Entity</th>
                    <th>Status</th>
                    <th>Summary</th>
                    <th>Timestamp</th>
                </tr>
                </thead>
                <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td>{{ $log->id }}</td>
                        <td>{{ $log->action }}</td>
                        <td>{{ $log->entity_type }}#{{ $log->entity_id }}</td>
                        <td>{{ $log->status }}</td>
                        <td>{{ $log->change_summary }}</td>
                        <td>{{ optional($log->created_at)->format('Y-m-d H:i:s') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center">No audit entries.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $logs->links() }}</div>
    </div>
@stop

