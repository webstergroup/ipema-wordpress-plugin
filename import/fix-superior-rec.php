<?php

set_time_limit(0);
ini_set('memory_limit', '2G');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$page = 1;
if (array_key_exists('i', $_GET))
{
    $page = $_GET['i'];
}

$possible = get_posts(array(
    'post_type' => 'product',
    'posts_per_page' => 50,
    'paged' => $page,
    'post_status' => 'any',
    'meta_query' => array(array(
        'key' => '_wpcf_belongs_company_id',
        'value' => 65604
    )),
    'order' => 'ASC'
));

if (count($possible) == 0)
{
    die('Correction Complete');
}

$changed = 0;
foreach ($possible as $product)
{
    $content = trim(strip_tags($product->post_content));
    $model = get_post_meta($product->ID, 'model', true);

    if ($product->post_title != $model)
    {
        continue;
    }

    $productID = ipema_new_product_change($product->ID, $product->post_author);
    wp_update_post(array(
        'ID' => $productID,
        'post_title' => $content,
        'post_content' => ''
    ));

    ipema_complete_product_change($productID);

    $public_id = get_option('rv_autoincrement');
    $public_id++;
    update_option('rv_autoincrement', $public_id);

    $model = ipema_model_number(array('product' => $productID));
    $rv_id = wp_insert_post(array(
        'post_type' => 'rv',
        'post_title' => $model,
        'post_status' => 'publish',
        'post_content' => 'Automated name correction',
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
    $changed++;
}

$page++;
wp_redirect("/about-ipema/?cron=superior&i=$page");
die();
