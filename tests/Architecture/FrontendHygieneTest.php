<?php

/*
 * Frontend budgets are ratchets: lower a budget in the same commit that removes
 * the corresponding code; never raise it.
 */

const FRONTEND_BUDGET = [
    'handlers' => 64,
    'scripts' => 16,
    'styles' => 152,
    'style_blocks' => 12,
    'raw_echo' => 0,
];

const FRONTEND_HYGIENE_PATTERNS = [
    'handlers' => '/\son(click|change|submit|input|keyup|keydown|keypress|blur|focus)\s*=/i',
    'scripts' => '/<script\b(?![^>]*\bsrc=)(?![^>]*type=["\']application\/json["\'])[^>]*>/i',
    'styles' => '/\sstyle\s*=\s*["\']/i',
    'style_blocks' => '/<style\b/i',
    'raw_echo' => '/\{!!/',
];

const FRONTEND_HYGIENE_SELF_CHECKS = [
    'handlers' => [
        'positive' => '<button onclick="save()">Save</button>',
        'negative' => [
            '<button data-onclick="save">Save</button>',
        ],
    ],
    'scripts' => [
        'positive' => '<script>window.app = true;</script>',
        'negative' => [
            '<script src="x.js"></script>',
            '<script type="application/json" id="d">{}</script>',
        ],
    ],
    'styles' => [
        'positive' => '<div style="color: red"></div>',
        'negative' => [
            '<div class="card"></div>',
        ],
    ],
    'style_blocks' => [
        'positive' => '<style>.card { color: red; }</style>',
        'negative' => [
            '<div class="card"></div>',
        ],
    ],
    'raw_echo' => [
        'positive' => '<div>{!! $html !!}</div>',
        'negative' => [
            '<div>{{ $html }}</div>',
        ],
    ],
];

/** Count matches for one frontend hygiene pattern. */
function frontendHygieneMatchCount(string $pattern, string $contents): int
{
    preg_match_all($pattern, $contents, $matches);

    return count($matches[0]);
}

/** @return array<string, array{total: int, files: array<string, int>}> */
function frontendHygieneMetrics(string $root): array
{
    $metrics = [];

    foreach (array_keys(FRONTEND_BUDGET) as $metric) {
        $metrics[$metric] = [
            'total' => 0,
            'files' => [],
        ];
    }

    $views = $root.'/resources/views';
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($views, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if (! $file->isFile() || ! str_ends_with(strtolower($file->getFilename()), '.blade.php')) {
            continue;
        }

        $path = $file->getPathname();
        $relativePath = str_replace('\\', '/', substr($path, strlen($root) + 1));
        $contents = (string) file_get_contents($path);

        foreach (FRONTEND_HYGIENE_PATTERNS as $metric => $pattern) {
            $count = frontendHygieneMatchCount($pattern, $contents);

            $metrics[$metric]['files'][$relativePath] = $count;
            $metrics[$metric]['total'] += $count;
        }
    }

    return $metrics;
}

/** @return list<array{0: string, 1: int}> */
function frontendHygieneTopFiles(array $files): array
{
    $files = array_filter($files, fn (int $count): bool => $count > 0);
    arsort($files, SORT_NUMERIC);
    $files = array_slice($files, 0, 5, true);

    return array_map(
        fn (int $count, string $path): array => [$path, $count],
        array_values($files),
        array_keys($files),
    );
}

test('frontend hygiene patterns count only intended syntax', function (string $metric) {
    $pattern = FRONTEND_HYGIENE_PATTERNS[$metric];
    $samples = FRONTEND_HYGIENE_SELF_CHECKS[$metric];

    expect(frontendHygieneMatchCount($pattern, $samples['positive']))->toBe(1);

    foreach ($samples['negative'] as $negativeSample) {
        expect(frontendHygieneMatchCount($pattern, $negativeSample))->toBe(0);
    }
})->with(array_keys(FRONTEND_HYGIENE_SELF_CHECKS));

test('frontend hygiene metrics stay within their budgets', function (string $metric) {
    $metrics = frontendHygieneMetrics(dirname(__DIR__, 2));
    $total = $metrics[$metric]['total'];
    $budget = FRONTEND_BUDGET[$metric];
    $topFiles = frontendHygieneTopFiles($metrics[$metric]['files']);

    $topFilesMessage = $topFiles === []
        ? 'none'
        : implode(', ', array_map(
            fn (array $file): string => $file[0].': '.$file[1],
            $topFiles,
        ));

    expect($total)->toBeLessThanOrEqual(
        $budget,
        sprintf(
            '%s total: %d; budget: %d; top files: %s',
            $metric,
            $total,
            $budget,
            $topFilesMessage,
        ),
    );
})->with(array_keys(FRONTEND_BUDGET));
