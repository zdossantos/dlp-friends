<?php

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

/** @return array<string, string> */
function editorialTranslationValues(): array
{
    $values = [];
    $flatten = function (array $catalogue, string $prefix) use (&$flatten, &$values): void {
        foreach ($catalogue as $key => $value) {
            $path = "{$prefix}.{$key}";

            if (is_array($value)) {
                $flatten($value, $path);
            } else {
                $values[$path] = (string) $value;
            }
        }
    };

    foreach (['fr', 'en'] as $locale) {
        foreach (['common', 'account', 'profile', 'onboarding', 'discovery', 'conversations', 'blocking', 'administration'] as $domain) {
            $flatten(require lang_path("{$locale}/{$domain}.php"), "{$locale}.{$domain}");
        }
    }

    return $values;
}

test('feature catalogues contain no forbidden romantic language', function () {
    $forbidden = [
        '/âme[ -]sœur/iu', '/coups? de cœur/iu', '/alchimie/iu',
        '/sédui(?:re|sant|sante)|séduction/iu', '/craquer pour/iu',
        '/partenaire idéal/iu', '/relation amoureuse/iu',
        '/rendez-vous amoureux/iu', '/compatibilité amoureuse/iu',
        '/flirt(?:er)?/iu', '/dating/iu',
    ];

    foreach (editorialTranslationValues() as $key => $value) {
        expect(trim($value), "Empty translation at {$key}")->not->toBe('');

        foreach ($forbidden as $pattern) {
            expect($value, "Forbidden editorial term at {$key}")->not->toMatch($pattern);
        }
    }
});

test('visible interface copy is always translated explicitly', function () {
    $visiblePatterns = [
        '/>([^<>{}\n]*\p{L}[^<>{}\n]*)</u',
        '/(?<![:@])(?:aria-label|placeholder|title|alt)="([^"@:]*\p{L}[^"@:]*)"/u',
        "/['\"](?:message|error|title|description)['\"]\s*=>\s*['\"]([^'\"]*\\p{L}[^'\"]*)['\"]/u",
    ];

    $templateSources = collect([
        ...File::allFiles(resource_path('js')),
        ...File::allFiles(resource_path('views')),
    ])->filter(fn (SplFileInfo $file): bool => str_ends_with($file->getFilename(), '.vue') || str_ends_with($file->getFilename(), '.blade.php'));
    $phpSources = collect(File::allFiles(app_path()))
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php');
    $violations = [];

    foreach ($templateSources as $source) {
        $contents = $source->getContents();
        $contents = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/su', '', $contents) ?? $contents;
        $contents = preg_replace('/<!--.*?-->/su', '', $contents) ?? $contents;

        foreach (array_slice($visiblePatterns, 0, 2) as $pattern) {
            preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[1] as [$literal, $offset]) {
                $trimmed = trim(strip_tags($literal));

                if ($trimmed !== '' && preg_match('/\p{L}/u', $trimmed) === 1) {
                    $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                    $violations[] = str_replace(base_path().'/', '', $source->getPathname()).":{$line} {$trimmed}";
                }
            }
        }
    }

    foreach ($phpSources as $source) {
        $contents = $source->getContents();
        preg_match_all($visiblePatterns[2], $contents, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[1] as [$literal, $offset]) {
            $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
            $violations[] = str_replace(base_path().'/', '', $source->getPathname()).":{$line} {$literal}";
        }
    }

    expect($violations)->toBe([]);
});

test('legacy frontend catalogues and DOM translation bridge are removed', function () {
    expect(File::exists(lang_path('fr/frontend.php')))->toBeFalse()
        ->and(File::exists(lang_path('en/frontend.php')))->toBeFalse()
        ->and(File::exists(lang_path('fr.json')))->toBeFalse()
        ->and(File::exists(lang_path('en.json')))->toBeFalse()
        ->and(File::exists(resource_path('js/lib/translateDom.ts')))->toBeFalse();
});
