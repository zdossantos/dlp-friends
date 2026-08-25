<?php

namespace App\Http\Controllers\Admin;

use App\Actions\MoveAvatar;
use App\Http\Controllers\Controller;
use App\Http\Requests\MoveAvatarRequest;
use App\Models\Avatar;
use Illuminate\Http\RedirectResponse;

class AvatarOrderController extends Controller
{
    public function __construct(private readonly MoveAvatar $moveAvatar) {}

    public function __invoke(MoveAvatarRequest $request, Avatar $avatar): RedirectResponse
    {
        $direction = $request->validated('direction');

        abort_unless(is_string($direction), 422);

        /** @var 'up'|'down' $direction */
        $this->moveAvatar->handle($avatar, $direction);

        return back();
    }
}
