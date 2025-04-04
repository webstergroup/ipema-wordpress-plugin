<?php
set_time_limit(0);
ini_set('memory_limit', '2G');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
while (@ob_end_clean()) {}
$timeout = 35;
require_once(ABSPATH.'wp-admin/includes/user.php' );

if ($_SERVER['REQUEST_METHOD'] != 'POST')
{
    ?>
    <form method="post">
        <input type="hidden" name="action" value="start">
        <input type="submit" value="Fix">
    </form>
    <?php
    die();
}

$startTime = time();
$start = @file_get_contents(__DIR__ . '/bookmark.txt');
if ($start === FALSE)
{
    $start = 0;
}

if ($start == 0)
{
    @unlink(__DIR__ . '/import.log');
    debug(date('Y-m-d H:i:s'));
}


$members = readCSV('fix.csv');
$total = count($members);
$members = array_slice($members, $start);

if ($start > 0 && $_POST['action'] == 'start')
{
    debug('Resuming a broken import...');
    foreach ($members as $member)
    {
        $duplicates = get_posts(array(
            'title' => $member['Company'],
            'post_type' => 'company'
        ));

        if (count($duplicates) == 0)
        {
            break;
        }

        debug("Deleting {$member['Company']}");

        foreach ($duplicates as $company)
        {
            $products = get_posts(array(
                'post_type' => 'product',
                'post_status' => 'any',
                'nopaging' => true,
                'meta_key' => '_wpcf_belongs_company_id',
                'meta_value' => $company->ID
            ));

            debug('Found ' . count($products) . ' products');
            foreach ($products as $product)
            {
                if ((time() - $startTime) > $timeout)
                {
                    ?>
                    <form method="post">
                        <input type="hidden" name="action" value="start">
                        <input type="submit" value="Continue Fix">
                    </form>
                    <?php if ( ! array_key_exists('single', $_GET) || $_GET['single'] !== 'forced') : ?>
                    <script type="text/javascript">
                        var forms = document.getElementsByTagName('form');
                        console.log(forms);
                        for (var i in forms)
                        {
                            forms[i].submit();
                            var fields = forms[i].getElementsByTagName('input');
                            for (var j in fields)
                            {
                                fields[j].disabled = true;
                            }
                        }
                    </script>
                    <?php endif; ?>
                    <?php
                    die();
                }
                $rvs = get_posts(array(
                    'post_type' => 'rv',
                    'post_status' => 'any',
                    'nopaging' => true,
                    'meta_key' => '_wpcf_belongs_product_id',
                    'meta_value' => $product->ID
                ));

                foreach ($rvs as $rv)
                {
                    wp_delete_post($rv->ID, true);
                }

                wp_delete_post($product->ID, true);
            }

            $terms = get_terms(array(
                'taxonomy' => array('product-line', 'base'),
                'hide_empty' => false
            ));

            foreach ($terms as $term)
            {
                if (strpos("{$company->ID}-", $term->slug) === 0)
                {
                    wp_delete_term($term->term_id, $term->taxonomy);
                }
            }
        }
    }
}

$products = readCSV('PROD_Product.csv');
$totalProducts = count($products);
$productLineRows = readCSV('PROD_ProductLine.csv');

$bases = array();
foreach ($products as $product)
{
    $bases[$product['ProductId']] = false;
    if ($product['BaseProductId'])
    {
        $bases[$product['BaseProductId']] = true;
    }
}

$productLines = array();
foreach ($productLineRows as $row)
{
    $productLines[$row['ProductLineId']] = $row;
}

$rvID = get_option('rv_autoincrement');
$reviewers = array();

print count($members) . ' companies remaining...<br>';

