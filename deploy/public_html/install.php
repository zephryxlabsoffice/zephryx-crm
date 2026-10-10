<?php

/*
 * One-time web installer for shared hosting with no Terminal (docs/DEPLOY.md).
 *
 * Writes ../zephryx-crm/.env, creates every table and seeds the production
 * rows (roles, permissions, master data, the owner account), then locks
 * itself and deletes this file.
 *
 * Two guards, because until it runs anybody who finds this URL could install
 * the application with THEIR owner password:
 *
 *   1. A setup code. The first visit writes a random code to
 *      zephryx-crm/storage/install-token.txt — outside the web root, so only
 *      somebody with cPanel File Manager can read it — and the form refuses
 *      to do anything without it.
 *   2. A lock. Success writes zephryx-crm/storage/installed.lock, and while it
 *      exists this file does nothing at all, even if the delete below failed.
 *
 * The owner password is never written to disk: it is handed to the seeder
 * through the process environment for this one request only.
 *
 * Deliberately NO config:cache / route:cache. Without Terminal, .env is edited
 * in File Manager and new code arrives by upload; a cached config or route
 * table would silently ignore both.
 */

declare(strict_types=1);

$appDir = realpath(__DIR__.'/../zephryx-crm');
$lockFile = $appDir ? $appDir.'/storage/installed.lock' : null;
$tokenFile = $appDir ? $appDir.'/storage/install-token.txt' : null;

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Quote a value for .env so phpdotenv reads back exactly what was typed. */
function envValue(string $value): string
{
    if ($value === '' || preg_match('/^[A-Za-z0-9_.\/:@+=-]+$/', $value)) {
        return $value;
    }
    if (! str_contains($value, "'")) {
        return "'".$value."'"; // single quotes: no interpolation, no escapes
    }

    return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
}

/** Replace KEY=… in the template (or append it), keeping every comment. */
function setEnv(string $env, string $key, string $value): string
{
    $line = $key.'='.envValue($value);
    $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

    return preg_match($pattern, $env)
        ? preg_replace_callback($pattern, fn () => $line, $env)
        : rtrim($env)."\n".$line."\n";
}

function page(string $title, string $body): never
{
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex');
    header('Cache-Control: no-store');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        .'<meta name="viewport" content="width=device-width, initial-scale=1">'
        .'<title>'.h($title).'</title><style>'
        .'body{font:15px/1.5 system-ui,sans-serif;background:#f4f5f7;color:#1c1e21;margin:0;padding:24px 16px}'
        .'main{max-width:620px;margin:0 auto;background:#fff;border-radius:10px;padding:28px;box-shadow:0 1px 3px rgba(0,0,0,.08)}'
        .'h1{font-size:22px;margin:0 0 4px}h2{font-size:15px;margin:26px 0 8px;text-transform:uppercase;letter-spacing:.04em;color:#555}'
        .'label{display:block;font-weight:600;margin:12px 0 4px}small{display:block;color:#666;font-weight:400}'
        .'input{width:100%;box-sizing:border-box;padding:9px 10px;border:1px solid #c9ccd1;border-radius:6px;font:inherit}'
        .'button{margin-top:24px;padding:11px 20px;border:0;border-radius:6px;background:#111;color:#fff;font:inherit;font-weight:600;cursor:pointer}'
        .'.err{background:#fdecea;color:#8a1c12;padding:12px 14px;border-radius:6px;margin:14px 0}'
        .'.ok{background:#e7f6ec;color:#185c2c;padding:12px 14px;border-radius:6px;margin:14px 0}'
        .'.row{display:grid;grid-template-columns:2fr 1fr;gap:12px}'
        .'pre{background:#f4f5f7;padding:12px;border-radius:6px;overflow:auto;font-size:12px;max-height:280px}'
        .'li{margin:4px 0}code{background:#f0f1f3;padding:1px 5px;border-radius:4px}'
        .'</style></head><body><main>'.$body.'</main></body></html>';
    exit;
}

// ── Already installed: do nothing, and try once more to remove this file ────
if ($lockFile && is_file($lockFile)) {
    @unlink(__FILE__);
    http_response_code(404);
    page('Not found', '<h1>Not found</h1>');
}

