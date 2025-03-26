<?php
$output = fopen(__DIR__ . "/odd-expirations.csv", 'a');
if ( ! $output)
{
    die('Could not create spreadsheet file');
}
/*
$header = array(
    'Manufacturer',
    'Model Number',
    'Name',
    'Product Line',
    'Created',
    'Certification',
    'Tested',
    'RV',
    'Expires',
    'Reason'
);

fputcsv($output, $header);

$families = get_terms(array(
    'taxonomy' => 'base',
    'hide_empty' => false
));

foreach ($families as $family)
{
    $familyID = $family->name;
    $modelPrefix = '';
    if ($familyID =='~Unnamed~')
    {
        $familyID = explode('-', $family->slug, 2)[1];
    }
    else
    {
        $modelPrefix = $familyID;
    }

    $namePrefix = get_term_meta($family->term_id, 'name', true);
    if ($namePrefix)
    {
        $namePrefix .= ' ';
    }

    $products = get_posts(array(
        'post_type' => 'product',
        'post_status' => 'any',
        'tax_query' => array(array(
            'taxonomy' => 'base',
            'terms' => $family->term_id
        )),
        'nopaging' => true
    ));

    if (count($products) == 0)
    {
        continue;
    }

    $familyExpirations = array();
    $productInfo = array();
    foreach ($products as $product)
    {
        $productLine = get_the_terms($product->ID, 'product-line');
        if ($productLine == false)
        {
            $productLine = '';
        }
        else
        {
            $productLine = html_entity_decode($productLine[0]->name);
        }

        $certs = array();
        $activeCerts = get_the_terms($product->ID, 'certification');
        if ($activeCerts)
        {
            foreach ($activeCerts as $cert)
            {
                $testDate = '';
                $testRV = '';
                $lastTest = get_posts(array(
                    'post_type' => 'rv',
                    'meta_query' => array(
                        array(
                            'key' => 'affected_id',
                            'value' => $product->ID
                        ),
                        array(
                            'key' => 'status',
                            'value' => 'approved'
                        )
                    ),
                    'tax_query' => array(
                        array(
                            'taxonomy' => 'request',
                            'field' => 'slug',
                            'terms' => 'test'
                        ),
                        array(
                            'taxonomy' => 'certification',
                            'terms' => $cert->term_id
                        ),
                    ),
                    'posts_per_page' => 1
                ));

                if (count($lastTest) == 1)
                {
                    $testDate = explode(' ', $lastTest[0]->post_modified)[0];
                    $testRV = $lastTest[0]->public_id;
                }

                $expiration = explode(
                    ' ',
                    get_post_meta($product->ID, $cert->slug, true)
                )[0];
                $certs[] = array(
                    'certification' => $cert,
                    'tested' => $testDate,
                    'rv' => $testRV,
                    'expires' => $expiration
                );

                if ( ! array_key_exists($cert->slug, $familyExpirations))
                {
                    $familyExpirations[$cert->slug] = array();
                }
                if ( ! array_key_exists($expiration, $familyExpirations[$cert->slug]))
                {
                    $familyExpirations[$cert->slug][$expiration] = array();
                }
                $familyExpirations[$cert->slug][$expiration][] = $product->ID;
            }
        }

        $productInfo[] = array(
            'id' => $product->ID,
            'manufacturer' => $product->_wpcf_belongs_company_id,
            'name' => $namePrefix . html_entity_decode($product->post_title),
            'model' => $modelPrefix . $product->model,
            'created' => explode(' ', $product->post_date)[0],
            'product_line' => $productLine,
            'certs' => $certs
        );
    }

    $variations = array();
    foreach ($familyExpirations as $slug => $productGroups)
    {
        if (count($productGroups) <= 1)
        {
            continue;
        }

        $majorityExpiration = NULL;
        $majoritySize = 0;
        foreach ($productGroups as $expiration => $productIDs)
        {
            if (count($productIDs) > $majoritySize)
            {
                $majoritySize = count($productIDs);
                $majorityExpiration = $expiration;
            }
        }

        $variations[$slug] = $majorityExpiration;
    }

    foreach ($productInfo as $product)
    {
        foreach ($product['certs'] as $cert)
        {
            $reason = array();
            $normal = true;
            if (array_key_exists($cert['certification']->slug, $variations))
            {
                if ($cert['expires'] != $variations[$cert['certification']->slug])
                {
                    $normal = false;
                    $reason[] = "Family expires {$variations[$cert['certification']->slug]}";
                }
            }

            if ($cert['tested'])
            {
                $testedYear = explode('-', $cert['tested'])[0];
                $expireYear = explode('-', $cert['expires'])[0];
                $span = $expireYear - $testedYear;

                if ($span != 5)
                {
                    $normal = false;
                    $reason[] = "Expires {$span} years after test";
                }
            }

            if ($normal)
            {
                continue;
            }

            writeRow($output, $product, $cert, $reason);
        }
    }
}
die();*/
$products = get_posts(array(
    'post_type' => 'product',
    'post_status' => 'any',
    'tax_query' => array(array(
        'taxonomy' => 'base',
        'operator' => 'NOT EXISTS'
    )),
    'nopaging' => true
));

