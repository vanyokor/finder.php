<?php
/*

      Инструкция: https://github.com/vanyokor/finder.php/blob/main/README.md
    ❗Пожалуйста, не забывайте удалять скрипт, для сохранения безопасности сайта❗

*/
define('VERSION', '1.2');

// GET параметр, который необходимо передать в скрипт, для его запуска
define('STARTER', 'run');

// исключить из поиска директории
define('IGNORE_DIR', array(
    './.git',
    './cgi-bin',
    './stats',
    './bitrix/sounds',
    './bitrix/services',
    './bitrix/panel',
    './bitrix/otp',
    './bitrix/legal',
    './bitrix/blocks',
    './bitrix/fonts',
    './bitrix/themes',
    './bitrix/gadgets',
    './bitrix/tmp',
    './bitrix/backup',
    './bitrix/images',
    './bitrix/cache',
    './bitrix/managed_cache',
    './bitrix/html_pages',
    './bitrix/stack_cache',
    './bitrix/updates',
    './bitrix/modules',
    './bitrix/wizards',
    './upload/resize_cache',
    './upload/medialibrary',
    './upload/iblock',
    './upload/tmp',
    './upload/uf',
    './system/storage',
    './image/cache',
    './wp-content/cache',
    './core/cache',
    './assets/cache',
    './logs',
    './cache',
    './administrator/cache',
    './wa-cache',
    './var/cache',
    './wp-content/plugins/akeebabackupwp/app/tmp',
));

// исключить из поиска файлы
define('IGNORE_FILE', array(
    './finder.php',
));

// Скрывать содержимое файла
define('SENSITIVE_DATA_FILES', array(
    './bitrix/.settings.php',
    './bitrix/php_interface/dbconn.php',
    './config.php',
    './admin/config.php',
    './wp-config.php',
    './manager/includes/config.inc.php',
    './core/config/config.inc.php',
    './configuration.php',
    './sites/default/settings.php',
    './wa-config/db.php',
    './wp-content/plugins/akeebabackupwp/helpers/private/wp-config.php',
));

// Доступные для выбора расширения файлов
define('FILE_EXTENSIONS', array(
    '.php',
    '.js',
    '.css',
    '.html',
    '.tpl',
    '.twig',
    'all', // не удалять, запускает поиск по всем файлам
));
define('FILE_EXTENSIONS_COUNT', count(FILE_EXTENSIONS));

// Режимы сканирования
define('MODES', array(
    'default',
    'case sensitive',
    'show only folder names',
));
define('MODES_COUNT', count(MODES));
define('MODE_SENSITIVE', 1);
define('MODE_SHOW_FOLDER_NAMES', 2);

// запуск таймера, чтобы скрипт не сканировал больше минуты
define('START_TIME', time());

// Максимальная глубина вложенности
define('MAX_DEPTH', 12);

// Запрос должен содержать ограниченную длину символов
define('MIN_SEARCH_LEN', 3);
define('MAX_SEARCH_LEN', 51);

