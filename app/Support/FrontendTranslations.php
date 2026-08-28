<?php

namespace App\Support;

final class FrontendTranslations
{
    /** @return array<string, array<string, string>> */
    public static function messages(): array
    {
        /** @var array{
         *     copy: array<string, string>,
         *     onboarding: array<string, mixed>,
         *     admin_onboarding: array<string, string>,
         *     registration: array<string, string>,
         *     match_dialog: array<string, string>,
         *     blocking: array<string, string>,
         *     stepper: array<string, string>
         * } $frontend
         */
        $frontend = trans('frontend');

        $onboarding = $frontend['onboarding'];
        unset($onboarding['demo_profiles']);

        return [
            'locale' => [
                'label' => __('frontend.locale.label'),
                'fr' => __('frontend.locale.fr'),
                'en' => __('frontend.locale.en'),
            ],
            'navigation' => [
                'settings' => __('frontend.navigation.settings'),
                'profile' => __('frontend.navigation.profile'),
                'discovery' => __('frontend.navigation.discovery'),
                'conversations' => __('frontend.navigation.conversations'),
            ],
            'onboarding' => $onboarding,
            'admin_onboarding' => $frontend['admin_onboarding'],
            'registration' => $frontend['registration'],
            'match_dialog' => $frontend['match_dialog'],
            'blocking' => $frontend['blocking'],
            'stepper' => $frontend['stepper'],
            'copy' => $frontend['copy'],
        ];
    }
}
