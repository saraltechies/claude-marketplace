<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;

class CreateAdmin extends Command
{
    protected $signature = 'admin:create {username} {--email=} {--password= : Omit to be prompted (recommended, keeps it out of shell history)}';

    protected $description = 'Create an admin panel account, or reset the password of an existing one';

    public function handle(): int
    {
        $username = $this->argument('username');
        $password = $this->option('password') ?: $this->secret('Password (min 8 characters)');

        if (!$password || strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');

            return self::FAILURE;
        }

        $admin = Admin::firstOrNew(['username' => $username]);
        $existed = $admin->exists;
        $admin->password = $password;
        if ($this->option('email')) {
            $admin->email = $this->option('email');
        }
        $admin->save();

        $this->info(($existed ? 'Updated' : 'Created') . " admin \"{$username}\". Log in at /admin/login on your site.");

        return self::SUCCESS;
    }
}
