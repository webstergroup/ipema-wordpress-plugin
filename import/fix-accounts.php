<?php
set_time_limit(0);
ini_set('memory_limit', '2G');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$h = fopen(__DIR__ . "/wp_usermeta.csv", 'r');
if ($h === false)
{
    die("Cannot open CSV\n");
}

while ($row = fgetcsv($h))
{
    if (strpos($row[3], 'administrator') !== false)
    {
        continue;
    }

    $user = get_user_by('id', $row[1]);
    if ( ! $user)
    {
        continue;
    }
    if ( ! $user->company_id)
    {
        continue;
    }

    if (strpos($row[3], 'can_manage_products') !== false)
    {
        $user->add_cap('can_manage_products');
    }
    if (strpos($row[3], 'can_manage_account') !== false)
    {
        $user->add_cap('can_manage_account');
    }
}

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
        __DIR__ . '/import.log',
        $msg,
        FILE_APPEND
    );
}
