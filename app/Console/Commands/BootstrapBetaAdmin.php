<?php

namespace App\Console\Commands;

use App\Actions\AssignRole;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BootstrapBetaAdmin extends Command
{
    protected $signature = 'beta:bootstrap-admin {--credentials=} {--force}';

    protected $description = 'Create the dedicated administrator of an empty, closed beta';

    public function handle(AssignRole $assignRole): int
    {
        if (! app()->environment('beta') || ! app()->isDownForMaintenance() || ! $this->option('force')) {
            $this->error('beta_closed_confirmation_required');

            return self::FAILURE;
        }

        $path = $this->option('credentials');
        if (! is_string($path) || ! is_file($path) || is_link($path) || (fileperms($path) & 0777) !== 0600 || filesize($path) > 4096) {
            $this->error('private_credentials_required');

            return self::FAILURE;
        }

        $contents = file_get_contents($path);
        $credentials = $contents === false ? null : json_decode($contents, true);
        if (! is_array($credentials) || Validator::make($credentials, [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:48', 'max:255'],
        ])->fails()) {
            $this->error('invalid_beta_credentials');

            return self::FAILURE;
        }

        if (User::query()->exists()) {
            $this->error('empty_beta_required');

            return self::FAILURE;
        }

        DB::transaction(function () use ($credentials, $assignRole): void {
            $user = User::withoutEvents(fn (): User => User::query()->create([
                'email' => $credentials['email'], 'password' => $credentials['password'],
                'birth_date' => now()->subYears(25)->toDateString(), 'locale' => 'fr',
            ]));
            $user->forceFill(['email_verified_at' => now()])->save();
            $assignRole->handle($user, RoleName::User);
            $assignRole->handle($user, RoleName::Admin);
        });

        $this->line('beta_admin_created');

        return self::SUCCESS;
    }
}
