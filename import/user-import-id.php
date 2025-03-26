<?php
set_time_limit(0);
ini_set('memory_limit', '2G');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$timeout = 35;
$startTime = time();
$url = "/api/?cron=import_id&o=";

$offset = 0;
if ($_GET['o'])
{
    $offset = $_GET['o'];
}
else
{
    unlink(__DIR__ . '/import-user-ids.log');
}

while ($row = getRowCSV('CORE_User.csv', $offset))
{
    if (time() - $startTime > $timeout)
    {
        wp_redirect($url . getOffsetCSV('CORE_User.csv'));
        die();
    }

    $username = $row['MemberUserName'];
    $match = get_user_by('login', $username);
    if ( ! $match)
    {
        continue;
    }

    if ($match->import_id)
    {
        continue;
    }

    add_user_meta($match->ID, 'import_id', $row['UserID'], true);
}

$CSVs = array();
$CSVKeyCache = array();
function openCSV($filename, $offset=0)
{
    global $CSVs;

    if ( ! array_key_exists($filename, $CSVs))
    {
        $h = fopen(__DIR__ . "/$filename", 'r');
        if ($h === false)
        {
            die("Cannot open $filename\n");
        }

        $header = fgetcsv($h);
        if ($header === false)
        {
            die("$filename is empty\n");
        }

        if ($offset > 0)
        {
            fseek($h, $offset);
        }

        $CSVs[$filename] = array(
            'handle' => $h,
            'header' => $header
        );
    }
}

function getRowCSV($filename, $offset=0)
{
    global $CSVs;
    openCSV($filename, $offset);

    $row = fgetcsv($CSVs[$filename]['handle']);

    if ($row === false)
    {
        fclose($CSVs[$filename]['handle']);
        unset($CSVs[$filename]);
        return false;
    }

    $data = array();
    foreach ($CSVs[$filename]['header'] as $index => $value)
    {
        $data[$value] = trim($row[$index]);
    }

    return $data;
}

function getRowFilteredCSV($filename, $key, $value, $offset=0)
{
    global $CSVs, $CSVKeyCache;
    openCSV($filename, $offset);

    if ( ! array_key_exists($filename, $CSVKeyCache))
    {
        $CSVKeyCache[$filename] = array();
    }
    if ( ! array_key_exists($key, $CSVKeyCache[$filename]))
    {
        foreach ($CSVs[$filename]['header'] as $index => $name)
        {
            if ($key == $name)
            {
                $CSVKeyCache[$filename][$key] = $index;
                break;
            }
        }
    }

    while (true)
    {
        $row = fgetcsv($CSVs[$filename]['handle']);

        if ($row === false)
        {
            fclose($CSVs[$filename]['handle']);
            unset($CSVs[$filename]);
            return false;
        }

        if ($row[$CSVKeyCache[$filename][$key]] != $value)
        {
            continue;
        }

        $data = array();
        foreach ($CSVs[$filename]['header'] as $index => $name)
        {
            $data[$name] = trim($row[$index]);
        }

        return $data;
    }
}

function getOffsetCSV($filename)
{
    global $CSVs;

    return ftell($CSVs[$filename]['handle']);
}

function accessToMySQL($date)
{
    if ($date == '')
    {
        return NULL;
    }
    list($date, $time, $meridian) = explode(' ', $date);
    list($month, $day, $year) = explode('/', $date);
    list($hour, $min, $sec) = explode(':', $time);

    if ($hour == 12 && $meridian == 'AM')
    {
        $hour = 0;
    }
    elseif ($hour != 12 && $meridian == 'PM')
    {
        $hour += 12;
    }

    return "$year-$month-$day $hour:$min:$sec";
}

function debug($msg, $obj=NULL)
{
    print "$msg";
    if ($obj != NULL)
    {
        print '<hr>';
        var_dump($obj);
        print '<hr>';
    }
    print '<br>';
    //flush();

    $msg = str_replace('<br>', "\n", $msg);
    $msg = strip_tags($msg);
    if ($obj != NULL)
    {
        ob_start();
        var_dump($obj);
        $msg .= "\n" . ob_get_clean();
    }
    $msg .= "\n";

    file_put_contents(
        __DIR__ . '/import-new-product-rvs.log',
        $msg,
        FILE_APPEND
    );
}
