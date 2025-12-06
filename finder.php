<?php
/*
    ❗Пожалуйста, в конце работ, не забывайте удалять скрипт с сайта❗
    Инструкция по работе: https://github.com/vanyokor/finder.php/blob/main/README.md
*/
const VERSION = '1.4';

// GET параметр, который необходимо передать в скрипт, для его запуска
const STARTER = 'run';

// корневая папка для сканирования ('.' - текущая)
const FOLDER = '.';

// Пропускать символьные ссылки
const SKIP_SYMLINKS = true;

// исключить из поиска директории
const IGNORE_DIR = array(
    FOLDER . '/.git',
    FOLDER . '/.well-known',
    FOLDER . '/cgi-bin',
    FOLDER . '/stats',
    FOLDER . '/awstats',
    FOLDER . '/bitrix/sounds',
    FOLDER . '/bitrix/services',
    FOLDER . '/bitrix/panel',
    FOLDER . '/bitrix/otp',
    FOLDER . '/bitrix/legal',
    FOLDER . '/bitrix/blocks',
    FOLDER . '/bitrix/fonts',
    FOLDER . '/bitrix/themes',
    FOLDER . '/bitrix/gadgets',
    FOLDER . '/bitrix/tmp',
    FOLDER . '/bitrix/backup',
    FOLDER . '/bitrix/images',
    FOLDER . '/bitrix/cache',
    FOLDER . '/bitrix/managed_cache',
    FOLDER . '/bitrix/html_pages',
    FOLDER . '/bitrix/stack_cache',
    FOLDER . '/bitrix/updates',
    FOLDER . '/bitrix/wizards',
    FOLDER . '/upload/resize_cache',
    FOLDER . '/upload/medialibrary',
    FOLDER . '/upload/iblock',
    FOLDER . '/upload/tmp',
    FOLDER . '/upload/uf',
    FOLDER . '/system/storage',
    FOLDER . '/image/cache',
    FOLDER . '/wp-content/cache',
    FOLDER . '/core/cache',
    FOLDER . '/assets/cache',
    FOLDER . '/logs',
    FOLDER . '/cache',
    FOLDER . '/administrator/cache',
    FOLDER . '/wa-cache',
    FOLDER . '/var/cache',
    FOLDER . '/wp-content/plugins/akeebabackupwp/app/tmp',
    FOLDER . '/seo_backup',
);

// исключить из поиска файлы
const IGNORE_FILE = array(
    FOLDER . '/finder.php',
);

// Скрывать содержимое файла
const SENSITIVE_DATA_FILES = array(
    FOLDER . '/bitrix/.settings.php',
    FOLDER . '/bitrix/php_interface/dbconn.php',
    FOLDER . '/config.php',
    FOLDER . '/admin/config.php',
    FOLDER . '/wp-config.php',
    FOLDER . '/manager/includes/config.inc.php',
    FOLDER . '/core/config/config.inc.php',
    FOLDER . '/configuration.php',
    FOLDER . '/sites/default/settings.php',
    FOLDER . '/wa-config/db.php',
    FOLDER . '/wp-content/plugins/akeebabackupwp/helpers/private/wp-config.php',
);

// Доступные для выбора расширения файлов
const FILE_EXTENSIONS = array(
    '.php',
    '.js',
    '.css',
    '.html',
    '.tpl',
    '.twig',
    'all',
);
define('FILE_EXTENSIONS_COUNT', count(FILE_EXTENSIONS));

// Режимы сканирования
const MODES = array(
    'default',
    'case-insensitive',
    'just show all folder names',
);
define('MODES_COUNT', count(MODES));
define('MODE_CASE_INSENSITIVE', 1);
define('MODE_SHOW_FOLDER_NAMES', 2);

// запуск таймера, чтобы скрипт не сканировал больше минуты
define('START_TIME', time());

// Максимальная глубина вложенности
define('MAX_DEPTH', 12);