// ── Requirements ───────────────────────────────────────────────────────────
$problems = [];
if (! $appDir || ! is_file($appDir.'/vendor/autoload.php')) {
    $problems[] = 'The application folder was not found. Upload and extract <code>zephryx-crm.zip</code> '
        .'so that <code>zephryx-crm</code> sits BESIDE <code>public_html</code> (not inside it).';
}
if (version_compare(PHP_VERSION, '8.3.0', '<')) {
    $problems[] = 'PHP '.h(PHP_VERSION).' is too old. In cPanel → MultiPHP Manager, choose PHP 8.3 or newer for this domain.';
}
foreach (['pdo_mysql', 'openssl', 'mbstring', 'fileinfo', 'tokenizer', 'xml', 'ctype', 'curl'] as $ext) {
    if (! extension_loaded($ext)) {
        $problems[] = 'PHP extension <code>'.h($ext).'</code> is missing. Turn it on in cPanel → Select PHP Version → Extensions.';
    }
}
if ($appDir) {
    foreach (['', '/storage', '/storage/framework', '/storage/logs', '/bootstrap/cache'] as $dir) {
        if (is_dir($appDir.$dir) && ! is_writable($appDir.$dir)) {
            $problems[] = 'Folder <code>zephryx-crm'.h($dir).'</code> is not writable. Set its permissions to 755 in File Manager.';
        }
    }
    if (! is_file($appDir.'/.env.production.example')) {
        $problems[] = '<code>zephryx-crm/.env.production.example</code> is missing — re-upload <code>zephryx-crm.zip</code>.';
    }
}
if ($problems) {
    page('Install — fix these first', '<h1>Fix these first</h1><ul><li>'.implode('</li><li>', $problems).'</li></ul>'
        .'<p>Then reload this page.</p>');
}

// ── Setup code (outside the web root) ──────────────────────────────────────
if (! is_file($tokenFile)) {
    file_put_contents($tokenFile, bin2hex(random_bytes(8))."\n");
}
$token = trim((string) file_get_contents($tokenFile));

