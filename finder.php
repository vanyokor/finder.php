<?php
/*
    ❗Пожалуйста, в конце работ, не забывайте удалять скрипт с сайта❗
    Инструкция по работе: https://github.com/vanyokor/finder.php/blob/main/README.md
*/

// TODO удалить к релизу
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Ключ запуска, для GET параметра ?run=
define('F2_UI_KEY', '__PASTE_UI_KEY_HERE__');
// Ключ авторизации
define('F2_TOKEN', '__PASTE_TOKEN_HERE__');

// Версия скрипта
define('F2_VERSION', '2.0');

// Стандартная маска файлов 
define('F2_DEFAULT_FILE_MASK', '*.{php,js}');

define('F2_TTL', 28800);
define('F2_TIME_BUDGET', 50);
define('F2_MAX_FILE_SIZE', 5242880);
define('F2_MAX_SENSITIVE_SIZE', 524288);
define('F2_MAX_LISTED_SKIPPED', 20);
define('F2_MAX_DEPTH', 25);
define('F2_DEFAULT_RESULTS', 200);
define('F2_MAX_RESULTS', 1000);
define('F2_MAX_PATTERN', 500);
define('F2_MAX_GLOB', 200);
define('F2_MAX_EXCLUDE', 50);
define('F2_MAX_PATH', 1000);
define('F2_MAX_LINE', 500);
define('F2_MATCH_LEAD', 150);
define('F2_MAX_CONTEXT', 10);
define('F2_MAX_CONTEXT_LINES', 2000);
define('F2_MINIFIED_LINE', 300);
define('F2_BINARY_CONTROL', 0.05);
define('F2_READ_SENSITIVE', true);
define('F2_MASK_SECRETS', true);
define('F2_TOKEN_MIN_LENGTH', 10);
define('F2_UI_KEY_MIN_LENGTH', 5);
define('F2_TOKEN_PLACEHOLDER', '__PASTE_' . 'TOKEN_HERE__');
define('F2_UI_KEY_PLACEHOLDER', '__PASTE_' . 'UI_KEY_HERE__');

/*
    Запрещённые имена файлов и папок
*/
function f2_forbidden_regex()
{
    return '/^(?:\.git|\.svn|\.hg|\.bzr|\.ssh|\.aws|\.gnupg|\.git-credentials|\.htpasswd|\.htdigest|\.netrc|\.pgpass|\.idea|\.vscode|\.\w*_history|id_(?:rsa|dsa|ecdsa|ed25519)(?:\..*)?|.*\.(?:pem|key|p12|pfx|jks|keystore|kdbx|ppk|crt|csr)|.*\.(?:sql|sqlite3?|db|dump|bak|backup|old|orig|save|swp|swo|tmp)|.*\.(?:zip|tar|gz|tgz|bz2|xz|7z|rar)|.*~)$/is';
}

/*
    Файлы с секретами
*/
function f2_sensitive_regex()
{
    return '/^(?:\.env.*|.*\.env|wp-config.*|config\.php|config\.inc\.php|configuration\.php|settings(?:\.local)?\.php|\.settings(?:_extra)?\.php|dbconn\.php|db\.php|database\.php|localsettings\.php|database\.ya?ml|local\.xml|env\.php|parameters\.ya?ml|secrets?(?:\..*)?|auth\.json|credentials(?:\..*)?|\.npmrc|\.yarnrc(?:\.ya?ml)?|\.pypirc|\.my\.cnf|sftp-config\.json|\.ftpconfig|\.remote-sync\.json)$/is';
}

/*
    Проверка чувствительности файла
*/
function f2_name_kind($name)
{
    if (preg_match(f2_forbidden_regex(), $name)) {
        return 'forbidden';
    }
    if (preg_match(f2_sensitive_regex(), $name)) {
        return F2_READ_SENSITIVE ? 'sensitive' : 'forbidden';
    }
    return '';
}

/*
    Папки для исключения
*/
function f2_ignored_dirs()
{
    return array(
        'node_modules',
        'vendor',
        'cache',
        'tmp',
        'logs',
        'stats',
        'awstats',
        'managed_cache',
        'stack_cache',
        'html_pages',
        'resize_cache',
        'wa-cache',
        'vqcache',
        'seo_backup',
        'system/storage',
        'bitrix/js', 
        'bitrix/updates', 
        'bitrix/modules/*/install',
    );
}

/*
    Точка входа
*/
f2_main();

function f2_main()
{
    if (function_exists('ini_set')) {
        // @ini_set('display_errors', '0'); TODO вернуть к релизу
        @ini_set('memory_limit', '1G');
        @ini_set('max_execution_time', '60');
    }
    if (function_exists('set_time_limit')) {
        @set_time_limit(60);
    }
    $started = microtime(true);
    $expired = f2_expired();
    $deleted = $expired && f2_self_delete();

    // Авторизация
    if (!f2_token_ok()) {
        f2_deny();
    }

    // Передача текста ошибок только после авторизации
    if (function_exists('register_shutdown_function')) {
        register_shutdown_function('f2_on_shutdown');
    }

    $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : '';
    if ($expired) {
        f2_gone($method, $deleted);
    }

    if ($method === 'GET') {
        f2_page();
    }
    if ($method !== 'POST') {
        f2_json(405, array('error' => 'поддерживаются только GET, POST'));
    }
    if (f2_param('action') === 'status') {
        f2_info();
    }
    if (f2_param('action') === 'delete') {
        f2_delete_now();
    }
    f2_search($started);
}

/*
    Ответы сервера
*/
function f2_status($code)
{
    $texts = array(
        200 => 'OK',
        204 => 'No Content',
        400 => 'Bad Request',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        410 => 'Gone',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
    );
    header('HTTP/1.1 ' . $code . ' ' . $texts[$code], true, $code);
}

/*
    Защитные заголовки ответа
*/
function f2_common_headers()
{
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    header('X-Content-Type-Options: nosniff');
}

/*
    Маскировка под отсутствующий файл, при неудачной авторизации
*/
function f2_deny()
{
    if (function_exists('usleep')) {
        usleep(function_exists('mt_rand') ? mt_rand(200000, 400000) : 300000);
    }
    f2_common_headers();
    f2_status(404);
    header('Content-Type: text/html; charset=iso-8859-1');
    echo "<!DOCTYPE HTML PUBLIC \"-//IETF//DTD HTML 2.0//EN\">\n"
        . "<html><head>\n<title>404 Not Found</title>\n</head><body>\n"
        . "<h1>Not Found</h1>\n<p>The requested URL was not found on this server.</p>\n"
        . "</body></html>\n";
    exit;
}

/*
    Ответ после истечения срока работы скрипта
*/
function f2_gone($method, $deleted)
{
    if ($method === 'GET') {
        f2_common_headers();
        f2_status(410);
        header('Content-Type: text/plain; charset=utf-8');
        echo $deleted
            ? "Срок жизни скрипта истёк, скрипт удалён.\n"
            : "Срок жизни скрипта истёк, удалить скрипт не удалось: удалите его вручную.\n";
        exit;
    }
    f2_json(410, array(
        'error' => $deleted
            ? 'срок жизни скрипта истёк, скрипт удалён'
            : 'срок жизни скрипта истёк, удалить скрипт не удалось: удалите его вручную',
        'deleted' => $deleted,
    ));
}

/*
    Ответ JSON
*/
function f2_json($code, $data)
{
    f2_common_headers();
    f2_status($code);
    header('Content-Type: application/json; charset=utf-8');
    $flags = version_compare(PHP_VERSION, '5.4.0', '>=') ? 320 : 0;
    $out = json_encode($data, $flags);
    if (!is_string($out)) {
        f2_status(500);
        $out = '{"error":"internal: json_encode failed"}';
    }
    echo $out;
    $GLOBALS['f2_sent'] = true;
    exit;
}

