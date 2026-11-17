<?php

/*
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>CentralCorp Panel</title>
</head>
<body style="font-family: sans-serif; text-align: center; margin-top: 1rem">
<h1>CentralCorp Panel - PHP installation issue</h1>
<h2>PHP is not executed</h2>
<p>If you see this page in your browser, it means that PHP is not installed or not configured properly on your server.</p>
<p>On Linux with Apache2 you can try the following command: <code>apt install libapache2-mod-php</code></p>
<p>If you are using another setup, please refer to your web server documentation.</p>
<hr>
<p>This is NOT an issue related to CentralCorp Panel.</p>
</body>
</html><!--
*/

/**
 * The CentralCorp Panel installer.
 *
 * This file is not a part of CentralCorp Panel itself,
 * and can be removed when CentralCorp Panel is installed.
 *
 * @author CentralCorp
 */
$installerVersion = '2.0.0-preview.1';
$panelRepository = 'CentralCorp/centralpanel-v2';
$panelBranch = 'v2-rework';

$minPhpVersion = '8.2';

$requiredExtensions = [
    'bcmath',
    'ctype',
    'json',
    'mbstring',
    'openssl',
    'PDO',
    'tokenizer',
    'xml',
    'xmlwriter',
    'curl',
    'fileinfo',
    'zip',
];

set_error_handler(function ($level, $message, $file = 'unknown', $line = 0) {
    http_response_code(500);
    exit(json_encode(['message' => "A fatal error occurred: {$message} ({$file}:{$line})"]));
});

//
// Some helper functions
//

/**
 * Parse the PHP version to x.x format.
 *
 * @return string
 */
function parse_php_version()
{
    preg_match('/^(\d+)\.(\d+)/', PHP_VERSION, $matches);

    if (count($matches) > 2) {
        return "{$matches[1]}.{$matches[2]}";
    }

    return PHP_VERSION;
}

/**
 * Get an item from an array using "dot" notation.
 *
 * @param  array  $array
 * @param  int|string  $key
 * @param  mixed  $default
 *
 * @return mixed
 */
function array_get($array, $key, $default = null)
{
    if (array_key_exists($key, $array)) {
        return $array[$key];
    }

    if (strpos($key, '.') === false) {
        return isset($array[$key]) ? $array[$key] : $default;
    }

    foreach (explode('.', $key) as $segment) {
        if (!array_key_exists($segment, $array)) {
            return $default;
        }

        $array = $array[$segment];
    }

    return $array;
}

/**
 * Get the HTTP method of the request.
 *
 * @return string
 */
function request_method()
{
    return strtoupper(array_get($_SERVER, 'REQUEST_METHOD', 'GET'));
}

/**
 * Get the base url of the request.
 *
 * @return string
 */
function request_url()
{
    $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];
    $path = !empty($_SERVER['REQUEST_URI']) ? explode('?', $_SERVER['REQUEST_URI'])[0] : '';

    return "{$scheme}://{$host}{$path}";
}

/**
 * Detect whether URL rewrite is effectively enabled for the installer.
 *
 * @return bool
 */
function detect_url_rewrite()
{
    global $validInstallationUrlRewrite;

    if (isset($validInstallationUrlRewrite) && $validInstallationUrlRewrite === true) {
        return true;
    }

    // IIS exposes whether the current request was rewritten.
    if (array_get($_SERVER, 'IIS_WasUrlRewritten') === '1') {
        return true;
    }

    // Apache usually sets REDIRECT_URL on internally rewritten requests.
    if (array_get($_SERVER, 'REDIRECT_URL') !== null) {
        return true;
    }

    return false;
}

$requestContent = null;

/**
 * Get an input from the request.
 *
 * @param  string  $key
 * @param  mixed  $default
 *
 * @return null|string
 */
function request_input($key, $default = null)
{
    global $requestContent;

    if (!in_array(request_method(), ['GET', 'HEAD'], true)) {
        if ($requestContent === null) {
            $requestContent = json_decode(file_get_contents('php://input'), true);
        }

        if ($requestContent) {
            $value = array_get($requestContent, $key);

            if ($value !== null) {
                return $value;
            }
        }
    }

    return array_get($_GET, $key, $default);
}

/**
 * Send the response as JSON and exit.
 *
 * @param  array  $data
 * @param  int  $status
 */
function send_json_response($data = null, $status = 200)
{
    if ($data === null && $status === 200) {
        $status = 204;
    }

    if ($status !== 200) {
        http_response_code($status);
    }

    header('Content-Type: application/json');

    if ($data === null) {
        exit();
    }

    exit(json_encode($data));
}

/**
 * Read the given url as a string.
 *
 * @param  string  $url
 * @param  null|array  $curlOptions
 *
 * @return string
 */
