<?php

namespace App\Support;

final class FrontendTranslations
{
    /** @return array<string, array<string, string>> */
    public static function messages(): array
    {
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
            ],
        ];
    }
}