// Запрос должен содержать ограниченную длину символов
define('MIN_SEARCH_LEN', 2);
define('MAX_SEARCH_LEN', 51);

// Лимит количества найденных подстрок
define('LIMIT_MATCHES', 2500);

define('RESULTS_START_POS', 46);
define('RESULTS_END_POS', 126);
define('TIME_LIMIT', 55);
define('SCRIPT_TIMEOUT', 28800);

const ORIGINAL_SYMBOLS = array("\r\n", "\r", "\n", "\t", '  ', '    ', '    ');
const REPLACED_SYMBOLS = array(' ', ' ', ' ', '', '', '', '');

define('FIELD_FILE_EXTENSION', 'file_extension');
define('FIELD_WIDESCREEN', 'widescreen');
define('FIELD_CUR_DEPTH', 'cur_depth');
define('FIELD_MODE', 'mode');
define('FIELD_FILE_SIZE_LIMIT', 'file_size_limit');


/*
    Вывод поля select в форме
*/
function show_select_field($name, $list, $current)
{
    echo '<select name="',$name,'">';
    foreach ($list as $key => $value) {
        echo '<option value="',$key,'"';
        if ($current == $key) {
            echo ' selected';
        }
        echo '>',$value,'</option>';
    }
    echo '</select>';
}


/*
    Вывод положительного результата поиска
*/
function show_result($filename, $matches)
{
    echo '<section><header>',$filename,'</header>';
    foreach ($matches as $match) {
        echo '<code>', $match[0], '<b>', $match[1], '</b>', $match[2], '</code>';
    }
    echo '</section>';
}


/*
    Поиск подстроки в тексте
*/
function searching($content, $pos)
{
    return call_user_func(SEARCH_FUNC_NAME, $content, SEARCH_STR, $pos);
}


/*
    Вернуть отрывок из текста в файле
*/
function excerpt($content, $start, $end)
{
    return escape_str(call_user_func(SUBSTR_FUNC_NAME, $content, $start, $end));
}


/*
    Поиск в содержимом файла
*/
function find_substr($content, $filename, &$foundFilesCount, &$foundSubstrCount)
{
    $startpos = RESULTS_START_POS;
    $pos = 0;
    $matches = array();
    while (($pos = searching($content, $pos)) !== false) {
        if ($pos < $startpos) {
            $startpos = $pos;
        }
        $newpos = $pos + SEARCH_STR_LEN;
        if (in_array($filename, SENSITIVE_DATA_FILES)) {
            $matches[] = array('content contains &quot;', escape_str(SEARCH_STR), '&quot;');
        } else {
            $matches[] = array(
                excerpt($content, $pos - $startpos, $startpos),
                excerpt($content, $pos, SEARCH_STR_LEN),
                excerpt($content, $newpos, RESULTS_END_POS)
            );
        }
        $pos = $newpos;
    }
    $count = count($matches);
    if ($count) {
        ++$foundFilesCount;
        $foundSubstrCount += $count;
        show_result(escape_str($filename), $matches);
    }
}


/*
    Сканирование директории
*/
function list_dir($directoryName)
{
    $result = array();
    if (is_readable($directoryName)) {
        $dir = opendir($directoryName);
        if ($dir) {
            while ($fname = readdir($dir)) {
                if ($fname == '.' || $fname == '..') {
                    continue;
                }
                $result[] = $directoryName.DIRECTORY_SEPARATOR.$fname;
            }
            closedir($dir);
        }
    }
    return $result;
}


/*
    Проверка расширения файла
*/
function is_correct_extension($file)
{
    if (SEARCH_IN_ALL) {
        return true;
    }
    $extensionPos = strpos($file, SEARCHED_FILE_EXTENSION);
    if ($extensionPos !== false && substr($file, $extensionPos) == SEARCHED_FILE_EXTENSION) {
        return true;
    }
    return false;
}


