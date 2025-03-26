<?php

set_time_limit(0);
ini_set('memory_limit', '2G');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$name = 0;
if (array_key_exists('i', $_GET))
{
    $name = $_GET['i'];
}

$names = array(
    'LITTLE TIKES COMMERICAL',
    'Plalypower LT Farmington Inc.',
    'Playpower LT Farmington',
    'PLAYPOWER LT FARMINGTON',
    'PLAYPOWER LT FARMINGTON INC',
    'PlayPower LT Farmington, Inc',
    'Playpower LT Farmington Inc.',
    'Playpower LT Farmington Inc.-',
    'PLAYPOWER LT FARMINGTION',
    'Playpower LT Farmington, Inc.',
    'PLAYPOWER LT. FARMINGTON, INC'
);

$possible = get_posts(array(
    'post_type' => 'product',
    'title' => $names[$name],
    'posts_per_page' => 50,
    'post_status' => 'any',
    'meta_query' => array(array(
        'key' => '_wpcf_belongs_company_id',
        'value' => 44073
    )),
    'order' => 'ASC'
));

$changed = 0;
foreach ($possible as $product)
{
    $content = trim($product->post_content);
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

    $french = get_post_meta($product->ID, 'french-description', true);
    delete_post_meta($productID, 'french-description');
    if ($french && $french != $content)
    {
        update_post_meta($productID, 'french_name', trim($french));
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

if ($changed == 0)
{
    $name += 1;
    if ($name >= count($names))
    {
        die();
    }
}

wp_redirect("/about-ipema/?cron=playpower&i=$name");
die();