function read_url($url, $curlOptions = null)
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_CONNECTTIMEOUT => 150,
        CURLOPT_HTTPHEADER => [
            'User-Agent: CentralCorp Panel Installer v1',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    if ($curlOptions !== null) {
        curl_setopt_array($ch, $curlOptions);
    }

    $response = curl_exec($ch);
    $errno = curl_errno($ch);

    if ($errno || $response === false) {
        $error = curl_error($ch);

        throw new RuntimeException("cURL error {$errno}: {$error}");
    }

    $statusCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

    if ($statusCode >= 400) {
        throw new RuntimeException("HTTP code {$statusCode} returned for '{$url}'.", $statusCode);
    }

    curl_close($ch);

    return $response;
}

/**
 * Download a file from the given url and save it to the given path.
 *
 * @param  string  $url
 * @param  string  $path
 *
 * @return string
 */
function download_file($url, $path)
{
    $fp = fopen($path, 'wb+');

    if ($fp === false) {
        throw new RuntimeException("Unable to open '{$path}' for writing.");
    }

    try {
        return read_url($url, [CURLOPT_FILE => $fp]);
    } finally {
        fclose($fp);
    }
}

/**
 * Find the panel-*.zip asset inside a GitHub release payload.
 *
 * @param  object  $release
 * @return null|object
 */
function find_panel_asset($release)
{
    if (!is_object($release) || !isset($release->assets) || !is_array($release->assets)) {
        return null;
    }

    foreach ($release->assets as $asset) {
        if (
            isset($asset->name, $asset->browser_download_url)
            && str_starts_with($asset->name, 'panel-')
            && str_ends_with($asset->name, '.zip')
        ) {
            return $asset;
        }
    }

    return null;
}

/**
 * Find the matching .sha256 checksum asset inside a GitHub release payload.
 *
 * @param  object  $release
 * @param  string  $zipAssetName
 * @return null|object
 */
function find_panel_hash_asset($release, $zipAssetName)
{
    if (!is_object($release) || !isset($release->assets) || !is_array($release->assets)) {
        return null;
    }

    $expectedName = $zipAssetName . '.sha256';

    foreach ($release->assets as $asset) {
        if (isset($asset->name, $asset->browser_download_url) && $asset->name === $expectedName) {
            return $asset;
        }
    }

    return null;
}

/**
 * Resolve the latest release (including pre-releases) built from the target branch.
 *
 * @param  string  $repository
 * @param  string  $branch
 * @return object
 */
function resolve_panel_release($repository, $branch)
{
    $headers = [
        CURLOPT_HTTPHEADER => [
            'Accept: application/vnd.github+json',
            'User-Agent: CentralCorp Panel Installer v2',
        ],
    ];

    $json = read_url("https://api.github.com/repos/{$repository}/releases?per_page=30", $headers);
    $releases = json_decode($json);

    if (!is_array($releases)) {
        throw new RuntimeException("Unable to fetch releases from GitHub ({$repository}).");
    }

    // Pass 1: newest non-draft release explicitly targeting $branch with a panel-*.zip asset.
    foreach ($releases as $release) {
        if (!empty($release->draft) || find_panel_asset($release) === null) {
            continue;
        }

        if (($release->target_commitish ?? '') === $branch) {
            return $release;
        }
    }

    // Pass 2: newest v2.x release whose tag belongs to $branch history (handles direct tag pushes).
    foreach ($releases as $release) {
        if (!empty($release->draft) || find_panel_asset($release) === null) {
            continue;
        }

        $tag = (string) ($release->tag_name ?? '');
        if (!str_starts_with(ltrim($tag, 'vV'), '2.')) {
            continue;
        }

        try {
            $compareUrl = "https://api.github.com/repos/{$repository}/compare/"
                . rawurlencode($tag) . '...' . rawurlencode($branch);
            $compare = json_decode(read_url($compareUrl, $headers));

            if (is_object($compare) && in_array($compare->status ?? '', ['identical', 'ahead'], true)) {
                return $release;
            }
        } catch (Throwable $e) {
            // Ignore compare error and continue scanning candidates.
        }
    }

    throw new RuntimeException(
        "No matching release with panel-*.zip found for {$repository} on branch '{$branch}'."
    );
}

/**
 * Determines if a function exists and is not disabled.
 *
 * @param  string  $function
 *
 * @return bool
 */
