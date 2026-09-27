---
name: laravel-admin-panel
description: Scaffold a production-ready admin panel into a Laravel app (10 and newer, incl. 11/12/13), or add a new CRUD section to one - separate admin login guard (not the users table), dark sidebar layout on Bootstrap 5, searchable/sortable paginated tables, active/hidden toggles, delete guards, an admin activity log, site settings with custom header/footer scripts, and an artisan command to create admins. Use this whenever someone wants an admin area, backend, back-office, dashboard, CMS-style management screens, or "a page to manage X" in a Laravel project, even if they don't say "admin panel" - and whenever they want to add a new manageable resource (products, categories, posts, coupons...) to an admin panel this skill installed. Not for Filament/Nova/Backpack setups or for non-Laravel PHP.
---

# Laravel Admin Panel

A lightweight, dependency-free admin panel for Laravel, extracted from a real production store. It is plain Laravel (controllers, Form Requests, Blade, one small JS file) plus Bootstrap 5 from a CDN, so there is no admin package to learn, no npm build step, and every file is the user's to edit.

What gets installed:

| Piece | Where |
|---|---|
| Separate `admin` guard + `admins` table (admins never mix with site users) | `app/Models/Admin.php`, migration, `config/auth.php` |
| Login with rate-limiting (5 tries/min), logout, "remember me" | `Admin\AuthController`, `admin/login.blade.php` |
| `admin.auth` middleware | `app/Http/Middleware/AdminAuthenticate.php` |
| `php artisan admin:create {username}` (prompts for the password) | `app/Console/Commands/CreateAdmin.php` |
| Sidebar layout (collapses to a menu button on phones) + guest layout | `resources/views/layouts/admin*.blade.php`, `public/css/admin.css` |
| Dashboard with stat cards + recent activity | `Admin\DashboardController` |
| Activity log (who did what, when, from which IP) | `ActivityLog` model, `App\Support\ActivityLogger::log()` |
| Settings: header/body/footer custom scripts | `Setting::get()/set()` (cached), `Admin\SettingController` |
| Searchable, sortable tables; always-visible "Showing X–Y of Z" pagination | `public/js/admin-datatable.js`, `admin/partials/pagination.blade.php` |
| All admin routes in one file | `routes/admin.php` (required from `routes/web.php`) |

## Before you start

1. Confirm it is a Laravel project (`artisan` at the root) and find the version in `composer.lock` (`laravel/framework`). Laravel 10 and newer are supported (tested on 13). The only difference between versions is where middleware is registered: Laravel 10 uses `app/Http/Kernel.php`, 11 and later use `bootstrap/app.php`.
2. Make sure `vendor/` exists (`composer install`) — the scaffolder loads the project's autoloader.
3. Check what already exists so you don't collide: an `admins` table, an `Admin` model, `routes/admin.php`, an `admin` guard, or an existing admin package (Filament, Nova, Backpack, Voyager). If a different admin system is already there, stop and ask the user whether they really want a second one.
4. Find a PHP binary. On Windows/WAMP/XAMPP `php` is often not on PATH; look in places like `C:\wamp64\bin\php\php8.x\php.exe` or `C:\xampp\php\php.exe` and use the full path.

## Mode A: install the admin panel

### 1. Run the scaffolder

```bash
php <skill-dir>/scripts/scaffold.php install --path=<laravel-root>
```

It copies every file in `stubs/install/` into the project (skipping any that already exist — it tells you which, and `--force` overwrites), timestamps the three migrations in the right order, and adds `require __DIR__.'/admin.php';` to `routes/web.php`. It then prints a wiring check.

If the report shows a skipped file, look at the existing file before deciding anything: it may be the user's own code with the same name (e.g. an unrelated `Setting` model). Don't `--force` over user code; adapt instead (rename, or merge by hand) and tell the user what you did.

### 2. Wire it up (the script prints these as TODO until done)