define('RESULTS_START_POS', 46);
define('RESULTS_END_POS', 126);
define('TIME_LIMIT', 55);
define('SECONDS_PER_DAY', 86400);
define('FILE_SIZE_LIMIT', 512000);
define('ORIGINAL_SYMBOLS', array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '));
define('REPLACED_SYMBOLS', array(' ', ' ', ' ', '', '', '', ''));

define('FIELD_WIDESCREEN', 'widescreen');
define('FIELD_CUR_DEPTH', 'cur_depth');
define('FIELD_MODE', 'mode');


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
    Поиск в содержимом файла
*/
function find_substr($content, $filename)
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
                escape_str(substr($content, $pos - $startpos, $startpos)),
                escape_str(substr($content, $pos, SEARCH_STR_LEN)),
                escape_str(substr($content, $newpos, RESULTS_END_POS))
            );
        }
        $pos = $newpos;
    }
    if (count($matches)) {
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
function scan_recursive($directory, &$interrupted, &$currentDepth)
{
    if ($currentDepth > DEPTH_LIMIT) {
        return;
    }
    $directory = list_dir($directory);
    foreach ($directory as $filename) {
        if (is_link($filename)) {
            continue;
        } elseif (is_dir($filename)) {
            if (!in_array($filename, IGNORE_DIR)) {
                ++$currentDepth;
                scan_recursive($filename, $interrupted, $currentDepth);
                --$currentDepth;
            }
        } else {
            if (is_correct_file($filename)) {
                $content = read_file($filename);
                if ($content !== false) {
                    find_substr($content, $filename);
                }
                unset($content);
            }
        }
        if ((time() - START_TIME) > TIME_LIMIT) {
            $interrupted = true;
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
        if (is_link($filename)) {
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
            $interrupted = true;
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


// Самоудаление скрипта, при попытке запуска, через сутки
if (time() > (filectime(__FILE__) + SECONDS_PER_DAY)) {
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

// Чувствительность к регистру
define('SEARCH_FUNC_NAME', $cur_mode == MODE_SENSITIVE ? 'strpos' : 'stripos');

// Полноэкранный режим
define('IS_WIDESCREEN', (bool) read_post_or_default(FIELD_WIDESCREEN, 0));

// искомая строка
define('SEARCH_STR', (string)filter_input(INPUT_POST, 'search_str'));
define('SEARCH_STR_LEN', strlen(SEARCH_STR));

// Выбор расширения файла
$file_extension_id = read_post_or_default('file_extension', 0, FILE_EXTENSIONS_COUNT);
define('SEARCHED_FILE_EXTENSION', FILE_EXTENSIONS[$file_extension_id]);

// Запуск поиска во всех файлах
define('SEARCH_IN_ALL', $file_extension_id == (FILE_EXTENSIONS_COUNT - 1));

// Отобразить отсканированные директории без чтения файлов
$show_only_folders = $cur_mode == MODE_SHOW_FOLDER_NAMES;

// Флаг, прерван ли поиск из-за таймаута
$interrupted = false;

// Текущая сканиуемая глубина
$currentDepth = 1;

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
<style>*,:after,:before{box-sizing:inherit}html{background:#424146;font-family:sans-serif;box-sizing:border-box}body{background:#bab6b5;padding:15px;border-radius:3px;max-width:<?=IS_WIDESCREEN ? '1460px' : '800px'?>;margin:10px auto 60px}form,p,output{text-align:center;font-size:small;user-select:none}section{margin-top:30px;padding:10px;background:#f1f1f1;border-radius:3px}header{font-size:small;overflow-wrap:break-word;font-weight:700}code{width:100%;display:block;background:#d4d9dd;padding:5px;border-radius:3px;margin-top:10px;overflow-wrap:break-word}label{text-align:left;display:block;width:300px;margin:10px auto 0}code b{color:red}details{margin-top:1em}slot{font-size:smaller;overflow-wrap:break-word}ul{padding-left:1em}output{background:#ff4b4b;color:#fff;padding:15px;margin:15px;border-radius:3px;display:block}</style>
</head>
<body>
<form method="POST">
in 
<?php
// Доступные расширения файла
show_select_field('file_extension', FILE_EXTENSIONS, $file_extension_id);
unset($file_extension_id);
?> 
<input type="text" placeholder="text" name="search_str" value="<?=htmlentities(SEARCH_STR)?>" maxlength="<?=MAX_SEARCH_LEN - 1?>">
<button type="submit">search</button>
<p> don't forget to <?=is_writable(__FILE__) ? '<a href="?delete">delete</a>' : 'delete' ?> this script from server</p>
<details>
<summary>Advanced settings</summary>
<label> Scan mode: 
<?php
// Режим сканирования
show_select_field(FIELD_MODE, MODES, $cur_mode);
unset($cur_mode);
?>
</label>
<label> Max depth: 
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
<label> Widescreen: 
<?php
// Широкоэкранный режим
show_select_field(FIELD_WIDESCREEN, array(0 => 'no', 1 => 'yes'), IS_WIDESCREEN);
?>
</label>
</details>
</form>
<?php
if ($show_only_folders) {
    echo '<section><header>Folders:</header><slot>';
    list_recursive('.', $interrupted, $currentDepth);
    echo '</slot></section>';
    echo $interrupted ? '<output>Scan time has expired!</output>' : '<p>Scan completed!</p>';
} elseif (SEARCH_STR) {
    if (SEARCH_STR_LEN > MIN_SEARCH_LEN) {
        if (SEARCH_STR_LEN < MAX_SEARCH_LEN) {
            scan_recursive('.', $interrupted, $currentDepth);
            echo $interrupted ? '<output>Search time has expired!</output>' : '<p>Search completed!</p>';
        } else {
            echo '<output>Request is too long.<br>',SEARCH_STR_LEN,' > ', MAX_SEARCH_LEN - 1, '</output>';
        }
    } else {
        echo '<output>Request is too short.<br>',SEARCH_STR_LEN,' < ', MIN_SEARCH_LEN + 1, '</output>';
    }
}
?>
</body>
</html>
