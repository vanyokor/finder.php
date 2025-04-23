<?php
/*

    ❗Пожалуйста, не забывайте удалять скрипт, для сохранения безопасности сайта❗

*/
define('VERSION', '1.2');

// GET параметр, который необходимо передать в скрипт, для его запуска
define('STARTER', 'run');

// TODO: Попробовать вернуть полные пути

// исключить из поиска директории
define('IGNORE_DIR', array(
    "./.git",
    "./cgi-bin",
    "./stats",
    "./bitrix/sounds",
    "./bitrix/services",
    "./bitrix/panel",
    "./bitrix/otp",
    "./bitrix/legal",
    "./bitrix/blocks",
    "./bitrix/fonts",
    "./bitrix/themes",
    "./bitrix/gadgets",
    "./bitrix/tmp",
    "./bitrix/backup",
    "./bitrix/images",
    "./bitrix/cache",
    "./bitrix/managed_cache",
    "./bitrix/html_pages",
    "./bitrix/stack_cache",
    "./bitrix/updates",
    "./bitrix/modules",
    "./bitrix/wizards",
    "./upload/resize_cache",
    "./upload/medialibrary",
    "./upload/iblock",
    "./upload/tmp",
    "./upload/uf",
    "./system/storage",
    "./image/cache",
    "./wp-content/cache",
    "./core/cache",
    "./assets/cache",
    "./logs",
    "./cache",
    "./administrator/cache",
    "./wa-cache",
    "./var/cache",
    "./wp-content/plugins/akeebabackupwp/app/tmp",
));

// исключить из поиска файлы
define('IGNORE_FILE', array(
    "./finder.php",
));

