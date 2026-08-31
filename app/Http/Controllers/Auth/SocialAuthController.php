<?php

namespace App\Http\Controllers\Auth;

use App\Data\PendingSocialIdentity;
use App\Enums\SocialProvider;
use App\Enums\UserStatus;
use App\Exceptions\SocialAuthenticationException;
use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class SocialAuthController extends Controller
{
    public function redirect(string $provider): RedirectResponse|SymfonyRedirectResponse
    {
        return Socialite::driver(SocialProvider::from($provider)->value)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        try {
            if ($request->filled('error')) {
                throw new SocialAuthenticationException('social_auth.cancelled');
            }

            $socialProvider = SocialProvider::from($provider);
            $socialiteUser = Socialite::driver($socialProvider->value)->user();
            $providerUserId = trim((string) $socialiteUser->getId());

            if ($providerUserId === '') {
                throw new SocialAuthenticationException('social_auth.invalid_identity');
            }

            $account = SocialAccount::query()
                ->with('user')
                ->where('provider', $socialProvider->value)
                ->where('provider_user_id', $providerUserId)
                ->first();

            if ($account !== null) {
                if ($account->user->status !== UserStatus::Active) {
                    throw new SocialAuthenticationException('social_auth.inactive');
                }

                Auth::login($account->user);
                $request->session()->regenerate();

                return redirect()->route('app');
            }

            $email = Str::lower(trim((string) $socialiteUser->getEmail()));

            if ($email === '') {
                throw new SocialAuthenticationException('social_auth.email_required');
            }

            if (! $socialProvider->hasVerifiedEmail($socialiteUser)) {
                throw new SocialAuthenticationException('social_auth.email_unverified');
            }

            if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
                throw new SocialAuthenticationException('social_auth.email_conflict');
            }

            $identity = new PendingSocialIdentity($socialProvider, $providerUserId, $email);
            $request->session()->put(PendingSocialIdentity::SESSION_KEY, $identity->toSession());

            return redirect()->route('social.registration.create');
        } catch (InvalidStateException) {
            return $this->failure('social_auth.invalid_callback');
        } catch (SocialAuthenticationException $exception) {
            return $this->failure($exception->translationKey());
        }
    }

    private function failure(string $translationKey): RedirectResponse
    {
        return redirect()->route('login')->withErrors([
            'social_auth' => __($translationKey),
        ]);
    }
}
