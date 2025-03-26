<?php
global $wpdb;

$batch = 10;

$broken = get_posts(array(
    'post_type' => 'product',
    'post_status' => 'any',
    'date_query' => array(array(
        'year' => 2019,
        'month' => 6,
        'day' => 21,
        'hour' => 7,
        'minute' => 21,
        'second' => 8
    )),
    'posts_per_page' => $batch,
    'fields' => 'ids'
));

$product_count = 0;
foreach ($broken as $postID)
{
    wp_delete_post($postID, $skip_trash=True);

    $display = get_posts(array(
        'post_type' => 'certified-product',
        'post_status' => 'any',
        'meta_query' => array(array(
            'key' => '_wpcf_belongs_product_id',
            'value' => $postID
        )),
        'nopaging' => true
    ));
    foreach ($display as $post)
    {
        wp_delete_post($post->ID, $skip_trash=True);
    }

    $changes = get_posts(array(
        'post_type' => 'product-change',
        'post-status' => 'any',
        'meta_query' => array(array(
            'key' => '_wpcf_belongs_product_id',
            'value' => $postID
        )),
        'nopaging' => true
    ));
    foreach ($changes as $post)
    {
        wp_delete_post($post->ID, $skip_trash=True);
    }

    $wpdb->delete(
        $wpdb->postmeta,
        array(
            'meta_key' => 'affected_id',
            'meta_value' => $postID
        )
    );

    $product_count++;

    if ($product_count >= $batch)
    {
        $product_count += $_GET['count'];
        wp_redirect("/api/?cron=delete-dupes&count=$product_count");
        die();
    }
}

$product_count += $_GET['count'];
die("Deleted $product_count products");
