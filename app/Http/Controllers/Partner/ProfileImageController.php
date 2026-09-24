<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\PartnerProfileRevision;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ProfileImageController extends Controller
{
    public function __invoke(PartnerProfileRevision $revision): StreamedResponse
    {
        $revision->loadMissing('partnerProfile');
        Gate::authorize('viewRevision', [$revision->partnerProfile, $revision]);

        abort_if($revision->image_path === null, 404);
        abort_unless(Storage::exists($revision->image_path), 404);

        return Storage::response($revision->image_path);
    }
}
