# Avatar-first profile UX design

## Goal

Make the catalog avatar the central visual identity across profile onboarding,
the member profile, and discovery while preserving fast mobile use and the
existing Laravel form contract.

## Direction

The selected avatar is displayed as a large, contained full-body image. Its
`primary_color` and `secondary_color` drive gradients, glows, borders, and
small accents around it. Text always remains on an opaque card surface rather
than directly over the image. The treatment supports the real catalog assets,
which are expected to be transparent character images with varied proportions.

No new avatar assets or intellectual property are introduced by this change.
Catalog rights remain an operational prerequisite.

## Profile wizard

Creation and editing share a four-step flow:

1. **Avatar** — a horizontally navigable carousel with the selected avatar in
   the center and adjacent options partially visible.
2. **Identity** — display name and optional biography.
3. **Affinities** — interests and visit frequency.
4. **Preview** — a compact discovery-card preview, visibility choice, and the
   final submit action.

The underlying form remains a single Inertia form. Moving between steps does
not submit or discard values. The final step submits the same field names and
HTTP methods as the existing form.

Every step displays a text counter and four-segment progress indicator. Back
and Continue controls remain in a stable footer. Completed segments may be
revisited. Validation responses return the member to the first step containing
an invalid field.

The avatar carousel supports previous/next buttons, Left/Right keys, and a
horizontal pointer or touch gesture. Selection uses a check mark, label, and
outline in addition to color. Motion is disabled or shortened under
`prefers-reduced-motion`.

## Member profile

The avatar hero occupies the upper portion of the card and uses both catalog
colors for its background treatment. The character may overlap the opaque
information panel without covering text. Member actions remain labelled and
keyboard accessible. Identity, visibility, frequency, biography, and interests
retain their current meaning.

## Discovery card

The avatar occupies the majority of the upper card. An opaque information
sheet presents identity, biography, common-interest count, common-interest
chips, and visit frequency. Visible Pass and Like actions supplement existing
swipe and keyboard gestures. Product copy remains friendship-oriented and does
not use romantic symbols.

## Accessibility and responsive behavior

- All actionable controls have at least a 44-by-44 CSS-pixel target.
- Selection never relies on color alone.
- Text has an opaque high-contrast backing in light and dark themes.
- The carousel and discovery card retain complete keyboard operation.
- Important transitions respect `prefers-reduced-motion`.
- Layouts work at 320 CSS pixels without horizontal page overflow.
- Existing server-side authorization and validation remain authoritative.

## Testing

Pest Browser tests cover wizard progression, carousel keyboard selection,
preservation of values across steps, final submission, profile hero semantics,
visible discovery actions, and existing swipe keyboard behavior. Frontend lint,
format, type checking, build, and relevant PHP tests complete verification.
