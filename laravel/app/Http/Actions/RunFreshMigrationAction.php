<?php

namespace App\Http\Actions;

use Illuminate\Support\Facades\Artisan;

class RunFreshMigrationAction
{
    /**
     * Drops every table, runs all migrations again and loads the seed data.
     */
    public static function execute(): string
    {
        // Shared hosting caps execution time low and a fresh migration with
        // seeds easily crosses 30s. The raise is best effort: the host may
        // refuse it, and then the request dies halfway through the schema.
        @set_time_limit((int) config('deploy.timeout'));

        Artisan::call('migrate:fresh', [
            // Without --force the command refuses to run outside development.
            '--force' => true,
            '--seed' => true,
        ]);

        return trim(Artisan::output());
    }
}
