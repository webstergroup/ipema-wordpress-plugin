 <?php
set_time_limit(0);
ini_set('memory_limit', '2G');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$timeout = 35;
$startTime = time();
$url = "/about-ipema/?do=fix_rvs&o=";


$offset = 0;
if ($_GET['o'])
{
    $offset = $_GET['o'];
}
else
{
    unlink(__DIR__ . '/import.log');
}

$notTests = array('supplemental', 'obsolete');

while ($row = getRowCSV('RV_Form.csv', $offset))
{
    if (time() - $startTime > $timeout)
    {
        wp_redirect($url . getOffsetCSV('RV_Form.csv'));
        die();
    }
    $rv = get_posts(array(
        'post_type' => 'rv',
        'post_status' => 'any',
        'meta_query' => array(array(
            'key' => 'public_id',
            'value' => $row['ID']
        ))
    ));

    if (count($rv) != 1)
    {
        continue;
    }

    $rv = $rv[0];
    delete_post_meta($rv->ID, 'form_id');
    delete_post_meta($rv->ID, 'legacy_action');
    delete_post_meta($rv->ID, 'affected_id');

    //debug('RV ID: ' . $row['ID']);
    //debug('WP ID: ' . $rv->ID);
    //debug('Form ID: ' . $row['FormID']);
    //debug('Legacy Action: ' . $row['ActionName']);
    add_post_meta($rv->ID, 'form_id', $row['FormID']);
    add_post_meta($rv->ID, 'legacy_action', $row['ActionName']);
    add_post_meta($rv->ID, 'guess', true);
    if ( ! in_array(strtolower($row['ActionName']), $notTests))
    {
        //debug('Set as Test Request');
        wp_set_post_terms($rv->ID, 'test', 'request');
    }

    $product = get_post_meta($rv->ID, '_wpcf_belongs_product_id', true);
    $base = get_the_terms($product, 'base');
    if ($base == false)
    {
        //debug('Affected: ' . $product);
        add_post_meta($rv->ID, 'affected_id', $product);
        continue;
    }

    $products = get_posts(array(
        'post_type' => 'product',
        'post_status' => 'any',
        'nopaging' => true,
        'tax_query' => array(array(
            'taxonomy' => 'base',
            'terms' => $base[0]->term_id
        ))
    ));
    foreach ($products as $product)
    {
        //debug('Affected: ' . $product->ID);
        add_post_meta($rv->ID, 'affected_id', $product->ID);
    }
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
