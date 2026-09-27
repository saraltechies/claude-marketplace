@extends('layouts.admin-guest')

@section('title', 'Log in')

@section('content')
<h1 class="h4 mb-3">Log in</h1>

@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif
@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<form method="POST" action="{{ route('admin.login.attempt') }}">
    @csrf
    <div class="mb-3">
        <label class="form-label" for="username">Username</label>
        <input type="text" id="username" name="username" class="form-control" value="{{ old('username') }}" required autofocus autocomplete="username">
    </div>
    <div class="mb-3">
        <label class="form-label" for="password">Password</label>
        <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password">
    </div>
    <div class="form-check mb-3">
        <input type="checkbox" id="remember" name="remember" value="1" class="form-check-input">
        <label class="form-check-label" for="remember">Remember me</label>
    </div>
    <button type="submit" class="btn btn-primary w-100">Log in</button>
</form>
@endsection
