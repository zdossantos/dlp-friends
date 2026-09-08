<?php

namespace App\Http\Controllers\Settings;

use App\Actions\RequestUserDataExport;
use App\Enums\UserDataExportStatus;
use App\Http\Controllers\Controller;
use App\Models\UserDataExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class UserDataExportController extends Controller
{
    public function store(Request $request, RequestUserDataExport $requestExport): RedirectResponse
    {
        $requestExport->handle($request->user());

        return to_route('account.edit');
    }

    public function download(Request $request, UserDataExport $export): StreamedResponse|BinaryFileResponse
    {
        abort_if($export->user_id !== $request->user()->id, 404);
        Gate::authorize('download', $export);
        abort_unless(
            $export->status === UserDataExportStatus::Ready
                && $export->path !== null
                && $export->expires_at?->isFuture()
                && Storage::disk((string) config('data-control.exports.disk'))->exists($export->path),
            404,
        );

        return Storage::disk((string) config('data-control.exports.disk'))->download(
            $export->path,
            "dlp-friends-data-{$export->id}.json",
            ['Content-Type' => 'application/json; charset=UTF-8'],
        );
    }
}
