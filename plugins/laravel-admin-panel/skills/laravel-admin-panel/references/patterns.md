# Admin resource patterns

Read the section that matches the resource you're building. Each one adapts the files the `resource` command generated.

- [Slugs](#slugs)
- [SEO fields](#seo-fields)
- [Foreign-key dropdowns](#foreign-key-dropdowns)
- [Hierarchical (parent/child) resources](#hierarchical-parentchild-resources)
- [Image / file uploads](#image--file-uploads)
- [Read-only listings (orders, payments)](#read-only-listings)
- [Managing site users (activate/deactivate)](#managing-site-users)
- [Server-side sort for large tables](#server-side-sort-for-large-tables)

## Slugs

Generate the slug on create only, so published URLs don't change when an admin fixes a typo in the name:

```php
private function uniqueSlug(string $name): string
{
    $base = Str::slug($name) ?: 'item';
    $slug = $base;
    $i = 1;
    while (Category::where('slug', $slug)->exists()) {
        $slug = $base . '-' . (++$i);
    }
    return $slug;
}

// store():
$category = Category::create($request->validated() + ['slug' => $this->uniqueSlug($request->validated('name'))]);
```

Add a unique index on `slug` in the migration. If the admin needs to edit slugs, add a `slug` field with `['required', 'alpha_dash', Rule::unique(...)->ignore(...)]`.

## SEO fields

Nullable `meta_title` (255) and `meta_description` (500) columns, an "SEO (optional)" section at the bottom of the form, and a fallback on the public page:

```php
'meta_title' => ['nullable', 'string', 'max:255'],
'meta_description' => ['nullable', 'string', 'max:500'],
```

```blade
<h2 class="h6 mt-4">SEO <span class="text-secondary fw-normal">(optional — leave blank to auto-generate)</span></h2>
<div class="mb-3">
    <label class="form-label" for="meta_title">Meta title</label>
    <input type="text" id="meta_title" name="meta_title" class="form-control" maxlength="255" value="{{ old('meta_title', $item->meta_title) }}">
</div>
<div class="mb-3">
    <label class="form-label" for="meta_description">Meta description</label>
    <textarea id="meta_description" name="meta_description" class="form-control" rows="2" maxlength="500">{{ old('meta_description', $item->meta_description) }}</textarea>
</div>
```

Public side: `$title = $item->meta_title ?: $item->name . ' | ' . config('app.name');`

## Foreign-key dropdowns

Pass options from the controller's `create()` and `edit()`; validate with `exists`; use a nullable FK with `nullOnDelete()` when the relation is optional, and a delete guard on the parent side when it's required.

```php
'publisher_id' => ['nullable', 'integer', 'exists:publishers,id'],
```

```blade
<select id="publisher_id" name="publisher_id" class="form-select">
    <option value="">— None —</option>
    @foreach($publishers as $publisher)
        <option value="{{ $publisher->id }}" @selected(old('publisher_id', $item->publisher_id) == $publisher->id)>
            {{ $publisher->name }}{{ $publisher->is_active ? '' : ' (hidden)' }}
        </option>
    @endforeach
</select>
```

Include hidden options (labelled) so an admin can still assign to something temporarily hidden. When saving, normalize the "None" option: `($data['publisher_id'] ?? null) ?: null`.

## Hierarchical (parent/child) resources

For categories and similar trees, a nullable self-referencing `parent_id`:

```php
$table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
$table->unique(['parent_id', 'name']); // same name allowed under different parents
```

Model:

```php
public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
public function children(): HasMany { return $this->hasMany(self::class, 'parent_id')->orderBy('name'); }

/** Ids of this node and everything below it - a node can't be moved under any of these. */
public function descendantIds(): array
{
    $ids = [$this->id];
    foreach ($this->children as $child) {
        $ids = array_merge($ids, $child->descendantIds());
    }
    return $ids;
}

/** Every row in display order with its depth, for indented lists and <select>s. Loads all rows once (no N+1). */
public static function flatTree(): array
{
    $byParent = static::orderBy('name')->get()->groupBy(fn ($c) => $c->parent_id ?? 0);
    $walk = function ($parentId, $depth) use (&$walk, $byParent) {
        $rows = [];
        foreach ($byParent->get($parentId, collect()) as $node) {
            $rows[] = ['item' => $node, 'depth' => $depth];
            $rows = array_merge($rows, $walk($node->id, $depth + 1));
        }
        return $rows;
    };
    return $walk(0, 0);
}
```

Validation — the unique rule has to handle the top level (NULL parent) explicitly, because SQL `NULL = NULL` is never true, and the DB unique index won't catch top-level duplicates on its own:

```php
$category = $this->route('category');
$parentId = $this->input('parent_id') ?: null;

return [
    'name' => ['required', 'string', 'max:100',
        Rule::unique('categories', 'name')
            ->where(fn ($q) => $parentId ? $q->where('parent_id', $parentId) : $q->whereNull('parent_id'))
            ->ignore($category?->id),
    ],
    'parent_id' => array_filter(['nullable', 'integer', 'exists:categories,id',
        $category ? Rule::notIn($category->descendantIds()) : null]),
];
```

In `edit()`, exclude `$category->descendantIds()` from the parent options. In `destroy()`, refuse while `children()->exists()` as well as while dependent records exist.

List view: render `flatTree()` with indentation (`style="padding-left: {{ 16 + $row['depth'] * 22 }}px"`, prefix children with `↳`). Keep the search box but **drop `data-sort` and pagination** here — sorting or paging would break the parent/child grouping.

## Image / file uploads

```php
'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
```

```php
$data = $request->validated();
if ($request->hasFile('image')) {
    if ($item->image_path) {
        Storage::disk('public')->delete($item->image_path);
    }
    $data['image_path'] = $request->file('image')->store('items', 'public');
}
unset($data['image']);
$item->update($data);
```

The form needs `enctype="multipart/form-data"`; show the current image with `asset('storage/'.$item->image_path)` (requires `php artisan storage:link`). Files that must not be publicly downloadable (paid downloads, private documents) go on the `local` disk instead and are served through a controller route that checks permission.

## Read-only listings

For things created by the site, not the admin (orders, payments, form submissions): generate the resource, then delete `create/store/edit/update/destroy`, the form view and the "+ Add" button, and register only what's used:

```php
Route::get('/orders', [Admin\OrderController::class, 'index'])->name('orders.index');
Route::get('/orders/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
```

Eager-load what the table shows (`Order::with('user')->withCount('items')`) to avoid N+1 queries, and add useful filters as GET params (status, date range) alongside `q`.

## Managing site users

List the app's `User`s (`withCount` of their orders or posts) with an activate/deactivate toggle — don't edit their identity fields from admin. Add a boolean `is_active` (default true) to `users`, then enforce it in two places:

1. At login: `Auth::attempt($credentials + ['is_active' => true])`, or check after attempt and log them out with a clear message.
2. Mid-session, so deactivating someone takes effect on their very next request — a middleware on the site's authenticated routes:

```php
public function handle(Request $request, Closure $next): Response
{
    $user = $request->user();
    if ($user && !$user->is_active) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login')->withErrors(['email' => 'Your account has been deactivated.']);
    }
    return $next($request);
}
```

## Server-side sort for large tables

The bundled `data-sort` headers sort only the rows on the current page. For big tables where users need a true global sort, use links instead:

```php
$sort = in_array($request->query('sort'), ['name', 'price', 'created_at'], true) ? $request->query('sort') : 'name';
$dir = $request->query('dir') === 'desc' ? 'desc' : 'asc';
$items = Item::orderBy($sort, $dir)->paginate(20)->withQueryString();
```

Whitelist the column name as shown — never pass the query string straight into `orderBy`. Remove `data-sort` from those headers so the two mechanisms don't conflict.