/*
    Ответ при падении скрипта
*/
function f2_on_shutdown()
{
    if (!empty($GLOBALS['f2_sent']) || !function_exists('error_get_last')) {
        return;
    }
    $error = error_get_last();
    if (!is_array($error) || !in_array($error['type'], array(E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        return;
    }
    $message = f2_fatal_message($error);
    if (isset($GLOBALS['f2_ctx'])) {
        $ctx =& $GLOBALS['f2_ctx'];
        $ctx['truncated']['fatal'] = true;
        $ctx['error'] = 'поиск прерван ошибкой PHP: ' . $message . ', показано найденное до неё';
        f2_result($ctx);
    }
    f2_json(500, array('error' => 'ошибка PHP: ' . $message));
}

/*
    Парсер сообщения об ошибке
*/
function f2_fatal_message($error)
{
    $message = (string) $error['message'];
    $nl = strpos($message, "\n");
    if ($nl !== false) {
        $message = substr($message, 0, $nl);
    }
    $at = strpos($message, ' in ' . $error['file']);
    if ($at !== false) {
        $message = substr($message, 0, $at);
    }
    $message = preg_replace('/^Uncaught \w+: /', '', $message);
    return f2_utf8(str_replace(dirname(__FILE__), '...', substr($message, 0, 300)));
}

/*
    Ответ с ошибкой
*/
function f2_error($message)
{
    f2_json(400, array('error' => $message));
}

/*
    Проверка срока жизни скрипта
*/
function f2_expired()
{
    $uploaded = f2_uploaded_at();
    return $uploaded === 0 || time() > $uploaded + F2_TTL;
}

/*
    Время загрузки скрипта
*/
function f2_uploaded_at()
{
    if (function_exists('clearstatcache')) {
        clearstatcache();
    }
    $now = time();
    $latest = 0;
    foreach (array('filemtime', 'filectime') as $func) {
        $t = function_exists($func) ? @$func(__FILE__) : false;
        if ($t !== false && $t <= $now + 600 && $t > $latest) {
            $latest = $t;
        }
    }
    return $latest;
}

/*
    Удаляет сам скрипт
*/
function f2_self_delete()
{
    if (!function_exists('unlink')) {
        return false;
    }
    @unlink(__FILE__);
    $invalidate = 'opcache_invalidate';
    if (function_exists($invalidate)) {
        @$invalidate(__FILE__, true);
    }
    if (function_exists('clearstatcache')) {
        clearstatcache();
    }
    return !@file_exists(__FILE__);
}

/*
    Удаление скрипта по запросу
*/
function f2_delete_now()
{
    if (!f2_self_delete()) {
        f2_json(500, array(
            'error' => 'удалить скрипт не удалось: удалите его вручную',
            'deleted' => false,
        ));
    }

    f2_json(200, array('deleted' => true));
}

/*
    Авторизация
*/
function f2_token_ok()
{
    if (F2_TOKEN === F2_TOKEN_PLACEHOLDER || strlen(F2_TOKEN) < F2_TOKEN_MIN_LENGTH) {
        return false;
    }
    $given = isset($_SERVER['HTTP_X_F2_TOKEN']) ? (string) $_SERVER['HTTP_X_F2_TOKEN'] : '';
    if (f2_secret_equals(F2_TOKEN, $given)) {
        return true;
    }
    $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : '';
    if ($method === 'GET' && f2_ui_key_ok() && isset($_GET['run']) && is_string($_GET['run'])) {
        return f2_secret_equals(F2_UI_KEY, $_GET['run']);
    }
    return false;
}

/*
    Сравнивает хэши
*/
function f2_secret_equals($known, $given)
{
    if (!function_exists('hash')) {
        return f2_equals(sha1($known), sha1($given));
    }
    return f2_equals(hash('sha256', $known), hash('sha256', $given));
}

/*
    Проверка ключа запуска
*/
function f2_ui_key_ok()
{
    return F2_UI_KEY !== F2_UI_KEY_PLACEHOLDER && strlen(F2_UI_KEY) >= F2_UI_KEY_MIN_LENGTH && F2_UI_KEY !== F2_TOKEN;
}

/*
    Сравнение строк, аналог hash_equals
*/
function f2_equals($known, $given)
{
    if (strlen($known) !== strlen($given)) {
        return false;
    }
    $diff = 0;
    for ($i = 0, $n = strlen($known); $i < $n; $i++) {
        $diff |= ord($known[$i]) ^ ord($given[$i]);
    }
    return $diff === 0;
}

/*
    Прием значения hex из формы
*/
function f2_param($name)
{
    if (!isset($_POST[$name]) || !is_string($_POST[$name])) {
        return '';
    }
    $hex = $_POST[$name];
    if (strlen($hex) % 2 !== 0 || !preg_match('/^[0-9a-fA-F]*$/', $hex)) {
        f2_error('параметр ' . $name . ' должен быть в hex');
    }
    return (string) pack('H*', $hex);
}

/*
    Параметр - Корень сайта
*/
function f2_root_param()
{
    $root = f2_param('root');
    if ($root === '') {
        $root = '.';
    }
    if ($root !== '.' && $root !== '..') {
        f2_error("root может быть только '.' или '..'");
    }
    return $root;
}

/*
    Состояние скрипта
*/
function f2_info()
{
    $root = f2_root_param();
    $missing = f2_missing_functions();
    $base = function_exists('realpath') ? realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR . $root) : false;
    $readable = $base !== false && is_dir($base) && is_readable($base);
    $listable = false;
    if ($readable && function_exists('opendir')) {
        $dh = @opendir($base);
        if ($dh) {
            $listable = true;
            closedir($dh);
        }
    }
    f2_json(200, array(
        'expires_in' => max(0, f2_uploaded_at() + F2_TTL - time()),
        'root' => array(
            'listable' => $listable,
        ),
        // Отключённые функции, без них скрипт не работает
        'missing_functions' => $missing,
        // Чем перекодируются файлы в Windows-1251 и UTF-16
        'cp1251' => f2_cp1251_converter(),
    ));
}

/*
    Получает значение параметра конфигурации
*/
function f2_ini($name)
{
    return function_exists('ini_get') ? (string) ini_get($name) : '';
}

/*
    Проверка наличия функций, без которых поиск невозможен
*/
function f2_missing_functions()
{
    $missing = array();
    foreach (array('pack', 'realpath', 'scandir', 'fopen', 'fread', 'stream_get_contents', 'filesize') as $func) {
        if (!function_exists($func)) {
            $missing[] = $func;
        }
    }
    return $missing;
}

/*
    Бюджет времени на поиск
*/
function f2_time_budget()
{
    $limit = (int) f2_ini('max_execution_time');
    if ($limit <= 0) {
        return F2_TIME_BUDGET;
    }
    return min(F2_TIME_BUDGET, max(1, (int) floor($limit * 5 / 6)));
}

/*
    Запуск поиска
*/
function f2_search($started)
{
    $missing = f2_missing_functions();
    if ($missing) {
        f2_json(500, array('error' => 'на хостинге отключены функции PHP: ' . implode(', ', $missing)));
    }
    $deadline = $started + f2_time_budget();

    // pattern
    $pattern = f2_param('pattern');
    if ($pattern === '') {
        f2_error('pattern не может быть пустым');
    }
    if (strlen($pattern) > F2_MAX_PATTERN) {
        f2_error('pattern длиннее ' . F2_MAX_PATTERN . ' байт');
    }
    if (strpos($pattern, "\0") !== false) {
        f2_error('pattern содержит нулевой байт');
    }
    $regex = f2_build_regex($pattern);

    // glob
    $glob = trim(f2_param('glob'));
    $globRegex = null;
    if ($glob !== '') {
        if (strlen($glob) > F2_MAX_GLOB) {
            f2_error('glob длиннее ' . F2_MAX_GLOB . ' байт');
        }
        $globRegex = f2_glob_regex($glob);
        if ($globRegex === false || @preg_match($globRegex, '') === false) {
            f2_error('некорректный glob');
        }
    }

    $exclude = f2_exclude_param();

    // mode
    $mode = trim(f2_param('mode'));
    if ($mode === '') {
        $mode = 'lines';
    }
    if ($mode !== 'lines' && $mode !== 'count' && $mode !== 'files') {
        f2_error('mode должен быть lines, count или files');
    }

    // max_results
    $max = F2_DEFAULT_RESULTS;
    $rawMax = trim(f2_param('max_results'));
    if ($rawMax !== '') {
        if (!preg_match('/^\d{1,9}$/', $rawMax)) {
            f2_error('max_results должен быть целым числом');
        }
        $max = max(1, min(F2_MAX_RESULTS, (int) $rawMax));
    }

    $minified = trim(f2_param('minified'));
    if ($minified !== '' && $minified !== '0' && $minified !== '1') {
        f2_error('minified должен быть 0 или 1');
    }

    $mtime = trim(f2_param('mtime'));
    if ($mtime !== '' && $mtime !== '0' && $mtime !== '1') {
        f2_error('mtime должен быть 0 или 1');
    }

    // before, after: контекст нужен только строкам
    $before = $mode === 'lines' ? f2_context_param('before') : 0;
    $after = $mode === 'lines' ? f2_context_param('after') : 0;

    // root и path
    $base = realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR . f2_root_param());
    if ($base === false) {
        f2_error('корень сканирования недоступен');
    }
    $path = f2_param('path');
    $parts = f2_resolve_path($base, f2_split_path($path, false));
    if ($parts === false && strpos($path, '\\') !== false && DIRECTORY_SEPARATOR === '/') {
        $parts = f2_resolve_path($base, f2_split_path($path, true));
    }
    if ($parts === false) {
        f2_error('path не найден');
    }
    $target = $base;
    foreach ($parts as $part) {
        $target .= DIRECTORY_SEPARATOR . $part;
    }
    $real = realpath($target);
    if ($real === false || !f2_same_path($real, $target)) {
        f2_error('path не найден');
    }
    $name = $parts ? $parts[count($parts) - 1] : '';
    if ($name !== '' && f2_name_kind($name) === 'sensitive' && !is_file($real)) {
        f2_error('path не найден');
    }

    $GLOBALS['f2_ctx'] = array(
        'started' => $started,
        'regex' => $regex,
        'glob' => $globRegex,
        'glob_path' => $glob !== '' && strpos($glob, '/') !== false,
        'max' => $max,
        'mode' => $mode,
        'before' => $before,
        'after' => $after,
        'context_left' => F2_MAX_CONTEXT_LINES,
        'deadline' => $deadline,
        'self' => realpath(__FILE__),
        'ignored' => f2_ignored_dirs(),
        'exclude' => $exclude,
        'skip_minified' => $minified !== '1' && !is_file($real),
        'want_mtime' => $mtime === '1',
        'mtime' => null,
        'matches' => array(),
        'files' => array(),
        'current' => null,
        'masked' => false,
        'masked_lines' => array(),
        'line_starts' => array(),
        'stop' => false,
        'truncated' => array('timeout' => false, 'max_results' => false, 'context' => false, 'fatal' => false),
        'stats' => array(
            'files_scanned' => 0,
            'files_masked' => 0,
            'secrets_masked' => 0,
            'dirs_scanned' => 0,
            'regex_errors' => 0,
            'skipped' => array(
                'forbidden' => 0,
                'unmaskable' => 0,
                'symlink' => 0,
                'too_large' => 0,
                'binary' => 0,
                'unreadable' => 0,
                'depth' => 0,
                'ignored_dirs' => 0,
                'excluded' => 0,
                'minified' => 0,
            ),
            'too_large_files' => array(),
        ),
    );
    $ctx =& $GLOBALS['f2_ctx'];

    $rel = implode('/', $parts);
    if (is_dir($real)) {
        f2_walk($real, $rel, $ctx);
    } elseif (is_file($real)) {
        f2_scan_file($real, $rel, $name, f2_name_kind($name), $ctx);
    } else {
        f2_error('path не найден');
    }
    f2_result($ctx);
}

/*
    Ответ поиска
*/
function f2_result(&$ctx)
{
    $mode = $ctx['mode'];
    $ctx['stats']['elapsed_ms'] = (int) round((microtime(true) - $ctx['started']) * 1000);
    $result = array();
    if ($mode === 'lines') {
        $result['matches'] = $ctx['matches'];
    } else {
        $result['files'] = $ctx['files'];
        if ($mode === 'count') {
            $total = 0;
            $occurrences = 0;
            foreach ($ctx['files'] as $file) {
                $total += $file['count'];
                $occurrences += $file['occurrences'];
            }
            $result['total'] = $total;
            $result['total_occurrences'] = $occurrences;
        }
    }
    $result['truncated'] = $ctx['truncated'];
    if (isset($ctx['error'])) {
        $result['error'] = $ctx['error'];
    }
    $result['stats'] = $ctx['stats'];
    f2_json(200, $result);
}

/*
    Параметр - количество строк контекста
*/
function f2_context_param($name)
{
    $raw = trim(f2_param($name));
    if ($raw === '') {
        return 0;
    }
    if (!preg_match('/^\d{1,9}$/', $raw)) {
        f2_error($name . ' должен быть целым числом');
    }
    return min(F2_MAX_CONTEXT, (int) $raw);
}

/*
    Запуск регулярного выражения
*/
function f2_build_regex($pattern)
{
    $flags = 'm';
    if (preg_match('/[\x80-\xFF]/', $pattern)) {
        if (!preg_match('//u', $pattern)) {
            f2_error('pattern должен быть в кодировке UTF-8');
        }
        $flags .= 'u';
    }
    $body = '';
    $escaped = array();
    $n = strlen($pattern);
    for ($i = 0; $i < $n; $i++) {
        $c = $pattern[$i];
        if ($c === '\\' && $i + 1 < $n) {
            $body .= $c . $pattern[$i + 1];
            $i++;
        } elseif ($c === '/') {
            $escaped[] = strlen($body);
            $body .= '\\/';
        } else {
            $body .= $c;
        }
    }
    $prefix = '(*ANYCRLF)';
    $regex = '/' . $prefix . $body . '/' . $flags;
    if (@preg_match($regex, '') === false) {
        $message = 'некорректное регулярное выражение';
        $last = error_get_last();
        if (is_array($last) && strpos($last['message'], 'preg_match') !== false) {
            $detail = preg_replace('/^preg_match\(\):\s*/', '', $last['message']);
            if (preg_match('/at offset (\d+)/', $detail, $m)) {
                $offset = max(0, (int) $m[1] - strlen($prefix));
                $shift = 0;
                foreach ($escaped as $pos) {
                    if ($pos < $offset) {
                        $shift++;
                    }
                }
                $detail = str_replace($m[0], 'at offset ' . ($offset - $shift), $detail);
            }
            $message .= ': ' . $detail;
        }
        f2_error($message);
    }
    return $regex;
}

/*
    Glob в regex
*/
function f2_glob_regex($glob)
{
    $out = '';
    $braces = 0;
    $n = strlen($glob);
    for ($i = 0; $i < $n; $i++) {
        $c = $glob[$i];
        if ($c === '*') {
            if ($i + 1 < $n && $glob[$i + 1] === '*') {
                $out .= '.*';
                $i++;
            } else {
                $out .= '[^/]*';
            }
        } elseif ($c === '?') {
            $out .= '[^/]';
        } elseif ($c === '{') {
            $out .= '(?:';
            $braces++;
        } elseif ($c === '}' && $braces > 0) {
            $out .= ')';
            $braces--;
        } elseif ($c === ',' && $braces > 0) {
            $out .= '|';
        } elseif ($c === '[') {
            $end = strpos($glob, ']', $i + 1);
            if ($end === false) {
                return false;
            }
            $class = substr($glob, $i + 1, $end - $i - 1);
            $negate = $class !== '' && ($class[0] === '!' || $class[0] === '^');
            if ($negate) {
                $class = substr($class, 1);
            }
            $out .= '[' . ($negate ? '^' : '') . addcslashes($class, '\\#]^') . ']';
            $i = $end;
        } else {
            $out .= preg_quote($c, '#');
        }
    }
    if ($braces !== 0) {
        return false;
    }
    return '#^' . $out . '$#is';
}

