<?php

namespace App\Http\Controllers\Admin;

use App\Actions\SetInterestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateInterestStatusRequest;
use App\Models\Interest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class InterestStatusController extends Controller
{
    public function __construct(
        private readonly SetInterestStatus $setInterestStatus,
    ) {}

    public function __invoke(
        UpdateInterestStatusRequest $request,
        Interest $interest,
    ): RedirectResponse {
        $isActive = $request->boolean('is_active');

        $this->setInterestStatus->handle($interest, $isActive);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $isActive
                ? 'Intérêt réactivé.'
                : 'Intérêt archivé.',
        ]);

        return back();
    }
}