/*
    Проверка на подходящий для чтения файл
*/
function is_correct_file($file)
{
    if (is_correct_extension($file)) {
        if (!in_array($file, IGNORE_FILE)) {
            if (is_readable($file)) {
                $filesize = filesize($file);
                if ($filesize) {
                    if ($filesize < FILE_SIZE_LIMIT) {
                        return true;
                    }
                }
            }
        }
    }
    return false;
}


/*
    Чтение содержимого файла
*/
function read_file($filepath)
{
    $handle = fopen($filepath, 'r');
    if ($handle === false) {
        return false;
    }
    $content = fread($handle, filesize($filepath));
    if ($content === false) {
        fclose($handle);
        return false;
    }
    fclose($handle);
    return $content;
}


/*
    Рекурсивный поиск файлов, содержащих искомую строку
*/
function scan_recursive($directory, &$interrupted, &$currentDepth, &$foundFilesCount, &$foundSubstrCount)
{
    if ($currentDepth > DEPTH_LIMIT) {
        return;
    }
    $directory = list_dir($directory);
    foreach ($directory as $filename) {
        if (SKIP_SYMLINKS && is_link($filename)) {
            continue;
        } elseif (is_dir($filename)) {
            if (!in_array($filename, IGNORE_DIR)) {
                ++$currentDepth;
                scan_recursive($filename, $interrupted, $currentDepth, $foundFilesCount, $foundSubstrCount);
                --$currentDepth;
            }
        } else {
            if (is_correct_file($filename)) {
                $content = read_file($filename);
                if ($content !== false) {
                    find_substr($content, $filename, $foundFilesCount, $foundSubstrCount);
                }
                unset($content);
            }
        }
        if ((time() - START_TIME) > TIME_LIMIT) {
            $interrupted = INTERRUPT_TIME_EXPIRED;
            return;
        }
        if ($foundSubstrCount > LIMIT_MATCHES) {
            $interrupted = INTERRUPT_TOO_MANY_MATCHES;
            return;
        }
    }
}


/*
    Отображение сканируемых директорий
*/
function list_recursive($directory, &$interrupted, &$currentDepth)
{
    if ($currentDepth > DEPTH_LIMIT) {
        return;
    }
    $directory = list_dir($directory);
    echo '<ul>';
    foreach ($directory as $filename) {
        if (SKIP_SYMLINKS && is_link($filename)) {
            continue;
        } elseif (is_dir($filename)) {
            if (!in_array($filename, IGNORE_DIR)) {
                ++$currentDepth;
                echo '<li>',$filename,'</li>';
                list_recursive($filename, $interrupted, $currentDepth);
                --$currentDepth;
            }
        }
        if ((time() - START_TIME) > TIME_LIMIT) {
            $interrupted = INTERRUPT_TIME_EXPIRED;
            return;
        }
    }
    echo '</ul>';
}


/*
    Экранирование строки
*/
function escape_str($text)
{
    $text = str_replace(ORIGINAL_SYMBOLS, REPLACED_SYMBOLS, $text);
    $text = htmlentities($text, ENT_QUOTES | ENT_SUBSTITUTE);
    return $text;
}


/*
    Получение настройки с POST запроса или возврат значения по умолчанию
*/
function read_post_or_default($name, $default = 0, $maxValue = 2)
{
    $selected = (int)filter_input(INPUT_POST, $name, FILTER_SANITIZE_NUMBER_INT);
    if (($selected > 0) && ($selected < $maxValue)) {
        return $selected;
    }
    return $default;
}


// Самоудаление скрипта, при попытке запуска, через некоторое время
if (time() > (filectime(__FILE__) + SCRIPT_TIMEOUT)) {
    @unlink(__FILE__);
    exit('file timeout');
}
// Самоудаление скрипта по get запросу
if (isset($_GET['delete'])) {
    if (is_writable(__FILE__)) {
        unlink(__FILE__);
        exit('deleted');
    } else {
        exit('Error! no permission to delete');
    }
}

// Для запуска, не забудь добавить GET параметр в адресную строку
if (!isset($_GET[STARTER])) {
    die();
}

