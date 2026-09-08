<?php

namespace App\Http\Controllers\Settings;

use App\Enums\UserDataExportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AccountUpdateRequest;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function edit(Request $request): Response
    {
        $export = $request->user()->dataExports()->latest()->first();

        return Inertia::render('settings/Account', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'dataExport' => $export === null ? null : [
                'status' => $export->status->value,
                'expires_at' => $export->expires_at?->toISOString(),
                'download_url' => $export->status === UserDataExportStatus::Ready
                    && $export->expires_at?->isFuture()
                    ? URL::temporarySignedRoute(
                        'data-export.download',
                        min($export->expires_at, now()->addMinutes(10)),
                        ['export' => $export],
                    )
                    : null,
            ],
        ]);
    }

    public function update(AccountUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Compte mis à jour.')]);

        return $request->user()->email_verified_at === null
            ? to_route('verification.notice')
            : to_route('account.edit');
    }

    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();
        $user->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
