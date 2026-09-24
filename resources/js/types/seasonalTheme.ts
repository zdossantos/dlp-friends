export type SeasonalThemeName = 'halloween' | 'christmas';

export type SeasonalThemeState = {
    active: SeasonalThemeName | null;
    nextTransitionAt: string | null;
};
