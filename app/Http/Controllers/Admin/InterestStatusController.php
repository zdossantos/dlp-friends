<?php

namespace App\Http\Controllers\Admin;

use App\Actions\SetInterestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateInterestStatusRequest;
use App\Models\Interest;
use Illuminate\Http\RedirectResponse;

class InterestStatusController extends Controller
{
    public function __construct(
        private readonly SetInterestStatus $setInterestStatus,
    ) {}

    public function __invoke(
        UpdateInterestStatusRequest $request,
        Interest $interest,
    ): RedirectResponse {
        $this->setInterestStatus->handle($interest, $request->boolean('is_active'));

        return back();
    }
}
