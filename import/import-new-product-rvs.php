 <?php
set_time_limit(0);
ini_set('memory_limit', '2G');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$timeout = 35;
$startTime = time();
$url = "/api/?cron=new_product&o=";


$offset = 0;
if ($_GET['o'])
{
    $offset = $_GET['o'];
}
else
{
    unlink(__DIR__ . '/import-new-product-rvs.log');
}

$notTests = array('supplemental', 'obsolete');

while ($row = getRowCSV('RV_Form.csv', $offset))
{
    if (time() - $startTime > $timeout)
    {
        wp_redirect($url . getOffsetCSV('RV_Form.csv'));
        die();
    }

    $isNewProductRV = in_array(strtolower($row['ActionName']), array(
        'new product',
        'new modified product',
        'new product w/ modification',
    ));
    if (! $isNewProductRV)
    {
        continue;
    }

    $rv = get_posts(array(
        'post_type' => 'rv',
        'post_status' => 'any',
        'meta_query' => array(array(
            'key' => 'public_id',
            'value' => $row['ID']
        ))
    ));

    if (count($rv) > 0)
    {
        continue;
    }

    $model_number = $row['ModelNumber'];
    if ($row['ModificationNumber'])
    {
        $model_number = $row['ModificationNumber'];
    }

    $products = get_posts(array(
        'post_type' => 'product',
        'post_status' => 'any',
        'meta_query' => array(array(
            'key' => 'model',
            'value' => $model_number
        ))
    ));

    if (count($products) == 0)
    {
        debug("No match for model #$model_number");
        continue;
    }

    $manufacturers = array();
    foreach ($products as $product)
    {
        $manufacturers[] = $product->_wpcf_belongs_company_id;
    }
    $manufacturers = array_unique($manufacturers);
    if (count($manufacturers) > 1)
    {
        debug("Multiple manufacturers found for model #$model_number");
        continue;
    }

    $admins = get_users(array(
        'meta_query' => array(array(
            'key' => 'import_id',
            'value' => $row['UserId'],
        ))
    ));

    if (count($admins) > 0)
    {
        $adminID = $admins[0]->ID;
    }
    else
    {
        $adminID = 19; // Unknown User
    }

    $created = accessToMySQL($row['DateSubmitted']);
    $one_year = date('Y-m-d H:i:s', strtotime('+1 year', strtotime($created)));
    $rvData = array(
        'post_type' => 'rv',
        'post_title' => $products[0]->model,
        'post_content' => $row['Message'],
        'post_excerpt' => $row['RVNotes'],
        'post_author' => $adminID,
        'post_date' => $created
    );

    $rv_meta = array(
        '_wpcf_belongs_product_id' => $products[0]->ID,
        'public_id' => $row['ID'],
        'guess' => true,
        'form_id' => $row['FormID'],
        'legacy_action' => $row['ActionName']
    );

    if ($row['FormStatus'] == 'Processed')
    {
        $rvData['post_status'] = 'publish';
        $rvData['post_modified'] = accessToMySQL(
            $row['ApprovedDate']
        );
        if ($row['Denied'] == 1)
        {
            $rv_meta['status'] = 'rejected';
        }
        else
        {
            $rv_meta['status'] = 'approved';
        }

        if ( ! array_key_exists($row['ApprovedBy'], $reviewers))
        {
            $users = get_users(array(
                'meta_key' => 'import_id',
                'meta_value' => $row['ApprovedBy']
            ));
            if (count($users) != 1)
            {
                debug("Unknown reviewer {$row['ApprovedBy']}");
                $reviewers[$row['ApprovedBy']] = 19;
            }
            else
            {
                $reviewers[$row['ApprovedBy']] = $users[0]->id;
            }
        }

        $rv_meta['reviewer'] = $reviewers[$row['ApprovedBy']];
    }

    $rvData['meta_input'] = $rv_meta;
    $rvID = wp_insert_post($rvData);
    debug("Created RV {$row['ID']}");

    foreach ($products as $product)
    {
        $base = get_the_terms($product, 'base');
        if ($base == false)
        {
            //debug('Affected: ' . $product);
            add_post_meta($rvID, 'affected_id', $product->ID);
            continue;
        }

        $family_products = get_posts(array(
            'post_type' => 'product',
            'post_status' => 'any',
            'nopaging' => true,
            'date_query' => array(
                'before' => $one_year,
            ),
            'tax_query' => array(array(
                'taxonomy' => 'base',
                'terms' => $base[0]->term_id
            ))
        ));
        foreach ($family_products as $product)
        {
            //debug('Affected: ' . $product->ID);
            add_post_meta($rvID, 'affected_id', $product->ID);
        }
    }

    if ($row['ASTMF1487'])
    {
        wp_set_object_terms($rvID, 'astm-f1487-11', 'certification', $append=true);
    }
    if ($row['CSAZ614'])
    {
        wp_set_object_terms($rvID, 'z-614-14', 'certification', $append=true);
    }
    if ($row['ASTMF1292'])
    {
        wp_set_object_terms($rvID, 'astm-f1292-13', 'certification', $append=true);
    }
    if ($row['ASTMF2075'])
    {
        wp_set_object_terms($rvID, 'astm-f2075-15', 'certification', $append=true);
    }
    if ($row['ASTMF3012'])
    {
        wp_set_object_terms($rvID, 'astm-f3012-14', 'certification', $append=true);
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
