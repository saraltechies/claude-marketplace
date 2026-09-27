@extends('layouts.admin')

@section('title', 'Settings')

@section('content')
<h1 class="admin-page-title">Settings</h1>

@include('admin.partials.errors')

<form method="POST" action="{{ route('admin.settings.update') }}" class="admin-card">
    @csrf
    @method('PUT')

    <h2 class="h5">Custom scripts</h2>
    <p class="text-secondary small">
        Paste analytics, chat widgets or verification tags here. They are output exactly as entered on every public page,
        so only paste code from sources you trust.
    </p>

    <div class="mb-3">
        <label class="form-label" for="header_scripts">Header scripts <span class="text-secondary small">(before &lt;/head&gt;)</span></label>
        <textarea id="header_scripts" name="header_scripts" class="form-control font-monospace" rows="5">{{ old('header_scripts', $settings['header_scripts']) }}</textarea>
    </div>
    <div class="mb-3">
        <label class="form-label" for="body_scripts">Body scripts <span class="text-secondary small">(right after &lt;body&gt;)</span></label>
        <textarea id="body_scripts" name="body_scripts" class="form-control font-monospace" rows="5">{{ old('body_scripts', $settings['body_scripts']) }}</textarea>
    </div>
    <div class="mb-3">
        <label class="form-label" for="footer_scripts">Footer scripts <span class="text-secondary small">(before &lt;/body&gt;)</span></label>
        <textarea id="footer_scripts" name="footer_scripts" class="form-control font-monospace" rows="5">{{ old('footer_scripts', $settings['footer_scripts']) }}</textarea>
    </div>

    <button type="submit" class="btn btn-primary">Save settings</button>
</form>
@endsection
