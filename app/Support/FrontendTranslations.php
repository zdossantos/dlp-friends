<?php

namespace App\Support;

final class FrontendTranslations
{
    /** @return array<string, array<string, mixed>> */
    public static function messages(): array
    {
        /** @var array<string, mixed> $onboarding */
        $onboarding = trans('onboarding');
        unset($onboarding['demo_profiles']);

        return [
            'common' => trans('common'),
            'account' => trans('account'),
            'profile' => trans('profile'),
            'onboarding' => $onboarding,
            'discovery' => trans('discovery'),
            'conversations' => trans('conversations'),
            'blocking' => trans('blocking'),
            'administration' => trans('administration'),
        ];
    }
}
