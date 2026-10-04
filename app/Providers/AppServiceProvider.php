<?php

namespace App\Providers;

use App\Broadcasting\IdentityAwareReverbBroadcaster;
use App\Contracts\DiscoveryTieBreaker;
use App\Contracts\WebPushTransport;
use App\Reverb\IdentityAwareChannelManager;
use App\Services\MinishlinkWebPushTransport;
use App\Services\RandomDiscoveryTieBreaker;
use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DiscoveryTieBreaker::class, RandomDiscoveryTieBreaker::class);
        $this->app->bind(WebPushTransport::class, fn (): MinishlinkWebPushTransport => MinishlinkWebPushTransport::fromConfig());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        Broadcast::extend('reverb', fn ($app, array $config) => new IdentityAwareReverbBroadcaster($app->make(BroadcastManager::class)->pusher($config)));
        $this->app->booted(function (): void {
            $this->app->singleton(ChannelManager::class, IdentityAwareChannelManager::class);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
