<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateLocaleRequest;
use Illuminate\Http\RedirectResponse;

class LocaleController extends Controller
{
    public function __invoke(UpdateLocaleRequest $request): RedirectResponse
    {
        $locale = $request->string('locale')->toString();

        $request->user()?->forceFill(['locale' => $locale])->save();

        return back()->withCookie(cookie(
            name: 'locale',
            value: $locale,
            minutes: 60 * 24 * 365,
            secure: $request->isSecure(),
            httpOnly: true,
            sameSite: 'lax',
        ));
    }
}