/*
    Параметр - исключить из поиска
*/
function f2_exclude_param()
{
    $raw = str_replace(array("\r\n", "\r"), "\n", f2_param('exclude'));
    $list = array();
    foreach (explode("\n", $raw) as $item) {
        $item = trim(str_replace('\\', '/', $item));
        if (substr($item, 0, 2) === './') {
            $item = substr($item, 2);
        }
        $item = rtrim($item, '/');
        if ($item === '') {
            continue;
        }
        if (count($list) >= F2_MAX_EXCLUDE) {
            f2_error('exclude: не больше ' . F2_MAX_EXCLUDE . ' масок');
        }
        if (strlen($item) > F2_MAX_GLOB || strpos($item, "\0") !== false) {
            f2_error('exclude: маска длиннее ' . F2_MAX_GLOB . ' байт или содержит нулевой байт');
        }
        $byPath = strpos($item, '/') !== false;
        $regex = f2_glob_regex(ltrim($item, '/'));
        if ($regex === false || @preg_match($regex, '') === false) {
            f2_error('exclude: некорректная маска ' . f2_utf8($item));
        }
        $list[] = array($regex, $byPath);
    }
    return $list;
}

/*
    Исключен ли путь из поиска
*/
function f2_is_excluded($name, $rel, $exclude)
{
    $name = f2_display_path($name);
    $rel = f2_display_path($rel);
    foreach ($exclude as $item) {
        if (preg_match($item[0], $item[1] ? $rel : $name)) {
            return true;
        }
    }
    return false;
}

/*
    Разбивает введеный путь
*/
function f2_split_path($path, $literalBackslash)
{
    if (strlen($path) > F2_MAX_PATH || strpos($path, "\0") !== false) {
        f2_error('некорректный path');
    }
    $path = trim($literalBackslash ? $path : str_replace('\\', '/', $path));
    if ($path !== '' && ($path[0] === '/' || preg_match('/^[A-Za-z]:/', $path))) {
        f2_error('path должен быть относительным');
    }
    $parts = array();
    foreach (explode('/', $path) as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..') {
            f2_error("path не может содержать '..'");
        }
        $parts[] = $part;
    }
    $last = count($parts) - 1;
    foreach ($parts as $i => $part) {
        $kind = f2_name_kind($part);
        if ($kind === 'forbidden' || ($kind === 'sensitive' && $i !== $last)) {
            f2_error('path не найден');
        }
    }
    return $parts;
}

/*
    Составление корректного пути, независимо от ОС 
*/
function f2_resolve_path($base, $parts)
{
    $dir = $base;
    $found = array();
    foreach ($parts as $part) {
        $match = false;
        foreach (f2_name_variants($part) as $name) {
            if (@file_exists($dir . DIRECTORY_SEPARATOR . $name)) {
                $match = $name;
                break;
            }
        }
        if ($match === false) {
            return false;
        }
        $found[] = $match;
        $dir .= DIRECTORY_SEPARATOR . $match;
    }
    return $found;
}

/*
    Нормализация имени
*/
function f2_name_variants($name)
{
    $variants = array($name);
    if (!preg_match('/[\x80-\xFF]/', $name) || !preg_match('//u', $name)) {
        return $variants;
    }
    $cp1251 = f2_utf8_to_cp1251($name);
    if ($cp1251 !== null) {
        $variants[] = $cp1251;
    }
    $nfc = array(
        "\xD0\xB9" => "\xD0\xB8\xCC\x86",
        "\xD0\x99" => "\xD0\x98\xCC\x86",
        "\xD1\x91" => "\xD0\xB5\xCC\x88",
        "\xD0\x81" => "\xD0\x95\xCC\x88"
    );
    $variants[] = strtr($name, $nfc);
    $variants[] = strtr($name, array_flip($nfc));
    if (class_exists('Normalizer')) {
        $variants[] = Normalizer::normalize($name, Normalizer::FORM_D);
        $variants[] = Normalizer::normalize($name, Normalizer::FORM_C);
    }
    return array_values(array_unique(array_filter($variants, 'is_string')));
}

/*
    UTF-8 в Windows-1251
*/
function f2_utf8_to_cp1251($text)
{
    $converted = false;
    if (function_exists('mb_convert_encoding')) {
        $converted = mb_convert_encoding($text, 'Windows-1251', 'UTF-8');
    } elseif (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'Windows-1251', $text);
    }
    if (!is_string($converted) || f2_cp1251_to_utf8($converted) !== $text) {
        return null;
    }
    return $converted;
}

/*
    Экранизация пути
*/
function f2_display_path($rel)
{
    if (preg_match('//u', $rel)) {
        return $rel;
    }
    $parts = explode('/', $rel);
    foreach ($parts as $k => $part) {
        if (!preg_match('//u', $part)) {
            $parts[$k] = f2_utf8(f2_cp1251_to_utf8($part));
        }
    }
    return implode('/', $parts);
}

/*
    Проверка на совпадение путей
*/
function f2_same_path($a, $b)
{
    $a = rtrim($a, '/\\');
    $b = rtrim($b, '/\\');
    if (DIRECTORY_SEPARATOR === '\\') {
        return strtolower($a) === strtolower($b);
    }
    return $a === $b;
}

/*
    Обход в глубину без рекурсии
*/
function f2_walk($dir, $rel, &$ctx)
{
    $stack = array(array($dir, $rel, 0));
    while ($stack && !$ctx['stop']) {
        if (microtime(true) > $ctx['deadline']) {
            $ctx['truncated']['timeout'] = true;
            return;
        }
        $item = array_pop($stack);
        $current = $item[0];
        $currentRel = $item[1];
        $depth = $item[2];

        $entries = @scandir($current);
        if ($entries === false) {
            $ctx['stats']['skipped']['unreadable']++;
            continue;
        }
        $ctx['stats']['dirs_scanned']++;
        $subdirs = array();
        foreach ($entries as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $abs = $current . DIRECTORY_SEPARATOR . $name;
            $entryRel = $currentRel === '' ? $name : $currentRel . '/' . $name;
            $kind = f2_name_kind($name);
            if ($kind === 'forbidden' || ($kind === 'sensitive' && is_dir($abs))) {
                if (is_dir($abs) || f2_glob_matches($entryRel, $ctx)) {
                    $ctx['stats']['skipped']['forbidden']++;
                }
                continue;
            }
            if (is_link($abs)) {
                $ctx['stats']['skipped']['symlink']++;
                continue;
            }
            if ($ctx['exclude'] && f2_is_excluded($name, $entryRel, $ctx['exclude'])) {
                $ctx['stats']['skipped']['excluded']++;
                continue;
            }
            if (is_dir($abs)) {
                if (f2_is_ignored_dir($name, $entryRel, $ctx['ignored'])) {
                    $ctx['stats']['skipped']['ignored_dirs']++;
                } elseif ($depth + 1 > F2_MAX_DEPTH) {
                    $ctx['stats']['skipped']['depth']++;
                } else {
                    $subdirs[] = array($abs, $entryRel, $depth + 1);
                }
                continue;
            }
            if (is_file($abs)) {
                f2_scan_file($abs, $entryRel, $name, $kind, $ctx);
                if ($ctx['stop']) {
                    return;
                }
            } elseif (!@file_exists($abs)) {
                $ctx['stats']['skipped']['unreadable']++;
            }
        }
        for ($i = count($subdirs) - 1; $i >= 0; $i--) {
            $stack[] = $subdirs[$i];
        }
    }
}

/*
    Игнорировать ли путь
*/
function f2_is_ignored_dir($name, $rel, $ignored)
{
    foreach ($ignored as $item) {
        if (strpos($item, '/') === false) {
            if ($name === $item) {
                return true;
            }
        } elseif (strpos($item, '*') !== false) {
            $regex = '#(?:^|/)' . str_replace('\\*', '[^/]*', preg_quote($item, '#')) . '$#';
            if (preg_match($regex, $rel)) {
                return true;
            }
        } elseif ($rel === $item || substr($rel, -strlen($item) - 1) === '/' . $item) {
            return true;
        }
    }
    return false;
}

/*
    Список пропущенных строк
*/
function f2_glob_matches($rel, $ctx)
{
    if ($ctx['glob'] === null) {
        return true;
    }
    $rel = f2_display_path($rel);
    $slash = strrpos($rel, '/');
    return preg_match($ctx['glob'], $ctx['glob_path'] || $slash === false ? $rel : substr($rel, $slash + 1)) === 1;
}

/*
    Название функции для перекодировки Windows-1251
*/
function f2_cp1251_converter()
{
    if (function_exists('mb_convert_encoding')) {
        return 'mbstring';
    }
    return function_exists('iconv') ? 'iconv' : '';
}

/*
    Корректор определения кодировки
*/
function f2_decode($content)
{
    $utf16 = f2_utf16_bom($content);
    if ($utf16 !== '') {
        return f2_utf16_to_utf8(substr($content, 2), $utf16);
    }
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
        $content = substr($content, 3);
    }
    if (preg_match('//u', $content)) {
        return $content;
    }
    if (!preg_match('/[\xC2-\xF4][\x80-\xBF]/', $content)) {
        return f2_cp1251_to_utf8($content);
    }
    $out = '';
    $len = strlen($content);
    for ($pos = 0; $pos < $len; $pos = $end + 1) {
        $end = strpos($content, "\n", $pos);
        if ($end === false) {
            $end = $len;
        }
        $line = substr($content, $pos, $end - $pos);
        $out .= (preg_match('//u', $line) ? $line : f2_cp1251_to_utf8($line)) . ($end < $len ? "\n" : '');
    }
    return $out;
}

/*
    Чтение BOM символов
*/
function f2_utf16_bom($head)
{
    $bom = substr($head, 0, 2);
    if ($bom === "\xFE\xFF") {
        return 'UTF-16BE';
    }
    if ($bom === "\xFF\xFE" && substr($head, 2, 2) !== "\0\0") {
        return 'UTF-16LE';
    }
    return '';
}

/*
    Перекодирует UTF-16 в UTF-8
*/
function f2_utf16_to_utf8($content, $encoding)
{
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($content, 'UTF-8', $encoding);
    }
    if (function_exists('iconv')) {
        $converted = @iconv($encoding, 'UTF-8', $content);
        if ($converted !== false) {
            return $converted;
        }
    }
    return null;
}

/*
    Перекодирует Windows-1251 в UTF-8
*/
function f2_cp1251_to_utf8($content)
{
    $converter = f2_cp1251_converter();
    if ($converter === 'mbstring') {
        return mb_convert_encoding($content, 'UTF-8', 'Windows-1251');
    }
    if ($converter === 'iconv') {
        $converted = @iconv('Windows-1251', 'UTF-8', str_replace("\x98", '?', $content));
        if ($converted !== false) {
            return $converted;
        }
    }
    return $content;
}

/*
    Скан файла
*/
function f2_scan_file($abs, $rel, $name, $kind, &$ctx)
{
    if ($abs === $ctx['self']) {
        return;
    }
    if (!f2_glob_matches($rel, $ctx)) {
        return;
    }
    if (microtime(true) > $ctx['deadline']) {
        $ctx['truncated']['timeout'] = true;
        $ctx['stop'] = true;
        return;
    }
    if ($ctx['skip_minified'] && f2_is_minified($name, null)) {
        $ctx['stats']['skipped']['minified']++;
        return;
    }
    $size = @filesize($abs);
    if ($size === false) {
        $ctx['stats']['skipped']['unreadable']++;
        return;
    }
    if ($size > F2_MAX_FILE_SIZE) {
        $ctx['stats']['skipped']['too_large']++;
        if (count($ctx['stats']['too_large_files']) < F2_MAX_LISTED_SKIPPED) {
            $ctx['stats']['too_large_files'][] = array('file' => f2_display_path($rel), 'size' => $size);
        }
        return;
    }
    if ($kind === 'sensitive' && $size > F2_MAX_SENSITIVE_SIZE) {
        $ctx['stats']['skipped']['unmaskable']++;
        return;
    }
    $content = f2_read_text($abs);
    if ($content === false) {
        $ctx['stats']['skipped']['unreadable']++;
        return;
    }
    if ($content === null) {
        $ctx['stats']['skipped']['binary']++;
        return;
    }
    $content = f2_decode($content);
    if ($content === null) {
        $ctx['stats']['skipped']['binary']++;
        return;
    }
    if (strpos($content, "\n") === false && strpos($content, "\r") !== false) {
        $content = str_replace("\r", "\n", $content);
    }
    if ($ctx['skip_minified'] && f2_is_minified($name, $content)) {
        $ctx['stats']['skipped']['minified']++;
        return;
    }
    $ctx['masked'] = $kind === 'sensitive';
    if ($ctx['masked']) {
        $content = f2_mask($content, $name);
        if ($content === null) {
            $ctx['stats']['skipped']['unmaskable']++;
            return;
        }
        $ctx['stats']['files_masked']++;
    }
    $ctx['masked_lines'] = array();
    if (!$ctx['masked'] && F2_MASK_SECRETS) {
        $content = f2_mask_secrets($content, $name, $ctx);
        if ($content === null) {
            $ctx['stats']['skipped']['unmaskable']++;
            return;
        }
    }
    $ctx['stats']['files_scanned']++;
    $ctx['current'] = null;
    $ctx['mtime'] = null;
    if ($ctx['want_mtime'] && function_exists('filemtime')) {
        $time = @filemtime($abs);
        if ($time !== false) {
            $ctx['mtime'] = gmdate('Y-m-d\\TH:i:s\\Z', $time);
        }
    }
    $ctx['line_starts'] = array();
    $first = count($ctx['matches']);
    f2_match_content($content, $rel, $ctx);
    if (($ctx['before'] || $ctx['after']) && count($ctx['matches']) > $first) {
        f2_add_context($content, $first, $ctx);
    }
}

