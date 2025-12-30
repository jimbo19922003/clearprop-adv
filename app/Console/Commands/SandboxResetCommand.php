<?php

namespace App\Console\Commands;

use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class SandboxResetCommand extends Command
{
    protected $signature = 'sandbox:reset {--seed : Seed demo data after migrating}';
    protected $description = 'Reset the /sandbox database (SQLite) and optionally seed demo data.';

    public function handle(): int
    {
        if (!((bool) env('SANDBOX_ENABLED', false))) {
            $this->error('SANDBOX_ENABLED is false. Refusing to reset sandbox.');
            return self::FAILURE;
        }

        $path = config('database.connections.sqlite_sandbox.database');
        if (!is_string($path) || $path === '') {
            $this->error('Sandbox database path is not configured.');
            return self::FAILURE;
        }

        $this->info('Resetting sandbox database: ' . $path);

        // Recreate file.
        if (File::exists($path)) {
            File::delete($path);
        }
        File::ensureDirectoryExists(dirname($path));
        File::put($path, '');

        // Migrate app tables
        $this->info('Running migrations (app)...');
        Artisan::call('migrate', [
            '--database' => 'sqlite_sandbox',
            '--path' => 'database/migrations',
            '--force' => true,
        ]);
        $this->output->write(Artisan::output());

        // Migrate settings tables/rows (Spatie settings migrations)
        $this->info('Running migrations (settings)...');
        Artisan::call('migrate', [
            '--database' => 'sqlite_sandbox',
            '--path' => 'database/settings',
            '--force' => true,
        ]);
        $this->output->write(Artisan::output());

        if ($this->option('seed')) {
            $this->info('Seeding demo data...');
            Artisan::call('db:seed', [
                '--class' => DemoDataSeeder::class,
                '--database' => 'sqlite_sandbox',
                '--force' => true,
            ]);
            $this->output->write(Artisan::output());
        }

        $this->info('Sandbox reset complete.');
        return self::SUCCESS;
    }
}

