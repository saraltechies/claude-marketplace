<?php

/**
 * Laravel admin panel scaffolder.
 *
 *   php scaffold.php install  [--path=<laravel-root>] [--force]
 *   php scaffold.php resource <Model> [--label="Blog post"] [--with-model] [--with-migration] [--path=<laravel-root>] [--force]
 *   php scaffold.php check    [--path=<laravel-root>]
 *
 * Never overwrites an existing file unless --force is given; skipped files are reported.
 * Edits to existing project files are limited to: one require line in routes/web.php,
 * and inserting at the "@admin-resources" / "@admin-nav" markers this script itself installed.
 */

const STUBS = __DIR__ . '/../stubs';

[$command, $args, $opts] = parseArgv(array_slice($argv, 1));
$root = realpath($opts['path'] ?? getcwd());

if (!$root || !is_file("$root/artisan")) {
    fail('Not a Laravel project root (no artisan file). Pass --path=<laravel-root>.');
}
if (!is_file("$root/vendor/autoload.php")) {
    fail('vendor/ is missing. Run "composer install" in the project first.');
}
require "$root/vendor/autoload.php";

$force = isset($opts['force']);

switch ($command) {
    case 'install':
        install($root, $force);
        check($root);
        break;
    case 'resource':
        if (empty($args[0])) {
            fail('Usage: php scaffold.php resource <Model> [--label="Blog post"] [--with-model] [--with-migration]');
        }
        resource($root, $args[0], $opts, $force);
        break;
    case 'check':
        exit(check($root) ? 0 : 1);
    default:
        fail("Unknown command \"$command\". Use install, resource or check.");
}

// ---------------------------------------------------------------------------

function install(string $root, bool $force): void
{
    out("Installing admin panel into $root");
    $base = STUBS . '/install';
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    $migrationIndex = 0;

    $files = [];
    foreach ($iterator as $file) {
        $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($base) + 1));
    }
    // Migrations must run in a fixed order (activity_logs references admins).
    $order = ['create_admins_table.php', 'create_activity_logs_table.php', 'create_settings_table.php'];
    usort($files, function ($a, $b) use ($order) {
        $ia = array_search(basename($a), $order, true);
        $ib = array_search(basename($b), $order, true);
        if ($ia !== false && $ib !== false) {
            return $ia <=> $ib;
        }
        return strcmp($a, $b);
    });

    foreach ($files as $relative) {
        $source = "$base/$relative";

        if (str_starts_with($relative, 'database/migrations/')) {
            $name = basename($relative);
            $existing = glob("$root/database/migrations/*_$name");
            if ($existing && !$force) {
                out("  skip   database/migrations/" . basename($existing[0]) . ' (already exists)');
                continue;
            }
            $target = $existing ? $existing[0] : "$root/database/migrations/" . date('Y_m_d_His', time() + $migrationIndex++) . "_$name";
            writeFile($target, file_get_contents($source), true, $root);
            continue;
        }

        writeFile("$root/$relative", file_get_contents($source), $force, $root);
    }

    // Load routes/admin.php from routes/web.php (keeps the web middleware group: sessions + CSRF).
    $web = "$root/routes/web.php";
    $webContents = file_get_contents($web);
    if (!str_contains($webContents, "admin.php")) {
        file_put_contents($web, rtrim($webContents) . "\n\nrequire __DIR__.'/admin.php';\n");
        out('  edit   routes/web.php (require admin.php)');
    }
}