/*
    Проверка на минифицированный JS/CSS
*/
function f2_is_minified($name, $content)
{
    if ($content === null) {
        return (bool) preg_match('/\.(?:min\.(?:js|mjs|css)|map)$/i', $name);
    }
    if (!preg_match('/\.(?:js|mjs|css)$/i', $name)) {
        return false;
    }
    return strlen($content) / (substr_count($content, "\n") + 1) > F2_MINIFIED_LINE;
}

/*
    Чтение содержимого файла
*/
function f2_read_text($abs)
{
    $fp = @fopen($abs, 'rb');
    if (!$fp) {
        return false;
    }
    $head = @fread($fp, 8192);
    if ($head === false) {
        fclose($fp);
        return false;
    }
    if (f2_is_binary($head)) {
        fclose($fp);
        return null;
    }
    $rest = @stream_get_contents($fp);
    fclose($fp);
    if ($rest === false) {
        return false;
    }
    return $head . $rest;
}

/*
    Бинарный ли файл
*/
function f2_is_binary($head)
{
    if (f2_utf16_bom($head) !== '') {
        return false;
    }
    if (strpos($head, "\0") !== false || substr($head, 0, 5) === '%PDF-') {
        return true;
    }
    $control = 0;
    foreach (count_chars($head, 1) as $byte => $count) {
        if ($byte < 0x20 && ($byte < 0x09 || $byte > 0x0D) && $byte !== 0x1B) {
            $control += $count;
        }
    }
    return $control > strlen($head) * F2_BINARY_CONTROL;
}

/*
    Ищет по всему файлу сразу
*/
function f2_match_content($content, $rel, &$ctx)
{
    $len = strlen($content);
    $offset = 0;
    $line = 1;
    while ($offset < $len) {
        if (microtime(true) > $ctx['deadline']) {
            $ctx['truncated']['timeout'] = true;
            $ctx['stop'] = true;
            return;
        }
        $m = null;
        $found = @preg_match($ctx['regex'], $content, $m, PREG_OFFSET_CAPTURE, $offset);
        if ($found === false) {
            f2_match_lines($content, $offset, $line, $rel, $ctx);
            return;
        }
        if ($found === 0) {
            return;
        }
        $pos = $m[0][1];
        $before = substr($content, $offset, $pos - $offset);
        $nl = strrpos($before, "\n");
        $lineStart = $nl === false ? $offset : $offset + $nl + 1;
        $line += substr_count($before, "\n");
        $lineEnd = $pos < $len ? strpos($content, "\n", $pos) : false;
        if ($lineEnd === false) {
            $lineEnd = $len;
        }
        $text = substr($content, $lineStart, $lineEnd - $lineStart);
        if (!f2_add_match($ctx, $rel, $line, $text, $pos - $lineStart, $lineStart)) {
            return;
        }
        $offset = $lineEnd + 1;
        $line++;
    }
}

/*
    Построчный поиск совпадения
*/
function f2_match_lines($content, $offset, $line, $rel, &$ctx)
{
    $len = strlen($content);
    $errorCounted = false;
    while ($offset < $len) {
        if (($line & 255) === 0 && microtime(true) > $ctx['deadline']) {
            $ctx['truncated']['timeout'] = true;
            $ctx['stop'] = true;
            return;
        }
        $end = strpos($content, "\n", $offset);
        if ($end === false) {
            $end = $len;
        }
        $text = substr($content, $offset, $end - $offset);
        $m = null;
        $found = @preg_match($ctx['regex'], $text, $m, PREG_OFFSET_CAPTURE);
        if ($found === false) {
            if (!$errorCounted) {
                $ctx['stats']['regex_errors']++;
                $errorCounted = true;
            }
        } elseif ($found && !f2_add_match($ctx, $rel, $line, $text, $m[0][1], $offset)) {
            return;
        }
        $offset = $end + 1;
        $line++;
    }
}

/*
    Добавление совпадения в список результатов
*/
function f2_add_match(&$ctx, $rel, $line, $text, $at, $start)
{
    $masked = $ctx['masked'] || isset($ctx['masked_lines'][$line]);
    if ($ctx['mode'] !== 'lines') {
        return f2_add_file($ctx, $rel, $text, $masked);
    }
    if (count($ctx['matches']) >= $ctx['max']) {
        $ctx['truncated']['max_results'] = true;
        $ctx['stop'] = true;
        return false;
    }
    $match = array(
        'file' => f2_display_path($rel),
        'line' => $line,
        'text' => f2_line_text($text, $at),
    );
    if ($ctx['mtime'] !== null) {
        $match['mtime'] = $ctx['mtime'];
    }
    if ($masked) {
        $match['masked'] = true;
    }
    $ctx['matches'][] = $match;
    if ($ctx['before'] || $ctx['after']) {
        $ctx['line_starts'][count($ctx['matches']) - 1] = $start;
    }
    return true;
}

/*
    Подготовка текста строки для ответа
*/
function f2_line_text($text, $at = 0)
{
    $text = rtrim($text, "\r");
    $len = strlen($text);
    if ($len <= F2_MAX_LINE) {
        return f2_utf8($text);
    }
    $start = 0;
    if ($at > F2_MAX_LINE - F2_MATCH_LEAD) {
        $start = min($at - F2_MATCH_LEAD, $len - F2_MAX_LINE);
    }
    $end = $start + F2_MAX_LINE;
    for ($i = 0; $i < 3 && $start > 0 && (ord($text[$start]) & 0xC0) === 0x80; $i++) {
        $start++;
    }
    for ($i = 0; $i < 3 && $end < $len && (ord($text[$end]) & 0xC0) === 0x80; $i++) {
        $end--;
    }
    return ($start > 0 ? '…' : '') . f2_utf8(substr($text, $start, $end - $start)) . ($end < $len ? '…' : '');
}

/*
    Добавление строк контекста
*/
function f2_add_context($content, $first, &$ctx)
{
    $total = substr_count($content, "\n") + (substr($content, -1) === "\n" ? 0 : 1);
    $shown = 0;
    $n = count($ctx['matches']);
    for ($i = $first; $i < $n; $i++) {
        $line = $ctx['matches'][$i]['line'];
        $from = max($line - $ctx['before'], $shown + 1);
        $next = $i + 1 < $n ? $ctx['matches'][$i + 1]['line'] : $total + 1;
        $to = min($line + $ctx['after'], $next - 1, $total);
        if ($line - $from + $to - $line > $ctx['context_left']) {
            $ctx['truncated']['context'] = true;
            $from = max($from, $line - $ctx['context_left']);
            $to = min($to, $line + $ctx['context_left'] - ($line - $from));
        }
        $start = $ctx['line_starts'][$i];
        if ($from < $line) {
            $ctx['matches'][$i]['before'] = f2_lines_before($content, $start, $line - $from);
        }
        if ($to > $line) {
            $ctx['matches'][$i]['after'] = f2_lines_after($content, $start, $to - $line);
        }
        $ctx['context_left'] -= max(0, $line - $from) + max(0, $to - $line);
        $shown = max($line, $to);
    }
}

/*
    Контекст перед найденной строкой
*/
function f2_lines_before($content, $start, $count)
{
    $len = strlen($content);
    $out = array();
    $end = $start - 1;
    for ($k = 0; $k < $count; $k++) {
        $nl = $end > 0 ? strrpos($content, "\n", $end - 1 - $len) : false;
        $begin = $nl === false ? 0 : $nl + 1;
        $out[] = f2_line_text(substr($content, $begin, $end - $begin));
        $end = $begin - 1;
    }
    return array_reverse($out);
}

/*
    Контекст после найденной строки
*/
function f2_lines_after($content, $start, $count)
{
    $len = strlen($content);
    $out = array();
    $end = strpos($content, "\n", $start);
    for ($k = 0; $k < $count; $k++) {
        $begin = $end + 1;
        $end = strpos($content, "\n", $begin);
        if ($end === false) {
            $end = $len;
        }
        $out[] = f2_line_text(substr($content, $begin, $end - $begin));
    }
    return $out;
}

/*
    Добавление файлов в режиме count и files
*/
function f2_add_file(&$ctx, $rel, $text, $masked)
{
    if ($ctx['current'] !== null) {
        if ($masked) {
            $ctx['files'][$ctx['current']]['masked'] = true;
        }
        $ctx['files'][$ctx['current']]['count']++;
        $ctx['files'][$ctx['current']]['occurrences'] += f2_count_occurrences($ctx, $text);
        return true;
    }
    if (count($ctx['files']) >= $ctx['max']) {
        $ctx['truncated']['max_results'] = true;
        $ctx['stop'] = true;
        return false;
    }
    $file = array('file' => f2_display_path($rel));
    if ($ctx['mode'] === 'count') {
        $file['count'] = 1;
        $file['occurrences'] = f2_count_occurrences($ctx, $text);
    }
    if ($ctx['mtime'] !== null) {
        $file['mtime'] = $ctx['mtime'];
    }
    if ($masked) {
        $file['masked'] = true;
    }
    $ctx['files'][] = $file;
    if ($ctx['mode'] === 'files') {
        return false;
    }
    $ctx['current'] = count($ctx['files']) - 1;
    return true;
}

/*
    Число непустых вхождений шаблона в строке
*/
function f2_count_occurrences(&$ctx, $text)
{
    $utf8 = substr($ctx['regex'], -1) === 'u';
    $len = strlen($text);
    $count = 0;
    $offset = 0;
    for ($i = 1; $offset < $len; $i++) {
        if (($i & 1023) === 0 && microtime(true) > $ctx['deadline']) {
            $ctx['truncated']['timeout'] = true;
            $ctx['stop'] = true;
            break;
        }
        $m = null;
        $found = @preg_match($ctx['regex'], $text, $m, PREG_OFFSET_CAPTURE, $offset);
        if ($found === false) {
            $ctx['stats']['regex_errors']++;
            break;
        }
        if (!$found) {
            break;
        }
        $end = $m[0][1] + strlen($m[0][0]);
        if ($end > $m[0][1]) {
            $count++;
            $offset = $end;
            continue;
        }
        $offset = $end + 1;
        while ($utf8 && $offset < $len && (ord($text[$offset]) & 0xC0) === 0x80) {
            $offset++;
        }
    }
    return max(1, $count);
}

/*
    Заменяет некорректные UTF-8 последовательности на U+FFFD
*/
function f2_utf8($s)
{
    if ($s === '' || preg_match('//u', $s)) {
        return $s;
    }
    return preg_replace_callback(
        '/((?:[\x00-\x7F]|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2})+)|[\x80-\xFF]/',
        'f2_utf8_replace',
        $s
    );
}

/*
    Был ли захвачен какой-либо текст в регулярном выражении
*/
function f2_utf8_replace($m)
{
    return isset($m[1]) && $m[1] !== '' ? $m[1] : "\xEF\xBF\xBD";
}