$host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost'));
$in = fn (string $key, string $default = '') => trim((string) ($_POST[$key] ?? $default));
$errors = [];
$log = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    set_time_limit(300);

    $f = [
        'url' => rtrim($in('url'), '/'),
        'db_host' => $in('db_host'), 'db_port' => $in('db_port'), 'db_name' => $in('db_name'),
        'db_user' => $in('db_user'), 'db_pass' => (string) ($_POST['db_pass'] ?? ''),
        'mail_host' => $in('mail_host'), 'mail_port' => $in('mail_port'),
        'mail_user' => $in('mail_user'), 'mail_pass' => (string) ($_POST['mail_pass'] ?? ''),
        'mail_from' => $in('mail_from'), 'admin_email' => $in('admin_email'),
        'owner_pass' => (string) ($_POST['owner_pass'] ?? ''),
        'owner_pass2' => (string) ($_POST['owner_pass2'] ?? ''),
    ];

    if (! hash_equals($token, $in('token'))) {
        $errors[] = 'The setup code is wrong. Open <code>zephryx-crm/storage/install-token.txt</code> in File Manager and copy it exactly.';
    }
    if (! preg_match('#^https?://[^/\s]+$#', $f['url'])) {
        $errors[] = 'Site address must look like <code>https://crm.zephryxlabs.in</code>.';
    }
    foreach (['db_host' => 'Database host', 'db_name' => 'Database name', 'db_user' => 'Database user',
        'mail_host' => 'SMTP host', 'mail_user' => 'SMTP username'] as $k => $label) {
        if ($f[$k] === '') {
            $errors[] = h($label).' is required.';
        }
    }
    if (! ctype_digit($f['db_port']) || ! ctype_digit($f['mail_port'])) {
        $errors[] = 'Ports must be numbers.';
    }
    foreach (['mail_from' => 'Send-from address', 'admin_email' => 'Admin email'] as $k => $label) {
        if (! filter_var($f[$k], FILTER_VALIDATE_EMAIL)) {
            $errors[] = h($label).' is not a valid email address.';
        }
    }
    if (strlen($f['owner_pass']) < 12) {
        $errors[] = 'Owner password must be at least 12 characters.';
    } elseif ($f['owner_pass'] !== $f['owner_pass2']) {
        $errors[] = 'The two owner passwords do not match.';
    }
    foreach (['db_pass', 'mail_pass'] as $k) {
        if (str_contains($f[$k], "'") && str_contains($f[$k], '${')) {
            $errors[] = 'A password contains both <code>\'</code> and <code>${</code>, which .env cannot store. Change it in cPanel.';
        }
    }

    if (! $errors) {
        try {
            new PDO(
                "mysql:host={$f['db_host']};port={$f['db_port']};dbname={$f['db_name']};charset=utf8mb4",
                $f['db_user'], $f['db_pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5],
            );
        } catch (Throwable $e) {
            $errors[] = 'Could not connect to the database: '.h($e->getMessage())
                .'<br>Check the name and user in cPanel → MySQL Databases (they include the cPanel prefix, like <code>user_zephryx_crm</code>), '
                .'and that the user is added to the database with ALL PRIVILEGES.';
        }
    }

    if (! $errors) {
        $envFile = $appDir.'/.env';

        // Keep the key from an earlier, failed attempt rather than minting a
        // second one — harmless now, but never a habit worth having.
        $key = '';
        if (is_file($envFile) && preg_match('/^APP_KEY=(base64:\S+)$/m', (string) file_get_contents($envFile), $m)) {
            $key = $m[1];
        }
        $key = $key ?: 'base64:'.base64_encode(random_bytes(32));

        $urlHost = parse_url($f['url'], PHP_URL_HOST);
        $env = (string) file_get_contents($appDir.'/.env.production.example');
        foreach ([
            'APP_KEY' => $key,
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => $f['url'],
            'SESSION_DOMAIN' => $urlHost,
            'SESSION_SECURE_COOKIE' => str_starts_with($f['url'], 'https://') ? 'true' : 'false',
            'DB_HOST' => $f['db_host'], 'DB_PORT' => $f['db_port'], 'DB_DATABASE' => $f['db_name'],
            'DB_USERNAME' => $f['db_user'], 'DB_PASSWORD' => $f['db_pass'],
            'MAIL_HOST' => $f['mail_host'], 'MAIL_PORT' => $f['mail_port'],
            'MAIL_USERNAME' => $f['mail_user'], 'MAIL_PASSWORD' => $f['mail_pass'],
            'MAIL_FROM_ADDRESS' => $f['mail_from'],
            'ZEPHRYX_SUPPORT_EMAIL' => $f['admin_email'],
            'ZEPHRYX_OWNER_PASSWORD' => '',
        ] as $k => $v) {
            $env = setEnv($env, $k, $v);
        }

        if (file_put_contents($envFile, $env) === false) {
            $errors[] = 'Could not write <code>zephryx-crm/.env</code>. Check the folder is writable.';
        } else {
            @chmod($envFile, 0600);

            // The seeder reads ZEPHRYX_OWNER_PASSWORD with env(); phpdotenv is
            // immutable, so a value already in the process environment wins
            // over the blank line in .env and never touches the disk.
            putenv('ZEPHRYX_OWNER_PASSWORD='.$f['owner_pass']);
            $_ENV['ZEPHRYX_OWNER_PASSWORD'] = $_SERVER['ZEPHRYX_OWNER_PASSWORD'] = $f['owner_pass'];

            try {
                require $appDir.'/vendor/autoload.php';
                $app = require $appDir.'/bootstrap/app.php';
                $app->usePublicPath(__DIR__);
                $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

                // Stale caches from the PC would override the new .env.
                $kernel->call('optimize:clear');
                $kernel->call('migrate', ['--force' => true, '--seed' => true]);
                $log = $kernel->output();

                file_put_contents($lockFile, 'Installed '.date('c')."\n");
                @unlink($tokenFile);
                $deleted = @unlink(__FILE__);

                $adminUrl = h($f['url']).'/login';
                page('Installed', '<h1>Installed</h1><div class="ok">The database is built and the owner account exists.</div>'
                    .'<ol>'
                    .($deleted ? '' : '<li><strong>Delete <code>public_html/install.php</code> now</strong> in File Manager. (It is locked and harmless, but should not stay.)</li>')
                    .'<li>Sign in at <a href="'.$adminUrl.'">'.$adminUrl.'</a> with <code>'.h($f['admin_email']).'</code> (or user ID <code>OWNER</code>) and the owner password.</li>'
                    .'<li>The sign-in sends a code by email. If it does not arrive (check spam), the SMTP settings in '
                    .'<code>zephryx-crm/.env</code> are wrong — fix them in File Manager; no reinstall needed.</li>'
                    .'<li>Change the owner password after the first sign-in.</li>'
                    .'<li>Back up <code>zephryx-crm/.env</code> somewhere safe. Its <code>APP_KEY</code> unlocks every encrypted '
                    .'record — if it is lost or changed, ID numbers, PANs and bank details become unreadable forever.</li>'
                    .'</ol><details><summary>Technical log</summary><pre>'.h($log).'</pre></details>');
            } catch (Throwable $e) {
                $errors[] = 'Setup stopped: '.h($e->getMessage())
                    .'<br>Nothing is lost — fix the cause and submit again. The details are also in '
                    .'<code>zephryx-crm/storage/logs</code>.';
                $log = isset($kernel) ? $kernel->output() : '';
            }
        }
    }
}