// Скрывать содержимое файла
define('SENSITIVE_DATA_FILES', array(
    "./bitrix/.settings.php",
    "./bitrix/php_interface/dbconn.php",
    "./config.php",
    "./admin/config.php",
    "./wp-config.php",
    "./manager/includes/config.inc.php",
    "./core/config/config.inc.php",
    "./configuration.php",
    "./sites/default/settings.php",
    "./wa-config/db.php",
    "./wp-content/plugins/akeebabackupwp/helpers/private/wp-config.php",
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

// запуск таймера, чтобы скрипт не сканировал больше минуты
define('START_TIME', time());


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
function searching($content, $needle, $pos)
{
    return call_user_func(SEARCH_FUNC_NAME, $content, $needle, $pos);
}


/*
    Поиск в содержимом файла
*/
function find_substr($content, $filename, $needle)
{
    $startpos = 46;
    $endpos = 126;
    $pos = 0;
    $len = strlen($needle);
    $matches = array();
    while (($pos = searching($content, $needle, $pos)) !== false) {
        if ($pos < $startpos) {
            $startpos = $pos;
        }
        $newpos = $pos + $len;
        if (in_array($filename, SENSITIVE_DATA_FILES)) {
            $matches[] = array('content contains &quot;', escape_str($needle), '&quot;');
        } else {
            $matches[] = array(
                escape_str(substr($content, $pos - $startpos, $startpos)),
                escape_str(substr($content, $pos, $len)),
                escape_str(substr($content, $newpos, $endpos))
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
function list_dir($directory)
{
    $result = array();
    if (is_readable($directory)) {
        if ($d = opendir($directory)) {
            while ($fname = readdir($d)) {
                if ($fname == '.' || $fname == '..') {
                    continue;
                }
                $result[] = $directory.DIRECTORY_SEPARATOR.$fname;
            }
            closedir($d);
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
    $extension_pos = strpos($file, SEARCHED_FILE_EXTENSION);
    if ($extension_pos !== false && substr($file, $extension_pos) == SEARCHED_FILE_EXTENSION) {
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
                    if ($filesize < 512000) {
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
function scan_recursive($directory, $search)
{
    global $interrupted, $current_depth;
    if ($current_depth > DEPTH_LIMIT) {
        return;
    }
    $directory = list_dir($directory);
    foreach ($directory as $filename) {
        if (is_link($filename)) {
            continue;
        } elseif (is_dir($filename)) {
            if (!in_array($filename, IGNORE_DIR)) {
                $current_depth++;
                scan_recursive($filename, $search);
                $current_depth--;
            }
        } else {
            if (is_correct_file($filename)) {
                $content = read_file($filename);
                if ($content !== false) {
                    find_substr($content, $filename, $search);
                }
                unset($content);
            }
        }
        if ((time() - START_TIME) > 55) {
            $interrupted = true;
            return;
        }
    }
}


/*
    Отображение сканируемых директорий
*/
function list_recursive($directory)
{
    global $interrupted, $current_depth;
    if ($current_depth > DEPTH_LIMIT) {
        return;
    }
    $directory = list_dir($directory);
    echo '<ul>';
    foreach ($directory as $filename) {
        if (is_link($filename)) {
            continue;
        } elseif (is_dir($filename)) {
            if (!in_array($filename, IGNORE_DIR)) {
                $current_depth++;
                echo '<li>',$filename,'</li>';
                list_recursive($filename);
                $current_depth--;
            }
        }
        if ((time() - START_TIME) > 55) {
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
    $text = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), array(' ', ' ', ' ', '', '', '', ''), $text);
    $text = htmlentities($text, ENT_QUOTES | ENT_SUBSTITUTE);
    return $text;
}


/*
    Получение настройки с POST запроса или возврат значения по умолчанию
*/
function read_post_or_default($name, $default = 0, $max_value = 1)
{
    if (isset($_POST[$name])) {
        $selected = (int) $_POST[$name];
        if (($selected > 0) && ($selected < $max_value)) {
            return $selected;
        }
    }
    return $default;
}


// Самоудаление скрипта, при попытке запуска, через сутки
if (time() > (filectime(__FILE__) + 86400)) {
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
$cur_mode = read_post_or_default('mode', 0, MODES_COUNT);

// Максимальная глубина вложенности
$max_depth = 12;

// Выбранная глубина вложенности
define('DEPTH_LIMIT', read_post_or_default('cur_depth', $max_depth, $max_depth));

// Чувствительность к регистру
define('SEARCH_FUNC_NAME', $cur_mode == 1 ? 'strpos' : 'stripos');

// искомая строка
define('SEARCH_STR', !empty($_POST['search_str']) ? $_POST['search_str'] : '');
define('SEARCH_STR_LEN', strlen(SEARCH_STR));

// Выбор расширения файла
$file_extension_id = read_post_or_default('file_extension', 0, FILE_EXTENSIONS_COUNT);
define('SEARCHED_FILE_EXTENSION', FILE_EXTENSIONS[$file_extension_id]);

// Запуск поиска во всех файлах
define('SEARCH_IN_ALL', $file_extension_id == (FILE_EXTENSIONS_COUNT - 1));

// Отобразить отсканированные директории без чтения файлов
$show_only_folders = $cur_mode == 2;

// Флаг, прерван ли поиск из-за таймаута
$interrupted = false;

// Текущая сканиуемая глубина
$current_depth = 1;

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
<meta charset="UTF-8"><title>finder v<?=VERSION?></title><meta name="robots" content="noindex, nofollow"/>
<style>*,:after,:before{box-sizing:inherit}html{background:#424146;font-family:sans-serif;box-sizing:border-box}body{background:#bab6b5;padding:15px;border-radius:3px;max-width:800px;margin:10px auto 60px}form,p,output{text-align:center;font-size:small;user-select:none}section{margin-top:30px;padding:10px;background:#f1f1f1;border-radius:3px}header{font-size:small;overflow-wrap:break-word;font-weight:700}code{width:100%;display:block;background:#d4d9dd;padding:5px;border-radius:3px;margin-top:10px;overflow-wrap:break-word}code b{color:red}details{margin-top:1em}slot{font-size:smaller;overflow-wrap:break-word}ul{padding-left:1em}output{background:#ff4b4b;color:#fff;padding:15px;margin:15px;border-radius:3px;display:block}</style>
</head>
<body>
<form method="POST">
in 
<?php
// Доступные расширения файла
show_select_field('file_extension', FILE_EXTENSIONS, $file_extension_id);
unset($file_extension_id);
?>
<input type="text" placeholder="text" name="search_str" value="<?=htmlentities(SEARCH_STR)?>" maxlength="50">
<button type="submit">search</button>
<p> don't forget to <?=is_writable(__FILE__) ? '<a href="?delete">delete</a>' : 'delete' ?> this script from server</p>
<details>
<summary>Advanced settings</summary>
<h5> Scan mode: 
<?php
// Режим сканирования
show_select_field('mode', MODES, $cur_mode);
unset($cur_mode);
?>
</h5>
<h5> Max depth: 
<?php
// Глубина сканирования
$depths = array();
for ($i = 1; $i <= $max_depth; $i++) {
    $depths[$i] = $i;
}
unset($i);
unset($max_depth);
show_select_field('cur_depth', $depths, DEPTH_LIMIT);
unset($depths);
?>
 folders
</h5>
</details>
</form>
<?php
if ($show_only_folders) {
    echo '<section><header>Folders:</header><slot>';
    list_recursive('.');
    echo '</slot></section>';
    echo $interrupted ? '<output>Scan time has expired!</output>' : '<p>Scan completed</p>';
} elseif (SEARCH_STR) {
    // Запрос должен содержать более 3 и менее 50 символов
    if (SEARCH_STR_LEN > 3) {
        if (SEARCH_STR_LEN < 51) {
            scan_recursive('.', SEARCH_STR);
            echo $interrupted ? '<output>Search time has expired!</output>' : '<p>Search completed</p>';
        } else {
            echo '<output>Request is too long.<br>',SEARCH_STR_LEN,' > 50</output>';
        }
    } else {
        echo '<output>Request is too short.<br>',SEARCH_STR_LEN,' < 4</output>';
    }
}
?>
</body>
</html>
