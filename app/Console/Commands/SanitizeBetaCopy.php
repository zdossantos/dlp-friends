<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SanitizeBetaCopy extends Command
{
    protected $signature = 'beta:sanitize-copy {--force}';

    protected $description = 'Remove production authentication and delivery credentials from a closed beta copy';

    public function handle(): int
    {
        if (! app()->environment('beta') || ! app()->isDownForMaintenance() || ! $this->option('force')) {
            $this->error('beta_closed_confirmation_required');

            return self::FAILURE;
        }

        DB::transaction(function (): void {
            // Query builder deliberately avoids decrypting values made with the production key.
            foreach (['web_push_deliveries', 'web_push_subscriptions', 'sessions', 'password_reset_tokens',
                'passkeys', 'social_accounts', 'jobs', 'job_batches', 'failed_jobs'] as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->delete();
                }
            }

            $columns = array_filter(['remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at'],
                fn (string $column): bool => Schema::hasColumn('users', $column));
            if ($columns !== []) {
                DB::table('users')->update(array_fill_keys($columns, null));
            }
        });

        $this->line('sanitized');

        return self::SUCCESS;
    }
}
