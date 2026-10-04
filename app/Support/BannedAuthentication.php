<?php

namespace App\Support;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class BannedAuthentication
{
    public static function message(): string
    {
        $contact = config('legal.contact_email');

        return __('moderation.banned').(is_string($contact) && $contact !== '' ? ' '.__('moderation.banned_contact', ['email' => $contact]) : '');
    }

    public static function rejectIfBanned(Request $request, User $user): void
    {
        if (User::query()->whereKey($user->id)->value('status') !== UserStatus::Banned) {
            return;
        }
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        throw ValidationException::withMessages(['email' => self::message()]);
    }
}
