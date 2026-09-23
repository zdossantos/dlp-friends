<?php

namespace App\Http\Responses;

use App\Support\AuthenticatedHome;
use Illuminate\Http\Response as HttpResponse;
use Laravel\Fortify\Contracts\VerifyEmailResponse as VerifyEmailResponseContract;
use Symfony\Component\HttpFoundation\Response;

class VerifyEmailResponse implements VerifyEmailResponseContract
{
    public function toResponse($request): Response
    {
        return $request->wantsJson()
            ? new HttpResponse(status: 204)
            : redirect()->to(AuthenticatedHome::url($request->user()).'?verified=1');
    }
}
