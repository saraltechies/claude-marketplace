<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    /**
     * Keys editable on the settings screen. The script fields are output raw on
     * the public site - trusted-admin input, same model as any CMS "custom code" box.
     */
    private const KEYS = ['header_scripts', 'body_scripts', 'footer_scripts'];

    public function edit(): View
    {
        $settings = [];
        foreach (self::KEYS as $key) {
            $settings[$key] = Setting::get($key);
        }

        return view('admin.settings.edit', ['settings' => $settings]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (self::KEYS as $key) {
            $rules[$key] = ['nullable', 'string', 'max:20000'];
        }
        $data = $request->validate($rules);

        foreach (self::KEYS as $key) {
            Setting::set($key, $data[$key] ?? null);
        }

        ActivityLogger::log('settings.updated', 'Updated site settings');

        return redirect()->route('admin.settings.edit')->with('success', 'Settings saved.');
    }
}
