<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMemberFeedbackRequest;
use App\Models\MemberFeedback;
use Illuminate\Http\RedirectResponse;

final class MemberFeedbackController extends Controller
{
    public function store(StoreMemberFeedbackRequest $request): RedirectResponse
    {
        MemberFeedback::query()->create([
            'user_id' => $request->user()->id,
            ...$request->safe()->only(['kind', 'area', 'content']),
        ]);

        return back();
    }
}
