<?php
global $wpdb;

$certifications = get_terms(array(
    'taxonomy' => 'certification'
));

foreach ($certifications as $certification)
{
    for ($i = 2020; $i <= 2026; $i++)
    {
        $sql = "UPDATE {$wpdb->postmeta}
                SET meta_value = %s
                WHERE meta_key = %s
                AND meta_value LIKE '$i-12-31%%'";

        $wpdb->query($wpdb->prepare(
            $sql,
            $i + 1 . '-' . CERTIFICATION_RENEWAL_DATE,
            $certification->slug
        ));
    }
}

$wpdb->update(
    $wpdb->postmeta,
    array('meta_value' => 2021),
    array('meta_key' => 'retest_year', 'meta_value' => 2020)
);

die('Finished');