// Выбранный режим сканирования
$cur_mode = read_post_or_default(FIELD_MODE, 0, MODES_COUNT);

// Выбранная глубина вложенности
define('DEPTH_LIMIT', read_post_or_default(FIELD_CUR_DEPTH, MAX_DEPTH, MAX_DEPTH));

// Лимит размера файла
$file_size_limits = array(
    1024000,
    512000,
    128000,
    32000,
    8000,
    1000
);
$front_file_size_limits = array(
    '< 1 Mb',
    '< 512 kb',
    '< 128 kb',
    '< 32 kb',
    '< 8 kb',
    '< 1 kb'
);
$front_file_size_limit = read_post_or_default(FIELD_FILE_SIZE_LIMIT, 2, count($file_size_limits));
define('FILE_SIZE_LIMIT', $file_size_limits[$front_file_size_limit]);
unset($file_size_limits);

// Поддержка многобайтовых строк
define('SUBSTR_FUNC_NAME', function_exists('mb_substr') ? 'mb_substr' : 'substr');
define('STRPOS_FUNC_NAME', function_exists('mb_strpos') ? 'mb_strpos' : 'strpos');
define('STRIPOS_FUNC_NAME', function_exists('mb_stripos') ? 'mb_stripos' : 'stripos');

// Чувствительность к регистру
define('SEARCH_FUNC_NAME', $cur_mode == MODE_CASE_INSENSITIVE ? STRIPOS_FUNC_NAME : STRPOS_FUNC_NAME);

// Полноэкранный режим
define('IS_WIDESCREEN', (bool) read_post_or_default(FIELD_WIDESCREEN, 0));

// искомая строка
define('SEARCH_STR', (string)filter_input(INPUT_POST, 'search_str'));
define('SEARCH_STR_LEN', function_exists('mb_strlen') ? mb_strlen(SEARCH_STR) : strlen(SEARCH_STR));

// Выбор расширения файла
$file_extension_id = read_post_or_default(FIELD_FILE_EXTENSION, 0, FILE_EXTENSIONS_COUNT);
define('SEARCHED_FILE_EXTENSION', FILE_EXTENSIONS[$file_extension_id]);

// Запуск поиска во всех файлах
define('SEARCH_IN_ALL', $file_extension_id == (FILE_EXTENSIONS_COUNT - 1));

// Флаг, прерван ли поиск
const INTERRUPT_TIME_EXPIRED = 1;
const INTERRUPT_TOO_MANY_MATCHES = 2;
$interrupted = 0;

// Отобразить отсканированные директории без чтения файлов
$show_only_folders = $cur_mode == MODE_SHOW_FOLDER_NAMES;

// Текущая сканиуемая глубина
$currentDepth = 1;

// Счетчик подходящих файлов
$foundFilesCount = 0;

// Счетчик найденных подстрок
$foundSubstrCount = 0;

// Защита от межсайтового скриптинга
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self'");
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

