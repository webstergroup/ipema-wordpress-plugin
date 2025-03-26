<?php

set_time_limit(0);
ini_set('memory_limit', '2G');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$offset = 0;
$certIdx = 0;
if (array_key_exists('offset', $_GET))
{
    $offset = $_GET['offset'];
}
if (array_key_exists('cert', $_GET))
{
    $certIdx = $_GET['cert'];
}

$certs = ipema_active_certs();
$cert = $certs[$certIdx];

$possible = get_posts(array(
    'post_type' => 'product',
    'posts_per_page' => 100,
    'meta_query' => array(array(
        'key' => $cert->slug,
        'value' => ''
    )),
    'fields' => 'ids',
    'offset' => $_GET['offset'],
    'order' => 'ASC'
));

if (count($possible) == 0)
{
    $certIdx++;
    if ($certIdx < count($certs))
    {
        wp_redirect("/about-ipema/?cron=1970&offset=0&cert=$certIdx");
    }
    die();
}

foreach ($possible as $productID)
{
    foreach ($certs as $otherCert)
    {
        $expires = get_post_meta($productID, $otherCert->slug, true);
        if ($expires)
        {
            update_post_meta($productID, $cert->slug, $expires);
            continue 2;
        }
    }

    $base = ipema_get_product_base($productID);
    if ($base)
    {
        $other = get_posts(array(
            'post_type' => 'product',
            'tax_query' => array(
                array(
                    'taxonomy' => 'base',
                    'terms' => $base->term_id
                ),
                array(
                    'taxonomy' => 'certification',
                    'operator' => 'EXISTS'
                )
            ),
            'fields' => 'ids',
            'posts_per_page' => 1
        ));

        if (count($other) > 0)
        {
            $otherCerts = get_the_terms($other[0], 'certification');
            $expires = get_post_meta(
                $other[0],
                $otherCerts[0]->slug,
                true
            );

            update_post_meta($productID, $cert->slug, $expires);
        }
    }
}

$offset += 100;

wp_redirect("/about-ipema/?cron=1970&offset=$offset&cert=$certIdx");
die();