foreach ($products as $product)
{
    $productLine = get_the_terms($product->ID, 'product-line');
    if ($productLine == false)
    {
        $productLine = '';
    }
    else
    {
        $productLine = html_entity_decode($productLine[0]->name);
    }

    $productInfo = array(
        'id' => $product->ID,
        'manufacturer' => $product->_wpcf_belongs_company_id,
        'name' => html_entity_decode($product->post_title),
        'model' => $product->model,
        'created' => explode(' ', $product->post_date)[0],
        'product_line' => $productLine
    );

    $activeCerts = get_the_terms($product->ID, 'certification');
    if ($activeCerts)
    {
        foreach ($activeCerts as $cert)
        {
            $lastTest = get_posts(array(
                'post_type' => 'rv',
                'meta_query' => array(
                    array(
                        'key' => 'affected_id',
                        'value' => $product->ID
                    ),
                    array(
                        'key' => 'status',
                        'value' => 'approved'
                    )
                ),
                'tax_query' => array(
                    array(
                        'taxonomy' => 'request',
                        'field' => 'slug',
                        'terms' => 'test'
                    ),
                    array(
                        'taxonomy' => 'certification',
                        'terms' => $cert->term_id
                    ),
                ),
                'posts_per_page' => 1
            ));

            if (count($lastTest) != 1)
            {
                continue;
            }

            $testDate = explode(' ', $lastTest[0]->post_modified)[0];
            $testRV = $lastTest[0]->public_id;

            $expiration = explode(
                ' ',
                get_post_meta($product->ID, $cert->slug, true)
            )[0];

            $testedYear = explode('-', $testDate)[0];
            $expireYear = explode('-', $expiration)[0];
            $span = $expireYear - $testedYear;

            if ($span != 5)
            {
                $cert = array(
                    'certification' => $cert,
                    'tested' => $testDate,
                    'rv' => $testRV,
                    'expires' => $expiration
                );
                $reason = array("Expires {$span} years after test");
                writeRow($output, $productInfo, $cert, $reason);
            }
        }
    }
}

function writeRow($csv, $product, $cert, $reasons)
{
    $manufacturer = get_post($product['manufacturer']);
    $manufacturer = html_entity_decode($manufacturer->post_title);
    $row = array(
        $manufacturer,
        $product['model'],
        $product['name'],
        $product['product_line'],
        $product['created'],
        $cert['certification']->name,
        $cert['tested'],
        $cert['rv'],
        $cert['expires'],
        implode(', ', $reasons)
    );
    fputcsv($csv, $row);
}
?>
