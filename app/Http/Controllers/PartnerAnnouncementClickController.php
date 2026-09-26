<?php

namespace App\Http\Controllers;

use App\Actions\RecordPartnerAnnouncementClick;
use Illuminate\Http\RedirectResponse;

final class PartnerAnnouncementClickController extends Controller
{
    public function __invoke(string $token, RecordPartnerAnnouncementClick $recordClick): RedirectResponse
    {
        return redirect()->away($recordClick->handle($token));
    }
}
