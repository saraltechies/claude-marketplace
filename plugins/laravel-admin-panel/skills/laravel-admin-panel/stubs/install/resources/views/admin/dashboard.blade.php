@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<h1 class="admin-page-title">Dashboard</h1>

<div class="row g-3 mb-4">
    @foreach($stats as $label => $value)
        <div class="col-6 col-lg-3">
            <div class="admin-stat">
                <div class="admin-stat__label">{{ $label }}</div>
                <div class="admin-stat__value">{{ is_numeric($value) ? number_format($value) : $value }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="d-flex justify-content-between align-items-center mb-2">
    <h2 class="h6 text-uppercase text-secondary mb-0">Recent activity</h2>
    <a href="{{ route('admin.activity-log.index') }}" class="small">View all</a>
</div>
<div class="table-scroll">
<table class="table admin-table align-middle">
    <thead>
        <tr><th>When</th><th>Admin</th><th>What</th></tr>
    </thead>
    <tbody>
    @forelse($recentActivity as $log)
        <tr>
            <td class="text-nowrap" title="{{ $log->created_at }}">{{ $log->created_at->diffForHumans() }}</td>
            <td>{{ $log->admin->username ?? '—' }}</td>
            <td>{{ $log->description }}</td>
        </tr>
    @empty
        <tr><td colspan="3" class="text-secondary">Nothing yet.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
@endsection