// Установка рекомендуемого лимита оперативной памяти и времени выполнения
ini_set('memory_limit', '1G');
ini_set('max_execution_time', '60');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>finder v<?=VERSION?></title>
<meta name="robots" content="noindex, nofollow"/>
<style>*,:after,:before{box-sizing:inherit}html{background:#424146;font-family:sans-serif;box-sizing:border-box}body{background:#bab6b5;padding:15px;border-radius:3px;max-width:<?=IS_WIDESCREEN ? '1460px' : '800px'?>;margin:10px auto 60px}form,p,output{text-align:center;font-size:small;user-select:none}section{margin-top:30px;padding:10px;background:#f1f1f1;border-radius:3px}header{font-size:small;overflow-wrap:break-word;font-weight:700}code{width:100%;display:block;background:#d4d9dd;padding:5px;border-radius:3px;margin-top:10px;overflow-wrap:break-word}label{text-align:left;display:block;width:300px;margin:10px auto 0}code b{color:red}details{margin-top:1em}summary:hover{background:#b1b1b1;cursor:pointer}slot{font-size:smaller;overflow-wrap:break-word}ul{padding-left:1em}output{background:#ff4b4b;color:#fff;padding:15px;margin:15px;border-radius:3px;display:block}aside{position:fixed;bottom:12px;right:calc(50% - 388px);padding:3px;border-radius:3px;backdrop-filter:blur(3px);border:1px solid #dfdfdf63;user-select:none;}aside a{padding:7px;background:#424146;opacity:.5;display:inline-block;width:30px;height:30px;border-radius:3px;text-decoration:none;color:#fff;font-size:small;text-align:center;}aside a:hover{opacity:.7;}</style>
</head>
<body id="start">
<form method="POST">
in 
<?php
// Доступные расширения файла
show_select_field(FIELD_FILE_EXTENSION, FILE_EXTENSIONS, $file_extension_id);
unset($file_extension_id);
?> 
<input type="text" placeholder="text" name="search_str" value="<?=htmlentities(SEARCH_STR)?>" maxlength="<?=MAX_SEARCH_LEN - 1?>">
<button type="submit">search</button>
<p> don't forget to <?=is_writable(__FILE__) ? '<a href="?delete">delete</a>' : 'delete' ?> this script from server</p>
<details>
<summary>Advanced settings</summary>
<label>
Scan mode: 
<?php
// Режим сканирования
show_select_field(FIELD_MODE, MODES, $cur_mode);
unset($cur_mode);
?>
</label>
<label>
File size limit: 
<?php
// Лимит размера файла
show_select_field(FIELD_FILE_SIZE_LIMIT, $front_file_size_limits, $front_file_size_limit);
unset($front_file_size_limits, $front_file_size_limit);
?>
</label>
<label>
Max depth: 
<?php
// Глубина сканирования
$depths = array();
for ($i = 1; $i <= MAX_DEPTH; $i++) {
    $depths[$i] = $i;
}
unset($i);
show_select_field(FIELD_CUR_DEPTH, $depths, DEPTH_LIMIT);
unset($depths);
?>
 folders
</label>
<label>
Widescreen: 
<?php
// Широкоэкранный режим
show_select_field(FIELD_WIDESCREEN, array(0 => 'no', 1 => 'yes'), IS_WIDESCREEN);
?>
</label>
</details>
</form>
<?php if ($show_only_folders) { ?>
<section>
<header>Folders:</header>
<slot>
<?php list_recursive(FOLDER, $interrupted, $currentDepth); ?>
</slot>
</section>
<?=$interrupted ? '<output>Scan time has expired!</output>' : '<p>Scan completed!</p>'; ?>
<?php } elseif (SEARCH_STR) {
    if (SEARCH_STR_LEN > MIN_SEARCH_LEN) {
        if (SEARCH_STR_LEN < MAX_SEARCH_LEN) {
            scan_recursive(FOLDER, $interrupted, $currentDepth, $foundFilesCount, $foundSubstrCount);
            switch ($interrupted) {
                case INTERRUPT_TIME_EXPIRED:
                    echo '<output>Search time has expired!</output>';
                    break;
                case INTERRUPT_TOO_MANY_MATCHES:
                    echo '<output>Too many matches!</output>';
                    break;
                default:
                    echo '<p>Search completed!</p>';
            }
            echo '<p>Files found: ', $foundFilesCount, ', мatches: ',$foundSubstrCount, '</p>';
        } else {
            echo '<output>Request is too long.<br>',SEARCH_STR_LEN,' > ', MAX_SEARCH_LEN - 1, '</output>';
        }
    } else {
        echo '<output>Request is too short.<br>',SEARCH_STR_LEN,' < ', MIN_SEARCH_LEN + 1, '</output>';
    }
}
if (!empty($_POST)) { ?>
<div id="end"></div>
<aside>
<a href="#start">⯅</a>
<a href="#end">⯆</a>
</aside>
<?php } ?>
</body>
</html>
