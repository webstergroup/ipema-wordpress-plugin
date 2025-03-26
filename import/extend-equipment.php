<?php
global $wpdb;

$certifications = get_terms(array(
    'taxonomy' => 'certification'
));

foreach ($certifications as $certification)
{
    if ( ! ipema_certification_product_type($certification->term_id, 'equipment'))
    {
        continue;
    }
    for ($i = 2028; $i > 2022; $i--)
    {
        $original_exp = "$i-" . CERTIFICATION_RENEWAL_DATE;
        $sql = "UPDATE {$wpdb->postmeta}
                SET meta_value = %s
                WHERE meta_key = %s
                AND meta_value LIKE '$original_exp'";

        $wpdb->query($wpdb->prepare(
            $sql,
            $i + 2 . '-' . CERTIFICATION_RENEWAL_DATE,
            $certification->slug
        ));
    }
}

die('Finished');
