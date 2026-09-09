<?php

namespace App\Http\Controllers\Settings;

use App\Actions\BuildUserDataExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class UserDataExportController extends Controller
{
    public function store(Request $request, BuildUserDataExport $builder): StreamedResponse
    {
        $payload = $builder->handle($request->user());
        $filename = 'dlp-friends-data-'.now()->toDateString().'.json';

        return response()->streamDownload(
            static fn () => print json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $filename,
            ['Content-Type' => 'application/json; charset=UTF-8'],
        );
    }
}