$doSkip = false;
$product = @file_get_contents(__DIR__ . '/product.txt');
if ($product !== false)
{
    extract(unserialize($product));
    unlink(__DIR__ . '/product.txt');
}
foreach ($members as $member)
{
    if ((time() - $startTime) > $timeout)
    {
        debug('Company timeout');
        break;
    }

    if ($doSkip)
    {
        $doSkip = false;
        print "Resuming {$member['Company']} product import...<br>";
        flush();
        goto load_products;
    }
    debug("Importing {$member['Company']}");

    $duplicates = get_posts(array(
        'title' => $member['Company'],
        'post_type' => 'company'
    ));
    if (count($duplicates) == 0)
    {
        die("{$member['Company']} does not exist");
    }
    $companyID = $duplicates[0]->ID;
    print "Found company ID $companyID<br>";

    $qry = new WP_User_Query(array(
        'meta_query' => array(array(
            'company_id' => $companyID
        ))
    ));
    $users = $qry->results;
    if (count($users) == 0)
    {
        debug("No users for {$member['Company']}");
        $adminID = 19; // Unknown User
    }
    else
    {
        $adminID = $users[0]->id;
    }

    $companyLines = array();
    $companyBases = array();
    $i = 0;
    $equipmentCount = 0;
    $surfacingCount = 0;

    load_products:
    $productCount = 0;

    $companyRVs = getRVs($member['ID']);
    while ($i < $totalProducts)
    {
        if ($products[$i]['ManufacturerId'] != $member['ID'])
        {
            goto continue_products;
        }
        $product = $products[$i];
        $productCount++;

        if (@count($companyRVs[$product['ProductId']]) > 10 && $productCount > 1)
        {
            $timeout = 0;
            $i--;
            goto continue_products;
        }

        if ( ! array_key_exists($product['ProductLineId'], $companyLines))
        {
            $productLine = $productLines[$product['ProductLineId']];

            $description = $productLine['ProductLineDescription'];
            if ($description == $productLine['ProductLineName'])
            {
                $description = '';
            }

            $slug = "$companyID-" . sanitize_title($productLine['ProductLineName']);
            $lineInfo = wp_insert_term(
                $productLine['ProductLineName'],
                'product-line',
                array(
                    'description' => $description,
                    'slug' => $slug
                )
            );

            if (is_wp_error($lineInfo))
            {
                if ($lineInfo->get_error_code() == 'empty_term_name')
                {
                    $companyLines[$product['ProductLineId']] = array();
                }
                else
                {
                    debug(
                        'Problem creating product line '
                        . $productLine['ProductLineName'] . ': '
                        . $lineInfo->get_error_message(),
                        $productLine
                    );

                    goto continue_products;
                }
            }
            else
            {
                $companyLines[$product['ProductLineId']] = array(
                    $lineInfo['term_id']
                );
            }
        }

        $tax_input = array(
            'product-line' => $companyLines[$product['ProductLineId']]
        );
        $meta_input = array(
            'model' => $product['ModificationNumber'],
            '_wpcf_belongs_company_id' => $companyID
        );

        if ($product['RetestDate'])
        {
            $retest = accessToMySQL($product['RetestDate']);
            $meta_input['retest_year'] = substr($retest, 0, 4);
        }

        if ($bases[$product['ProductId']])
        {
            $product['BaseProductId'] = $product['ProductId'];
        }
        if ($product['BaseProductId'] && ! array_key_exists($product['BaseProductId'], $companyBases))
        {

            $slug = "$companyID-" . ipema_generate_code(4);
            while (get_term_by('slug', $slug, 'base') !== false)
            {
                $slug = "$companyID-" . ipema_generate_code(4);
            }
            $baseInfo = wp_insert_term('~Unnamed~', 'base', array(
                'slug' => $slug,
                'description' => ''
            ));

            if (is_wp_error($baseInfo))
            {
                debug(
                    "Problem creating base model {$product['BaseProductId']}: "
                    . $baseInfo->get_error_message()
                );
            }
            else
            {
                $companyBases[$product['BaseProductId']] = array($baseInfo['term_id']);
            }
        }
        if ($product['BaseProductId'])
        {
            $tax_input['base'] = $companyBases[$product['BaseProductId']];
        }

        $created = accessToMySQL($product['CreatedDate']);
        $modified = accessToMySQL($product['ModifiedDate']);

        $status = 'draft';
        $equipmentCerts = 0;
        $surfacingCerts = 0;
        $certs = array();
        if ($product['F1292_Certified'] == 1)
        {
            $surfacingCerts++;
            $certs[] = 'astm-f1292-13';
        }
        if ($product['F1487_Certified'])
        {
            $equipmentCerts++;
            $certs[] = 'astm-f1487-11';
        }
        if ($product['CSA_Z614_Certified'])
        {
            $equipmentCerts++;
            $certs[] = 'z-614-14';
        }
        if ($product['F2075_Certified'])
        {
            $surfacingCerts++;
            $certs[] = 'astm-f2075-15';
        }
        if ($product['F3012_Certified'])
        {
            $surfacingCerts++;
            $certs[] = 'astm-f3012-14';
        }

        $tax_input['certification'] = $certs;

        $expires = accessToMySQL($product['ExpirationDate']);
        if (time() < strtotime($expires))
        {
            $status = 'publish';
        }
        foreach ($certs as $cert)
        {
            $meta_input[$cert] = $expires;
        }

        if ($product['FrenchDescription'] && in_array('z-614-14', $certs))
        {
            $meta_input['french-description'] = $product['FrenchDescription'];
        }

        if ($product['DeletedFlag'] == 1)
        {
            $meta_input['obsolete'] = true;
        }

        $productID = wp_insert_post(array(
            'post_type' => 'product',
            'post_title' => $product['BrandName'],
            'post_content' => $product['Description'],
            'post_date' => $created,
            'post_modified' => $modified,
            'post_author' => $adminID,
            'post_status' => $status,
            'tax_input' => $tax_input,
            'meta_input' => $meta_input
        ));

        if (array_key_exists($product['ProductId'], $companyRVs))
        {
            foreach ($companyRVs[$product['ProductId']] as $rv)
            {
                print ' ';
                flush();
                $created = accessToMySQL($rv['DateSubmitted']);
                $rvData = array(
                    'post_type' => 'rv',
                    'post_title' => $meta_input['model'],
                    'post_content' => $rv['Message'],
                    'post_excerpt' => $rv['RVNotes'],
                    'post_author' => $adminID,
                    'post_date' => $created
                );

                $rv_meta = array(
                    '_wpcf_belongs_product_id' => $productID,
                    'public_id' => $rv['ID']
                );

                if ($rv['ID'] > $rvID)
                {
                    $rvID = $rv['ID'];
                    update_option('rv_autoincrement', $rvID);
                }

                if ($rv['FormStatus'] == 'Processed')
                {
                    $rvData['post_status'] = 'publish';
                    $rvData['post_modified'] = accessToMySQL(
                        $rv['ApprovedDate']
                    );
                    if ($rv['Denied'] == 1)
                    {
                        $rv_meta['status'] = 'rejected';
                    }
                    else
                    {
                        $rv_meta['status'] = 'approved';
                    }

                    if ( ! array_key_exists($rv['ApprovedBy'], $reviewers))
                    {
                        $users = get_users(array(
                            'meta_key' => 'import_id',
                            'meta_value' => $rv['ApprovedBy']
                        ));
                        if (count($users) != 1)
                        {
                            debug("Unknown reviewer {$rv['ApprovedBy']}");
                            $reviewers[$rv['ApprovedBy']] = 19;
                        }
                        else
                        {
                            $reviewers[$rv['ApprovedBy']] = $users[0]->id;
                        }
                    }

                    $rv_meta['reviewer'] = $reviewers[$rv['ApprovedBy']];
                }

                $certs = array();
                if ($rv['ASTMF1487'])
                {
                    $certs[] = 'astm-f1487-11';
                    $equipmentCerts++;
                }
                if ($rv['CSAZ614'])
                {
                    $certs[] = 'z-614-14';
                    $equipmentCerts++;
                }
                if ($rv['ASTMF1292'])
                {
                    $certs[] = 'astm-f1292-13';
                    $surfacingCerts++;
                }
                if ($rv['ASTMF2075'])
                {
                    $certs[] = 'astm-f2075-15';
                    $surfacingCerts++;
                }
                if ($rv['ASTMF3012'])
                {
                    $certs[] = 'astm-f3012-14';
                    $surfacingCerts++;
                }

                $rvData['tax_input'] = array(
                    'certification' => $certs
                );
                $rvData['meta_input'] = $rv_meta;

                wp_insert_post($rvData);
            }
        }
        else
        {
            //debug("No RVs for {$product['ModificationNumber']}");
        }

        if ($surfacingCerts == 0 && $equipmentCerts == 0)
        {
            debug('Product has no certifications', $product);
        }
        elseif ($surfacingCerts != 0 && $equipmentCerts != 0)
        {
            debug(
                'Product has both equipment and surfacing certifications',
                $product
            );
        }
        elseif ($equipmentCerts > 0)
        {
            wp_set_object_terms($productID, 'equipment', 'product-type');
            if ($status == 'publish' && $product['DeletedFlag'] != 1)
            {
                $equipmentCount++;
            }
        }
        else
        {
            wp_set_object_terms($productID, 'surfacing', 'product-type');

            if ($product['ThkHtRatio'])
            {
                update_post_meta(
                    $productID,
                    'thickness_to_height',
                    convertThkToHeight($product['ThkHtRatio'])
                );
            }
            else
            {
                update_post_meta(
                    $productID,
                    'thickness_to_height',
                    'Unknown:Unknown'
                );
                debug('Found surfacing without ThkToHt Ratio', $product);
            }
            if ($status == 'publish' && $product['DeletedFlag'] != 1)
            {
                $surfacingCount++;
            }
        }

        continue_products:
        $i++;

        if ((time() - $startTime) > $timeout)
        {
            file_put_contents(
                __DIR__ . '/product.txt',
                serialize(array(
                    'companyID' => $companyID,
                    'i' => $i,
                    'companyBases' => $companyBases,
                    'companyLines' => $companyLines,
                    'adminID' => $adminID,
                    'doSkip' => true,
                    'equipmentCount' => $equipmentCount,
                    'surfacingCount' => $surfacingCount
                ))
            );

            print 'Interrupted product import due to timeout<br>';
            print "Imported $productCount products<br>";
            print 'Elapsed Time: ' . (time() - $startTime) . ' seconds';
            print '<br><br>';

            break 2;
        }

    }

    if ($equipmentCount > 0)
    {
        update_post_meta(
            $companyID,
            'equipment_retest_goal',
            ceil($equipmentCount / 5)
        );
    }
    if ($surfacingCount > 0)
    {
        update_post_meta(
            $companyID,
            'surfacing_retest_goal',
            ceil($surfacingCount / 5)
        );
    }

    print "Imported $productCount products<br>";
    print 'Elapsed Time: ' . (time() - $startTime) . ' seconds';
    print '<br><br>';

    $start++;
}

