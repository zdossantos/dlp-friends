export type SeasonalThemeName = 'halloween' | 'christmas';

export type SeasonalThemeState = {
    active: SeasonalThemeName | null;
    nextTransitionAt: string | null;
};

export type SeasonalThemeConfiguration = {
    theme: SeasonalThemeName;
    is_manually_active: boolean;
    starts_at: string | null;
    ends_at: string | null;
};
