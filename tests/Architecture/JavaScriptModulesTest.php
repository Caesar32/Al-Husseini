<?php

use Symfony\Component\Process\Process;

/*
 * Frontend F4: the admin JS modules parse, and their Node tests (tests/js) pass.
 * Requires Node.js (CI installs Node 20).
 */

function adminJsFiles(): array
{
    $root = dirname(__DIR__, 2).'/public/assets/js/admin';
    $files = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() === 'js') {
            $files[] = $file->getPathname();
        }
    }
    sort($files);

    return $files;
}

test('every admin JS module parses', function () {
    $files = adminJsFiles();
    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $process = new Process(['node', '--check', $file]);
        $process->run();
        expect($process->isSuccessful())->toBeTrue("node --check failed for {$file}:\n".$process->getErrorOutput());
    }
});

test('the admin JS behaviour tests pass', function () {
    $tests = glob(dirname(__DIR__).'/js/*.test.mjs');
    expect($tests)->not->toBeEmpty();

    $process = new Process(['node', '--test', ...$tests], dirname(__DIR__, 2));
    $process->setTimeout(120);
    $process->run();

    expect($process->isSuccessful())->toBeTrue($process->getOutput().$process->getErrorOutput());
});