/*
    Маскирование конфигов с секретами
*/
function f2_mask($content, $name)
{
    if (preg_match('/\.(?:php\d?|phtml|inc)$/i', $name)) {
        return f2_mask_php($content);
    }
    if (preg_match('/\.xml$/i', $name)) {
        return f2_mask_xml($content);
    }
    return f2_mask_lines($content);
}

/*
    Замена текста на звездочки
*/
function f2_mask_text($text)
{
    if (trim($text) === '') {
        return $text;
    }
    return '***' . str_repeat("\n", substr_count($text, "\n"));
}

/*
    Маскировка строковых литералов
*/
function f2_mask_php($content)
{
    if (!function_exists('token_get_all')) {
        return null;
    }
    $tokens = @token_get_all($content);
    if (!is_array($tokens)) {
        return null;
    }
    $out = '';
    $n = count($tokens);
    for ($i = 0; $i < $n; $i++) {
        $token = $tokens[$i];
        if (!is_array($token)) {
            $out .= $token;
            continue;
        }
        $id = $token[0];
        $text = $token[1];
        if ($id === T_CONSTANT_ENCAPSED_STRING) {
            $out .= f2_php_is_name($tokens, $i) ? $text : f2_mask_quoted($text);
        } elseif ($id === T_ENCAPSED_AND_WHITESPACE || $id === T_LNUMBER || $id === T_DNUMBER) {
            $out .= f2_mask_text($text);
        } elseif ($id === T_COMMENT || $id === T_DOC_COMMENT) {
            $out .= f2_mask_comment($text);
        } elseif ($id === T_INLINE_HTML) {
            $out .= f2_mask_lines($text);
        } else {
            $out .= $text;
        }
    }
    return $out;
}

/*
    Является ли литерал именем
*/
function f2_php_is_name($tokens, $i)
{
    $next = f2_php_neighbor($tokens, $i, 1);
    $prev = f2_php_neighbor($tokens, $i, -1);
    if (is_array($next) && $next[0] === T_DOUBLE_ARROW) {
        return true;
    }
    if ($next === ']' && $prev === '[') {
        $base = f2_php_neighbor($tokens, $i, -2);
        return in_array($base, array(']', ')', '}'), true)
            || (is_array($base) && in_array($base[0], array(T_VARIABLE, T_STRING), true));
    }
    if ($prev === '(') {
        $func = f2_php_neighbor($tokens, $i, -2);
        $names = array('define', 'defined', 'constant', 'getenv', 'env', 'ini_get', 'ini_set', 'config');
        return is_array($func) && $func[0] === T_STRING && in_array(strtolower($func[1]), $names, true);
    }
    return false;
}

/*
    Соседний значимый токен
*/
function f2_php_neighbor($tokens, $i, $step)
{
    $dir = $step > 0 ? 1 : -1;
    $left = abs($step);
    $n = count($tokens);
    for ($j = $i + $dir; $j >= 0 && $j < $n; $j += $dir) {
        $token = $tokens[$j];
        if (is_array($token) && in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
            continue;
        }
        if (--$left === 0) {
            return $token;
        }
    }
    return null;
}

/*
    Маскировка строки
*/
function f2_mask_quoted($text)
{
    if (!preg_match('/^([bB]?)([\'"])(.*)\2$/s', $text, $m)) {
        return f2_mask_text($text);
    }
    if ($m[3] === '') {
        return $text;
    }
    return $m[1] . $m[2] . f2_mask_text($m[3]) . $m[2];
}

/*
    Маскировка комментария
*/
function f2_mask_comment($text)
{
    if (!preg_match('/^(\/\*\*?|\/\/|#)(.*?)(\*\/)?(\s*)$/s', $text, $m)) {
        return f2_mask_text($text);
    }
    $close = isset($m[3]) ? $m[3] : '';
    if (trim($m[2]) === '') {
        return $text;
    }
    $body = ' ***' . str_repeat("\n", substr_count($m[2], "\n"));
    return $m[1] . $body . ($close !== '' ? ' ' . $close : '') . $m[4];
}

/*
    Маскировка XML
*/
function f2_mask_xml($content)
{
    $parts = array();
    $re = '/<!\[CDATA\[.*?(?:\]\]>|\z)|<!--.*?(?:-->|\z)|<[^>]*>?|[^<]+/s';
    if (!preg_match_all($re, $content, $parts)) {
        return $content === '' ? '' : null;
    }
    $out = '';
    foreach ($parts[0] as $part) {
        if (substr($part, 0, 9) === '<![CDATA[') {
            $out .= '<![CDATA[' . f2_mask_text(substr($part, 9)) . (substr($part, -3) === ']]>' ? ']]>' : '');
        } elseif (substr($part, 0, 4) === '<!--') {
            $out .= '<!--' . f2_mask_text(substr($part, 4)) . (substr($part, -3) === '-->' ? '-->' : '');
        } elseif ($part[0] === '<') {
            $tag = preg_replace_callback('/(["\'])(.*?)(\1|\z)/s', 'f2_mask_xml_attr', $part);
            if (!is_string($tag)) {
                return null;
            }
            $out .= $tag;
        } else {
            $out .= f2_mask_text($part);
        }
    }
    return strlen($out) > 0 || $content === '' ? $out : null;
}

/*
    Маскировка XML атрибута
*/
function f2_mask_xml_attr($m)
{
    return $m[2] === '' ? $m[0] : $m[1] . f2_mask_text($m[2]) . $m[3];
}

/*
    Маскировка в текстовых конфигах
*/
function f2_mask_lines($text)
{
    $lines = explode("\n", $text);
    $quote = '';
    $block = -1;
    foreach ($lines as $k => $line) {
        $body = rtrim($line, "\r");
        $cr = (string) substr($line, strlen($body));
        if (trim($body) === '') {
            continue;
        }
        $indent = strlen($body) - strlen(ltrim($body));
        if ($quote !== '') {
            if (preg_match('/(?<!\\\\)' . $quote . '/', $body)) {
                $quote = '';
            }
            $lines[$k] = substr($body, 0, $indent) . '***' . $cr;
            continue;
        }
        if ($block >= 0) {
            if ($indent > $block) {
                $lines[$k] = substr($body, 0, $indent) . '***' . $cr;
                continue;
            }
            $block = -1;
        }
        $m = null;
        if (preg_match('/^(\s*(?:export\s+)?(["\']?)[\w.\-]+\2\s*(?:=>|[=:])\s*)(.*)$/', $body, $m)) {
            $value = trim($m[3]);
            $first = substr($value, 0, 1);
            if (($first === '"' || $first === "'")
                && !preg_match('/(?<!\\\\)' . $first . '/', (string) substr($value, 1))) {
                $quote = $first;
            } elseif (preg_match('/^[|>][+-]?\d*\s*(?:#.*)?$/', $value)) {
                $block = $indent;
            }
            $lines[$k] = $m[1] . f2_mask_value($value) . $cr;
        } elseif (!preg_match('/^\s*(?:[\[\]{}(),;]+|\[[\w.\- ]+\])\s*$/', $body)) {
            preg_match('/^\s*(?:#|;|\/\/|-(?=\s))?\s*/', $body, $m);
            $lines[$k] = $m[0] . '***' . $cr;
        }
    }
    return implode("\n", $lines);
}

/*
    Значение остаётся, только если в нём нет данных
*/
function f2_mask_value($value)
{
    $comma = substr($value, -1) === ',' ? ',' : '';
    $core = trim($comma === '' ? $value : substr($value, 0, -1));
    if (preg_match('/^(?:|true|false|null|yes|no|on|off|~|[\[{(]|[\]})]|\[\]|\{\}|""|\'\')$/i', $core)) {
        return $value;
    }
    return '***' . $comma;
}

/*
    Секреты в обычных файлах, ключи капчи, токены
*/
function f2_secret_rules($config)
{
    $name = '(?:secret|passw|pass(?![a-z])|pwd|token|api_?key|access_?key|private_?key|auth_?key|app_?key|license_?key|encryption_?key|sign(?:ing|ature)?_?key|hmac|salt)[\w.\-]*';
    $key = '[\w.\-]{0,64}' . $name;
    $value = '[^\x00-\x20\'"\x7f-\xff]{4,512}';
    $rules = array(
        array('/(?<![A-Za-z0-9_\-])(?:'
            . '6L[0-9A-Za-z_\-]{38}'
            . '|(?:sk|rk)_(?:live|test)_[0-9A-Za-z]{16,}|whsec_[0-9A-Za-z]{16,}'
            . '|sk-(?=[\w\-]*\d)(?=[\w\-]*[A-Z])[\w\-]{20,}'
            . '|(?:AKIA|ASIA)[0-9A-Z]{16}'
            . '|AIza[0-9A-Za-z_\-]{35}'
            . '|gh[pousr]_[A-Za-z0-9]{36,}|github_pat_\w{22,}|glpat-[\w\-]{20,}'
            . '|xox[abposr]-[A-Za-z0-9\-]{10,}'
            . '|SG\.[\w\-]{16,}\.[\w\-]{16,}'
            . '|\d{8,10}:AA[\w\-]{33}'
            . '|y0_[\w\-]{40,}|AQAAAAA[\w\-]{30,}'
            . '|eyJ[\w\-]{8,}\.eyJ[\w\-]{8,}\.[\w\-]{8,}'
            . ')(?![A-Za-z0-9_\-])/', 0),
        array('/-----BEGIN [A-Z0-9 ]*PRIVATE KEY-----(.+?)-----END [A-Z0-9 ]*PRIVATE KEY-----/s', 1),
        array('/(?<![a-z0-9+.\-])[a-z][a-z0-9+.\-]{0,20}:\/\/[^\s:\/@\'"<>]{1,256}:([^\s\/@\'"<>]{1,256})@/i', 1),
        array('/\b(?:Bearer|Basic)\s+([A-Za-z0-9._~+\/=\-]{16,})/', 1),
        array('/' . $name . '[\'"]?\]?\s*(?:=>|=|:)\s*([\'"])(' . $value . ')\1/i', 2),
        array('/\(\s*[\'"]' . $key . '[\'"]\s*,\s*([\'"])(' . $value . ')\1/i', 2),
    );
    if ($config) {
        $rules[] = array('/^[ \t]*(?:export[ \t]+)?' . $key . '[ \t]*[=:][ \t]*([^\s\'"#;]{4,512})/im', 1);
    }
    return $rules;
}

/*
    Скрывает значения, похожие на секреты
*/
function f2_mask_secrets($content, $name, &$ctx)
{
    $config = (bool) preg_match('/\.(?:ini|conf|cnf|cfg|properties|toml|ya?ml|env|sh|txt)$/i', $name);
    $starts = array();
    $ends = array();
    $len = strlen($content);
    foreach (f2_secret_rules($config) as $rule) {
        $offset = 0;
        while ($offset < $len) {
            $m = null;
            $found = @preg_match($rule[0], $content, $m, PREG_OFFSET_CAPTURE, $offset);
            if ($found === false) {
                return null;
            }
            if (!$found) {
                break;
            }
            $g = $m[$rule[1]];
            if ($g[0] !== '') {
                $starts[] = $g[1];
                $ends[] = $g[1] + strlen($g[0]);
            }
            $offset = max($m[0][1] + strlen($m[0][0]), $offset + 1);
        }
    }
    if (!$starts) {
        return $content;
    }
    array_multisort($starts, SORT_NUMERIC, $ends, SORT_NUMERIC);
    $out = '';
    $pos = 0;
    $line = 1;
    foreach ($starts as $i => $start) {
        $end = $ends[$i];
        if ($end <= $pos) {
            continue;
        }
        $start = max($start, $pos);
        $chunk = substr($content, $pos, $start - $pos);
        $line += substr_count($chunk, "\n");
        $secret = substr($content, $start, $end - $start);
        $lines = substr_count($secret, "\n");
        for ($k = 0; $k <= $lines; $k++) {
            $ctx['masked_lines'][$line + $k] = true;
        }
        $line += $lines;
        $out .= $chunk . '***' . str_repeat("\n", $lines);
        $pos = $end;
        $ctx['stats']['secrets_masked']++;
    }
    return $out . substr($content, $pos);
}

