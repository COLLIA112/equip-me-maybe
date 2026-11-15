<?php

// Local setup only. Existing environment files, keys and databases are preserved.
if (PHP_VERSION_ID < 80200) {
    fwrite(STDERR, "PHP 8.2 or newer is required.\n");
    exit(1);
}

chdir(__DIR__);
$arguments = array_slice($argv, 1);
$knownArguments = ['--serve', '--seed-demo', '--help'];
foreach ($arguments as $argument) {
    if (! in_array($argument, $knownArguments, true)) {
        fwrite(STDERR, "Unknown option: {$argument}\nUse --help for supported options.\n");
        exit(1);
    }
}
if (in_array('--help', $arguments, true)) {
    echo "Usage: php setup-demo.php [--serve] [--seed-demo]\n";
    echo "Creates a local .env only when absent, migrates without resetting data, and seeds only a newly created SQLite database.\n";
    echo "--serve      Start the local development server after setup.\n";
    echo "--seed-demo  Explicitly add demo accounts/data to the configured local database. Never use this for production.\n";
    exit(0);
}

function runSetupCommand(array $command, bool $required = true): bool
{
    echo "\n> ".implode(' ', $command)."\n";
    $process = proc_open($command, [0 => STDIN, 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, __DIR__);
    if (! is_resource($process)) {
        $exitCode = 1;
    } else {
        foreach ([1, 2] as $index) {
            stream_set_blocking($pipes[$index], false);
        }
        $open = [1 => $pipes[1], 2 => $pipes[2]];
        while ($open) {
            $read = array_values($open);
            $write = $except = null;
            if (stream_select($read, $write, $except, 1) !== false) {
                foreach ($read as $stream) {
                    $index = array_search($stream, $open, true);
                    $chunk = fread($stream, 8192);
                    if ($chunk !== false && $chunk !== '') {
                        fwrite($index === 2 ? STDERR : STDOUT, $chunk);
                    }
                    if (feof($stream)) {
                        fclose($stream);
                        unset($open[$index]);
                    }
                }
            }
        }
        $exitCode = proc_close($process);
    }
    if ($exitCode !== 0 && $required) {
        fwrite(STDERR, "Setup stopped. Resolve the command error above and run the same setup again; your data has been preserved.\n");
        exit($exitCode ?: 1);
    }
    return $exitCode === 0;
}

$missingExtensions = array_filter(['ctype', 'dom', 'fileinfo', 'mbstring', 'openssl', 'pdo', 'tokenizer', 'xml', 'xmlwriter', 'gd'], fn ($extension) => ! extension_loaded($extension));
if ($missingExtensions) {
    fwrite(STDERR, 'Enable these PHP extensions in the CLI php.ini: '.implode(', ', $missingExtensions)."\nRun php --ini to locate that file.\n");
    exit(1);
}

if (! is_file(__DIR__.'/vendor/autoload.php')) {
    echo "Bundled PHP dependencies were not found; Composer must be installed for this fallback.\n";
    $composer = PHP_OS_FAMILY === 'Windows' ? ['cmd', '/c', 'composer', 'install', '--no-interaction'] : ['composer', 'install', '--no-interaction'];
    runSetupCommand($composer);
}
require __DIR__.'/vendor/autoload.php';

if (! is_file('.env')) {
    if (! copy('.env.example', '.env')) {
        fwrite(STDERR, "Could not create .env. Check folder permissions.\n");
        exit(1);
    }
    echo "Created .env from the safe example.\n";
} else {
    echo "Keeping the existing .env and database settings.\n";
}

$environment = Dotenv\Dotenv::parse(file_get_contents('.env'));
$connection = $environment['DB_CONNECTION'] ?? 'sqlite';
$createdDatabase = false;
if ($connection === 'sqlite' && empty($environment['DB_URL'])) {
    if (! extension_loaded('pdo_sqlite')) {
        fwrite(STDERR, "Enable pdo_sqlite in the PHP CLI configuration before using the default SQLite demo.\n");
        exit(1);
    }
    $databasePath = $environment['DB_DATABASE'] ?? __DIR__.'/database/database.sqlite';
    if ($databasePath === ':memory:') {
        fwrite(STDERR, "The local demo requires a persistent SQLite database. Remove DB_DATABASE=:memory: from .env; PHPUnit config uses memory separately.\n");
        exit(1);
    }
    if (! preg_match('~^(?:[A-Za-z]:[\\\\/]|[\\\\/])~', $databasePath)) {
        $databasePath = __DIR__.'/'.$databasePath;
    }
    if (! is_file($databasePath)) {
        $parent = dirname($databasePath);
        if (! is_dir($parent) || ! is_writable($parent) || ! touch($databasePath)) {
            fwrite(STDERR, "Unable to create the SQLite file at {$databasePath}. Check DB_DATABASE and folder permissions.\n");
            exit(1);
        }
        $createdDatabase = true;
        echo "Created a new SQLite database.\n";
    }
} elseif ($connection === 'mysql' && ! extension_loaded('pdo_mysql')) {
    fwrite(STDERR, "Enable pdo_mysql before using the configured MySQL/MariaDB database.\n");
    exit(1);
}

runSetupCommand([PHP_BINARY, 'artisan', 'config:clear']);
if (empty($environment['APP_KEY'])) {
    runSetupCommand([PHP_BINARY, 'artisan', 'key:generate', '--force']);
} else {
    echo "Keeping the existing application key.\n";
}
runSetupCommand([PHP_BINARY, 'artisan', 'migrate', '--force']);
if ($createdDatabase || in_array('--seed-demo', $arguments, true)) {
    runSetupCommand([PHP_BINARY, 'artisan', 'db:seed', '--force']);
} else {
    echo "Existing database preserved. Demo seeding skipped; use --seed-demo only when you explicitly want demo records.\n";
}
if (! file_exists('public/storage') && ! is_link('public/storage')) {
    if (! runSetupCommand([PHP_BINARY, 'artisan', 'storage:link'], false)) {
        echo "Photo storage link could not be created. On Windows enable Developer Mode, then run php artisan storage:link.\n";
    }
}

$manifestPath = __DIR__.'/public/build/manifest.json';
$manifest = is_file($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : null;
$builtAssetsPresent = is_array($manifest) && isset($manifest['resources/js/app.js'], $manifest['resources/css/app.css']);
foreach (($manifest ?? []) as $entry) {
    if (isset($entry['file']) && ! is_file(__DIR__.'/public/build/'.$entry['file'])) {
        $builtAssetsPresent = false;
    }
}
if (! $builtAssetsPresent) {
    echo "Compiled frontend assets were not found. Node 20.19+ or 22.12+ and npm are required for this fallback.\n";
    $npm = PHP_OS_FAMILY === 'Windows' ? ['cmd', '/c', 'npm'] : ['npm'];
    runSetupCommand([...$npm, 'ci']);
    runSetupCommand([...$npm, 'run', 'build']);
}

echo "\nSetup complete. Start with: php artisan serve\nOpen http://127.0.0.1:8000 after the server starts.\n";
if (in_array('--serve', $arguments, true)) {
    runSetupCommand([PHP_BINARY, 'artisan', 'serve']);
}
