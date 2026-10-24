<?php

namespace App\Support;

use Illuminate\Database\Migrations\Migrator;
use Throwable;

final class DeploymentReadiness
{
    public function __construct(private readonly Migrator $migrator) {}

    public function assertReady(bool $allowMaintenance = false): void
    {
        if (app()->environment('beta')) {
            $this->assertBetaConfiguration();
        }

        $retryAfter = config('queue.connections.'.config('queue.default').'.retry_after');
        if ($retryAfter !== null && (int) $retryAfter < 120) {
            throw new DeploymentNotReady('unsafe_queue_reservation');
        }

        try {
            if (! $allowMaintenance && app()->isDownForMaintenance()) {
                throw new DeploymentNotReady('maintenance');
            }

            if (! $this->migrator->repositoryExists()) {
                throw new DeploymentNotReady('missing_migrations_table');
            }

            $files = $this->migrator->getMigrationFiles([
                ...$this->migrator->paths(), database_path('migrations'),
            ]);
            $pending = array_values(array_diff(array_keys($files), $this->migrator->getRepository()->getRan()));
            if ($pending !== []) {
                throw new DeploymentNotReady('pending_migrations', $pending);
            }
        } catch (DeploymentNotReady $exception) {
            throw $exception;
        } catch (Throwable) {
            // Never expose SQL, connection details or credentials in a readiness response.
            throw new DeploymentNotReady('dependency_unavailable');
        }
    }

    public function assertBetaConfiguration(): void
    {
        $host = config('deployment.beta_mail_host');
        if (! is_string($host) || $host === ''
            || config('mail.default') !== 'smtp'
            || config('mail.mailers.smtp.host') !== $host
            || (int) config('mail.mailers.smtp.port') !== 1025
            || filled(config('mail.mailers.smtp.url'))
            || filled(config('services.resend.key'))
            || config('app.debug') !== false
            || config('app.url') !== 'https://beta.dlp-friends.fr'
            || filled(config('session.domain'))
            || config('session.secure') !== true
            || config('session.driver') !== 'redis'
            || config('cache.default') !== 'redis'
            || config('queue.default') !== 'redis') {
            throw new DeploymentNotReady('unsafe_beta_configuration');
        }
    }
}
