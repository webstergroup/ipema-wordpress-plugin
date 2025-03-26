<?php
set_time_limit(0);
ini_set('memory_limit', '2G');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$CSVs = array();
$CSVKeyCache = array();
$timeout = 35;
$startTime = time();

$memberOffset = 0;
if (array_key_exists('member', $_GET))
{
    $memberOffset = $_GET['member'];
}
$counter = 0;
if (array_key_exists('c', $_GET))
{
    $counter = $_GET['c'];
}
$counter++;
$url = "/about-ipema/?do=fix_bases&c=$counter&member=";

if ($memberOffset == 0)
{
    @unlink(__DIR__ . '/import.log');
    debug(date('Y-m-d H:i:s'));
    @unlink(__DIR__ . '/baseInfo.txt');
}

$lastOffset = $memberOffset;
while ($member = getRowCSV('ipema.csv', $memberOffset))
{
    $joined = strtotime($member['Joined']);
    $certified = strtotime($member['Date Certified']);
    $years = array_filter(explode(' ', $member['Membership Year']));
    sort($years, SORT_NUMERIC);
    $year = array_shift($years);
    if ( ! $year)
    {
        $year = 2015;
    }
    $creation = strtotime("$year-12-31");

    if ($joined != 0 && $certified != 0)
    {
        if ($joined < $certified)
        {
            if ($joined < $creation)
            {
                $creation = $joined;
            }
        }
        else
        {
            if ($certified < $creation)
            {
                $creation = $certified;
            }
        }
    }
    elseif ($joined != 0)
    {
        if ($joined < $creation)
        {
            $creation = $joined;
        }
    }
    elseif ($certified != 0)
    {
        if ($certified < $creation)
        {
            $creation = $certified;
        }
    }
    $creation = date('Y-m-d', $creation);

    $company = get_posts(array(
        'post_type' => 'company',
        'date_query' => array(array(
            'before' => $creation,
            'after' => $creation,
            'inclusive' => true
        )),
        'meta_query' => array(
            array(
                'key' => 'ein',
                'value' => $member['EIN'],
            ),
            array(
                'key' => 'zip',
                'value' => $member['Zip']
            )
        )
    ));

    if (count($company) == 0)
    {
        if ($member['Company'] == 'Riverside Ranch, LLC')
        {
            $company = array(get_post(54502));
        }
        else
        {
            debug('No company matched ' . $member['Company']);
            continue;
        }
    }

    if (count($company) > 1)
    {
        $company = get_posts(array(
            'post_type' => 'company',
            'title' => $member['Company']
        ));

        if (count($company) != 1)
        {
            var_dump($company);
            debug('Too many matches for ' . $member['Company']);
            continue;
        }
    }

    $company = $company[0];

    if (file_exists(__DIR__ . '/baseInfo.txt'))
    {
        extract(unserialize(file_get_contents(__DIR__ . '/baseInfo.txt')));
        unlink(__DIR__ . '/baseInfo.txt');
    }
    else
    {
        $bases = array();
        $mistakes = array();
        while ($product = getRowFilteredCSV('PROD_Product.csv', 'ManufacturerId', $member['ID']))
        {
            if ($product['BaseProductId'])
            {
                if ( ! array_key_exists($product['BaseProductId'], $bases))
                {
                    $bases[$product['BaseProductId']] = array();
                }
                $bases[$product['BaseProductId']][] = array(
                    'model' => $product['ModificationNumber'],
                    'created' => $product['CreatedDate'],
                    'modified' => $product['ModifiedDate']
                );;
            }
            else
            {
                $mistakes[$product['ProductId']] = array(
                    'ModificationNumber' => $product['ModificationNumber'],
                    'CreatedDate' => $product['CreatedDate'],
                    'ModifiedDate' => $product['ModifiedDate'],
                    'Description' => $product['Description']
                );
            }
        }
    }
    foreach ($mistakes as $id => $model)
    {
        if (time() - $startTime > $timeout + 5)
        {
            $data = serialize(array(
                'bases' => $bases,
                'mistakes' => $mistakes
            ));
            file_put_contents(__DIR__ . '/baseInfo.txt', $data);
            wp_redirect($url . $lastOffset);
            die();
        }

        if ( ! array_key_exists($id, $bases))
        {
            unset($mistakes[$id]);
            continue;
        }

        $models = get_posts(array(
            'post_type' => 'product',
            'post_status' => 'any',
            'meta_query' => array(
                array(
                    'key' => '_wpcf_belongs_company_id',
                    'value' => $company->ID
                ),
                array(
                    'key' => 'model',
                    'value' => $model['ModificationNumber']
                )
            ),
            'tax_query' => array(array(
                'taxonomy' => 'base',
                'operator' => 'EXISTS'
            ))
        ));
        if (count($models) > 0)
        {
            unset($mistakes[$id]);
            continue;
        }

        $models = get_posts(array(
            'post_type' => 'product',
            'post_status' => 'any',
            'meta_query' => array(
                array(
                    'key' => '_wpcf_belongs_company_id',
                    'value' => $company->ID
                ),
                array(
                    'key' => 'model',
                    'value' => $model['ModificationNumber']
                )
            ),
            'nopaging' => true
        ));

        if (count($models) == 0)
        {
            debug(
                "No matching product for {$company->post_title} - "
                . "{$model['ModificationNumber']} ({$company->ID})"
            );

            unset($mistakes[$id]);
            continue;
        }
        if (count($models) > 1 && $model['CreatedDate'])
        {
            foreach ($models as $key => $modelPost)
            {
                $created = accessToMySQL($model['CreatedDate']);
                if ($modelPost->post_date != $created)
                {
                    debug("Create: {$modelPost->post_date} != $created");
                    unset($models[$key]);
                    if (count($models) == 1)
                    {
                        break;
                    }
                }
            }
        }
        if (count($models) > 1)
        {
            foreach ($models as $key => $modelPost)
            {
                if ($modelPost->post_content != $model['Description'])
                {
                    debug("Description {$modelPost->post_content} != {$model['Description']}");
                    unset($models[$key]);
                    if (count($models) == 1)
                    {
                        break;
                    }
                }
            }
        }
        if (count($models) > 1 && $model['ModifiedDate'])
        {
            foreach ($models as $key => $modelPost)
            {
                $modified = accessToMySQL($model['ModifiedDate']);
                if ($modelPost->post_modified != $modified)
                {
                    debug("Modify: {$modelPost->post_modified} != $modified");
                    unset($models[$key]);
                    if (count($models) == 1)
                    {
                        break;
                    }
                }
            }
        }

        if (count($models) > 1)
        {
            debug(
                "Too many matching products for {$company->post_title} - "
                . "{$model['ModificationNumber']}",
                $models
            );
        }

        $model = reset($models);
        /*debug(
            "Need family for {$company->post_title} - {$model->model}",
            $model
        );*/

        $realBase = array();
        foreach ($bases[$id] as $sibling)
        {
            $siblings = get_posts(array(
                'post_type' => 'product',
                'post_status' => 'any',
                'meta_query' => array(
                    array(
                        'key' => '_wpcf_belongs_company_id',
                        'value' => $company->ID
                    ),
                    array(
                        'key' => 'model',
                        'value' => $sibling['model']
                    )
                ),
                'tax_query' => array(array(
                    'taxonomy' => 'base',
                    'operator' => 'EXISTS'
                ))
            ));

            foreach ($siblings as $siblingPost)
            {
                $created = accessToMySQL($sibling['created']);
                if ($created && $siblingPost->post_date != $created)
                {
                    debug("Sibling Created: {$siblingPost->post_date} ! = $created");
                    continue;
                }
                /*$modified = accessToMySQL($sibling['modified']);
                if ($modified && $siblingPost->post_modified != $modified)
                {
                    debug("Sibling Modified: {$siblingPost->post_modified} ! = $modified");
                    continue;
                }*/
                $siblingBases = get_the_terms($siblingPost->ID, 'base');
                if ($siblingBases === false)
                {
                    debug("This isn't possible");
                    continue;
                }

                foreach ($siblingBases as $base)
                {
                    if ( ! array_key_exists($base->term_id, $realBase))
                    {
                        $realBase[$base->term_id] = 0;
                    }
                    $realBase[$base->term_id]++;
                }
            }
        }

        if (count($realBase) == 1)
        {
            $realBase = array_keys($realBase);
            $newBase = $realBase[0];
        }
        elseif (count($realBase) > 1)
        {
            arsort($realBase);
            $first = reset($realBase);
            $second = next($realBase);
            if ($first > $second + 1 && $first >= $second * 1.7)
            {
                $realBase = array_keys($realBase);
                $newBase = $realBase[0];
            }
            elseif ($second == 1)
            {
                $realBase = array_keys($realBase);
                $newBase = $realBase[0];
            }
            else
            {
                debug("Could not find a base for {$model->model}", $realBase);
                unset($mistakes[$id]);
                continue;
            }
        }
        else
        {
            debug("This shouldn't be possible");
            unset($mistakes[$id]);
            continue;
        }

        wp_set_object_terms($model->ID, (int)$newBase, 'base');
        debug("New base for {$model->model} ({$model->ID}) is $newBase");

        unset($mistakes[$id]);
    }

    if (time() - $startTime > $timeout)
    {
        wp_redirect($url . getOffsetCSV('ipema.csv'));
        die();
    }

    $lastOffset = getOffsetCSV('ipema.csv');
}

debug(date('Y-m-d H:i:s'));

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
