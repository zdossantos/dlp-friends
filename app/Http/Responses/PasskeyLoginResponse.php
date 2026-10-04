<?php

namespace App\Http\Responses;

use App\Support\AuthenticatedHome;
use App\Support\BannedAuthentication;
use Illuminate\Http\JsonResponse;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class PasskeyLoginResponse implements PasskeyLoginResponseContract
{
    public function toResponse($request): Response
    {
        BannedAuthentication::rejectIfBanned($request, $request->user());

        return $request->wantsJson()
            ? new JsonResponse(['redirect' => AuthenticatedHome::url($request->user())])
            : redirect()->to(AuthenticatedHome::url($request->user()));
    }
}
