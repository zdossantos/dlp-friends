<?php

namespace App\Http\Controllers\Auth;

use App\Actions\CreateSocialUser;
use App\Data\PendingSocialIdentity;
use App\Exceptions\SocialAuthenticationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteSocialRegistrationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class SocialRegistrationController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if ($this->identity($request) === null) {
            return $this->expired($request);
        }

        return Inertia::render('auth/CompleteSocialRegistration');
    }

    public function store(
        CompleteSocialRegistrationRequest $request,
        CreateSocialUser $createSocialUser,
    ): RedirectResponse {
        $identity = $this->identity($request);

        if ($identity === null) {
            return $this->expired($request);
        }

        try {
            $user = $createSocialUser->execute($identity, $request->string('birth_date')->toString());
        } catch (SocialAuthenticationException $exception) {
            $request->session()->forget(PendingSocialIdentity::SESSION_KEY);

            return redirect()->route('login')->withErrors([
                'social_auth' => __($exception->translationKey()),
            ]);
        }

        $request->session()->forget(PendingSocialIdentity::SESSION_KEY);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('app');
    }

    private function identity(Request $request): ?PendingSocialIdentity
    {
        $payload = $request->session()->get(PendingSocialIdentity::SESSION_KEY);

        if (! is_array($payload)) {
            return null;
        }

        try {
            return PendingSocialIdentity::fromSession($payload);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function expired(Request $request): RedirectResponse
    {
        $request->session()->forget(PendingSocialIdentity::SESSION_KEY);

        return redirect()->route('login')->withErrors([
            'social_auth' => __('social_auth.expired'),
        ]);
    }
}
