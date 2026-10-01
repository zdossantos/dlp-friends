<?php

namespace App\Support;

use LogicException;

final class PublicReleaseNotes
{
    /** @return list<array{version: string, date: string, changes: list<string>, release_url: string}> */
    public function items(string $locale): array
    {
        /** @var array<int, array{version: string, date: string, fr: list<string>, en: list<string>}> $items */
        $items = config('release-notes.items', []);
        $releaseNotes = [];

        foreach ($items as $item) {
            if (preg_match('/^\d+\.\d+\.\d+$/', $item['version']) !== 1 || $item['fr'] === [] || $item['en'] === []) {
                throw new LogicException('Every feature release must have a valid version and non-empty French and English notes.');
            }

            $changes = $locale === 'en' ? $item['en'] : $item['fr'];
            $releaseNotes[] = [
                'version' => $item['version'],
                'date' => $item['date'],
                'changes' => $changes,
                'release_url' => 'https://github.com/zdossantos/dlp-friends/releases/tag/v'.$item['version'],
            ];
        }

        usort($releaseNotes, fn (array $left, array $right): int => version_compare($right['version'], $left['version']));

        return $releaseNotes;
    }

    /** @return list<string> */
    public function featureVersions(string $changelog): array
    {
        preg_match_all('/^## \[(\d+\.\d+\.\d+)\][^\n]*\n(.*?)(?=^## \[|\z)/ms', $changelog, $matches, PREG_SET_ORDER);

        $versions = [];

        foreach ($matches as $release) {
            if (preg_match('/^### Features\s*$/m', $release[2]) === 1) {
                $versions[] = $release[1];
            }
        }

        return $versions;
    }
}
