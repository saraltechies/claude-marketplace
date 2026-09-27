@extends('layouts.admin')

@section('title', 'Activity Log')

@section('content')
<h1 class="admin-page-title">Activity Log</h1>

<div class="data-table-wrap">
    <div class="data-table-toolbar">
        <input type="search" class="data-table-search form-control" placeholder="Search this page…" aria-label="Search">
    </div>
    <div class="table-scroll">
    <table class="table admin-table align-middle">
        <thead>
            <tr>
                <th data-sort="text">When</th>
                <th data-sort="text">Admin</th>
                <th data-sort="text">Action</th>
                <th>Description</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
        @forelse($logs as $log)
            <tr>
                <td class="text-nowrap" data-value="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->format('Y-m-d H:i') }}</td>
                <td>{{ $log->admin->username ?? '—' }}</td>
                <td><code>{{ $log->action }}</code></td>
                <td>{{ $log->description }}</td>
                <td class="text-secondary small">{{ $log->ip_address }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-secondary">No activity recorded yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>

{{ $logs->links('admin.partials.pagination') }}
@endsection
