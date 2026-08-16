<?php

namespace App\Http\Responses;

use Illuminate\Http\Response as HttpResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    public function toResponse($request): Response
    {
        return $request->wantsJson()
            ? new HttpResponse(status: 204)
            : redirect()->route('app');
    }
}