print "<p>Imported $start total companies...</p>";

file_put_contents(__DIR__ . '/bookmark.txt', $start);

if ($start < $total)
{
    ?>
    <form method="post">
        <input type="hidden" name="action" value="continue">
        <input type="submit" value="Continue Import">
    </form>
    <?php if ( ! array_key_exists('single', $_GET) || $_GET['single'] !== 'forced') : ?>
    <script type="text/javascript">
        var forms = document.getElementsByTagName('form');
        for (var i in forms)
        {
            forms[i].submit();
            var fields = forms[i].getElementsByTagName('input');
            for (var j in fields)
            {
                fields[j].disabled = true;
            }
        }
    </script>
    <?php endif; ?>
    <?php
    die();
}

$rvLookup = array();
$h = fopen(__DIR__ . '/RV_Documents.csv', 'r');
if ($h === false)
{
    die("Cannot open RV_Documents.csv\n");
}

$header = fgetcsv($h);
if ($header === false)
{
    die("RV_Documents.csv is empty\n");
}

$start = file_get_contents(__DIR__ . '/rvs.txt');
if ($start === false)
{
    $start = 0;
}
$counter = 0;

$fileKey = array_search('DocumentLocation', $header);
$rvKey = array_search('RV_ID', $header);

while (($row = fgetcsv($h)) !== false)
{
    $counter++;
    if ($counter <= $start)
    {
        continue;
    }

    if (time() - $startTime > $timeout)
    {
        file_put_contents(__DIR__ . '/rvs.txt', $counter - 1);
        ?>
        <form method="post">
            <input type="hidden" name="action" value="continue">
            <input type="submit" value="Continue Import">
        </form>
        <?php if ( ! array_key_exists('single', $_GET) || $_GET['single'] !== 'forced') : ?>
        <script type="text/javascript">
            var forms = document.getElementsByTagName('form');
            console.log(forms);
            for (var i in forms)
            {
                forms[i].submit();
                var fields = forms[i].getElementsByTagName('input');
                for (var j in fields)
                {
                    fields[j].disabled = true;
                }
            }
        </script>
        <?php endif; ?>
        <?php
        die();
    }

    if ( ! array_key_exists($row[$rvKey], $rvLookup))
    {
        $match = get_posts(array(
            'post_type' => 'rv',
            'meta_key' => 'public_id',
            'meta_value' => $row[$rvKey],
            'post_status' => 'any'
        ));

        if (count($match) == 1)
        {
            $rvLookup[$row[$rvKey]] = $match[0]->ID;
        }
        else
        {
            $rvLookup[$row[$rvKey]] = NULL;
        }
    }

    if ($rvLookup[$row[$rvKey]])
    {
        $filename = rawurlencode($row[$fileKey]);
        add_post_meta(
            $rvLookup[$row[$rvKey]],
            'documentation',
            "/wp-content/uploads/import/$filename"
        );
    }
}

