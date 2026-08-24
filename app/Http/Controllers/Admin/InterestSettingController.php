<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateInterestSettingRequest;
use App\Models\InterestSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class InterestSettingController extends Controller
{
    public function __invoke(UpdateInterestSettingRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $setting = InterestSetting::query()
                ->whereKey(1)
                ->lockForUpdate()
                ->firstOrFail();
            $setting->update([
                'max_selections' => $request->integer('max_selections'),
            ]);
        });

        return back();
    }
}