**`config/auth.php`** — add a guard, a provider, and (only if you'll add password reset) a broker. Keep the existing `web`/`users` entries untouched:

```php
'guards' => [
    // ...existing 'web' guard...
    'admin' => [
        'driver' => 'session',
        'provider' => 'admins',
    ],
],

'providers' => [
    // ...existing 'users' provider...
    'admins' => [
        'driver' => 'eloquent',
        'model' => App\Models\Admin::class,
    ],
],
```

**Middleware alias `admin.auth`:**

- Laravel 11 and later — `bootstrap/app.php`:
  ```php
  ->withMiddleware(function (Middleware $middleware): void {
      $middleware->alias([
          'admin.auth' => \App\Http\Middleware\AdminAuthenticate::class,
      ]);
  })
  ```
  If there is already an `alias([...])` call, add the entry to it rather than calling `alias()` twice.
- Laravel 10 — `app/Http/Kernel.php`, in `$middlewareAliases` (or `$routeMiddleware` on older apps):
  `'admin.auth' => \App\Http\Middleware\AdminAuthenticate::class,`

Then confirm: `php <skill-dir>/scripts/scaffold.php check --path=<laravel-root>` should report all `ok`.

### 3. Migrate and create the first admin

```bash
php artisan migrate
php artisan admin:create <username> [--email=<email>]
```

`--email` is optional (only needed for the forgot-password extra). `admin:create` prompts for the password and hides it. When you run it yourself non-interactively you have to pass `--password=`; generate a strong random one with PHP so it works on any OS (`php -r "echo bin2hex(random_bytes(12));"`), give it to the user, and tell them it's temporary and they should change it by re-running `php artisan admin:create <username>`. Don't invent a weak default like `admin123` — admin panels are the first thing attackers try.

### 4. Output custom scripts on the public site (optional, ask if unclear)

The Settings screen stores header/body/footer scripts (analytics, chat widgets, verification tags). They only take effect once the site's main public layout prints them. Find that layout (often `resources/views/layouts/app.blade.php`) and add:

```blade
{!! \App\Models\Setting::get('header_scripts') !!}   {{-- just before </head> --}}
{!! \App\Models\Setting::get('body_scripts') !!}     {{-- right after <body> --}}
{!! \App\Models\Setting::get('footer_scripts') !!}   {{-- just before </body> --}}
```

This is intentionally unescaped: it's trusted admin input, the same model as any CMS "custom code" field. Never put these in the admin layouts. If the app has no shared public layout yet (a fresh app only has `welcome.blade.php`), skip this and list it as a follow-up for the user rather than editing one-off pages.

### 5. Make the dashboard about their app

Add real stats to `Admin\DashboardController` — each `'Label' => value` pair becomes a card (e.g. `'Orders today' => Order::whereDate('created_at', today())->count()`). Look at the app's models to choose 2–4 numbers the owner would actually check daily.

## Mode B: add a CRUD section (resource)

Use this for each thing the admin should manage. It produces the same pattern everywhere: list page with search + sortable columns + pagination, create/edit form with validation, an active/hidden toggle, delete with a confirmation, and an activity-log entry for every change.

```bash
php <skill-dir>/scripts/scaffold.php resource <Model> --path=<laravel-root> [--label="Blog post"] [--with-model] [--with-migration]
```

- `--with-model` / `--with-migration` create a starter model and table with `name` + `is_active`. Leave them off when the model already exists.
- It writes `Admin\<Model>Controller`, `Requests\Admin\<Model>Request`, `admin/<models>/index|form.blade.php`, inserts the routes in `routes/admin.php` and the sidebar link in the layout (at the `@admin-resources` / `@admin-nav` markers).
- Routes follow `Route::resource()` names: `admin.<models>.index|create|store|edit|update|destroy` plus `admin.<models>.toggle`.

The generated files are a **starting point that assumes a `name` column and an `is_active` boolean**. Now tailor them to the real model — this is the part that needs judgment:

1. **Read the model and its migration first.** Match the real columns. If you used `--with-migration`, edit that migration (and the model's `$fillable`/`$casts`) to the real columns *before* running `php artisan migrate` — the stub only has `name` + `is_active`. If there's no `name` column, switch the search/sort/log description to whatever identifies a row (`title`, `code`, `email`...). If the model has no `is_active`, either add a migration for it (ask the user if unsure) or remove the toggle route, method and buttons together.
2. **Fields:** for each editable column add a form block, a Form Request rule, and make sure it's in the model's `$fillable`. Match input types to columns: `textarea` for text, `number`+`step` for decimals, `form-select` for foreign keys (pass the options from the controller), checkboxes for booleans (normalize them in the Form Request's `prepareForValidation()` with `$this->boolean('field')` and give them a `boolean` rule, as the generated request already does for `is_active`, so `validated()` includes them), `type="file"` + `enctype="multipart/form-data"` for uploads.
3. **Delete guard:** if other tables reference this model, uncomment and adapt the guard at the top of `destroy()` so deletion is refused with a clear message instead of orphaning or cascading data. This is what keeps an admin from wiping out, say, every product in a category by accident.
4. **List columns:** show what the admin needs to scan quickly (price, status, counts via `withCount()`), and give columns `data-sort="text|number"`. For formatted values (currency, dates) put the raw value in `data-value` so sorting is correct.
5. **Unique codes** (coupon codes, SKUs, usernames): normalize in `prepareForValidation()` (e.g. `strtoupper(trim(...))`) so `save20` and `SAVE20` can't both exist, and restrict the format with a `regex` rule.
6. Run `php artisan migrate` if you created or changed a migration.
7. Read `references/patterns.md` when the resource is hierarchical (parent/child), needs slugs, SEO fields, image uploads, or read-only listing (e.g. orders, users).

## Conventions (why they matter)

- **Log every mutating action** with `ActivityLogger::log('thing.verb', 'Human sentence')` at the end of the action — create, update, toggle, delete, and anything like "refund" or "resend". Explicit calls (not model observers) keep logs meaningful and avoid logging seeders or background jobs as if an admin did them.
- **Validate in Form Requests**, not controllers, and only mass-assign `$request->validated()` — never `$request->all()`.
- **Guard optional keys**: write `($data['parent_id'] ?? null) ?: null`, not `$data['parent_id'] ?: null`; the latter throws "Undefined array key" when a field is absent from the request.
- **Hide, don't delete** is the default for content: the toggle keeps history and links intact. Public-facing queries should use the model's `active()` scope, and a direct visit to a hidden item should 404 (`abort_unless($item->is_active, 404)`).
- Admin pages are `noindex` and all live under `/admin`. Keep new admin controllers in `App\Http\Controllers\Admin` and new routes inside the `admin.auth` group in `routes/admin.php` — a route outside that group is a public route.
- Styling: use Bootstrap classes plus the few `admin-*` classes in `admin.css`. To rebrand, change `--admin-accent` at the top of `admin.css`.

## Verify before saying it's done

Run these, and report what you actually saw:

1. `php artisan route:list --path=admin -v` (the `-v` is what shows middleware) — every admin route except login/logout carries `admin.auth`.
2. `php artisan migrate:status` — the admin migrations ran.
3. With `php artisan serve` running: `GET /admin/dashboard` while logged out should redirect (302) to `/admin/login`; logging in with the created admin should reach the dashboard; each new resource page should return 200 once logged in. A curl session with a cookie jar works: fetch the login page, extract `_token`, POST the credentials, then request the pages.
4. For a new resource: create a record, edit it, toggle it, delete it — and check each appears in `/admin/activity-log`.

Then give the user: the admin URL, the username, how to change the password (`php artisan admin:create <username>`), and what's still theirs to do (e.g. add fields, pick dashboard stats, print custom scripts in the public layout).

## Optional extras

- **Forgot-password for admins** (needs working mail): see `references/forgot-password.md`.
- **Read-only screens** (orders, registered users with an activate/deactivate toggle): see `references/patterns.md`.