/*
    Веб-интерфейс
*/
function f2_page()
{
    $css = f2_page_css();
    $js = f2_page_js();
    $selfDeleteAvailable = is_writable(__FILE__) ? 'yes' : 'no';
    $csp = "default-src 'none'; connect-src 'self'; img-src data:; "
        . 'style-src ' . f2_csp_source($css) . '; '
        . 'script-src ' . f2_csp_source($js) . '; '
        . "base-uri 'none'; form-action 'none'; frame-ancestors 'none'";
    $attrs = ' data-max-results="' . F2_MAX_RESULTS . '"'
        . ' data-default-results="' . F2_DEFAULT_RESULTS . '"'
        . ' data-max-context="' . F2_MAX_CONTEXT . '"'
        . ' data-self-delete-available="' . $selfDeleteAvailable . '"';
    f2_common_headers();
    f2_status(200);
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Security-Policy: ' . $csp);
    header('Referrer-Policy: no-referrer');
    header('X-Frame-Options: DENY');
    echo strtr(f2_page_html(), array(
        '{{VERSION}}' => F2_VERSION,
        '{{CSS}}' => $css,
        '{{JS}}' => $js,
        '{{ATTRS}}' => $attrs,
        '{{FILE_MASK}}' => F2_DEFAULT_FILE_MASK
    ));
    exit;
}

/*
    Источник CSP по хэшу
*/
function f2_csp_source($code)
{
    if (!function_exists('hash') || !function_exists('base64_encode')) {
        return "'unsafe-inline'";
    }
    return "'sha256-" . base64_encode(hash('sha256', $code, true)) . "'";
}

/*
    Шаблон HTML
*/
function f2_page_html()
{
    return <<<'HTML'
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="data:,">
<title>Finder.php v{{VERSION}}</title>
<style>{{CSS}}</style>
</head>
<body{{ATTRS}}>
<header class="top">
  <h1>Finder.php</h1>
  <div class="conn">
    <span id="status" class="status">Не подключено</span>
    <button type="button" id="delete" class="link danger" hidden>Удалить скрипт</button>
    <button type="button" id="logout" class="link" hidden>Выйти</button>
  </div>
</header>
<main>
  <section class="card confirm" id="delete-confirm" hidden>
    <p><b>Удалить скрипт с сайта?</b> Поиск станет недоступен, пока файл не загрузят заново.</p>
    <div class="actions">
      <button type="button" id="delete-yes" class="danger-fill">Удалить</button>
      <button type="button" id="delete-no" class="small">Отмена</button>
    </div>
    <p id="delete-error" class="error" hidden></p>
  </section>
  <section class="card" id="delete-unavailable" hidden>
    <p class="error"><b>Нет прав на удаление скрипта через PHP!</b> Пожалуйста, удалите файл вручную.</p>
  </section>
  <section class="card" id="deleted" hidden>
    <p><b>Скрипт удалён с сайта.</b> Страницу можно закрыть.</p>
  </section>
  <section class="card" id="auth">
    <form id="auth-form" autocomplete="off">
      <label for="token">Токен доступа</label>
      <div class="row">
        <input id="token" type="password" autocomplete="off" spellcheck="false" required>
        <button type="submit" id="connect">Подключить</button>
      </div>
      <p class="hint">Токен хранится только в этой вкладке и забывается при её закрытии.</p>
      <p id="auth-error" class="error" hidden></p>
    </form>
  </section>

  <section class="card" id="search" hidden>
    <form id="search-form" autocomplete="off">
      <div class="row">
        <input id="pattern" class="mono" placeholder="Что искать" spellcheck="false" required>
        <button type="button" id="clear" class="secondary" tabindex="-1" disabled>Очистить</button>
        <button type="button" id="copy" class="secondary" tabindex="-1" disabled>Копировать</button>
        <button type="submit" id="go">Искать</button>
      </div>
      
      <details id="more">
        <summary>Настройки поиска</summary>
        <div class="toggles">
          <label title="Для кириллицы используйте [а-яё]: \w и \b понимают её только на PHP 8.2+"><input type="checkbox" id="regex"> Регулярное выражение (PCRE)</label>
          <label><input type="checkbox" id="icase" checked> Без учёта регистра</label>
          <label><input type="checkbox" id="minified"> В минифицированных JS/CSS</label>
          <label><input type="checkbox" id="mtime"> Дата изменения файлов</label>
        </div>
        <div class="grid">
          <label>Корень поиска
            <select id="root">
              <option value=".">папка скрипта</option>
              <option value="..">на уровень выше</option>
            </select></label>
          <label>Папка или файл
            <input id="path" class="mono" placeholder="например, local/templates" spellcheck="false"></label>
          <label>Маска файлов
            <input id="glob" class="mono" placeholder="*.php или *.{js,css}" spellcheck="false" value="{{FILE_MASK}}"></label>
          <label>Показать
            <select id="mode">
              <option value="lines">найденные строки</option>
              <option value="count">число совпадений по файлам</option>
              <option value="files">только список файлов</option>
            </select></label>
          <label>Строк вокруг совпадения
            <input id="context" type="number" min="0" value="2"></label>
          <label>Не больше результатов
            <input id="max" type="number" min="1"></label>
          <label class="wide">Исключить (по маске на строку, без / — имя на любой глубине, с / — путь от корня)
            <textarea id="exclude" class="mono" rows="2" spellcheck="false" placeholder="upload&#10;*.log"></textarea></label>
        </div>
        <p class="hint">Папки кеша и зависимостей (vendor, node_modules) при обходе пропускаются, но если указать их в «Папка или файл», поиск по ним идёт. Ключи, дампы и бэкапы не читаются никогда.</p>
      </details>
    </form>
  </section>

  <section id="result" aria-live="polite"></section>

  <div id="totop"></div>
</main>
<script>{{JS}}</script>
</body>
</html>
HTML;
}

