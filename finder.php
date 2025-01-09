<?php
// GET параметр, который необходимо передать в скрипт, для его запуска
define('STARTER', 'run');


// Самоудаление скрипта через сутки
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
    Поиск подстроки в содержимом файла
*/
function find_substr($content, $filename, $needle)
{
    global $sensitive_data_files;
    $startpos = 46;
    $endpos = 126;
    $pos = 0;
    $len = strlen($needle);
    $matches = array();
    while (($pos = stripos($content, $needle, $pos)) !== false) {
        if ($pos < $startpos) {
            $startpos = $pos;
        }
        $newpos = $pos + $len;
        if (in_array($filename, $sensitive_data_files)) {
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
            while($fname = readdir($d)) {
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
    global $file_extension, $search_in_all;
    if ($search_in_all) {
        return true;
    }
    $extension_pos = strpos($file, $file_extension);
    if ($extension_pos !== false && substr($file, $extension_pos) == $file_extension) {
        return true;
    }
    return false;
}


/*
    Проверка на подходящий для чтения файл
*/
function is_correct_file($file)
{
    global $ignore_file;

    if (is_correct_extension($file)) {
        if (!in_array($file, $ignore_file)) {
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
    global $ignore_dir, $start_time, $interrupted, $scan_depth, $cur_depth;
    if ($scan_depth > $cur_depth) {
        return;
    }
    $directory = list_dir($directory);
    foreach ($directory as $filename) {
        if (is_link($filename)) {
            continue;
        } elseif (is_dir($filename)) {
            if (!in_array($filename, $ignore_dir)) {
                $scan_depth++;
                scan_recursive($filename, $search);
                $scan_depth--;
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
        if ((time() - $start_time) > 55) {
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
    global $ignore_dir, $start_time, $interrupted, $scan_depth, $cur_depth;
    if ($scan_depth > $cur_depth) {
        return;
    }
    $directory = list_dir($directory);
    echo '<ul>';
    foreach ($directory as $filename) {
        if (is_link($filename)) {
            continue;
        } elseif (is_dir($filename)) {
            if (!in_array($filename, $ignore_dir)) {
                $scan_depth++;
                echo '<li>',$filename,'</li>';
                list_recursive($filename);
                $scan_depth--;
            }
        }
        if ((time() - $start_time) > 55) {
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

// исключить из поиска директории
$ignore_dir = array(
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
);

// исключить из поиска файлы
$ignore_file = array(
    "./finder.php",
);

// Скрывать содержимое файла
$sensitive_data_files = array(
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
);

// Доступные для выбора расширения файлов
$file_extensions = array(
    '.php',
    '.js',
    '.css',
    '.html',
    '.tpl',
    '.twig',
    'all',
);

// Максимальная глубина вложенности
$max_depth = 12;

// Выбранная глубина вложенности
$cur_depth = $max_depth;

$scan_depth = 1;

// Выбор глубины вложенности через форму
if (isset($_POST['cur_depth'])) {
    $selected = (int) $_POST['cur_depth'];
    if (($selected > 0) && ($selected < $max_depth)) {
        $cur_depth = $selected;
    }
    unset($selected);
}

// Отобразить отсканированные директории без чтения файлов
$show_only_folders = false;

if (isset($_POST['show_only_folders']) && $_POST['show_only_folders'] == '1') {
    $show_only_folders = true;
}

// запуск таймера, чтобы скрипт не сканировал больше минуты
$start_time = time();

// искомая строка
$search_str = '';
if (!empty($_POST['search_str'])) {
    $search_str = $_POST['search_str'];
}

// При старте, выбирается первое расширение из списка
$file_extension = $file_extensions[0];

// Флаг поиска во всех файлах
$search_in_all = false;

// Флаг, прерван ли поиск из-за таймаута
$interrupted = false;

// Выбор расширения файла через форму
if (isset($_POST['file_extension'])) {
    $selected_id = (int) $_POST['file_extension'];
    if (($selected_id > 0) && array_key_exists($selected_id, $file_extensions)) {
        $file_extension = $file_extensions[$selected_id];
        if ($file_extensions[$selected_id] === 'all') {
            $search_in_all = true;
        }
    }
    unset($selected_id);
}

// Защита от межсайтового скриптинга
header('Content-Security-Policy: default-src \'self\'; style-src \'self\' \'unsafe-inline\'; script-src \'self\'; connect-src \'self\'');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

// Установка рекомендуемого лимита оперативной памяти и времени выполнения
ini_set('memory_limit', '1G');
ini_set('max_execution_time', '60');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8"><title>finder</title><meta name="robots" content="noindex, nofollow"/>
<style>*,:after,:before{box-sizing:inherit}html{background:#424146;font-family:sans-serif;box-sizing:border-box}body{background:#bab6b5;padding:15px;border-radius:3px;max-width:800px;margin:10px auto 60px}form,p,output{text-align:center;font-size:small;user-select:none}section{margin-top:30px;padding:10px;background:#f1f1f1;border-radius:3px}header{font-size:small;overflow-wrap:break-word;font-weight:700}code{width:100%;display:block;background:#d4d9dd;padding:5px;border-radius:3px;margin-top:10px;overflow-wrap:break-word}code b{color:red}details{margin-top:1em}slot{font-size:smaller;overflow-wrap:break-word}ul{padding-left:1em}output{background:#ff4b4b;color:#fff;padding:15px;margin:15px;border-radius:3px;display:block}
</style>
</head>
<body>
<form method="POST">
in 
<select name="file_extension">
<?php
// Поле выбора доступных расширений файла
foreach ($file_extensions as $key => $extension) {
    echo '<option value="',$key,'"';
    if ($file_extension == $extension) {
        echo ' selected';
    }
    echo '>',$extension,'</option>';
}
unset($file_extensions);
?>
</select>
<input type="text" placeholder="text" name="search_str" value="<?=htmlentities($search_str)?>" maxlength="50">
<button type="submit">search</button>
<p> don't forget to <?=is_writable(__FILE__) ? '<a href="?delete">delete</a>' : 'delete' ?> this script from server</p>
<details>
<summary>Advanced settings</summary>
<h5> Max depth: 
<select name="cur_depth">
<?php
// Поле выбора доступных расширений файла
for ($i = 1; $i <= $max_depth; $i++) {
    echo '<option value="',$i,'"';
    if ($cur_depth == $i) {
        echo ' selected';
    }
    echo '>',$i,'</option>';
}
unset($i);
unset($max_depth);
?>
</select> folders
</h5>
<h5>Show only folder names: 
<input type="checkbox" name="show_only_folders" value="1" <?php if ($show_only_folders){echo 'checked';} ?>>
</h5>
</details>
</form>
<?php
if ($show_only_folders) {
    echo '<section><header>Folders:</header><slot>';
    list_recursive('.');
    echo '</slot></section>';
    if ($interrupted) {
        echo '<output>Scan time has expired!</output>';
    } else {
        echo '<p>Scan completed</p>';
    }
}else if ($search_str) {
    // Запрос должен содержать более 3 и менее 50 символов
    $search_str_len = strlen($search_str);
    if ($search_str_len > 3) {
        if ($search_str_len < 51) {
            unset($search_str_len);
            scan_recursive('.', $search_str);
            if ($interrupted) {
                echo '<output>Search time has expired!</output>';
            } else {
                echo '<p>Search completed</p>';
            }
        } else {
            echo '<output>Request is too long.<br>',$search_str_len,' > 50</output>';
        }
    } else {
        echo '<output>Request is too short.<br>',$search_str_len,' < 4</output>';
    }
}
?>
</body>
</html>
