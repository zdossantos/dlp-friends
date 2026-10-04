<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Http\Requests\AcceptCurrentTermsRequest;
use App\Models\User;
use App\Support\AuthenticatedHome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class TermsAcceptanceController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->user()->termsAcceptances()->where('terms_version', config('legal.terms.version'))->exists()) {
            return redirect()->to(AuthenticatedHome::url($request->user()));
        }

        return Inertia::render('auth/AcceptTerms', ['version' => config('legal.terms.version')]);
    }

    public function store(AcceptCurrentTermsRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            abort_unless($user->status === UserStatus::Active, 403);
            $user->termsAcceptances()->firstOrCreate(['terms_version' => config('legal.terms.version')], ['accepted_at' => now()]);
        }, 3);

        return redirect()->to(AuthenticatedHome::url($request->user()));
    }
}