function resource(string $root, string $model, array $opts, bool $force): void
{
    $Str = Illuminate\Support\Str::class;
    $model = $Str::studly($model);
    $label = $opts['label'] ?? $Str::ucfirst(str_replace('_', ' ', $Str::snake($model)));
    $labels = $opts['plural-label'] ?? $Str::plural($label);
    $route = $Str::kebab($Str::plural($model));

    $vars = [
        '{{Model}}' => $model,
        '{{modelVar}}' => $Str::camel($model),
        '{{modelsVar}}' => $Str::camel($Str::plural($model)),
        '{{route}}' => $route,
        '{{table}}' => $Str::snake($Str::plural($model)),
        '{{Label}}' => $label,
        '{{Labels}}' => $labels,
        '{{labelLower}}' => mb_strtolower($label),
        '{{labelsLower}}' => mb_strtolower($labels),
    ];
    $render = fn (string $stub) => strtr(file_get_contents(STUBS . "/resource/$stub"), $vars);

    if (!is_file("$root/routes/admin.php") || !is_file("$root/resources/views/layouts/admin.blade.php")) {
        fail('Admin panel is not installed yet. Run "php scaffold.php install" first.');
    }

    out("Adding admin resource $model (/admin/$route)");

    if (isset($opts['with-model'])) {
        writeFile("$root/app/Models/$model.php", $render('model.stub'), $force, $root);
    } elseif (!is_file("$root/app/Models/$model.php")) {
        out("  note   app/Models/$model.php does not exist - create it or re-run with --with-model");
    }
    if (isset($opts['with-migration'])) {
        $name = "create_{$vars['{{table}}']}_table.php";
        if (glob("$root/database/migrations/*_$name") && !$force) {
            out("  skip   migration $name (already exists)");
        } else {
            writeFile("$root/database/migrations/" . date('Y_m_d_His') . "_$name", $render('migration.stub'), true, $root);
        }
    }

    writeFile("$root/app/Http/Controllers/Admin/{$model}Controller.php", $render('Controller.stub'), $force, $root);
    writeFile("$root/app/Http/Requests/Admin/{$model}Request.php", $render('Request.stub'), $force, $root);
    writeFile("$root/resources/views/admin/$route/index.blade.php", $render('index.stub'), $force, $root);
    writeFile("$root/resources/views/admin/$route/form.blade.php", $render('form.stub'), $force, $root);

    insertAtMarker("$root/routes/admin.php", '// @admin-resources', $render('routes.stub'), "'$route'", $root);
    insertAtMarker("$root/resources/views/layouts/admin.blade.php", '{{-- @admin-nav', $render('nav.stub'), "admin.$route.index", $root);

    out("Done. Admin URL: /admin/$route");
}

function check(string $root): bool
{
    out("\nWiring check:");
    $ok = true;
    $auth = @file_get_contents("$root/config/auth.php") ?: '';
    $boot = @file_get_contents("$root/bootstrap/app.php") ?: '';
    $kernel = @file_get_contents("$root/app/Http/Kernel.php") ?: '';

    $checks = [
        "config/auth.php has an 'admin' guard" => (bool) preg_match("/'admin'\s*=>\s*\[\s*'driver'\s*=>\s*'session'/", $auth),
        "config/auth.php has an 'admins' provider" => (bool) preg_match("/'admins'\s*=>\s*\[\s*'driver'\s*=>\s*'eloquent'/", $auth),
        "'admin.auth' middleware alias registered" => str_contains($boot . $kernel, 'AdminAuthenticate'),
        'routes/web.php requires admin.php' => str_contains((string) @file_get_contents("$root/routes/web.php"), 'admin.php'),
    ];
    foreach ($checks as $label => $passed) {
        out(($passed ? '  ok     ' : '  TODO   ') . $label);
        $ok = $ok && $passed;
    }
    if (!$ok) {
        out('Finish the TODO items (see SKILL.md "Wire it up"), then run: php scaffold.php check');
    }

    return $ok;
}

// ---------------------------------------------------------------------------

function writeFile(string $target, string $contents, bool $force, string $root): void
{
    $relative = ltrim(str_replace('\\', '/', substr($target, strlen($root))), '/');
    if (is_file($target) && !$force) {
        out("  skip   $relative (already exists, use --force to overwrite)");
        return;
    }
    $existed = is_file($target);
    if (!is_dir(dirname($target))) {
        mkdir(dirname($target), 0775, true);
    }
    file_put_contents($target, $contents);
    out(($existed ? '  over   ' : '  create ') . $relative);
}

function insertAtMarker(string $file, string $marker, string $snippet, string $alreadyThere, string $root): void
{
    $relative = ltrim(str_replace('\\', '/', substr($file, strlen($root))), '/');
    $contents = file_get_contents($file);
    if (str_contains($contents, $alreadyThere)) {
        out("  skip   $relative (entry already present)");
        return;
    }
    $pos = strpos($contents, $marker);
    if ($pos === false) {
        out("  TODO   $relative has no \"$marker\" marker - add this manually:\n$snippet");
        return;
    }
    $lineStart = strrpos(substr($contents, 0, $pos), "\n");
    $lineStart = $lineStart === false ? 0 : $lineStart + 1;
    file_put_contents($file, substr($contents, 0, $lineStart) . $snippet . substr($contents, $lineStart));
    out("  edit   $relative");
}

function parseArgv(array $argv): array
{
    $command = null;
    $args = [];
    $opts = [];
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--')) {
            [$key, $value] = array_pad(explode('=', substr($arg, 2), 2), 2, true);
            $opts[$key] = $value;
        } elseif ($command === null) {
            $command = $arg;
        } else {
            $args[] = $arg;
        }
    }

    return [$command ?? '', $args, $opts];
}

function out(string $line): void
{
    fwrite(STDOUT, $line . PHP_EOL);
}

function fail(string $message): never
{
    fwrite(STDERR, "Error: $message" . PHP_EOL);
    exit(1);
}
