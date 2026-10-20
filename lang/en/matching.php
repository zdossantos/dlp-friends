<?php

return [
    'meta' => [
        'title' => 'How DLP Friends matching works',
        'description' => 'Learn how DLP Friends selects and ranks suggested profiles, then lets your worlds cross after two mutual Discoveries.',
    ],
    'eyebrow' => 'An algorithm explained simply',
    'title' => 'How suggestions work',
    'introduction' => 'A suggestion, a Discovery, and crossed worlds are three different steps. These are the exact rules applied today.',
    'toc_label' => 'On this page',
    'sections' => [
        [
            'id' => 'eligibility',
            'title' => '1. Who can appear in your suggestions?',
            'paragraphs' => ['Before ranking anything, DLP Friends checks that a profile can actually be suggested to you.'],
            'items' => [
                'The account must be active and belong to an adult.',
                'The profile must be complete, visible, and use an avatar that is still active.',
                'Your own profile and profiles you have already reviewed are excluded.',
                'Someone you blocked, or who blocked you, cannot appear.',
                'The profile meets your age range and minimum number of shared active favorite worlds.',
            ],
        ],
        [
            'id' => 'ranking',
            'title' => '2. How are profiles ranked?',
            'paragraphs' => ['Eligible profiles are ordered using four rules, applied in this order.'],
            'items' => [
                'Eligible administrators always appear first, even before other cards that are already loaded.',
                'Within each group, the number of shared active favorite worlds takes priority next.',
                'When that number is tied, a 0.25 bonus applies if you both entered the same visit frequency.',
                'If both criteria are still identical, a random tie-break determines the order.',
            ],
            'note' => 'Your filters are configured in account settings: five years below and above your age by default, starting at 18, and no minimum shared favorite worlds. Saved age limits stay fixed. They limit your suggestions without changing your visibility or existing chats; ranking stays the same.',
        ],
        [
            'id' => 'reciprocity',
            'title' => '3. From a suggestion to crossed worlds',
            'paragraphs' => [
                'A suggestion is simply a profile shown in Explore. Passing or choosing Discover then records your choice.',
                'Your worlds cross only after two mutual Discoveries. One Discover choice is never enough and does not open a chat.',
            ],
            'items' => [],
            'note' => 'Two mutual Discoveries let your worlds cross and let you start a private chat.',
        ],
        [
            'id' => 'unused-criteria',
            'title' => '4. What ranking does not use',
            'paragraphs' => [
                'DLP Friends does not ask for a city and does not rank members by distance, age, or romantic criteria. Age and minimum shared favorite worlds only filter your suggestions according to your settings.',
                'This explanation describes the engine currently in use. It and its tests will be updated whenever those rules change.',
            ],
            'items' => [],
        ],
    ],
    'back' => 'Back to the home page',
    'footer' => 'DLP Friends is for adults, independent, and not affiliated with Disney or Disneyland Paris.',
];
