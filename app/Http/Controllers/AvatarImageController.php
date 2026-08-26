<?php

namespace App\Http\Controllers;

use App\Models\Avatar;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AvatarImageController extends Controller
{
    public function __invoke(Avatar $avatar): StreamedResponse
    {
        abort_unless(Storage::exists($avatar->image_path), 404);

        return Storage::response($avatar->image_path);
    }
}
