<?php

namespace App\Http\Responses;

use App\Support\AuthenticatedHome;
use Illuminate\Http\JsonResponse;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class PasskeyLoginResponse implements PasskeyLoginResponseContract
{
    public function toResponse($request): Response
    {
        return $request->wantsJson()
            ? new JsonResponse(['redirect' => AuthenticatedHome::url($request->user())])
            : redirect()->to(AuthenticatedHome::url($request->user()));
    }
}
