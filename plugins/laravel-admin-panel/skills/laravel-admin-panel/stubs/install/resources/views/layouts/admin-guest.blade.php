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
<div class="admin-guest">
    <div class="admin-guest__card">
        <div class="admin-guest__brand">
            <span class="admin-sidebar__logo">{{ mb_strtoupper(mb_substr(config('app.name'), 0, 1)) }}</span>
            <span>{{ config('app.name') }} Admin</span>
        </div>
        @yield('content')
    </div>
</div>
</body>
</html>