/*
    Шаблон CSS
*/
function f2_page_css()
{
    return <<<'CSS'
:root {
  color-scheme: light dark;
  --bg: #f6f7f9; --card: #ffffff; --text: #1d2330; --muted: #667085; --line: #d1d1d1; --accent: #2f6fde; --accent-text: #ffffff; --mark: #ffe58a; --mark-text: #1d2330; --ctx: #8a93a3; --warn-bg: #fff4dc; --warn: #8a5a00; --err-bg: #fde8e8; --err: #b42318; --row-hover: #f1f4f9; --file-bg: #eef2f8;
}
@media (prefers-color-scheme: dark) {
  :root {
    --bg: #12151b; --card: #1a1e26; --text: #e4e7ec; --muted: #98a2b3; --line: #2c323d; --accent: #5b8ff0; --accent-text: #0b0d12; --mark: #b8860b; --mark-text: #ffffff; --ctx: #7c8596; --warn-bg: #3a2e12; --warn: #f2c66d; --err-bg: #3d1a1a; --err: #f59a92; --row-hover: #20252f; --file-bg: #222834;
  }
}
* { box-sizing: border-box; }
body { margin: 0; background: var(--bg); color: var(--text); font: 15px/1.45 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
.mono, code, .lines { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; }
.top { display: flex; flex-wrap: wrap; gap: 8px 16px; align-items: baseline; justify-content: space-between; max-width: 1200px; margin: 0 auto; padding: 16px 16px 4px; }
.top h1 { font-size: 18px; margin: 0; }
.conn { display: flex; gap: 12px; align-items: baseline; color: var(--muted); font-size: 13px; }
.status.warn { color: var(--warn); font-weight: 600; }
.status.bad { color: var(--err); font-weight: 600; }
main { max-width: 1200px; margin: 0 auto; padding: 8px 16px 48px; }
.card { background: var(--card); border: 1px solid var(--line); border-radius: 10px; padding: 14px 16px; margin-bottom: 14px; }
label { display: block; font-size: 13px; color: var(--muted); }
.row { display: flex; gap: 8px; margin-top: 4px; }
.row input { flex: 1; min-width: 0; }
input, select, textarea { font: inherit; color: var(--text); background: var(--bg); border: 1px solid var(--line); border-radius: 6px; padding: 7px 9px; width: 100%; }
textarea { resize: vertical; }
input[type=checkbox] { width: auto; margin: 0 4px 0 0; vertical-align: -1px; }
input:focus, select:focus, textarea:focus { outline: 2px solid var(--accent); outline-offset: -1px; }
input[disabled] { opacity: .6; }
#pattern { font-size: 16px; }
button { font: inherit; border: 0; border-radius: 6px; padding: 7px 16px; cursor: pointer; background: var(--accent); color: var(--accent-text); font-weight: 600; white-space: nowrap; user-select: none; }
button:disabled { opacity: .6; cursor: not-allowed; }
button.link { background: none; color: var(--accent); padding: 0; font-weight: normal; }
button.link.danger { color: var(--err); }
button.danger-fill { background: var(--err); color: var(--card); }
button.secondary { background: none; border: 1px solid var(--accent); color: var(--accent); }
.confirm { border-color: var(--err); }
.confirm p { margin: 0 0 10px; }
.confirm .actions { display: flex; gap: 8px; align-items: center; }
#deleted p { margin: 0; }
button.small { padding: 2px 8px; font-size: 12px; font-weight: normal; background: none; color: var(--accent); border: 1px solid var(--line); }
.toggles { display: flex; flex-wrap: wrap; gap: 6px 18px; margin: 10px 0 2px; }
.toggles label { color: var(--text); font-size: 14px; cursor: pointer; }
details { margin-top: 10px; }
summary { cursor: pointer; color: var(--accent); font-size: 14px; text-align: center; user-select: none; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 10px 14px; margin-top: 10px; }
.grid label input, .grid label select, .grid label textarea { margin-top: 3px; }
.grid .wide { grid-column: 1 / -1; }
.hint { color: var(--muted); font-size: 12px; margin: 8px 0 0; }
.error { background: var(--err-bg); color: var(--err); border-radius: 6px; padding: 8px 10px; margin: 10px 0 0; }
.warn-box { background: var(--warn-bg); color: var(--warn); border-radius: 6px; padding: 8px 10px; margin: 0 0 10px; }
.summary { margin: 4px 0 10px; }
.summary b { font-weight: 600; }
.muted { color: var(--muted); }
.file { background: var(--card); border: 1px solid var(--line); border-radius: 8px; margin-bottom: 10px; overflow: hidden; }
.file-head { display: flex; flex-wrap: wrap; gap: 6px 10px; align-items: center; padding: 6px 10px; background: var(--file-bg); border-bottom: 1px solid var(--line); }
.file-name { font-weight: 600; word-break: break-all; flex: 1; min-width: 130px; }
.badge { font-size: 11px; border-radius: 10px; padding: 1px 7px; background: var(--bg); color: var(--muted); border: 1px solid var(--line); white-space: nowrap; }
.badge.masked { color: var(--warn); border-color: var(--warn); }
.lines { font-size: 13px; overflow-x: auto; }
.lines.collapsed { display: none; }
.ln { display: flex; min-width: max-content; }
.ln:hover { background: var(--row-hover); }
.no { flex: 0 0 auto; min-width: 4.5em; padding: 0 10px 0 6px; text-align: right; color: var(--muted); user-select: none; border-right: 1px solid var(--line); }
.tx { padding: 0 10px; white-space: pre; }
.ln.ctx .tx { color: var(--ctx); }
.ln.hit .no { color: var(--accent); font-weight: 600; }
.ln.gap .no, .ln.gap .tx { color: var(--muted); }
mark { background: var(--mark); color: var(--mark-text); border-radius: 2px; }
table { width: 100%; border-collapse: collapse; background: var(--card); border: 1px solid var(--line); border-radius: 8px; overflow: hidden; }
th, td { text-align: left; padding: 6px 10px; border-bottom: 1px solid var(--line); vertical-align: top; }
th { font-size: 12px; color: var(--muted); font-weight: 600; background: var(--file-bg); }
td.num, th.num { text-align: right; white-space: nowrap; }
td.path { word-break: break-all; }
.stats { margin-top: 14px; font-size: 13px; }
.stats ul { margin: 6px 0 0; padding-left: 20px; }
.spinner { display: inline-block; width: 12px; height: 12px; border: 2px solid var(--line); border-top-color: var(--accent); border-radius: 50%; animation: spin .8s linear infinite; vertical-align: -1px; margin-right: 6px; }
#totop { position: fixed; top: 0; left: 0; width: 20px; height: 100%; border-right: 1px solid var(--line); background: var(--card); cursor: pointer; }
@keyframes spin { to { transform: rotate(360deg); } }
@media (max-width: 600px) {
  .row { flex-direction: column; }
  .no { min-width: 3.2em; }
}
@media (max-width: 1260px) {
  #totop { width: 12px; }
}
CSS;
}

/*
    Шаблон JS
*/
function f2_page_js()
{
    return <<<'JS'
(function () {
  'use strict';

  var $ = function (id) { return document.getElementById(id); };
  var data = document.body.dataset;
  var cfg = {
    maxResults: Number(data.maxResults),
    defaultResults: Number(data.defaultResults),
    maxContext: Number(data.maxContext),
    selfDeleteAvailable: Number(data.selfDeleteAvailable==='yes')
  };
  var TOKEN_KEY = 'f2-ui-token';
  var FORM_KEY = 'f2-ui-form';
  var FIELDS = ['pattern', 'regex', 'icase', 'minified', 'mtime', 'path', 'glob', 'root', 'mode', 'context', 'max', 'exclude'];
  var SKIPPED = {
    forbidden: 'запрещённые (ключи, дампы, бэкапы)',
    unmaskable: 'конфиги, которые не удалось замаскировать',
    symlink: 'символьные ссылки',
    too_large: 'слишком большие файлы',
    binary: 'двоичные файлы',
    unreadable: 'нечитаемые',
    depth: 'слишком глубоко вложенные',
    ignored_dirs: 'папки кеша и зависимостей',
    excluded: 'исключены масками',
    minified: 'минифицированные JS/CSS'
  };

  var token = '';
  var expiresAt = 0;
  var tick = null;
  var running = null;

  // ----- Хранилище вкладки (может быть недоступно) -----

  function load(key) {
    try { return sessionStorage.getItem(key); } catch (e) { return null; }
  }
  function save(key, value) {
    try {
      if (value === null) { sessionStorage.removeItem(key); } else { sessionStorage.setItem(key, value); }
    } catch (e) { /* без хранилища просто не запоминаем */ }
  }

  // ----- DOM -----

  function el(tag, cls, text) {
    var node = document.createElement(tag);
    if (cls) { node.className = cls; }
    if (text !== undefined && text !== null) { node.textContent = String(text); }
    return node;
  }
  function clear(node) {
    while (node.firstChild) { node.removeChild(node.firstChild); }
  }
  function plural(n, one, few, many) {
    var m10 = n % 10, m100 = n % 100;
    if (m10 === 1 && m100 !== 11) { return one; }
    if (m10 >= 2 && m10 <= 4 && (m100 < 12 || m100 > 14)) { return few; }
    return many;
  }
  function num(n) { return Number(n).toLocaleString('ru-RU'); }
  function size(bytes) {
    if (bytes >= 1048576) { return (bytes / 1048576).toFixed(1) + ' МБ'; }
    return Math.max(1, Math.round(bytes / 1024)) + ' КБ';
  }
  function date(iso) {
    var d = new Date(iso);
    return isNaN(d.getTime()) ? iso : d.toLocaleString('ru-RU', {
      year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit'
    });
  }

  // ----- Запросы -----
  function hex(value) {
    var bytes = new TextEncoder().encode(String(value));
    var out = '';
    for (var i = 0; i < bytes.length; i++) { out += (bytes[i] < 16 ? '0' : '') + bytes[i].toString(16); }
    return out;
  }

  function post(params, signal) {
    var body = new URLSearchParams();
    Object.keys(params).forEach(function (k) { body.append(k, hex(params[k])); });
    return fetch(location.pathname, {
      method: 'POST',
      headers: { 'X-F2-Token': token },
      body: body,
      credentials: 'same-origin',
      cache: 'no-store',
      redirect: 'error',
      signal: signal
    }).then(function (r) {
      var type = r.headers.get('Content-Type') || '';
      if (type.indexOf('application/json') === -1) {
        if (r.status === 404) {
          throw new Error('Неверный токен или скрипт недоступен.');
        }
        if (r.status === 401 || r.status === 403) {
          throw new Error('Веб-сервер отклонил запрос (HTTP ' + r.status + ') ещё до скрипта: '
            + 'вероятно, сайт закрыт HTTP-авторизацией или правилами доступа. '
            + 'Обновите страницу и войдите заново, при необходимости проверьте настройки сервера.');
        }
        throw new Error('Неожиданный ответ сервера: HTTP ' + r.status);
      }
      return r.json().then(function (json) {
        if (!r.ok) {
          var err = new Error(json && json.error ? json.error : 'HTTP ' + r.status);
          err.status = r.status;
          throw err;
        }
        return json;
      });
    });
  }

  // ----- Подключение и срок жизни -----

  function setStatus(text, cls) {
    var s = $('status');
    s.textContent = text;
    s.className = 'status' + (cls ? ' ' + cls : '');
  }

  function showTime() {
    var left = Math.max(0, Math.round((expiresAt - Date.now()) / 1000));
    if (left === 0) {
      setStatus('Срок жизни скрипта истёк', 'bad');
      clearInterval(tick);
      return;
    }
    var h = Math.floor(left / 3600), m = Math.floor(left % 3600 / 60), s = left % 60;
    setStatus('Подключено · скрипт отключится через ' + h + ':' + (m < 10 ? '0' : '') + m
      + ':' + (s < 10 ? '0' : '') + s, left < 1800 ? 'warn' : '');
  }

  function connect(value) {
    token = value;
    $('connect').disabled = true;
    $('auth-error').hidden = true;
    return post({ action: 'status', root: $('root').value }).then(function (info) {
      save(TOKEN_KEY, token);
      expiresAt = Date.now() + info.expires_in * 1000;
      clearInterval(tick);
      tick = setInterval(showTime, 1000);
      showTime();
      $('auth').hidden = true;
      $('search').hidden = false;
      $('logout').hidden = false;
      $('delete').hidden = false;
      $('pattern').focus();
      clear($('result'));
      if (info.missing_functions && info.missing_functions.length) {
        showError('На хостинге отключены функции PHP: ' + info.missing_functions.join(', ')
          + '. Без них поиск не работает.');
      }
      if (info.root && !info.root.listable) {
        showError('Корень поиска недоступен для чтения: выберите другой в «Настройках».');
      }
      if (info.cp1251 === '') {
        $('result').appendChild(el('p', 'warn-box', 'На хостинге нет mbstring и iconv: файлы в Windows-1251 '
          + 'ищутся только латиницей, а файлы в UTF-16 пропускаются как двоичные.'));
      }
    }).catch(function (e) {
      token = '';
      save(TOKEN_KEY, null);
      $('auth-error').textContent = e.message;
      $('auth-error').hidden = false;
      $('token').focus();
    }).then(function () {
      $('connect').disabled = false;
    });
  }

  function logout() {
    if (running) { running.abort(); }
    token = '';
    save(TOKEN_KEY, null);
    clearInterval(tick);
    setStatus('Не подключено');
    $('token').value = '';
    $('auth').hidden = false;
    $('search').hidden = true;
    $('logout').hidden = true;
    $('delete').hidden = true;
    $('delete-confirm').hidden = true;
    clear($('result'));
    $('token').focus();
  }

  // ----- Удаление скрипта -----

  function askDelete() {
    if (cfg.selfDeleteAvailable) {
        $('delete-error').hidden = true;
        $('delete-confirm').hidden = false;
        $('delete-no').focus();
    }else{
        $('delete-unavailable').hidden = false;
    }
  }

  function deleteScript() {
    if (running) { running.abort(); }
    $('delete-yes').disabled = true;
    $('delete-error').hidden = true;
    post({ action: 'delete' }).then(function () {
      token = '';
      save(TOKEN_KEY, null);
      clearInterval(tick);
      setStatus('Скрипт удалён', 'bad');
      ['auth', 'search', 'delete-confirm', 'logout', 'delete'].forEach(function (id) { $(id).hidden = true; });
      clear($('result'));
      $('deleted').hidden = false;
    }).catch(function (e) {
      $('delete-error').textContent = e.message;
      $('delete-error').hidden = false;
    }).then(function () {
      $('delete-yes').disabled = false;
    });
  }

  // ----- Форма -----

  function activateButtons() {
    if (this.value.length) {
        $('clear').disabled = false;
        $('copy').disabled = false;
    } else {
        $('clear').disabled = true;
        $('copy').disabled = true;
    }
  }

  function escapeRegex(s) {
    return s.replace(/[\\^$.*+?()[\]{}|\/#-]/g, '\\$&');
  }

  function serverPattern(raw, regex, icase) {
    var p = regex ? raw : escapeRegex(raw);
    if (icase && !/^\(\?[a-zA-Z]*i[a-zA-Z]*\)/.test(p)) { p = '(?i)' + p; }
    return p;
  }

  function highlighter(raw, regex, icase) {
    var src = raw;
    var flags = 'g';
    if (regex) {
      src = src.replace(/^\(\?([a-zA-Z]+)\)/, function (m, f) {
        if (f.indexOf('i') !== -1) { icase = true; }
        return '';
      });
    } else {
      src = escapeRegex(src);
    }
    if (icase) { flags += 'i'; }
    try { return new RegExp(src, flags); } catch (e) { return null; }
  }

  function paint(node, text, re) {
    if (!re) { node.textContent = text; return; }
    re.lastIndex = 0;
    var pos = 0, m, guard = 0;
    while ((m = re.exec(text)) !== null && guard++ < 500) {
      if (m[0] === '') { re.lastIndex++; continue; }
      if (m.index > pos) { node.appendChild(document.createTextNode(text.slice(pos, m.index))); }
      node.appendChild(el('mark', '', m[0]));
      pos = m.index + m[0].length;
    }
    if (pos < text.length) { node.appendChild(document.createTextNode(text.slice(pos))); }
  }

  function readForm() {
    var form = {};
    FIELDS.forEach(function (id) {
      var input = $(id);
      form[id] = input.type === 'checkbox' ? input.checked : input.value;
    });
    return form;
  }

  function restoreForm() {
    var raw = load(FORM_KEY);
    if (!raw) { return; }
    try {
      var form = JSON.parse(raw);
      FIELDS.forEach(function (id) {
        if (!(id in form)) { return; }
        var input = $(id);
        if (input.type === 'checkbox') { 
          input.checked = !!form[id]; 
        } else {
          input.value = form[id]; 
        }
      });
      if (form.path || form.exclude || form.mode !== 'lines' || form.root !== '.') {
        $('more').open = true;
      }
      if (form.pattern) {
        $('clear').disabled = false;
        $('copy').disabled = false;
      }
    } catch (e) { /* испорченное значение игнорируем */ }
  }

  function syncMode() {
    $('context').disabled = $('mode').value !== 'lines';
  }

  function clampInput(input, min, max, fallback) {
    var v = parseInt(input.value, 10);
    if (isNaN(v)) { v = fallback; }
    v = Math.max(min, Math.min(max, v));
    input.value = v;
    return v;
  }

  function search() {
    var form = readForm();
    if (form.pattern === '') { $('pattern').focus(); return; }
    var context = clampInput($('context'), 0, cfg.maxContext, 0);
    var max = clampInput($('max'), 1, cfg.maxResults, cfg.defaultResults);
    form.context = String(context);
    form.max = String(max);
    save(FORM_KEY, JSON.stringify(form));

    if (running) { running.abort(); }
    var ctrl = new AbortController();
    running = ctrl;
    var started = Date.now();
    var result = $('result');
    clear(result);
    var wait = el('p', 'muted');
    wait.appendChild(el('span', 'spinner'));
    var waitText = document.createTextNode('Ищу…');
    wait.appendChild(waitText);
    result.appendChild(wait);
    var waitTick = setInterval(function () {
      waitText.textContent = 'Ищу… ' + Math.round((Date.now() - started) / 1000) + ' с';
    }, 1000);
    $('go').disabled = true;

    var pattern = serverPattern(form.pattern, form.regex, form.icase);
    var lead = form.regex ? pattern.length - form.pattern.length : 0;
    post({
      pattern: pattern,
      path: form.path.trim(),
      glob: form.glob.trim(),
      exclude: form.exclude,
      root: form.root,
      mode: form.mode,
      max_results: form.max,
      before: form.mode === 'lines' ? form.context : '0',
      after: form.mode === 'lines' ? form.context : '0',
      minified: form.minified ? '1' : '0',
      mtime: form.mtime ? '1' : '0'
    }, ctrl.signal).then(function (res) {
      render(res, form);
    }).catch(function (e) {
      if (e.name === 'AbortError') { return; }
      clear(result);
      showError(lead && e.status === 400 ? e.message.replace(/at offset (\d+)/, function (m, n) {
        return 'at offset ' + Math.max(0, Number(n) - lead);
      }) : e.message);
      if (e.status === 410) { setStatus('Срок жизни скрипта истёк', 'bad'); clearInterval(tick); }
    }).then(function () {
      clearInterval(waitTick);
      if (running === ctrl) {
        running = null;
        $('go').disabled = false;
      }
    });
  }

  // ----- Вывод -----

  function showError(text) {
    var p = el('p', 'error', text);
    $('result').appendChild(p);
  }

  function searchIn(path) {
    $('path').value = path;
    $('mode').value = 'lines';
    syncMode();
    $('more').open = true;
    search();
  }

  function copyText(text, cb) {
    if (!navigator.clipboard) { return; }
    navigator.clipboard.writeText(text).then(cb);
  }

  function copyButton(path) {
    var b = el('button', 'small', 'копировать путь');
    b.addEventListener('click', function () {
      copyText(path, function () {
        b.textContent = 'скопировано';
        setTimeout(function () {
            b.textContent = 'копировать путь';
        }, 1500);
      });
    });
    return b;
  }

  function expandCollapseButton(classes) {
    var b = el('button', 'small', 'свернуть');
    b.addEventListener('click', function () {
      if (classes.toggle('collapsed')) {
        b.textContent = 'развернуть';
      } else {
        b.textContent = 'свернуть';
      }
    });
    return b;
  }

  function clearPattern() {
    $('pattern').value = '';
    $('clear').disabled = true;
    $('copy').disabled = true;
  }

  function copyPattern() {
    var b = this;
    var text = $('pattern').value;
    copyText(text, function () {
      b.textContent = 'Скопирован';
      setTimeout(function () {
        b.textContent = 'Копировать';
      }, 1000);
    });
  }

  function fileHead(path, badges, linesClasses, withSearch) {
    var head = el('div', 'file-head');
    head.appendChild(el('span', 'file-name mono', path));
    badges.forEach(function (b) { head.appendChild(b); });
    if (withSearch) {
      var s = el('button', 'small', 'искать только здесь');
      s.type = 'button';
      s.addEventListener('click', function () { searchIn(path); });
      head.appendChild(s);
    }
    head.appendChild(expandCollapseButton(linesClasses));
    head.appendChild(copyButton(path));
    return head;
  }

  function maskedBadge() {
    var b = el('span', 'badge masked', 'секреты скрыты');
    b.title = 'Значения паролей и ключей заменены на ***';
    return b;
  }

  function mtimeBadge(iso) {
    var b = el('span', 'badge', 'изменён ' + date(iso));
    b.title = iso;
    return b;
  }

  function render(res, form) {
    var result = $('result');
    clear(result);
    var stats = res.stats || {};
    var t = res.truncated || {};
    var seconds = ((stats.elapsed_ms || 0) / 1000).toFixed(1) + ' с';
    var scanned = num(stats.files_scanned || 0) + ' ' + plural(stats.files_scanned || 0, 'файл', 'файла', 'файлов');

    if (t.fatal) {
      result.appendChild(el('p', 'warn-box', (res.error || 'Поиск прерван ошибкой PHP.')
        + ' Сузьте поиск папкой или маской файлов.'));
    }
    if (t.timeout) {
      result.appendChild(el('p', 'warn-box', 'Поиск прерван по времени: результаты неполные. '
        + 'Сузьте поиск папкой или маской файлов.'));
    }
    if (t.max_results) {
      result.appendChild(el('p', 'warn-box', 'Показаны только первые ' + form.max + ' результатов. '
        + 'Уточните запрос или увеличьте лимит (до ' + cfg.maxResults + ').'));
    }
    if (t.context) {
      result.appendChild(el('p', 'warn-box', 'Строки вокруг показаны не у всех совпадений: '
        + 'достигнут лимит контекста на один ответ.'));
    }

    var summary = el('p', 'summary');
    var re = highlighter(form.pattern, form.regex, form.icase);
    if (form.mode === 'lines') {
      var groups = groupByFile(res.matches || []);
      summary.appendChild(el('b', '', num(res.matches.length) + ' '
        + plural(res.matches.length, 'строка', 'строки', 'строк')));
      summary.appendChild(document.createTextNode(' в ' + num(groups.length) + ' '
        + plural(groups.length, 'файле', 'файлах', 'файлах')));
      summary.appendChild(el('span', 'muted', ' · просмотрено ' + scanned + ' за ' + seconds));
      result.appendChild(summary);
      groups.forEach(function (g) { result.appendChild(renderFile(g, re)); });
    } else {
      var files = res.files || [];
      if (form.mode === 'count') {
        summary.appendChild(el('b', '', num(res.total) + ' ' + plural(res.total, 'строка', 'строки', 'строк')));
        summary.appendChild(document.createTextNode(' (' + num(res.total_occurrences) + ' '
          + plural(res.total_occurrences, 'вхождение', 'вхождения', 'вхождений') + ') в '
          + num(files.length) + ' ' + plural(files.length, 'файле', 'файлах', 'файлах')));
      } else {
        summary.appendChild(el('b', '', num(files.length) + ' ' + plural(files.length, 'файл', 'файла', 'файлов')));
      }
      summary.appendChild(el('span', 'muted', ' · просмотрено ' + scanned + ' за ' + seconds));
      result.appendChild(summary);
      if (files.length) { result.appendChild(renderTable(files, form.mode === 'count', form.mtime)); }
    }
    if ((form.mode === 'lines' ? res.matches.length : (res.files || []).length) === 0) {
      result.appendChild(el('p', 'muted', 'Ничего не найдено.'));
    }
    result.appendChild(renderStats(stats));
  }

  function groupByFile(matches) {
    var groups = [], index = {};
    matches.forEach(function (m) {
      if (!(m.file in index)) {
        index[m.file] = groups.length;
        groups.push({ file: m.file, lines: {}, hits: 0, masked: false, mtime: m.mtime });
      }
      var g = groups[index[m.file]];
      g.hits++;
      if (m.masked) { g.masked = true; }
      g.lines[m.line] = { text: m.text, hit: true };
      (m.before || []).forEach(function (text, i) {
        var no = m.line - m.before.length + i;
        if (!g.lines[no]) { g.lines[no] = { text: text, hit: false }; }
      });
      (m.after || []).forEach(function (text, i) {
        var no = m.line + 1 + i;
        if (!g.lines[no]) { g.lines[no] = { text: text, hit: false }; }
      });
    });
    return groups;
  }

  function renderFile(g, re) {
    var box = el('div', 'file');
    var lines = el('div', 'lines');
    var badges = [el('span', 'badge', g.hits + ' ' + plural(g.hits, 'строка', 'строки', 'строк'))];
    if (g.mtime) { badges.push(mtimeBadge(g.mtime)); }
    if (g.masked) { badges.push(maskedBadge()); }
    box.appendChild(fileHead(g.file, badges, lines.classList, false));
    var numbers = Object.keys(g.lines).map(Number).sort(function (a, b) { return a - b; });
    var prev = null;
    numbers.forEach(function (no) {
      if (prev !== null && no > prev + 1) {
        var gap = el('div', 'ln gap');
        gap.appendChild(el('span', 'no', '⋯'));
        gap.appendChild(el('span', 'tx', ''));
        lines.appendChild(gap);
      }
      var item = g.lines[no];
      var row = el('div', 'ln ' + (item.hit ? 'hit' : 'ctx'));
      row.appendChild(el('span', 'no', no));
      var tx = el('span', 'tx');
      if (item.hit) { paint(tx, item.text, re); } else { tx.textContent = item.text; }
      row.appendChild(tx);
      lines.appendChild(row);
      prev = no;
    });
    box.appendChild(lines);
    return box;
  }

  function renderTable(files, counts, mtime) {
    var table = el('table');
    var head = el('tr');
    head.appendChild(el('th', '', 'Файл'));
    if (counts) {
      head.appendChild(el('th', 'num', 'Строк'));
      head.appendChild(el('th', 'num', 'Вхождений'));
    }
    if (mtime) { head.appendChild(el('th', 'num', 'Изменён')); }
    head.appendChild(el('th', '', ''));
    table.appendChild(head);
    files.forEach(function (f) {
      var tr = el('tr');
      var name = el('td', 'path mono', f.file);
      if (f.masked) { name.appendChild(document.createTextNode(' ')); name.appendChild(maskedBadge()); }
      tr.appendChild(name);
      if (counts) {
        tr.appendChild(el('td', 'num', num(f.count)));
        tr.appendChild(el('td', 'num', num(f.occurrences)));
      }
      if (mtime) {
        var changed = el('td', 'num', f.mtime ? date(f.mtime) : '');
        if (f.mtime) { changed.title = f.mtime; }
        tr.appendChild(changed);
      }
      var actions = el('td', 'num');
      var s = el('button', 'small', 'показать строки');
      s.type = 'button';
      s.addEventListener('click', function () { searchIn(f.file); });
      actions.appendChild(s);
      tr.appendChild(actions);
      table.appendChild(tr);
    });
    return table;
  }

  function renderStats(stats) {
    var box = el('details', 'stats');
    box.appendChild(el('summary', '', 'Подробности поиска'));
    var list = el('ul');
    function add(text) { list.appendChild(el('li', '', text)); }
    add('Просмотрено файлов: ' + num(stats.files_scanned || 0) + ', папок: ' + num(stats.dirs_scanned || 0));
    if (stats.secrets_masked) { add('Скрыто похожих на секреты значений: ' + num(stats.secrets_masked)); }
    if (stats.files_masked) { add('Конфигов с секретами просмотрено с маскированием: ' + num(stats.files_masked)); }
    if (stats.regex_errors) {
      add('Файлов, где регулярное выражение упёрлось в лимиты PCRE: ' + num(stats.regex_errors));
    }
    var skipped = stats.skipped || {};
    Object.keys(SKIPPED).forEach(function (k) {
      if (skipped[k]) { add('Пропущено — ' + SKIPPED[k] + ': ' + num(skipped[k])); }
    });
    var large = stats.too_large_files || [];
    if (large.length) {
      var li = el('li', '', 'Слишком большие файлы (по ним можно искать только командой на сервере):');
      var sub = el('ul');
      large.forEach(function (f) { sub.appendChild(el('li', 'mono', f.file + ' — ' + size(f.size))); });
      li.appendChild(sub);
      list.appendChild(li);
    }
    box.appendChild(list);
    return box;
  }

  function totop() {
    window.scrollTo(0, 0);
  }

  // ----- Запуск -----

  $('max').max = cfg.maxResults;
  $('max').value = cfg.defaultResults;
  $('context').max = cfg.maxContext;
  restoreForm();
  syncMode();

  $('auth-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var value = $('token').value.trim();
    if (value) { connect(value); }
  });
  $('search-form').addEventListener('submit', function (e) {
    e.preventDefault();
    search();
  });
  $('exclude').addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); search(); }
  });
  $('mode').addEventListener('change', syncMode);
  $('logout').addEventListener('click', logout);
  $('delete').addEventListener('click', askDelete);
  $('delete-no').addEventListener('click', function () { $('delete-confirm').hidden = true; });
  $('delete-yes').addEventListener('click', deleteScript);
  $('clear').addEventListener('click', clearPattern);
  $('copy').addEventListener('click', copyPattern);
  $('pattern').addEventListener('input', activateButtons);
  $('totop').addEventListener('click', totop);

  var stored = load(TOKEN_KEY);
  if (stored) {
    $('token').value = stored;
    connect(stored);
  } else {
    $('token').focus();
  }
})();
JS;
}