fclose($h);

wp_cache_init();
unlink(__DIR__ . '/bookmark.txt');

debug(date('Y-m-d H:i:s'));


function readCSV($filename)
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

    $result = array();
    while (($row = fgetcsv($h)) !== false)
    {
        $data = array();
        foreach ($header as $key => $value)
        {
            $data[$value] = trim($row[$key]);
        }

        $result[] = $data;
    }

    fclose($h);

    return $result;
}

function getRVs($manufacturerID)
{
    $h = fopen(__DIR__ . '/RV_Form.csv', 'r');
    if ($h === false)
    {
        die("Cannot open RV_Form.csv\n");
    }

    $header = fgetcsv($h);
    if ($header === false)
    {
        die("RV_Form.csv is empty\n");
    }

    $idKey = array_search('ManufacturerID', $header);
    $prodKey = array_search('ProductID', $header);
    $baseKey = array_search('BaseProductID', $header);

    $result = array();
    $rvCount = 0;
    while(($row = fgetcsv($h)) !== false)
    {
        if ($row[$idKey] != $manufacturerID)
        {
            continue;
        }

        $activeKey = $row[$prodKey];
        if ( ! $activeKey)
        {
            $activeKey = $row[$baseKey];
        }
        if ( ! $activeKey)
        {
            continue;
        }

        $data = array();
        foreach ($header as $key => $value)
        {
            $data[$value] = trim($row[$key]);
        }

        if ( ! array_key_exists($activeKey, $result))
        {
            $result[$activeKey] = array();
        }
        $result[$activeKey][] = $data;
        $rvCount++;
    }

    fclose($h);

    return $result;
}

