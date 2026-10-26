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
            'brand' => trans('brand'),
            'common' => trans('common'),
            'account' => trans('account'),
            'profile' => trans('profile'),
            'onboarding' => $onboarding,
            'discovery' => trans('discovery'),
            'conversations' => trans('conversations'),
            'notifications' => trans('notifications'),
            'events' => trans('events'),
            'visits' => trans('visits'),
            'trips' => trans('trips'),
            'outings' => trans('outings'),
            'blocking' => trans('blocking'),
            'moderation' => trans('moderation'),
            'administration' => trans('administration'),
            'partners' => trans('partners'),
            'application_announcements' => trans('application_announcements'),
        ];
    }
}
