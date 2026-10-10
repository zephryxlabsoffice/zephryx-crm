<?php

/*
 * Builds the two upload zips for shared hosting (docs/DEPLOY.md):
 *
 *   upload/zephryx-crm.zip   the application → extract BESIDE public_html
 *   upload/public_html.zip   the web root    → extract INTO public_html
 *
 * Run from the project root:  php deploy/build.php   (or MAKE-UPLOAD.bat)
 *
 * Built from the last COMMIT (git archive), not the working folder, so a
 * half-finished edit, the PC's .env, node_modules and tests never ship.
 * vendor is installed fresh with --no-dev in a staging copy, leaving the
 * project's own vendor (with the test tools) untouched.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$out = $argv[1] ?? $root.DIRECTORY_SEPARATOR.'upload';
$stage = sys_get_temp_dir().DIRECTORY_SEPARATOR.'zephryx-build-'.bin2hex(random_bytes(4));

function step(string $msg): void
{
    echo "\n==> {$msg}\n";
}

function run(string $cmd, string $cwd): void
{
    $proc = proc_open($cmd, [STDIN, STDOUT, STDERR], $pipes, $cwd);
    if (! is_resource($proc) || proc_close($proc) !== 0) {
        fwrite(STDERR, "\nFAILED: {$cmd}\n");
        exit(1);
    }
}

function rrmdir(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($it as $f) {
        $f->isDir() && ! $f->isLink() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($dir);
}

/** Zip a folder with forward-slash paths, dotfiles and empty folders included. */
function zipDir(string $dir, string $zipPath): void
{
    @unlink($zipPath);
    $zip = new ZipArchive;
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        fwrite(STDERR, "Cannot create {$zipPath}\n");
        exit(1);
    }
    $base = strlen(rtrim($dir, '\\/')) + 1;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );
    foreach ($it as $f) {
        $rel = str_replace('\\', '/', substr($f->getPathname(), $base));
        $f->isDir() ? $zip->addEmptyDir($rel) : $zip->addFile($f->getPathname(), $rel);
    }
    $zip->close();
}

if (! class_exists(ZipArchive::class)) {
    fwrite(STDERR, "PHP's zip extension is required (enable extension=zip in php.ini).\n");
    exit(1);
}

$dirty = trim((string) shell_exec('git -C '.escapeshellarg($root).' status --porcelain --untracked-files=no'));
if ($dirty !== '') {
    echo "WARNING: uncommitted changes are NOT included — the upload is built from the last commit:\n{$dirty}\n";
}
$commit = trim((string) shell_exec('git -C '.escapeshellarg($root).' rev-parse --short HEAD'));

step("Exporting commit {$commit}");
mkdir($stage.'/app', 0777, true);
$tar = $stage.'/src.tar';
run('git archive --format=tar -o '.escapeshellarg($tar).' HEAD', $root);
(new PharData($tar))->extractTo($stage.'/app');
unlink($tar);
$app = $stage.'/app';

// Package caches from the PC list dev-only providers that --no-dev removes;
// package:discover rebuilds them during composer install below.
foreach (['packages.php', 'services.php', 'config.php', 'routes-v7.php', 'events.php'] as $cache) {
    @unlink($app.'/bootstrap/cache/'.$cache);
}

step('Installing production dependencies (composer --no-dev)');
run('composer install --no-dev --optimize-autoloader --no-interaction --no-progress', $app);

step('Shaping the upload');
// Development-only files that have no business on a server.
foreach (['tests', 'docs', 'deploy', '.github', 'node_modules', 'refference', 'claude-memory', '.claude'] as $d) {
    rrmdir($app.'/'.$d);
}
foreach (['phpunit.xml', '.phpunit.result.cache', 'package.json', 'package-lock.json', 'vite.config.js',
    '.editorconfig', '.gitattributes', '.gitignore', '.npmrc', '.env', '.env.example', 'CLAUDE.md', 'AGENTS.md'] as $f) {
    @unlink($app.'/'.$f);
}
foreach (['storage/app/private', 'storage/app/public', 'storage/framework/cache/data',
    'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $d) {
    is_dir($app.'/'.$d) || mkdir($app.'/'.$d, 0755, true);
}

// The web root leaves the app; its index.php is swapped for the one that
// looks in ../zephryx-crm, and the one-time installer joins it.
$web = $stage.'/public_html';
rename($app.'/public', $web);
copy(__DIR__.'/public_html/index.php', $web.'/index.php');
copy(__DIR__.'/public_html/install.php', $web.'/install.php');
foreach (['hot', 'public.rar', 'storage'] as $junk) {
    is_dir($web.'/'.$junk) ? rrmdir($web.'/'.$junk) : @unlink($web.'/'.$junk);
}
if (! is_file($web.'/build/manifest.json')) {
    fwrite(STDERR, "public/build/manifest.json is not in the commit — run `npm run build` and commit public/build.\n");
    exit(1);
}

step('Zipping');
is_dir($out) || mkdir($out, 0777, true);
zipDir($app, $out.'/zephryx-crm.zip');
zipDir($web, $out.'/public_html.zip');
rrmdir($stage);

printf("\nDone — built from commit %s.\n  %s (%.1f MB)\n  %s (%.1f MB)\n",
    $commit,
    $out.DIRECTORY_SEPARATOR.'zephryx-crm.zip', filesize($out.'/zephryx-crm.zip') / 1048576,
    $out.DIRECTORY_SEPARATOR.'public_html.zip', filesize($out.'/public_html.zip') / 1048576,
);