function import_user($info, &$users)
{
    if ($info['user_email'] == '')
    {
        return;
    }
    if ($info['user_login'] == '')
    {
        if (count($users) == 0)
        {
            print "No username for {$info['user_email']}<br>";
            return;
        }
        $info['user_login'] = $users[0]['user_login'] . '-' . count($users);
    }
    if ($info['user_pass'] == '')
    {
        $info['user_pass'] = base64_encode(openssl_random_pseudo_bytes(30));
    }

    $info['id'] = wp_insert_user($info);

    if (is_wp_error($info['id']))
    {
        debug(
            "Problem importing user {$info['user_email']}: "
            . $info['id']->get_error_message()
        );

        return;
    }

    $users[] = $info;
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

function convertThkToHeight($old)
{
    $old = str_replace('\"', '"', $old);
    $number = '(\.?\d+)(\s+(\d+)\/(\d+)|(\.\d+))?';
    $inches = '(in(ch(es)?)?\.?|"|\'\')';
    $ft = '(ft\.?|f(ee|oo)t\.?|\')';
    $sep = '(@|\/|x|-)';
    if (preg_match(
        "/^$number\s*$inches?\s*$sep\s*$number\s*$ft?(\s*\w+)?$/i",
        $old,
        $matches
    ))
    {
        $thickness = $matches[1];
        if ($matches[3])
        {
            $thickness += $matches[3] / $matches[4];
        }
        elseif ($matches[5])
        {
            $thickness += $matches[5];
        }

        $height = $matches[10];
        if ($matches[12])
        {
            $height += $matches[12] / $matches[13];
        }
        elseif ($matches[14])
        {
            $height += $matches[14];
        }

        print "Changed $old to $thickness:$height<br>";
        return "$thickness:$height";
    }
    elseif (preg_match(
        "/^$number\s*mm\s*$sep\s*$number\s*$ft?$/i",
        $old,
        $matches
    ))
    {
        $thickness = $matches[1];
        if ($matches[3])
        {
            $thickness += $matches[3] / $matches[4];
        }
        elseif ($matches[5])
        {
            $thickness += $matches[5];
        }

        $thickness *= 0.0393701;
        $thickness = round($thickness, 2);

        $height = $matches[7];
        if ($matches[9])
        {
            $height += $matches[9] / $matches[10];
        }
        elseif ($matches[11])
        {
            $height += $matches[11];
        }

        print "Changed $old to $thickness:$height<br>";
        return "$thickness:$height";
    }
    elseif (preg_match(
        "/^$number\s*$inches\s*$number\s*$ft?$/i",
        $old,
        $matches
    ))
    {
        $thickness = $matches[1];
        if ($matches[3])
        {
            $thickness += $matches[3] / $matches[4];
        }
        elseif ($matches[5])
        {
            $thickness += $matches[5];
        }

        $height = $matches[9];
        if ($matches[11])
        {
            $height += $matches[11] / $matches[12];
        }
        elseif ($matches[13])
        {
            $height += $matches[13];
        }

        print "Changed $old to $thickness:$height<br>";
        return "$thickness:$height";
    }
    elseif (preg_match(
        "/^$number\s*$inches\s*\w+\s*,\s*$number\s*$ft\s*\w+$/i",
        $old,
        $matches
    ))
    {
        $thickness = $matches[1];
        if ($matches[3])
        {
            $thickness += $matches[3] / $matches[4];
        }
        elseif ($matches[5])
        {
            $thickness += $matches[5];
        }

        $height = $matches[9];
        if ($matches[11])
        {
            $height += $matches[11] / $matches[12];
        }
        elseif ($matches[13])
        {
            $height += $matches[13];
        }

        print "Changed $old to $thickness:$height<br>";
        return "$thickness:$height";
    }
    elseif (preg_match(
        "/^$number\s*$ft\s*-\s*$number\s*$inches\s*\w+$/i",
        $old,
        $matches
    ))
    {
        $height = $matches[1];
        if ($matches[3])
        {
            $height += $matches[3] / $matches[4];
        }
        elseif ($matches[5])
        {
            $height += $matches[5];
        }

        $thickness = $matches[7];
        if ($matches[9])
        {
            $thickness += $matches[9] / $matches[10];
        }
        elseif ($matches[11])
        {
            $thickness += $matches[11];
        }

        print "Changed $old to $thickness:$height<br>";
        return "$thickness:$height";
    }
    elseif (preg_match(
        "/^(\d+,\d+)\s*$inches\s*$sep\s*$number\s*$ft?$/i",
        $old,
        $matches
    ))
    {
        $thickness += str_replace(',', '.', $matches[1]);

        $height = $matches[6];
        if ($matches[8])
        {
            $height += $matches[8] / $matches[9];
        }
        elseif ($matches[10])
        {
            $height += $matches[10];
        }

        print "Changed $old to $thickness:$height<br>";
        return "$thickness:$height";
    }

    debug("Unknown ThkToHeight: $old");
    return 'Unknown:Unknown';
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
    flush();

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
