<?php

namespace App\Http\Responses;

use App\Support\AuthenticatedHome;
use App\Support\BannedAuthentication;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response
    {
        BannedAuthentication::rejectIfBanned($request, $request->user());

        return $request->wantsJson()
            ? new JsonResponse(['two_factor' => false])
            : redirect()->to(AuthenticatedHome::url($request->user()));
    }
}
