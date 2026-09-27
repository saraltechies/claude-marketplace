<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>@yield('title', 'Admin') — {{ config('app.name') }}</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body class="admin-body">
<div class="admin-shell">
    <aside class="admin-sidebar" id="admin-sidebar">
        <div class="admin-sidebar__brand">
            <span class="admin-sidebar__logo">{{ mb_strtoupper(mb_substr(config('app.name'), 0, 1)) }}</span>
            <span>{{ config('app.name') }}</span>
        </div>
        <nav class="admin-sidebar__nav">
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}">Dashboard</a>
            {{-- @admin-nav (scaffold.php inserts new resource links above this line) --}}
            <a href="{{ route('admin.activity-log.index') }}" class="{{ request()->routeIs('admin.activity-log.*') ? 'is-active' : '' }}">Activity Log</a>
            <a href="{{ route('admin.settings.edit') }}" class="{{ request()->routeIs('admin.settings.*') ? 'is-active' : '' }}">Settings</a>
            <a href="{{ url('/') }}" target="_blank" rel="noopener" class="admin-sidebar__external">View Site ↗</a>
        </nav>
        <form method="POST" action="{{ route('admin.logout') }}" class="admin-sidebar__logout">
            @csrf
            <button type="submit">Log out ({{ auth('admin')->user()->username }})</button>
        </form>
    </aside>

    <div class="admin-main">
        <button type="button" class="admin-topbar__toggle" id="admin-sidebar-toggle" aria-label="Toggle menu">☰ Menu</button>
        <main class="admin-content">
            @include('admin.partials.flash')
            @yield('content')
        </main>
    </div>
</div>

<script>
document.getElementById('admin-sidebar-toggle')?.addEventListener('click', function () {
    document.getElementById('admin-sidebar')?.classList.toggle('is-open');
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/admin-datatable.js') }}"></script>
</body>
</html>
