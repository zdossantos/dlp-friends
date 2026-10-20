<?php

namespace App\Http\Controllers;

use App\Actions\UpdateVisitsVisibility;
use App\Enums\VisitsVisibility;
use App\Http\Requests\UpdateVisitsVisibilityRequest;
use Illuminate\Http\RedirectResponse;

final class VisitsVisibilityController
{
    public function __invoke(UpdateVisitsVisibilityRequest $request, UpdateVisitsVisibility $action): RedirectResponse
    {
        $action->handle($request->user(), VisitsVisibility::from($request->string('visibility')->toString()), $request->boolean('confirmed'));

        return back();
    }
}