// ── The form ───────────────────────────────────────────────────────────────
$v = fn (string $key, string $default = '') => h($in($key, $default));

page('Install Zephryx CRM', '<h1>Install Zephryx CRM</h1><p>One-time setup. This page deletes itself when it finishes.</p>'
    .($errors ? '<div class="err">'.implode('<br><br>', $errors).'</div>' : '')
    .($log ? '<details open><summary>Technical log</summary><pre>'.h($log).'</pre></details>' : '')
    .'<form method="post" autocomplete="off">'

    .'<h2>Setup code</h2>'
    .'<label>Setup code<small>In cPanel File Manager open <code>zephryx-crm/storage/install-token.txt</code> and copy the code.</small></label>'
    .'<input name="token" required>'

    .'<h2>Site</h2>'
    .'<label>Site address</label><input name="url" value="'.$v('url', 'https://'.$host).'" required>'

    .'<h2>Database</h2><small>From cPanel → MySQL Databases. Names include the cPanel prefix.</small>'
    .'<div class="row"><div><label>Host</label><input name="db_host" value="'.$v('db_host', 'localhost').'" required></div>'
    .'<div><label>Port</label><input name="db_port" value="'.$v('db_port', '3306').'" required></div></div>'
    .'<label>Database name</label><input name="db_name" value="'.$v('db_name').'" required>'
    .'<label>Database user</label><input name="db_user" value="'.$v('db_user').'" required>'
    .'<label>Database password</label><input type="password" name="db_pass">'

    .'<h2>Email (sign-in codes are sent from here)</h2><small>From cPanel → Email Accounts → Connect Devices.</small>'
    .'<div class="row"><div><label>SMTP host</label><input name="mail_host" value="'.$v('mail_host', 'mail.'.preg_replace('/^(www|crm)\./', '', $host)).'" required></div>'
    .'<div><label>Port</label><input name="mail_port" value="'.$v('mail_port', '587').'" required></div></div>'
    .'<label>SMTP username<small>Usually the full mailbox address.</small></label><input name="mail_user" value="'.$v('mail_user').'" required>'
    .'<label>SMTP password</label><input type="password" name="mail_pass">'
    .'<label>Send-from address</label><input name="mail_from" value="'.$v('mail_from', 'no-reply@'.$host).'" required>'

    .'<h2>Owner (Admin Panel) account</h2>'
    .'<label>Admin email<small>You sign in to the Admin Panel with this, and it receives the sign-in code.</small></label>'
    .'<input name="admin_email" value="'.$v('admin_email', 'admin@zephryxlabs.in').'" required>'
    .'<label>Owner password<small>At least 12 characters. Not saved in any file.</small></label>'
    .'<input type="password" name="owner_pass" required minlength="12">'
    .'<label>Owner password again</label><input type="password" name="owner_pass2" required minlength="12">'

    .'<button type="submit">Install</button></form>');
