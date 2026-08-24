<?php

namespace App\Http\Controllers\Admin;

use App\Actions\MoveInterest;
use App\Http\Controllers\Controller;
use App\Http\Requests\MoveInterestRequest;
use App\Models\Interest;
use Illuminate\Http\RedirectResponse;

class InterestOrderController extends Controller
{
    public function __construct(
        private readonly MoveInterest $moveInterest,
    ) {}

    public function __invoke(
        MoveInterestRequest $request,
        Interest $interest,
    ): RedirectResponse {
        $direction = $request->validated('direction');

        abort_unless(is_string($direction), 422);

        /** @var 'up'|'down' $direction */
        $this->moveInterest->handle($interest, $direction);

        return back();
    }
}
