<?php

namespace App\Http\Responses;

use App\Support\AuthenticatedHome;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Symfony\Component\HttpFoundation\Response;

class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request): Response
    {
        return $request->wantsJson()
            ? new JsonResponse(status: 201)
            : redirect()->to(AuthenticatedHome::url($request->user()));
    }
}
