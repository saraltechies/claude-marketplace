# Admin forgot-password

Only add this if the app can send email (check `MAIL_MAILER` in `.env`; `log` is fine for local testing because links land in `storage/logs/laravel.log`). Every admin who should be able to use it needs an `email` in the `admins` table (`php artisan admin:create <username> --email=...`).

## 1. Password broker

`config/auth.php`, under `'passwords'` (keep the existing `users` broker):

```php
'admins' => [
    'provider' => 'admins',
    'table' => 'admin_password_reset_tokens',
    'expire' => 60,
    'throttle' => 60,
],
```

Migration for the token table:

```php
Schema::create('admin_password_reset_tokens', function (Blueprint $table) {
    $table->string('email')->primary();
    $table->string('token');
    $table->timestamp('created_at')->nullable();
});
```

## 2. Point the reset link at the admin route

Laravel's default notification links to the site's `password.reset` route, which is the wrong one for admins. Override it in `app/Models/Admin.php`:

```php
use Illuminate\Auth\Notifications\ResetPassword;

public function sendPasswordResetNotification($token): void
{
    if (!$this->email) {
        return;
    }

    ResetPassword::createUrlUsing(fn ($notifiable, $token) => $notifiable instanceof self
        ? url(route('admin.password.reset', ['token' => $token, 'email' => $notifiable->email], false))
        : url(route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()], false)));

    $this->notify(new ResetPassword($token));
}
```

`Admin` must `use Illuminate\Notifications\Notifiable;` for `notify()`. If the app has no public `password.reset` route, the second branch can simply use the admin one too.

## 3. Controllers — `app/Http/Controllers/Admin/`

```php
class ForgotPasswordController extends Controller
{
    public function show(): View
    {
        return view('admin.forgot-password');
    }

    public function send(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // Same message either way - don't reveal whether an email is registered.
        Password::broker('admins')->sendResetLink($request->only('email'));

        return back()->with('status', 'If that email is registered, a reset link has been sent.');
    }
}

class ResetPasswordController extends Controller
{
    public function show(Request $request, string $token): View
    {
        return view('admin.reset-password', ['token' => $token, 'email' => $request->query('email', '')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::broker('admins')->reset($data, function (Admin $admin, string $password) {
            $admin->forceFill(['password' => $password])->save(); // 'hashed' cast hashes it
            ActivityLog::create([
                'admin_id' => $admin->id,
                'action' => 'admin.password_reset',
                'description' => "Reset password for \"{$admin->username}\" via forgot-password",
                'ip_address' => request()->ip(),
            ]);
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => __($status)]);
        }

        return redirect()->route('admin.login')->with('status', 'Your password has been reset. You can now log in.');
    }
}
```

(`ActivityLogger::log()` can't be used in the reset callback because nobody is logged in yet — that's why it writes the row directly.)

## 4. Routes — `routes/admin.php`, outside the `admin.auth` group

```php
Route::get('/forgot-password', [Admin\ForgotPasswordController::class, 'show'])->name('password.request');
Route::post('/forgot-password', [Admin\ForgotPasswordController::class, 'send'])->middleware('throttle:5,1')->name('password.email');
Route::get('/reset-password/{token}', [Admin\ResetPasswordController::class, 'show'])->name('password.reset');
Route::post('/reset-password', [Admin\ResetPasswordController::class, 'reset'])->name('password.update');
```

Inside the `admin.` name prefix these become `admin.password.request` etc., so they don't clash with the site's own password routes.

## 5. Views

Both extend `layouts.admin-guest`, mirroring `admin/login.blade.php`: `forgot-password.blade.php` has one email field posting to `admin.password.email`; `reset-password.blade.php` has hidden `token`, email, `password` and `password_confirmation` posting to `admin.password.update`. Add a "Forgot your password?" link under the login form.

## Verify

Request a reset → find the link in the mail log → set a new password → confirm the old password now fails and the new one works → confirm the `admin.password_reset` entry in the activity log.