function has_function($function)
{
    if (!function_exists($function)) {
        return false;
    }

    try {
        return strpos(ini_get('disable_functions'), $function) === false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Check if the current OS is Windows.
 *
 * @return bool
 */
function is_windows()
{
    return stripos(PHP_OS, 'WIN') === 0;
}

if (array_get($_GET, 'phpinfo') === '') {
    phpinfo();
    exit();
}

// If the panel has already been extracted and this entry point still exists, delegate directly to Laravel.
if (
    file_exists(__DIR__ . '/vendor/autoload.php')
    && file_exists(__DIR__ . '/public/index.php')
    && file_exists(__DIR__ . '/artisan')
    && array_get($_GET, 'execute') !== 'php'
    && array_get($_SERVER, 'HTTP_X_REQUESTED_WITH') !== 'XMLHttpRequest'
) {
    require __DIR__ . '/public/index.php';
    exit();
}

//
// Give the requested data if the request is from AJAX.
//
if (
    array_get($_SERVER, 'HTTP_X_REQUESTED_WITH') === 'XMLHttpRequest'
    || array_get($_GET, 'execute') === 'php'
) {
    try {
        $data = [
            'installerVersion' => $installerVersion,
            'panelRepository' => $panelRepository,
            'panelBranch' => $panelBranch,
            'minPhpVersion' => $minPhpVersion,
            'phpVersion' => parse_php_version(),
            'phpFullVersion' => PHP_VERSION,
            'phpIniPath' => php_ini_loaded_file(),
            'path' => __DIR__,
            'file' => __FILE__,
            'htaccess' => file_exists(__DIR__ . '/.htaccess') && file_exists(__DIR__ . '/public/.htaccess'),
            'webConfig' => file_exists(__DIR__ . '/web.config') && file_exists(__DIR__ . '/public/web.config'),
            'windows' => is_windows(),
        ];

        $step = 'check';

        $writable = is_writable(__DIR__) && is_writable(__DIR__ . '/public');

        $requirements = [
            'php' => version_compare(PHP_VERSION, $minPhpVersion, '>='),
            'writable' => $writable,
            'function-symlink' => has_function('symlink'),
            'rewrite' => detect_url_rewrite(),
        ];

        $extracted = file_exists(__DIR__ . '/vendor');

        foreach ($requiredExtensions as $extension) {
            $requirements['extension-' . $extension] = extension_loaded($extension);
        }

        $data['requirements'] = $requirements;

        $data['compatible'] = !in_array(false, $requirements, true);
        $data['downloaded'] = file_exists(__DIR__ . '/CentralCorpPanel.zip');
        $data['extracted'] = $extracted;

        $action = request_input('action');

        if (request_method() !== 'POST') {
            send_json_response($data);
        }

        if ($action === 'download') {
            // Resolve the latest release from CentralCorp/centralpanel-v2 targeting branch v2-rework
            $release = resolve_panel_release($panelRepository, $panelBranch);
            $asset = find_panel_asset($release);

            if (!$asset) {
                throw new RuntimeException(
                    "No matching asset (panel-*.zip) found in release '{$release->tag_name}' ({$panelBranch})."
                );
            }

            $file = __DIR__ . '/' . basename($asset->name);

            // Download archive
            download_file($asset->browser_download_url, $file);

            if (!file_exists($file)) {
                throw new RuntimeException('The file was not downloaded.');
            }

            // Verify SHA-256 checksum when provided alongside the release asset
            $hashAsset = find_panel_hash_asset($release, $asset->name);
            if ($hashAsset !== null) {
                $hashContent = trim(read_url($hashAsset->browser_download_url));
                if (preg_match('/^([a-f0-9]{64})\b/i', $hashContent, $hashMatches)) {
                    $actualHash = hash_file('sha256', $file);
                    if (!is_string($actualHash) || !hash_equals(strtolower($hashMatches[1]), strtolower($actualHash))) {
                        @unlink($file);
                        throw new RuntimeException('Downloaded archive SHA-256 checksum mismatch.');
                    }
                }
            }

            // Extract
            $zip = new ZipArchive();

            if (($status = $zip->open($file)) !== true) {
                @unlink($file);
                throw new RuntimeException('Unable to open zip: ' . $status . '.');
            }

            if (!$zip->extractTo(__DIR__)) {
                $zip->close();
                @unlink($file);
                throw new RuntimeException('Unable to extract zip');
            }

            $zip->close();
            $finalHtaccess = __DIR__ . '/.htaccess';

            // Define the content of the .htaccess file
            $htaccessContent = "<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Empecher l'accès aux fichiers sensibles
    RewriteRule ^(.env|composer.json|package.json)$ - [F,L]

    # Rediriger vers le dossier public
    RewriteCond %{REQUEST_FILENAME} -d [OR]
    RewriteCond %{REQUEST_FILENAME} -f
    RewriteRule ^ ^$1 [N]

    RewriteCond %{REQUEST_URI} (\.\w+$) [NC]
    RewriteRule ^(.*)$ public/$1

    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ server.php [L]
</IfModule>";

            // Write the .htaccess file
            file_put_contents($finalHtaccess, $htaccessContent);

            // Cleanup zip file and installer entry point
            if (file_exists($file)) {
                unlink($file);
            }

            @unlink(__FILE__);

            $data['extracted'] = true;
            send_json_response($data);
        }

        send_json_response('Unexpected action: ' . $action, 403);
    } catch (Throwable $t) {
        send_json_response(['message' => $t->getMessage()], 500);
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <link rel="icon" href="https://centralcorp.github.io/img/panel.png" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Installation - CentralCorp Panel</title>
    <script type="module" crossorigin src="/assets/index-CMKKpWNS.js"></script>
    <link rel="stylesheet" crossorigin href="/assets/index-BIySbxus.css">
</head>

<body>
    <div id="app"></div>
</body>

</html>
