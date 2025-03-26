<?php

set_time_limit(0);
ini_set('memory_limit', '2G');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$offset = 0;
if (array_key_exists('offset', $_GET))
{
    $offset = $_GET['offset'];
}

$possible = get_posts(array(
    'post_type' => 'product',
    'posts_per_page' => 100,
    'meta_query' => array(array(
        'key' => 'french_name',
        'compare' => 'NOT EXISTS'
    )),
    'offset' => $_GET['offset'],
    'order' => 'ASC'
));

if (count($possible) == 0)
{
    die();
}

$fixed = false;
foreach ($possible as $product)
{
    if ( ! $fixed)
    {
        $offset++;
    }
    if ($product->post_title)
    {
        continue;
    }

    $content = trim($product->post_content);
    if ( ! $content)
    {
        continue;
    }
    if (strlen($content) > 100)
    {
        continue;
    }
    if (strip_tags($content) != $content)
    {
        continue;
    }

    $productID = ipema_new_product_change($product->ID, $product->post_author);
    wp_update_post(array(
        'ID' => $productID,
        'post_title' => $content,
        'post_content' => ''
    ));

    $content = get_post_meta($product->ID, 'french-description', true);
    if (strlen($content) > 100)
    {
        $content = false;
    }
    if (strip_tags($content) != $content)
    {
        $content = false;
    }

    if ($content && trim($content))
    {
        $fixed = true;
        update_post_meta($productID, 'french_name', trim($content));
        delete_post_meta($productID, 'french-description');
    }
    ipema_complete_product_change($productID);

    $public_id = get_option('rv_autoincrement');
    $public_id++;
    update_option('rv_autoincrement', $public_id);

    $model = ipema_model_number(array('product' => $productID));
    $rv_id = wp_insert_post(array(
        'post_type' => 'rv',
        'post_title' => $model,
        'post_status' => 'publish',
        'post_content' => 'Automated description correction',
        'post_author' => 19, // Unknown User
        'meta_input' => array(
            '_wpcf_belongs_product_id' => $product->ID,
            '_wpcf_belongs_product-change_id' => $productID,
            'public_id' => $public_id,
            'status' => 'processed',
            'affected_id' => $product->ID,
        )
    ));

    wp_set_post_terms($rv_id, 'edit', 'request');
}

if ($fixed)
{
    $offset--;
}

wp_redirect("/about-ipema/?cron=descriptions&offset=$offset");
die();
