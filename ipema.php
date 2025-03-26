<?php
/*
Plugin Name: IPEMA Website
Description: Custom functionality to facilitate membership and product management
Version: 1.0
Author: The John Webster Company
Author URI: the.johnwebster.co
License: AGPLv3
*/

/* 1/20/2025 Added code to delay wp_generate_attachment_metadata function until Wordpress core was fully loaded */

// Renewal date in mm-dd format
define('IPEMA_RENEWAL_DATE', '05-31');
define('CERTIFICATION_RENEWAL_DATE', IPEMA_RENEWAL_DATE);
$rvChunk = 5;

$US_STATES = array(
    'AL' => 'Alabama',
    'AK' => 'Alaska',
    'AZ' => 'Arizona',
    'AR' => 'Arkansas',
    'CA' => 'California',
    'CO' => 'Colorado',
    'CT' => 'Connecticut',
    'DE' => 'Delaware',
    'DC' => 'District Of Columbia',
    'FL' => 'Florida',
    'GA' => 'Georgia',
    'HI' => 'Hawaii',
    'ID' => 'Idaho',
    'IL' => 'Illinois',
    'IN' => 'Indiana',
    'IA' => 'Iowa',
    'KS' => 'Kansas',
    'KY' => 'Kentucky',
    'LA' => 'Louisiana',
    'ME' => 'Maine',
    'MD' => 'Maryland',
    'MA' => 'Massachusetts',
    'MI' => 'Michigan',
    'MN' => 'Minnesota',
    'MS' => 'Mississippi',
    'MO' => 'Missouri',
    'MT' => 'Montana',
    'NE' => 'Nebraska',
    'NV' => 'Nevada',
    'NH' => 'New Hampshire',
    'NJ' => 'New Jersey',
    'NM' => 'New Mexico',
    'NY' => 'New York',
    'NC' => 'North Carolina',
    'ND' => 'North Dakota',
    'OH' => 'Ohio',
    'OK' => 'Oklahoma',
    'OR' => 'Oregon',
    'PA' => 'Pennsylvania',
    'RI' => 'Rhode Island',
    'SC' => 'South Carolina',
    'SD' => 'South Dakota',
    'TN' => 'Tennessee',
    'TX' => 'Texas',
    'UT' => 'Utah',
    'VT' => 'Vermont',
    'VA' => 'Virginia',
    'WA' => 'Washington',
    'WV' => 'West Virginia',
    'WI' => 'Wisconsin',
    'WY' => 'Wyoming'
);

function ipema_current_year()
{
    $year = date('Y');
    if (strtotime("$year-" . IPEMA_RENEWAL_DATE) > strtotime('15 days ago'))
    {
        $year -= 1;
    }

    return $year;
}

function ipema_retest_year($now=NULL)
{
    if ( ! $now)
    {
        $now = time();
    }
    $year = date('Y');
    $renewal_date = strtotime("$year-" . CERTIFICATION_RENEWAL_DATE);
    $renewal_date = strtotime('+1 day', $renewal_date);
    if ($renewal_date <= $now)
    {
        $year += 1;
    }

    return $year;
}

function ipema_next_renew_date()
{
    $renewal_date = strtotime(date('Y-') . IPEMA_RENEWAL_DATE);
    if ($renewal_date < strtotime('today'))
    {
        $renewal_date = strtotime('+1 year', $renewal_date);
    }

    return $renewal_date;
}

function ipema_expiration_date($approval_time, $product_type)
{
    $renewal_period = 7;
    if ($product_type == 'surfacing')
    {
        $renewal_period = 5;
    }
    $year = ipema_retest_year($approval_time) + $renewal_period;
    return "$year-" . CERTIFICATION_RENEWAL_DATE;
}

function ipema_form_js($form, $is_ajax)
{
    if ($form['id'] == 1)
    {
        wp_enqueue_script(
            'ipema-signup',
            plugins_url('js/signup.js', __FILE__),
            array('jquery')
        );
    }
}
add_action('gform_enqueue_scripts', 'ipema_form_js', 10, 2);

add_action('wp_enqueue_scripts', function() {
    if (is_post_type_archive('certified-product'))
    {
        wp_enqueue_script(
            'js-cookie',
            plugins_url('js/js.cookie-2.1.4.min.js', __FILE__)
        );
        wp_enqueue_script(
            'featherlight',
            plugins_url('js/featherlight.min.js', __FILE__),
            array('jquery')
        );
        wp_enqueue_style(
            'featherlight',
            plugins_url('css/featherlight.min.css', __FILE__)
        );
    }
});

function ipema_insurance_year($min_year, $form, $field)
{
    if ($form['id'] == 1)
    {
        if ($field->id == 60 || $field->id == 61)
        {
            return date('Y');
        }
    }
    elseif ($form['id'] == 24)
    {
        if ($field->id == 2 || $field->id == 4)
        {
            return date('Y');
        }
    }

    return $min_year;
}
add_filter('gform_date_min_year', 'ipema_insurance_year', 10, 3);

function ipema_calculate_prices($total, $equipment, $surfacing)
{
    $membershipPrices = array(
        3000000 => 1100,
        0 => 1100 //dues are now equal
    );
    $certificationPrices = array(
        10000000 => 5000,
        3000000 => 3000,
        1 => 2000
    );
    $bothPrices = array(
        10000000 => 1500, // There is custom logic for equipment prices below.
        3000000 => 1000,
        1 => 750
    );

    if ($total < $equipment + $surfacing)
    {
        $total = $equipment + $surfacing;
    }

    $prices = array();
    foreach ($membershipPrices as $threshhold => $price)
    {
        if ($total >= $threshhold)
        {
            $prices['memberOnly'] = $price;
            break;
        }
    }

    $prices['certOnly'] = 0;
    foreach ($certificationPrices as $threshhold => $price)
    {
        if ($equipment >= $threshhold)
        {
            $prices['certOnly'] += $price;
            $prices['equipmentOnly'] = $price;
            break;
        }
    }
    foreach ($certificationPrices as $threshhold => $price)
    {
        if ($surfacing >= $threshhold)
        {
            $prices['certOnly'] += $price;
            $prices['surfacingOnly'] = $price;
            break;
        }
    }

    $prices['combined'] = $prices['memberOnly'];
    foreach ($bothPrices as $threshhold => $price)
    {
        if ($equipment >= $threshhold)
        {
            $prices['combined'] += $price;
            $prices['equipmentCombined'] = $price;
            break;
        }
    }
    foreach ($bothPrices as $threshhold => $price)
    {
        if ($surfacing >= $threshhold)
        {
            $prices['combined'] += $price;
            $prices['surfacingCombined'] = $price;
            break;
        }
    }

    if ($equipment >= 10000000)
    {
        $prices['combined'] += 500;
        $prices['equipmentCombined'] += 500;
    }

    return $prices;
}

function ipema_calculate_prices_v2($company_id)
{
    $prices = array();    
    $prices['memberOnly'] = 1150;
    /*$prices['memberOnly'] = 500;
    
    switch (date("n")) {
      case 1:
        $prices['memberOnly'] = 250;
        break;
      case 2:
        $prices['memberOnly'] = 209;
        break;
      case 3:
        $prices['memberOnly'] = 167;
        break;
      case 4:
        $prices['memberOnly'] = 125;
        break;
      case 5:
      case 6:
      case 7:
        $prices['memberOnly'] = 500;
        break;
      case 8:
        $prices['memberOnly'] = 459;
        break;
      case 9:
        $prices['memberOnly'] = 417;
        break;
      case 10:
        $prices['memberOnly'] = 375;
        break;
      case 11:
        $prices['memberOnly'] = 334;
        break;
      case 12:
        $prices['memberOnly'] = 292;
        break;
    }*/
    
    $prices['certOnly'] = 5000;
    $prices['combined'] = 1150;
	
    $surfacing_certified = new WP_Query( 
	 array(
	  'post_type' => 'certified-product',
	  'fields' => 'ids',
	  'posts_per_page' => -1,
	  'tax_query' => array(
	    array(
	      'taxonomy' => 'product-type',
	      'field' => 'slug',
	      'terms' => 'surfacing',
	    ),
	  ),
	  'meta_query' => array(
	    array(
	     'key' => '_wpcf_belongs_company_id', 
	     'value' => $company_id
	    )
	   )
	 )
	);
	
    $equipment_certified = new WP_Query( 
	 array(
	  'post_type' => 'certified-product',
	  'fields' => 'ids',
	  'posts_per_page' => -1,
	  'tax_query' => array(
	    array(
	      'taxonomy' => 'product-type',
	      'field' => 'slug',
	      'terms' => 'equipment',
	    ),
	  ),
	  'meta_query' => array(
	    array(
	     'key' => '_wpcf_belongs_company_id', 
	     'value' => $company_id
	    )
	   )
	 )
	);
	
    $surfacing_product = new WP_Query( 
	 array(
	  'post_type' => 'product',
	  'fields' => 'ids',
	  'posts_per_page' => -1,
	  'tax_query' => array(
	    array(
	      'taxonomy' => 'product-type',
	      'field' => 'slug',
	      'terms' => 'surfacing',
	    ),
	  ),
	  'meta_query' => array(
	    array(
	     'key' => '_wpcf_belongs_company_id', 
	     'value' => $company_id
	    )
	   )
	 )
	);
	
    $equipment_product = new WP_Query( 
	 array(
	  'post_type' => 'product',
	  'fields' => 'ids',
	  'posts_per_page' => -1,
	  'tax_query' => array(
	    array(
	      'taxonomy' => 'product-type',
	      'field' => 'slug',
	      'terms' => 'equipment',
	    ),
	  ),
	  'meta_query' => array(
	    array(
	     'key' => '_wpcf_belongs_company_id', 
	     'value' => $company_id
	    )
	   )
	 )
	);
    
    $count_surfacing = (int)$surfacing_certified->post_count + (int)$surfacing_product->post_count;    
    /*switch (true) {
	    case ($count_surfacing > 1500):
		$prices['combined'] += 3000;
		break;
	    case ($count_surfacing > 100 && $count_surfacing <= 1500):
		$prices['combined'] += 1500;
		break;
	    case ($count_surfacing > 0 && $count_surfacing <= 100):
	       $prices['combined'] += 750;     
    }*/
    
    $count_equipment = (int)$equipment_certified->post_count + (int)$equipment_product->post_count;
    /*switch (true) {
	    case ($count_equipment > 1500):
		$prices['combined'] += 3000;
		break;
	    case ($count_equipment > 100 && $count_equipment <= 1500):
		$prices['combined'] += 1500;
		break;
	    case ($count_equipment > 0 && $count_equipment <= 100):
	       $prices['combined'] += 750;
    }*/
    
    $total_products = $count_surfacing + $count_equipment;
    switch (true) {
	    case ($total_products > 1500):
		$prices['combined'] += 3000;
		break;
	    case ($total_products > 100 && $total_products <= 1500):
		$prices['combined'] += 1500;
		break;
	    case ($total_products <= 100):
	       $prices['combined'] += 750;
    }

    return $prices;
}

function ipema_months_remaining()
{
    $renewal_date = ipema_next_renew_date();
    $prorate_date = strtotime('+2 months', $renewal_date);
    $created_date = strtotime('today');

    if ($prorate_date >= strtotime('+1 year', $created_date))
    {
        $prorate_date = strtotime('1 year ago', $prorate_date);
    }

    $prorate = -1;
    while ($created_date < $prorate_date)
    {
        $created_date = strtotime('+1 month', $created_date);
        $prorate++;
    }

    // The month before and two months after renewal pay full price
    if ($prorate < 3)
    {
        return 12;
    }

    return $prorate;
}

// Avoid double-prorating on validation + render
$ipema_prorated = null;
/*function ipema_customize_prices($form, $ajax, $field_values)
{
    global $ipema_prorated;
    $totalSales = rgar($_POST, 'input_11', 0);
    $equipmentSales = rgar($_POST, 'input_68', 0);
    $surfacingSales = rgar($_POST, 'input_69', 0);

    if ($totalSales + $equipmentSales + $surfacingSales == 0)
    {
        // Prevent wrong price if someone changes from manufacturer to associate
        if (array_key_exists('input_16_1', $_POST))
        {
            $basePrice = 0;
            foreach ($form['fields'] as $field)
            {
                if ($field->id == 16)
                {
                    if ( ! $ipema_prorated)
                    {
                        $price = ipema_prorate_price(
                            ipema_months_remaining(),
                            $field->basePrice
                        );

                        $price = '$' . number_format($price);

                        $ipema_prorated = $price;
                    }

                    $price = $ipema_prorated;

                    $field->basePrice = $price;
                    $_POST['input_16_2'] = $field->basePrice;
                    $_POST['input_76'] = trim($field->basePrice, '$ ');
                    $_POST['input_77'] = 0;
                    $_POST['input_78'] = 0;
                }
            }
        }
        return $form;
    }

    if ( ! $ipema_prorated)
    {
        $prices = ipema_calculate_prices(
            $totalSales,
            $equipmentSales,
            $surfacingSales
        );

        $originalMembership = $prices['memberOnly'];
        $prices['memberOnly'] = ipema_prorate_price(
            ipema_months_remaining(),
            $prices['memberOnly']
        );

        $diff = $originalMembership - $prices['memberOnly'];
        $prices['combined'] -= $diff;

        $ipema_prorated = $prices;
    }

    $prices = $ipema_prorated;

    $_POST['input_16_2'] = '$'. number_format($prices['memberOnly']);

    foreach ($form['fields'] as $key => $field)
    {
        if ($field->id == 16)
        {
            $price = '$' . number_format($prices['memberOnly']);
            $field->basePrice = $price;
        }
        else if ($field->id == 17)
        {
            $price = '$' . number_format($prices['certOnly']);
            $text = explode('&mdash;', $field->choices[0]['text']);
            $field->choices[0]['price'] =  "$price.00";
            $field->choices[0]['text'] = "$text[0]&mdash; $price";

            $price = '$' . number_format($prices['combined']);
            $text = explode('&mdash;', $field->choices[1]['text']);
            $field->choices[1]['price'] = "$price.00";
            $field->choices[1]['text'] = "$text[0]&mdash; $price";
        }
    }

    $_POST['input_76'] = 0;
    $_POST['input_77'] = 0;
    $_POST['input_78'] = 0;
    $choice = explode('|', rgpost('input_17'));
    $choice = $choice[0];
    if ($choice == 'both')
    {
        $_POST['input_76'] = $prices['memberOnly'];
        if (rgpost('input_66_1'))
        {
            $_POST['input_77'] = $prices['equipmentCombined'];
        }
        if (rgpost('input_67_1'))
        {
            $_POST['input_78'] = $prices['surfacingCombined'];
        }
    }
    else
    {
        if (rgpost('input_66_1'))
        {
            $_POST['input_77'] = $prices['equipmentOnly'];
        }
        if (rgpost('input_67_1'))
        {
            $_POST['input_78'] = $prices['surfacingOnly'];
        }
    }

    return $form;
}
add_filter('gform_pre_render_1', 'ipema_customize_prices', 10, 3);
add_filter('gform_pre_validation_1', 'ipema_customize_prices', 10, 3);
add_filter('gform_pre_submission_filter_1', 'ipema_customize_prices', 10, 3);*/

function ipema_calculate_prices_new_account()
{
    $prices = array();    
    $prices['memberOnly'] = 500;    
    switch (date("n")) {
      case 1:
        $prices['memberOnly'] = 250;
        break;
      case 2:
        $prices['memberOnly'] = 208.33;
        break;
      case 3:
        $prices['memberOnly'] = 166.67;
        break;
      case 4:
        $prices['memberOnly'] = 125;
        break;
      case 5:
      case 6:
      case 7:
        $prices['memberOnly'] = 500;
        break;
      case 8:
        $prices['memberOnly'] = 458.33;
        break;
      case 9:
        $prices['memberOnly'] = 416.67;
        break;
      case 10:
        $prices['memberOnly'] = 375;
        break;
      case 11:
        $prices['memberOnly'] = 333.33;
        break;
      case 12:
        $prices['memberOnly'] = 291.67;
        break;
    }
    
    $prices['certOnly'] = 5000;
    
    $prices['combined'] = 1150;
    /*switch (date("n")) {
      case 1:
        $prices['combined'] = 575;
        break;
      case 2:
        $prices['combined'] = 479.17;
        break;
      case 3:
        $prices['combined'] = 383.33;
        break;
      case 4:
        $prices['combined'] = 287.50;
        break;
      case 5:
      case 6:
      case 7:
        $prices['combined'] = 1150;
        break;
      case 8:
        $prices['combined'] = 1054.17;
        break;
      case 9:
        $prices['combined'] = 958.33;
        break;
      case 10:
        $prices['combined'] = 862.50;
        break;
      case 11:
        $prices['combined'] = 766.67;
        break;
      case 12:
        $prices['combined'] = 670.83;
        break;
    }*/
    
    $prices['equipmentOnly'] = 750;
    $prices['surfacingOnly'] = 750;

    return $prices;
}

function ipema_customize_prices($form, $ajax, $field_values)
{
    $company_type = rgar($_POST, 'input_9', 'manufacturer');

    if ($company_type == 'associate')
    {
        // Prevent wrong price if someone changes from manufacturer to associate
        if (array_key_exists('input_16_1', $_POST))
        {
            $basePrice = 0;
            foreach ($form['fields'] as $field)
            {
                if ($field->id == 16)
                {
                    $prices = ipema_calculate_prices_new_account();

                    $price = '$' . number_format($prices['memberOnly'], 2);

                    $field->basePrice = $price;
                    $_POST['input_16_2'] = $field->basePrice;
                    $_POST['input_76'] = trim($field->basePrice, '$');
                    $_POST['input_77'] = 0;
                    $_POST['input_78'] = 0;
                }
            }
        }
    } elseif ($company_type == 'manufacturer') {
        $basePrice = 0;
        foreach ($form['fields'] as $field)
        {
            if ($field->id == 16)
            {
                $prices = ipema_calculate_prices_new_account();

                $price = '$' . number_format($prices['combined'], 2);

                $field->basePrice = $price;
                $_POST['input_16_2'] = $field->basePrice;
                
                $membership_amount = $prices['combined'];
                $equipment_amount = 0;
                $surfacing_amount = 0;
                
                $membership_type = rgar($_POST, 'input_17', 'member-surfacing-certification|1900');
                
                switch ($membership_type) {
		    case 'member-surfacing-certification|1900':
                	$surfacing_amount = $prices['surfacingOnly'];
			break;
		    case 'member-equipment-certification|1900':
			$equipment_amount = $prices['equipmentOnly'];
			break;
		    case 'member-surfacing-equipment-certification|2650':
			$equipment_amount = $prices['equipmentOnly'];
                	$surfacing_amount = $prices['surfacingOnly'];
			break;
		    case 'non-member-surfacing-certification|5000':
			$surfacing_amount = $prices['certOnly'];
			$membership_amount = 0;
			break;
		    case 'non-member-equipment-certification|5000':
			$equipment_amount = $prices['certOnly'];
			$membership_amount = 0;
			break;
		    case 'non-member-surfacing-equipment-certification|10000':
			$equipment_amount = $prices['certOnly'];
			$surfacing_amount = $prices['certOnly'];
			$membership_amount = 0;
			break;
		}
		
		$_POST['input_76'] = $membership_amount;
                $_POST['input_77'] = $equipment_amount;
                $_POST['input_78'] = $surfacing_amount;
            }
        }
    }

    return $form;
}
add_filter('gform_pre_render_1', 'ipema_customize_prices', 10, 3);
add_filter('gform_pre_validation_1', 'ipema_customize_prices', 10, 3);
add_filter('gform_pre_submission_filter_1', 'ipema_customize_prices', 10, 3);

function ipema_validate_state($result, $value, $form, $field)
{
    global $US_STATES;
    if ( ! $result['is_valid'])
    {
        return $result;
    }

    if ($value['2.6'] != 'United States')
    {
        return $result;
    }

    $search = array_map('strtoupper', $US_STATES);
    if (in_array(strtoupper($value['2.4']), $search))
    {
        return $result;
    }
    if (in_array(strtoupper($value['2.4']), array_keys($US_STATES)))
    {
        return $result;
    }

    $result['is_valid'] = false;
    $result['message'] = 'Unknown state "' . $value['2.4'] . '"';

    return $result;
}
add_filter('gform_field_validation_1_2', 'ipema_validate_state', 10, 4);

function ipema_get_membership_type($entry)
{
    $membership = rgar($entry, 17);
    if ($membership)
    {
        $membership = explode('|', $membership);
        return $membership[0];
    }

    return 'member';
}

function ipema_signup_year($now)
{
    $year = date('Y', $now);
    $renewal = strtotime("$year-" . IPEMA_RENEWAL_DATE);
    if ($now < strtotime('-1 month', $renewal))
    {
        $year -= 1;
    }

    return $year;
}

function ipema_company_populate($data, $form, $entry)
{
    global $US_STATES;
    if ($form['id'] != 1)
    {
        return $data;
    }

    $data['post_type'] = 'company';

    $state = rgar($entry, '2.4');
    if (rgar($entry, '2.6') == 'United States')
    {
        $search = array_map('strtoupper', $US_STATES);
        $state = strtoupper($state);
        if (in_array($state, $search))
        {
            $state = array_search($state, $search);
        }
    }

    $data['post_custom_fields']['address'] = rgar($entry, "2.1");
    $data['post_custom_fields']['address2'] = rgar($entry, "2.2");
    $data['post_custom_fields']['city'] = rgar($entry, "2.3");
    $data['post_custom_fields']['state'] = $state;
    $data['post_custom_fields']['zip'] = rgar($entry, "2.5");
    $data['post_custom_fields']['country'] = rgar($entry, "2.6");

    if (rgar($entry, 60))
    {
        $data['post_custom_fields']['pending_insurance'] = $entry['id'];
        //$data['post_custom_fields']['ipema-insurance-exp'] = strtotime(rgar($entry, 60));
    }
    if (rgar($entry, 61))
    {
        $data['post_custom_fields']['pending_insurance'] = $entry['id'];
        //$data['post_custom_fields']['tuv-insurance-exp'] = strtotime(rgar($entry, 61));
    }

    if (rgar($entry, 73))
    {
        $data['post_custom_fields']['pending_equipment_agreement'] = $entry['id'];
    }
    if (rgar($entry, 74))
    {
        $data['post_custom_fields']['pending_surfacing_agreement'] = $entry['id'];
    }

    // We don't want to set insurance info until confirmed by IPEMA
    unset($data['post_custom_fields']['ipema-insurance']);
    unset($data['post_custom_fields']['ipema-insurance-exp']);
    unset($data['post_custom_fields']['tuv-insurance']);
    unset($data['post_custom_fields']['tuv-insurance-exp']);

    unset($data['post_custom_fields']['equipment-certification-agreement']);
    unset($data['post_custom_fields']['surface-certification-agreement']);


    $membership = ipema_get_membership_type($entry);
    if ($membership != 'certification' && rgar($entry, 63) != 'Check')
    {
        $data['post_custom_fields']['active'] = ipema_signup_year(time());
    }
    if (rgar($entry, 63) == 'Check')
    {
        $data['post_custom_fields']['pending_renewal'] = $entry['id'];
    }

    $data['post_category'] = array();

    return $data;
}
add_filter('gform_post_data', 'ipema_company_populate', 10, 3);

function ipema_personal_populate($data, $form, $entry)
{
    global $US_STATES;
    if ($form['id'] != 2)
    {
        return $data;
    }

    if (rgar($entry, 13) == 'Check')
    {
        $data['post_custom_fields']['pending_renewal'] = $entry['id'];
    }

    return $data;
}
add_filter('gform_post_data', 'ipema_personal_populate', 10, 3);

function ipema_company_link($user_id, $conf, $entry, $passwd)
{
    if ($entry['form_id'] == 1)
    {
        update_user_meta($user_id, 'company_id', $entry['post_id']);
        $user = new WP_User($user_id);
        $user->add_cap('can_manage_account');

        $membership = ipema_get_membership_type($entry);
        if ($membership != 'member')
        {
            $user->add_cap('can_manage_products');
        }
        if ($user->phone)
        {
            update_user_meta($user_id, 'contact-method', 'phone');
        }
        else
        {
            update_user_meta($user_id, 'contact-method', 'user_email');
        }
        update_user_meta($user_id, 'main_contact', true);

        wp_set_object_terms($entry['post_id'], rgar($entry, 9), 'account-type');
        if (rgar($entry, '66.1'))
        {
            wp_set_object_terms(
                $entry['post_id'],
                'equipment',
                'product-type',
                true
            );
        }
        if (rgar($entry, '67.1'))
        {
            wp_set_object_terms(
                $entry['post_id'],
                'surfacing',
                'product-type',
                true
            );
        }
    }
    elseif ($entry['form_id'] == 2)
    {
        $post = array(
            'post_title' => rgar($entry, '1.3') . ' ' . rgar($entry, '1.6'),
            'post_content' => '',
            'post_status' => 'publish',
            'post_type' => 'company',
            'post_author' => $user_id
        );
        $post_id = wp_insert_post($post);
        wp_set_object_terms($post_id, 'personal', 'account-type');

        add_post_meta($post_id, 'address', rgar($entry, '8.1'));
        add_post_meta($post_id, 'address2', rgar($entry, '8.2'));
        add_post_meta($post_id, 'city', rgar($entry, '8.3'));
        add_post_meta($post_id, 'state', rgar($entry, '8.4'));
        add_post_meta($post_id, 'zip', rgar($entry, '8.5'));
        add_post_meta($post_id, 'country', rgar($entry, '8.6'));

        add_post_meta($post_id, 'active', ipema_signup_year(time()));
        
        $payment = rgar($entry, '13');
        if ($payment == 'Check')
        {
            update_post_meta($post_id, 'pending_renewal', $entry['id']);
        }

        update_user_meta($user_id, 'company_id', $post_id);
        update_user_meta($user_id, 'main_contact', true);
        update_user_meta($user_id, 'contact-method', 'user_email');
    }
}
add_action('gform_user_registered', 'ipema_company_link', 10, 4);

function ipema_personal_prorate($form)
{
    global $ipema_prorated;

    foreach ($form['fields'] as &$field)
    {
        if ($field->id == 4)
        {
            if ( ! $ipema_prorated)
            {
                /*$price = ipema_prorate_price(
                    ipema_months_remaining(),
                    $field->basePrice
                );*/
                $price = ipema_prorate_price_v2();
                $price = '$' . number_format($price, 2);

                $ipema_prorated = $price;
            }

            $price = $ipema_prorated;

            $field->basePrice = $price;
            break;
        }
    }

    return $form;
}
add_filter('gform_pre_render_2', 'ipema_personal_prorate');
add_filter('gform_pre_validation_2', 'ipema_personal_prorate');
add_filter('gform_pre_submission_filter_2', 'ipema_personal_prorate');

function ipema_buffer_output()
{
    ob_start();
}
add_action('activate_header', 'ipema_buffer_output');

function ipema_first_login($user_id, $user_data, $meta)
{
    global $ipema_block_redirect;
    if (wp_doing_ajax())
    {
        return;
    }

    $current = wp_get_current_user();
    if ($current)
    {
        return;
    }

    $ipema_block_redirect = true;
    $user = wp_signon(array(
        'user_login' => $user_data['user_login'],
        'user_password' => $user_data['password'],
        'remember' => true
    ));

    if ( is_wp_error($user))
    {
        print $user->get_error_message();
    }
    else
    {
        $company_users = count(get_users(array(
            'meta_key' => 'company_id',
            'meta_value' => $user->company_id
        )));

        if ($company_users == 1)
        {
            wp_redirect('/members/welcome');
            exit();
        }

        update_user_meta($user_id, 'set-password', true);
        wp_redirect('/members/set-password');
        exit();
    }
}
add_action('gform_activate_user', 'ipema_first_login', 10, 3);

function ipema_verify_email_body($text, $user, $user_email, $key, $meta)
{
    return "In order to confirm your email address and gain access to the IPEMA website, please click on the link below or copy and paste it into your web browser:\n\n%s\n\nBy creating this account, you agree to be bound by the IPEMA.org terms and conditions, which can be found at http://www.ipema.org/terms-of-use/";
}
add_filter('wpmu_signup_user_notification_email', 'ipema_verify_email_body', 10, 5);

function ipema_verify_email_subject($subject, $user, $user_email, $key, $meta)
{
    return 'IPEMA: Confirm Email Address';
}
add_filter('wpmu_signup_user_notification_subject', 'ipema_verify_email_subject', 10, 5);

function ipema_populate_product_lines($form)
{
    $fields = array(
        3 => 4,
        5 => 4,
        6 => 7,
        9 => 4,
        10 => 4,
        11 => 4,
        12 => 7,
        42 => 8,
        43 => 5
    );
    if ( ! in_array($form['id'], array_keys($fields)))
    {
        return $form;
    }

    $user = wp_get_current_user();
    $prefix = $user->company_id . '-';

    $choices = array();
    $lines = get_terms(array(
        'taxonomy' => 'product-line',
        'hide_empty' => false,
        'meta_query' => array(array(
            'key' => 'company_id',
            'value' => $user->company_id
        ))
    ));
    foreach ($lines as $line)
    {
        $choices[] = array(
            'value' => $line->term_id,
            'text' => $line->name
        );
    }

    $product_line = '';
    if (array_key_exists('base', $_GET))
    {
        $product_line = get_term_meta((int)$_GET['base'], 'product_line', true);
    }

    foreach ($form['fields'] as &$field)
    {
        if ($field->id == $fields[$form['id']])
        {
            if (count($field->choices) == 1)
            {
                if ($product_line != '')
                {
                    $field->defaultValue = $product_line;
                }
                $field->choices = array_merge($field->choices, $choices);
            }
        }
    }

    return $form;
}
add_filter('gform_pre_render', 'ipema_populate_product_lines');
add_filter('gform_pre_validation', 'ipema_populate_product_lines');
add_filter('gform_pre_submission_filter', 'ipema_populate_product_lines');

function ipema_certification_product_type($term_id, $type)
{
    // We foolishly used types to store the product type, so we have to read its
    // brain-damaged way of storing arrays.
    $types = get_term_meta($term_id, 'product-type', true);
    if ( ! is_array($types))
    {
        return false;
    }

    $types = array_values($types);
    foreach ($types as $checkbox)
    {
        if ($checkbox[0] == $type)
        {
            return true;
        }
    }

    return false;
}

function ipema_populate_certifications($form) {
    $fields = array(
        3 => array(5, array(6, 10), 'equipment'),
        9 => array(5, array(6, 11), 'structure'),
        10 => array(5, array(6, 12), 'surfacing'),
        7 => array(2, array(), 'equipment'),
        13 => array(2, array(), 'surfacing'),
        16 => array(2, array(), 'equipment'),
        17 => array(2, array(), 'surfacing')
    );
    if ( ! in_array($form['id'], array_keys($fields)))
    {
        return $form;
    }

    list($cert_field, $french_field, $type) = $fields[$form['id']];

    $certifications = ipema_active_certs();

    $showCerts = false;
    if ($form['id'] == 13)
    {
        $material = get_term_meta($_GET['base'], 'material', true);
        $showCerts = get_term_meta(
            (int)$material,
            'additional-certification'
        );
    }

    $choices = array();
    $canadian = array();
    $needs_canadian = false;
    if (array_key_exists('base', $_GET))
    {
        $needs_canadian = get_term_meta($_GET['base'], 'canadian');
    }

    foreach ($certifications as $certification)
    {
        if ( ! ipema_certification_product_type($certification->term_id, $type))
        {
            continue;
        }

        $is_canadian = get_term_meta($certification->term_id, 'canadian');

        if ($is_canadian)
        {
            $canadian[] = $certification->name;
        }
        if ($form['id'] == 7 || $form['id'] == 13)
        {
            if ( ! $needs_canadian && $is_canadian)
            {
                continue;
            }
        }

        if (get_term_meta($certification->term_id, 'restricted', true) == 1)
        {
            if ($showCerts !== false)
            {
                if ( ! in_array($certification->slug, $showCerts))
                {
                    continue;
                }
            }
        }
        $choices[] = array(
            'value' => $certification->name,
            'text' => $certification->name . ' <a href="/certification-program/'
                . "#{$certification->slug}\" target='_blank'>"
                . "(learn more)</a>"
        );
    }

    foreach ($form['fields'] as &$field)
    {
        if ($field->id == $cert_field)
        {
            $field->choices = $choices;
            ipema_checkbox_inputs($field);
        }
        if (in_array($field->id, $french_field))
        {
            if (count($canadian) == 0)
            {
                $field->visibility = 'hidden';
            }
            $rules = array();
            foreach ($canadian as $cert)
            {
                $rules[] = array(
                    'fieldId' => $cert_field,
                    'operator' => 'is',
                    'value' => $cert
                );
            }
            $field->conditionalLogic['rules'] = $rules;
        }
    }

    return $form;
};
add_filter('gform_pre_render', 'ipema_populate_certifications');
add_filter('gform_pre_validation', 'ipema_populate_certifications');
add_filter('gform_pre_submission_filter', 'ipema_populate_certifications');

function ipema_populate_materials($form)
{
    $fields = array(
        10 => 11,
        11 => 8,
        42 => 9,
        43 => 7
    );
    if ( ! in_array($form['id'], array_keys($fields)))
    {
        return $form;
    }

    $user = wp_get_current_user();
    $prefix = $user->company_id . '-';

    $choices = array();
    $materials = get_terms(array(
        'taxonomy' => 'material',
        'hide_empty' => false
    ));
    $other = null;
    foreach ($materials as $material)
    {
        if ($material->name == 'Other')
        {
            $other = array(
                'value' => $material->term_id,
                'text' => $material->name
            );
            continue;
        }

        $choices[] = array(
            'value' => $material->term_id,
            'text' => $material->name
        );
    }
    if ($other != null)
    {
        $choices[] = $other;
    }

    foreach ($form['fields'] as &$field)
    {
        if ($field->id == $fields[$form['id']])
        {
            $field->choices = $choices;
        }
    }

    return $form;
}
add_filter('gform_pre_render', 'ipema_populate_materials');
add_filter('gform_pre_validation', 'ipema_populate_materials');
add_filter('gform_pre_submission_filter', 'ipema_populate_materials');

function ipema_marketed_in_canada($form)
{
    if ($form['id'] != 5 && $form['id'] != 11)
    {
        return $form;
    }

    $type = ($form['id'] == 5)? 'equipment' : 'surfacing';

    $certifications = get_terms(array(
        'taxonomy' => 'certification',
        'hide_empty' => false
    ));

    $show_canada = false;
    foreach ($certifications as $certification)
    {
        if ( ! ipema_certification_product_type($certification->term_id, $type))
        {
            continue;
        }

        if (get_term_meta($certification->term_id, 'canadian'))
        {
            $show_canada = true;
            break;
        }
    }

    if ($show_canada === false)
    {
        foreach ($form['fields'] as &$field)
        {
            if ($field->id == 5)
            {
                $field->adminOnly = true;
            }
        }
    }

    return $form;
}
add_filter('gform_pre_render', 'ipema_marketed_in_canada');
add_filter('gform_pre_validation', 'ipema_marketed_in_canada');
add_filter('gform_pre_submission_filter', 'ipema_marketed_in_canada');

function ipema_get_product_base($product_id)
{
    $base = get_the_terms($product_id, 'base');
    if ($base !== false)
    {
        return $base[0];
    }

    return false;
}

function ipema_sort_models($a, $b)
{
    return strcasecmp($a['text'], $b['text']);
}

/*function ipema_product_label($product_id)
{
    $product = get_post($product_id);
    $model = '';
    $label = '';
    $base = ipema_get_product_base($product_id);
    if ($base !== false)
    {
        if ($base->name != '~Unnamed~')
        {
            $model .= $base->name . '-';
        }
        $name = get_term_meta($base->term_id, 'name', true);
        if ($name)
        {
            $label = "$name ";
        }
    }
    $model .= $product->model;
    $label .= $product->post_title;

    if (mb_strlen($label) > 0)
    {
        return "$model: $label";
    }

    return $model;
}*/

function ipema_populate_components($form)
{
    if ($form['id'] != 9)
    {
        return $form;
    }

    $user = wp_get_current_user();

    $choices = array();
    $offset = 0;
    $products = get_posts(array(
        'post_type' => 'product',
        'post_status' => 'any',
        'meta_query' => array(
            array(
                'key' => '_wpcf_belongs_company_id',
                'value' => $user->company_id
            ),
        ),
        'nopaging' => true,
        'fields' => 'ids'
    ));
    foreach ($products as $productID)
    {
        $label = ipema_model_number(
            array('product' => $productID)
        );
        $name = ipema_brand_name(
            array('product' => $productID)
        );
        if ($name)
        {
            $label .= ": $name";
        }

        $choices[] = array(
            'value' => $productID,
            'text' => ipema_product_display_name($productID)
        );
    }

    usort($choices, 'ipema_sort_models');

    foreach ($form['fields'] as &$field)
    {
        if ($field->id == 10)
        {
            $field->choices = $choices;
        }
    }

    return $form;
}
add_filter('gform_pre_render', 'ipema_populate_components');
add_filter('gform_pre_validation', 'ipema_populate_components');
add_filter('gform_pre_submission_filter', 'ipema_populate_components');

function ipema_force_unique_model($result, $value, $form, $field)
{
    global $wpdb;
    if ( ! $result['is_valid'])
    {
        return $result;
    }
    $user = wp_get_current_user();

    if (array_key_exists('base', $_GET))
    {
        $base = get_term($_GET['base']);
        if ($base->name != '~Unnamed~')
        {
            $value = $base->name . $value;
        }
    }

    // Using get_posts() generates a query that is killed by WPEngine for being
    // too long.
    $sql = '
        SELECT
          wp_posts.ID
        FROM
          wp_posts
        LEFT JOIN
          wp_term_relationships
        ON
          (wp_posts.ID = wp_term_relationships.object_id)
        INNER JOIN
          wp_postmeta
        ON
          ( wp_posts.ID = wp_postmeta.post_id )
        INNER JOIN
          wp_postmeta AS mt1
        ON
          ( wp_posts.ID = mt1.post_id )
        WHERE
          (
            wp_term_relationships.term_taxonomy_id IN (
                SELECT
                  term_taxonomy_id
                FROM
                  wp_term_taxonomy
                WHERE
                  term_id IN (
                    SELECT
                      term_id
                    FROM
                      wp_terms
                    WHERE
                      name = "~Unnamed~"
                  )
            )
          OR
            NOT EXISTS (
                SELECT 1 FROM
                  wp_term_relationships
                INNER JOIN
                  wp_term_taxonomy
                ON
                  wp_term_taxonomy.term_taxonomy_id = wp_term_relationships.term_taxonomy_id
                WHERE
                  wp_term_taxonomy.taxonomy = "base"
                AND
                  wp_term_relationships.object_id = wp_posts.ID
            )
          )
        AND ( (
                wp_postmeta.meta_key = "_wpcf_belongs_company_id"
              AND
                wp_postmeta.meta_value = "%d"
              )
            AND (
                mt1.meta_key = "model" AND mt1.meta_value = "%s"
                )
            )
        AND
          wp_posts.post_type = "product"
        AND
          (
            wp_posts.post_status <> "trash"
          AND
            wp_posts.post_status <> "auto-draft"
          )
        LIMIT 1';

    if ($wpdb->get_var($wpdb->prepare($sql, $user->company_id, $value)))
    {
        $result['is_valid'] = false;
        $result['message'] = 'This model number is already in use';

        return $result;
    }

    $families = get_terms(array(
        'taxonomy' => 'base',
        'hide_empty' => false,
        'name__like' => substr($value, 0, 1),
        'meta_query' => array(
            array(
                'key' => 'company_id',
                'value' => $user->company_id
            )
        ),
    ));

    foreach ($families as $family)
    {
        if (strpos($value, $family->name) !== 0)
        {
            continue;
        }

        $part = strlen($family->name);
        $matches = get_posts(array(
            'post_type' => 'product',
            'post_status' => 'any',
            'meta_query' => array(
                array(
                    'key' => '_wpcf_belongs_company_id',
                    'value' => $user->company_id
                ),
                array(
                    'key' => 'model',
                    'value' => substr($value, $part)
                ),
            ),
            'tax_query' => array(
                array(
                    'taxonomy' => 'base',
                    'terms' => $family->term_id
                )
            )
        ));

        if (count($matches) > 0)
        {
            $result['is_valid'] = false;
            $result['message'] = 'This model number is already in use';
            break;
        }
    }

    return $result;
}
add_filter('gform_field_validation_3_2', 'ipema_force_unique_model', 10, 4);
add_filter('gform_field_validation_9_2', 'ipema_force_unique_model', 10, 4);
add_filter('gform_field_validation_10_2', 'ipema_force_unique_model', 10, 4);
add_filter('gform_field_validation_6_2', 'ipema_force_unique_model', 10, 4);
add_filter('gform_field_validation_12_2', 'ipema_force_unique_model', 10, 4);

function ipema_force_unique_product_line($result, $value, $form, $field)
{
    if ( ! $result['is_valid'])
    {
        return $result;
    }

    foreach ($field->choices as $choice)
    {
        if ($choice['value'] == $value)
        {
            return $result;
        }
    }

    $user = wp_get_current_user();

    $term = $user->company_id . '-' . sanitize_title($value);
    if (get_term_by('slug', $term, 'product-line'))
    {
        $result['is_valid'] = false;
        $result['message'] = 'You already have a product line with this name';
    }

    return $result;
}
add_filter('gform_field_validation_3_4', 'ipema_force_unique_product_line', 10, 4);
add_filter('gform_field_validation_5_4', 'ipema_force_unique_product_line', 10, 4);
add_filter('gform_field_validation_9_4', 'ipema_force_unique_product_line', 10, 4);
add_filter('gform_field_validation_10_4', 'ipema_force_unique_product_line', 10, 4);
add_filter('gform_field_validation_11_4', 'ipema_force_unique_product_line', 10, 4);
add_filter('gform_field_validation_6_7', 'ipema_force_unique_product_line', 10, 4);
add_filter('gform_field_validation_12_7', 'ipema_force_unique_product_line', 10, 4);

function ipema_match_component_certification($result, $components, $form, $field)
{
    if ($result['is_valid'] == false)
    {
        return $result;
    }

    $entry = GFFormsModel::get_current_lead();
    $certs = array();
    foreach ($entry as $key => $value)
    {
        if (strpos($key, '5.') === 0)
        {
            if ($value)
            {
                $certs[$value] = false;
            }
        }
    }

    $invalid = array();
    foreach ($components as $component_id)
    {
        $product_certs = get_the_terms($component_id, 'certification');
        if ($product_certs != false)
        {
            foreach ($product_certs as $cert)
            {
                if (array_key_exists($cert->name, $certs))
                {
                    $certs[$cert->name] = true;
                }
            }
        }

        if (count(array_filter($certs)) != count($certs))
        {
            $rvs = get_posts(array(
                'post_type' => 'rv',
                'post_status' => 'draft',
                'meta_query' => array(array(
                    'key' => 'affected_id',
                    'value' => $component_id
                )),
                'tax_query' => array(array(
                    'taxonomy' => 'request',
                    'terms' => array('test', 'add-certification'),
                    'field' => 'slug'
                )),
                'nopaging' => true,
                'fields' => 'ids'
            ));

            foreach ($rvs as $rvID)
            {
                $rv_certs = wp_get_post_terms($rvID, 'certification');

                foreach ($rv_certs as $cert)
                {
                    if (array_key_exists($cert->name, $certs))
                    {
                        $certs[$cert->name] = true;
                    }
                }
            }

            if (count(array_filter($certs)) != count($certs))
            {
                $label = ipema_product_display_name($component_id);

                if ($product_certs == false)
                {
                    $invalid[$label] = false;
                }
                else
                {
                    $invalid[$label] = array_keys(
                        array_filter($certs, function($a) { return !$a; })
                    );
                }
            }
        }

        foreach ($certs as $key => $value)
        {
            $certs[$key] = false;
        }
    }

    if (count($invalid) > 0)
    {
        $result['is_valid'] = false;
        $result['message'] = 'The following components have problems:<ul>';

        foreach ($invalid as $label => $missing_certs)
        {
            if ($missing_certs == false)
            {
                $result['message'] .= "<li>$label &ndash; obsolete</li>";
            }
            else
            {
                $result['message'] .= "<li>$label &ndash; not "
                    . implode(' or ', $missing_certs) . ' certified</li>';
            }
        }
        $result['message'] .= '</ul>';
    }

    return $result;
}
add_filter('gform_field_validation_9_10', 'ipema_match_component_certification', 10, 4);

function ipema_validate_certification($result, $components, $form, $field)
{
    if ($result['is_valid'] == false)
    {
        return $result;
    }

    $entry = GFFormsModel::get_current_lead();
    $specialCerts = get_term_meta($entry[11], 'additional-certification');

    foreach ($entry as $key => $value)
    {
        if (strpos($key, '5.') === 0)
        {
            if ($value)
            {
                $certification = get_term_by('name', $value, 'certification');
                if (get_term_meta($certification->term_id, 'restricted', true))
                {
                    if ( ! in_array($certification->slug, $specialCerts))
                    {
                        $material = get_term($entry[11], 'material');
                        $result['is_valid'] = false;
                        $result['message'] = "{$material->name} is not eligible"
                            . " for {$value} certification";

                        return $result;
                    }
                }
            }
        }
    }

    return $result;
}
add_filter('gform_field_validation_10_5', 'ipema_validate_certification', 10, 4);

function ipema_add_product($data, $form, $entry)
{
    $product_forms = array(3, 6, 9, 10, 12);
    if ( ! in_array($form['id'], $product_forms))
    {
        return $data;
    }

    $user = wp_get_current_user();

    $data['post_type'] = 'product';
    $data['post_custom_fields']['_wpcf_belongs_company_id'] = $user->company_id;
    $data['post_category'] = array();

    if (is_numeric($_GET['model']))
    {
        $parentCompany = get_post_meta($_GET['model'], '_wpcf_belongs_company_id', true);
        if ($parentCompany == $user->company_id)
        {
            $data['post_parent'] = $_GET['model'];
        }
    }

    return $data;
}
add_filter('gform_post_data', 'ipema_add_product', 10, 3);

/* Checks if we are adding a new product line from a radio button set with an
 * "Other" option.
 */
function ipema_product_line_id($field_id, $lead, $form)
{
    foreach ($form['fields'] as $field)
    {
        if ($field->id == $field_id)
        {
            $found_term = false;
            $selectedSlug = sanitize_title($lead[$field_id]);
            foreach ($field->choices as $choice)
            {
                if ($choice['value'] == $lead[$field_id])
                {
                    return $choice['value'];
                }

                if ($choice['text'] == 'None')
                {
                    continue;
                }

                $choiceSlug = sanitize_title($choice['text']);
                if ($choiceSlug == $selectedSlug)
                {
                    return $choice['value'];
                }
            }

            $user = wp_get_current_user();

            $term = wp_insert_term($lead[$field_id], 'product-line', array(
                'slug' => "{$user->company_id}-$selectedSlug"
            ));
            add_term_meta($term['term_id'], 'company_id', $user->company_id);

            return $term['term_id'];
        }
    }
}

function ipema_get_certifications($field_id, $lead, $form)
{
    $certifications = array();
    foreach ($form['fields'] as $field)
    {
        if ($field->id != $field_id)
        {
            continue;
        }

        $index = 1;
        foreach ($field->choices as $choice)
        {
            $term = rgar($lead, "$field_id.$index");
            if ($term)
            {
                $term = get_term_by('name', $term, 'certification');
                if ($term)
                {
                    $certifications[] = (int)$term->term_id;
                }
            }
            $index++;
        }
        break;
    }

    return $certifications;
}

function ipema_create_rv($product_id, $lead, $form)
{
    $product = get_post($product_id);
    $types = array(
        3 => 'equipment',
        9 => 'structure',
        10 => 'surfacing',
    );
    wp_set_object_terms($product_id, $types[$form['id']], 'product-type');

    $lead[4] = ipema_product_line_id(4, $lead, $form);
    if (is_numeric($lead[4]))
    {
        wp_set_object_terms($product_id, (int)$lead[4], 'product-line');
    }
    if ($form['id'] == 10)
    {
        wp_set_object_terms($product_id, (int)$lead[11], 'material');
    }
    if (rgar($lead, 1) == '')
    {
        wp_update_post(array(
            'ID' => $product_id,
            'post_title' => ''
        ));
    }
    if (function_exists('relevanssi_publish'))
    {
        relevanssi_publish($product_id, true);
    }

    $documents = json_decode(rgar($lead, 7));
    if ( ! is_array($documents))
    {
        $documents = array();
    }
    foreach ($documents as &$document)
    {
        $document = preg_replace('#^https?://[^/]+#i', '', $document);
    }

    $certifications = ipema_get_certifications(5, $lead, $form);
    $public_id = get_option('rv_autoincrement');
    $public_id++;
    update_option('rv_autoincrement', $public_id);
    $rv_id = wp_insert_post(array(
        'post_type' => 'rv',
        'post_title' => rgar($lead, 2),
        'post_content' => '',
        'post_excerpt' => rgar($lead, 9)
    ));

    add_post_meta($rv_id, '_wpcf_belongs_product_id', $product_id);
    add_post_meta($rv_id, 'public_id', $public_id);
    add_post_meta($rv_id, 'affected_id', $product_id);
    add_post_meta($rv_id, 'request_label', 'New');
    foreach ($documents as $url)
    {
        add_post_meta($rv_id, 'documentation', $url);
    }

    wp_set_object_terms($rv_id, $certifications, 'certification');
    wp_set_post_terms($rv_id, 'test', 'request');

    ipema_rv_email($rv_id);
}
add_action('gform_after_create_post_3', 'ipema_create_rv', 10, 4);
add_action('gform_after_create_post_9', 'ipema_create_rv', 10, 4);
add_action('gform_after_create_post_10', 'ipema_create_rv', 10, 4);

function ipema_process_test_approval($rv, $products)
{
    $certs = get_the_terms($rv->ID, 'certification');
    $product_type = 'equipment';
    $types = get_the_terms($rv->_wpcf_belongs_product_id, 'product-type');
    if ($types && ! is_wp_error($types))
    {
        $product_type = $types[0]->slug;
    }

    $approved = strtotime($rv->post_modified);
    $retest_year = ipema_retest_year($approved);
    $renew_date = ipema_expiration_date($approved, $product_type);

    if ($rv->request_label == 'Add Certification')
    {
        $base = ipema_get_product_base($products[0]);
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
                'fields' => 'ids'
            ));
        }
        else
        {
            $other = get_posts(array(
                'post_type' => 'product',
                'post__in' => $products,
                'tax_query' => array(array(
                    'taxonomy' => 'certification',
                    'operator' => 'EXISTS'
                )),
                'fields' => 'ids'
            ));
        }

        if (count($other) > 0)
        {
            $currentCerts = get_the_terms($other[0], 'certification');
            $renew_date = get_post_meta(
                $other[0],
                $currentCerts[0]->slug,
                true
            );
        }
    }

    foreach ($products as $productID)
    {
        $currentCerts = get_the_terms($productID, 'certification');
        $change = false;
        if ($currentCerts != false)
        {
            foreach ($currentCerts as $prodCert)
            {
                foreach ($certs as $cert)
                {
                    if ($prodCert->term_id == $cert->term_id)
                    {
                        continue 2;
                    }

                    $change = $productID;
                    break 2;
                }
            }
        }

        if ($change)
        {
            $productID = ipema_new_product_change($productID, $rv->post_author);
        }
        else
        {
            $product = get_post($productID);
            wp_update_post(array(
                'ID' => $productID,
                'post_status' => 'publish',
                'post_date' => $product->post_date,
                'edit_date' => 'no'
            ));
        }

        $isRetest = false;
        foreach ($certs as $cert)
        {
            wp_set_object_terms(
                $productID,
                (int)$cert->term_id,
                'certification',
                true
            );

            if ( ! $isRetest && get_post_meta($productID, $cert->slug, true))
            {
                update_post_meta($productID, 'retest_year', $retest_year);
                $isRetest = true;
            }

            update_post_meta($productID, $cert->slug, $renew_date);
        }
        delete_post_meta($productID, 'obsolete');

        if ($change)
        {
            ipema_complete_product_change($productID);
        }
    }
}

function ipema_process_add_approval($rv, $products)
{
    $certs = get_the_terms($rv->ID, 'certification');
    $original = get_post_meta($rv->ID, '_wpcf_belongs_product_id', true);
    $approved = strtotime($rv->post_modified);
    $renew_date = date('Y-' . CERTIFICATION_RENEWAL_DATE, strtotime('+5 years', $approved));

    $base = ipema_get_product_base($products[0]);
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
            'fields' => 'ids'
        ));
    }
    else
    {
        $other = get_posts(array(
            'post_type' => 'product',
            'p' => $products[0],
            'tax_query' => array(array(
                'taxonomy' => 'certification',
                'operator' => 'EXISTS'
            )),
            'fields' => 'ids'
        ));
    }

    if (count($other) > 0)
    {
        $currentCerts = get_the_terms($other[0], 'certification');
        $renew_date = get_post_meta(
            $other[0],
            $currentCerts[0]->slug,
            true
        );
    }

    foreach ($products as $productID)
    {
        $currentCerts = get_the_terms($productID, 'certification');
        $altered = false;
        if ($currentCerts != false)
        {
            foreach ($currentCerts as $prodCert)
            {
                foreach ($certs as $cert)
                {
                    if ($prodCert->term_id == $cert->term_id)
                    {
                        continue 2;
                    }

                    $altered = $productID;
                    break 2;
                }
            }
        }

        if ($altered)
        {
            $productID = ipema_new_product_change($productID, $rv->post_author);
        }
        else
        {
            $product = get_post($productID);
            wp_update_post(array(
                'ID' => $productID,
                'post_status' => 'publish',
                'post_date' => $product->post_date,
                'edit_date' => 'no'
            ));
        }

        foreach ($certs as $cert)
        {
            wp_set_object_terms(
                $productID,
                $cert->term_id,
                'certification',
                true
            );
            update_post_meta($productID, $cert->slug, $renew_date);
        }

        if ($altered)
        {
            ipema_complete_product_change($productID);
        }
    }
}

function ipema_process_family_change_approval($rv, $affected)
{
    $foundCerts = array();

    $base = get_the_terms($rv->ID, 'base');
    if ($base == false)
    {
        foreach ($affected as $productID)
        {
            $newID = ipema_new_product_change($productID, $rv->post_author);
            wp_delete_object_term_relationships($newID, 'base');
            ipema_complete_product_change($newID);
        }

        return;
    }
    $base = $base[0]->term_id;

    foreach ($affected as $productID)
    {
        $newID = ipema_new_product_change($productID, $rv->post_author);

        $certs = get_the_terms($newID, 'certification');
        if ($certs == false)
        {
            $certs = array();
        }
        foreach ($certs as $cert)
        {
            if ( ! array_key_exists($cert->term_id, $foundCerts))
            {
                $foundCerts[$cert->term_id] = false;

                $newest = get_posts(array(
                    'post_type' => 'product',
                    'tax_query' => array(array(
                        'taxonomy' => 'base',
                        'terms' => $base
                    )),
                    'meta_key' => $cert->slug,
                    'orderby' => 'meta_value',
                    'fields' => 'ids',
                    'posts_per_page' => 1
                ));

                if (count($newest) > 0)
                {
                    $renewal = get_post_meta($newest[0], $cert->slug, true);
                    if (strtotime($renewal) > time())
                    {
                        $foundCerts[$cert->term_id] = $renewal;
                    }
                }
            }

            if ($foundCerts[$cert->term_id])
            {
                update_post_meta(
                    $newID,
                    $cert->slug,
                    $foundCerts[$cert->term_id]
                );
            }

        }
        wp_set_object_terms($newID, $base, 'base');

        ipema_complete_product_change($newID);
    }
}

function ipema_process_edit_approval($rv)
{
    $newID = get_post_meta($rv->ID, '_wpcf_belongs_product-change_id', true);
    ipema_complete_product_change($newID);

    $productID = get_post_meta($rv->ID, '_wpcf_belongs_product_id', true);

    $brokenRV = get_posts(array(
        'post_type' => 'rv',
        'meta_query' => array(array(
            'key' => 'last_feature_change_product',
            'value' => $productID
        )),
        'fields' => 'ids',
        'nopaging' => true
    ));

    foreach ($brokenRV as $rvID)
    {
        update_post_meta($rvID, '_wpcf_belongs_product_id', $newID);
        delete_post_meta($rvID, 'last_feature_change_product');
    }

    update_post_meta($rv->ID, 'last_feature_change_product', $productID);
}

function ipema_process_shared_features_approval($rv, $affected)
{
    $base = get_the_terms($rv->ID, 'base');
    $base = $base[0]->term_id;

    $productLine = false;
    $eraseProductLine = false;
    if (get_post_meta($rv->ID, 'force_product_line', true))
    {
        $productLine = get_term_meta($base, 'product_line', true);
        if ($productLine)
        {
            $productLine = (int)$productLine;
        }
        else
        {
            $eraseProductLine = true;
        }
    }

    $oldBase = ipema_get_product_base($affected[0]);
    $oldMaterial = get_term_meta($oldBase->term_id, 'material', true);
    $oldThkToHt = get_term_meta($oldBase->term_id, 'thickness_to_height', true);

    $material = get_term_meta($base, 'material', true);
    $thkToHt = get_term_meta($base, 'thickness_to_height', true);

    if ($material == $oldMaterial)
    {
        $material = false;
    }
    elseif ($material)
    {
        $material = (int)$material;
    }

    if ($thkToHt == $oldThkToHt)
    {
        $thkToHt = false;
    }

    foreach ($affected as $productID)
    {
        $newID = ipema_new_product_change($productID, $rv->post_author);

        wp_set_object_terms($newID, $base, 'base');

        if ($productLine)
        {
            wp_set_object_terms($newID, $productLine, 'product-line');
        }
        if ($eraseProductLine)
        {
            wp_delete_object_term_relationships($newID, 'product-line');
        }
        if ($material)
        {
            wp_set_object_terms($newID, $material, 'material');
        }
        if ($thkToHt)
        {
            update_post_meta($newID, 'thickness_to_height', $thkToHt);
        }

        ipema_complete_product_change($newID);
    }
}

function ipema_process_remove_certification($rv, $affected)
{
    $certs = get_the_terms($rv->ID, 'certification');
    foreach ($affected as $productID)
    {
        $newID = ipema_new_product_change($productID);
        foreach ($certs as $cert)
        {
            wp_remove_object_terms(
                $newID,
                $cert->term_id,
                'certification'
            );
        }

        if (get_the_terms($newID, 'certification') == false)
        {
            wp_update_post(array(
                'ID' => $newID,
                'post_status' => 'draft'
            ));
        }
        ipema_complete_product_change($newID);
    }
}

function ipema_process_restore_certification($rv, $affected)
{
    $rvCerts = get_the_terms($rv->ID, 'certification');
    $certs = array();
    foreach ($rvCerts as $cert)
    {
        $certs[] = $cert->term_id;
    }
    foreach ($affected as $productID)
    {
        $currentCerts = get_the_terms($productID, 'certification');
        if ($currentCerts != false)
        {
            $productID = ipema_new_product_change($productID);
        }
        wp_set_object_terms(
            $productID,
            $certs,
            'certification',
            true
        );
        if ($currentCerts == false)
        {
            wp_update_post(array(
                'ID' => $productID,
                'post_status' => 'publish'
            ));
        }
        else
        {
            ipema_complete_product_change($productID);
        }
    }
}

function ipema_process_change_expiration($rv, $affected)
{
    $certs = get_the_terms($rv->ID, 'certification');
    $updates = array();
    foreach ($certs as $cert)
    {
        $updates[$cert->slug] = get_post_meta($rv->ID, $cert->slug, true);
    }
    foreach ($affected as $productID)
    {
        $newID = ipema_new_product_change($productID);
        foreach ($updates as $slug => $expiration)
        {
            update_post_meta($newID, $slug, $expiration);
        }
        ipema_complete_product_change($newID);
    }
}

function ipema_process_rvs()
{
    if ( ! array_key_exists('run', $_GET))
    {
        $_GET['run'] = ipema_generate_code(4);
    }

    $lockfile = __DIR__ . '/rv-cron.lock';
    $lock = file_get_contents($lockfile);
    if ($lock)
    {
        list($run, $time) = explode(' ', $lock);
        if ($run != $_GET['run'])
        {
            if (strtotime('5 minutes ago') < $time)
            {
                return;
            }
        }
    }
    $now = time();
    file_put_contents($lockfile, "{$_GET['run']} $now");

    if (array_key_exists('rv', $_GET))
    {
        $rv = get_post($_GET['rv']);
        $offset = $_GET['offset'];
        $destination = $_GET['destination'];
    }
    else
    {
        $rv = get_posts(array(
            'post_type' => 'rv',
            'meta_query' => array(array(
                'key' => 'new',
                'value' => true
            )),
            'orderby' => 'modified',
            'order' => 'ASC',
            'posts_per_page' => 1
        ));

        if (count($rv) == 0)
        {
            unlink($lockfile);
            return;
        }

        $rv = $rv[0];
        $offset = 0;
    }

    if (array_key_exists('destination', $_GET))
    {
        $destination = $_GET['destination'];
    }
    else
    {
        $destination = add_query_arg(array('run' => $_GET['run']));
    }

    ipema_chunk_approval($rv, $offset, $destination);
}

function ipema_chunk_approval($rv, $offset, $destination)
{
    global $rvChunk, $wpdb;

    // Use SQL query instead of get_post_meta to ensure order doesn't change.
    $sql = "SELECT
              meta_value
            FROM
              $wpdb->postmeta
            WHERE
              post_id = %s
            AND
              meta_key = 'affected_id'
            ORDER BY
              meta_value ASC";
    $affected = $wpdb->get_col($wpdb->prepare($sql, $rv->ID));

    $lastGroup = count($affected) <= $rvChunk * ($offset + 1);

    $affected = array_slice($affected, $offset * $rvChunk, $rvChunk);

    $type = get_the_terms($rv->ID, 'request')[0]->slug;
    if ($type == 'test')
    {
        ipema_process_test_approval($rv, $affected);
    }
    elseif (strpos($type, 'add') === 0)
    {
        ipema_process_add_approval($rv, $affected);
    }
    elseif ($type == 'family' || $type == 'leave')
    {
        ipema_process_family_change_approval($rv, $affected);
    }
    elseif ($type == 'shared-features')
    {
        ipema_process_shared_features_approval($rv, $affected);
    }
    elseif ($type == 'edit')
    {
        ipema_process_edit_approval($rv);
    }
    elseif ($type == 'remove')
    {
        ipema_process_remove_certification($rv, $affected);
    }
    elseif ($type == 'restore')
    {
        ipema_process_restore_certification($rv, $affected);
    }
    elseif ($type == 'expiration')
    {
        ipema_process_change_expiration($rv, $affected);
    }

    if ($lastGroup)
    {
        delete_post_meta($rv->ID, 'new');
        wp_redirect($destination);
        die();
    }


    wp_redirect(add_query_arg(array(
        'rv_chunk' => $rv->ID,
        'offset' => $offset + 1,
        'destination' => urlencode($destination)
    )));
    die();
}

add_action('template_redirect', function() {
    if ( ! array_key_exists('rv_chunk', $_GET))
    {
        return;
    }
    $rv = get_post($_GET['rv_chunk']);

    ipema_chunk_approval($rv, $_GET['offset'], $_GET['destination']);
});

function ipema_review_rv($entry, $form)
{
    $user = wp_get_current_user();
    $rv = get_post($_GET['request']);

    if ($rv->post_status != 'draft')
    {
        return;
    }

    wp_update_post(array(
        'ID' => $rv->ID,
        'post_date' => $rv->post_date,
        'post_status' => 'publish',
        'post_content' => rgar($entry, 2) . rgar($entry, 3),
        'edit_date' => 'no'
    ));
    add_post_meta($rv->ID, 'reviewer', $user->ID);
    add_post_meta($rv->ID, 'status', rgar($entry, 1));

    if (rgar($entry, 1) == 'approved')
    {
        add_post_meta($rv->ID, 'new', true);
    }
}
add_action('gform_after_submission_4', 'ipema_review_rv', 10, 2);

function ipema_term_conjunction($terms)
{
    if (count($terms) == 1)
    {
        return $terms[0]->name;
    }

    if (count($terms) == 2)
    {
        return $terms[0]->name . ' and '
            . $terms[1]->name;
    }

    $str = '';
    $lastTerm = array_pop($terms);
    foreach ($terms as $term)
    {
        $str .= $term->name . ', ';
    }
    $str .= ' and ' . $lastTerm->name;

    return $str;
}

function ipema_rejection_email($email, $form, $entry)
{
    /*if ($email['name'] != 'Rejection Notification')
    {
        return $email;
    }*/

    $rv_id = $_GET['request'];
    $rv = get_post($rv_id);
    $request = get_the_terms($rv_id, 'request')[0]->slug;
    $product_id = get_post_meta($rv_id, '_wpcf_belongs_product_id', true);

    $model_number = ipema_model_number(array('product' => $product_id));
    $model_name = ipema_brand_name(array('product' => $product_id));

    $product_name = $model_number;
    $short_product = $model_number;
    if (strlen($model_name) > 0)
    {
        $product_name .= " ($model_name)";
        $short_product = $model_name;
    }

    if ($request == 'test' || $request == 'add')
    {
        $action = "certify $product_name for the ";

        $certifications = get_the_terms($rv_id, 'certification');
        $action .= ipema_term_conjunction($certifications) . ' standard';

        if (count($certifications) > 1)
        {
            $action .= 's';
        }
    }
    elseif ($request == 'add-model')
    {
        $action = "add models to the family containing $product_name";
    }
    elseif ($request == 'add-certification')
    {
        $certifications = get_the_terms($rv_id, 'certification');
        $action = 'add ' . ipema_term_conjunction($certifications)
            . ' certification';
        if (count($certifications) > 1)
        {
            $action .= 's';
        }

        $action .= ' to ';

        $affected = get_post_meta($rv_id, 'affected_id');
        if (count($affected == 1))
        {
            $action .= ipema_product_display_name($affected[0]);
        }
        else
        {
            $action .= 'these models';
        }
    }
    elseif ($request == 'family')
    {
        $action = "move models to family containing $product_name";
    }
    elseif ($request == 'leave')
    {
        $action = "remove $product_name from family";
    }
    elseif ($request == 'shared-features')
    {
        $action = "change shared features of family containing $product_name";
    }
    elseif ($request == 'edit')
    {
        $action = "change information for $product_name";
    }

    $mergeTags = array(
        '{rv-url}' => get_page_link(360) . "?request=$rv_id",
        '{product}' => html_entity_decode($product_name),
        '{product-short}' => html_entity_decode($short_product),
        '{action}' => $action
    );

    $email['to'] = get_user_by('id', $rv->post_author)->user_email;

    $email['subject'] = str_replace(
        array_keys($mergeTags),
        array_values($mergeTags),
        $email['subject']
    );

    $email['message'] = str_replace(
        array_keys($mergeTags),
        array_values($mergeTags),
        $email['message']
    );

    return $email;
}
add_filter('gform_notification_4', 'ipema_rejection_email', 10, 3);

function ipema_reply_rv($data, $form, $entry)
{
    if ($form['id'] != 14)
    {
        return $data;
    }

    $documents = json_decode(rgar($entry, 2));
    if ( ! is_array($documents))
    {
        $documents = array();
    }
    foreach ($documents as &$document)
    {
        $document = preg_replace('#^https?://[^/]+#i', '', $document);
    }

    $originalRV = get_post($_GET['request']);
    $public_id = get_option('rv_autoincrement');
    $public_id++;
    update_option('rv_autoincrement', $public_id);

    $data['post_type'] = 'rv';
    $data['post_category'] = array();
    $data['post_title'] = $originalRV->post_title;
    $data['post_parent'] = $_GET['request'];
    $data['post_custom_fields']['documentation'] = $documents;
    $data['post_custom_fields']['reviewer'] = NULL;
    $data['post_custom_fields']['status'] = NULL;
    $data['post_custom_fields']['public_id'] = $public_id;

    $meta = get_post_meta($_GET['request']);
    foreach ($meta as $key => $value)
    {
        if (array_key_exists($key, $data['post_custom_fields']))
        {
            continue;
        }
        if ($key[0] == '_' && strpos($key, '_wpcf_belongs_product' !== 0))
        {
            continue;
        }

        $data['post_custom_fields'][$key] = $value;
    }
    $data['post_custom_fields'] = array_filter($data['post_custom_fields']);

    $label = get_post_meta($_GET['request'], 'request_label', true);
    if ($label)
    {
        $data['post_custom_fields']['request_label'] = $label;
    }

    return $data;
}
add_filter('gform_post_data', 'ipema_reply_rv', 10, 3);

function ipema_reply_rv_cert($rv_id, $lead, $form)
{
    $certs = get_the_terms($_GET['request'], 'certification');
    $slugs = array();
    foreach ($certs as $cert)
    {
        $slugs[] = $cert->slug;
    }
    wp_set_object_terms($rv_id, $slugs, 'certification');

    $request = get_the_terms($_GET['request'], 'request')[0]->slug;
    wp_set_object_terms($rv_id, $request, 'request');

    $bases = get_the_terms($_GET['request'], 'base');
    foreach ($bases as $base)
    {
        wp_set_object_terms($rv_id, $base->term_id, 'base', true);
    }

    ipema_rv_email($rv_id);
}
add_action('gform_after_create_post_14', 'ipema_reply_rv_cert', 10, 4);

function ipema_make_base_model($entry, $form)
{
    $user = wp_get_current_user();

    $entry[4] = ipema_product_line_id(4, $entry, $form);

    if ($entry[2])
    {
        $slug = $user->company_id . '-' . sanitize_title($entry[2]);
    }
    else
    {
        $entry[2] = '~Unnamed~';
        $slug = $user->company_id . '-' . ipema_generate_code(4);
    }
    while (get_term_by('slug', $slug, 'base') !== false)
    {
        $slug = $user->company_id . '-' . ipema_generate_code(4);
    }

    $term = wp_insert_term($entry[2], 'base', array(
        'slug' => $slug,
        'description' => $entry[3]
    ));

    if (is_numeric($entry[4]))
    {
        add_term_meta($term['term_id'], 'product_line', $entry[4]);
    }
    add_term_meta($term['term_id'], 'name', $entry[1]);
    add_term_meta($term['term_id'], 'company_id', $user->company_id);
    if ($entry['5.1'])
    {
        add_term_meta($term['term_id'], 'canadian', true);
        add_term_meta($term['term_id'], 'french-description', $entry[6]);
    }

    if ($form['id'] == 11)
    {
        $thk_to_ht = "{$entry[7]}:{$_POST['input_7b']}";
        add_term_meta($term['term_id'], 'thickness_to_height', $thk_to_ht);
        add_term_meta($term['term_id'], 'material', $entry[8]);
    }

    $GLOBALS['base-model'] = $term['term_id'];

    return $entry;
}
add_filter('gform_entry_post_save_5', 'ipema_make_base_model', 10, 2);
add_filter('gform_entry_post_save_11', 'ipema_make_base_model', 10, 2);

function ipema_base_model_redirect($confirmation, $form, $entry, $is_ajax)
{
    $confirmation['redirect'] .= '?base=' . $GLOBALS['base-model'];

    return $confirmation;
}
add_filter('gform_confirmation_5', 'ipema_base_model_redirect', 10, 4);
add_filter('gform_confirmation_11', 'ipema_base_model_redirect', 10, 4);

function ipema_hide_french($form)
{
    if ($form['id'] != 6 && $form['id'] != 12)
    {
        return $form;
    }

    $baseID = (int)$_GET['base'];
    $include_french = get_term_meta($baseID, 'canadian', true);
    if ( ! $include_french)
    {
        $siblings = get_objects_in_term($baseID, 'base');
        foreach ($siblings as $sibling)
        {
            $certs = wp_get_object_terms($sibling, 'certification');
            foreach ($certs as $cert)
            {
                if (get_term_meta($cert->term_id, 'canadian'))
                {
                    update_term_meta($baseID, 'canadian', true);
                    $include_french = true;
                    break 2;
                }
            }
        }
    }

    if ( ! $include_french)
    {
        foreach ($form['fields'] as $key => $field)
        {
            if (in_array($field->id, array(5, 8)))
            {
                unset($form['fields'][$key]);
            }
        }
    }

    return $form;
}
add_filter('gform_pre_render', 'ipema_hide_french');

function ipema_submodel_fields($html, $field, $value, $entry_id, $form_id)
{
    if ($form_id != 6 && $form_id != 12)
    {
        return $html;
    }

    if ($field->id == 1)
    {
        $name = get_term_meta((int)$_GET['base'], 'name', true);
        $html = str_replace('<input', "$name <input", $html);
    }
    elseif ($field->id == 2)
    {
        $term = get_term((int)$_GET['base'], 'base');
        if ($term->name != '~Unnamed~')
        {
            $html = str_replace('<input', $term->name . '<input', $html);
        }
    }
    elseif ($field->id == 8)
    {
        $prefix = get_term_meta((int)$_GET['base'], 'french_prefix', true);
        $html = str_replace('<input', "$prefix <input", $html);
    }

    return $html;
}
add_filter('gform_field_content', 'ipema_submodel_fields', 10, 5);

function ipema_submodel_submit($html, $form)
{
    $html = str_replace('value=', 'name="action" value=', $html);
    $second = $html;
    $second = preg_replace(
        '/value=["\'][\w ]+["\']/',
        'value="No More Models"',
        $second
    );
    $second = preg_replace('/id=["\'](\w+)["\']/', 'id="$1b"', $second);
    return "$html $second";
}
add_filter('gform_submit_button_6', 'ipema_submodel_submit', 10, 2);
add_filter('gform_submit_button_12', 'ipema_submodel_submit', 10, 2);

function ipema_allow_blank_modification($result)
{
    if ($result['is_valid'])
    {
        return $result;
    }

    if (rgpost('action') != 'No More Models')
    {
        return $result;
    }

    $blank = true;
    $form = $result['form'];
    foreach ($form['fields'] as $field)
    {
        if ($field->id == 7)
        {
            // Product line might be pre-populated. Skip it.
            continue;
        }
        if (rgpost("input_{$field->id}"))
        {
            $blank = false;
            break;
        }
    }

    if ($blank == false)
    {
        return $result;
    }

    $mods = get_posts(array(
        'post_type' => 'product',
        'post_status' => 'draft',
        'tax_query' => array(array(
            'taxonomy' => 'base',
            'field' => 'term_id',
            'terms' => $_GET['base']
        ))
    ));

    if (count($mods) > 0)
    {
        foreach ($form['confirmations'] as $settings)
        {
            if ($settings['name'] == 'Send to Review')
            {
                $url = get_permalink($settings['pageId'])
                    . '?base=' . $_GET['base'];

                wp_redirect($url);
                die();
            }
        }
    }

    return $result;
}
add_filter('gform_validation_6', 'ipema_allow_blank_modification');
add_filter('gform_validation_12', 'ipema_allow_blank_modification');

function ipema_finalize_variant($product_id, $lead, $form)
{
    $types = array(
        6 => 'equipment',
        12 => 'surfacing',
    );
    wp_set_object_terms($product_id, $types[$form['id']], 'product-type');
    wp_set_object_terms($product_id, (int)$_GET['base'], 'base');

    $product_line = ipema_product_line_id(7, $lead, $form);
    if (is_numeric($product_line))
    {
        wp_set_object_terms($product_id, (int)$product_line, 'product-line');
    }

    $thk_to_ht = get_term_meta((int)$_GET['base'], 'thickness_to_height', true);
    if (mb_strlen($thk_to_ht) > 0)
    {
        update_post_meta($product_id, 'thickness_to_height', $thk_to_ht);
        $material = get_term_meta((int)$_GET['base'], 'material', true);
        wp_set_object_terms($product_id, (int)$material, 'material');
    }

    if (rgar($lead, 1) == '')
    {
        wp_update_post(array(
            'ID' => $product_id,
            'post_title' => ''
        ));
    }

    if (function_exists('relevanssi_publish'))
    {
        relevanssi_publish($product_id, true);
    }
}
add_action('gform_after_create_post_6', 'ipema_finalize_variant', 10, 4);
add_action('gform_after_create_post_12', 'ipema_finalize_variant', 10, 4);

function ipema_variant_redirect($confirmation, $form, $entry, $is_ajax)
{
    if ($_POST['action'] == 'No More Models')
    {
        foreach ($form['confirmations'] as $settings)
        {
            if ($settings['name'] == 'Send to Review')
            {
                $confirmation['redirect'] = get_permalink($settings['pageId']);
                $confirmation['redirect'] .= '?base=' . $_GET['base'];
            }
        }
    }

    return $confirmation;
}
add_filter('gform_confirmation_6', 'ipema_variant_redirect', 10, 4);
add_filter('gform_confirmation_12', 'ipema_variant_redirect', 10, 4);

function ipema_populate_variants($form)
{
    $fields = array(
        7 => 1,
        13 => 1,
    );

    $variants = get_posts(array(
        'post_type' => 'product',
        'tax_query' => array(array(
            'taxonomy' => 'base',
            'terms' => $_GET['base']
        )),
        'post_status' => 'any',
        'order' => 'ASC',
        'nopaging' => true,
        'fields' => 'ids'
    ));

    $testedModel = NULL;
    $rv = get_posts(array(
        'post_type' => 'rv',
        'post_status' => 'any',
        'meta_query' => array(
            array(
                'key' => '_wpcf_belongs_product_id',
                'value' => $variants,
                'compare' => 'IN'
            ),
            array(
                'key' => 'status',
                'value' => 'approved'
            )
        ),
        'tax_query' => array(array(
            'taxonomy' => 'request',
            'terms' => 'test',
            'field' => 'slug'
        )),
        'posts_per_page' => 1,
        'fields' => 'ids'
    ));
    if (count($rv) == 1)
    {
        $testedModel = get_post_meta(
            $rv[0],
            '_wpcf_belongs_product_id',
            true
        );
    }

    $choices = array();
    foreach ($variants as $variantID)
    {
        $choices[] = array(
            'value' => $variantID,
            'text' => ipema_product_display_name($variantID),
            'isSelected' => ($variantID == $testedModel)
        );
    }
    usort($choices, 'ipema_sort_models');

    foreach ($form['fields'] as &$field)
    {
        if ($field->id == $fields[$form['id']])
        {
            $field->choices = $choices;

            if ($testedModel)
            {
                $user = wp_get_current_user();
                $oldStyle = get_post_meta(
                    $user->company_id,
                    'show_rv_description',
                    true
                );
                if ( ! $oldStyle)
                {
                    $field->visibility = 'hidden';
                }
            }
        }
    }

    return $form;
}
add_filter('gform_pre_render_7', 'ipema_populate_variants');
add_filter('gform_pre_validation_7', 'ipema_populate_variants');
add_filter('gform_pre_submission_filter_7', 'ipema_populate_variants');
add_filter('gform_pre_render_13', 'ipema_populate_variants');
add_filter('gform_pre_validation_13', 'ipema_populate_variants');
add_filter('gform_pre_submission_filter_13', 'ipema_populate_variants');

function ipema_validate_base_model($result)
{
    if ($result['is_valid'] == false)
    {
        return $result;
    }

    $lead = GFFormsModel::get_current_lead();
    $form = $result['form'];

    $base = rgpost('input_1');
    $certs = ipema_get_certifications(2, $lead, $form);

    $errors = array();
    $approved = get_the_terms($base, 'certification');
    if ($approved == false)
    {
        $other = get_posts(array(
            'post_type' => 'product',
            'post_status' => 'any',
            'tax_query' => array(
                array(
                    'taxonomy' => 'base',
                    'terms' => $_GET['base']
                ),
                array(
                    'taxonomy' => 'certification',
                    'terms' => $certs
                )
            ),
            'posts_per_page' => 1,
            'fields' => 'ids'
        ));

        if (count($other) > 0)
        {
            $errors = $certs;
        }
    }
    else
    {
        foreach ($certs as $certID)
        {
            $matched = false;
            foreach ($approved as $check)
            {
                if ($check->term_id == $certID)
                {
                    $matched = true;
                    break;
                }
            }

            if ( ! $matched)
            {
                $errors[] = $certID;
            }
        }
    }

    if (count($errors) > 0)
    {
        foreach ($form['fields'] as &$field)
        {
            if ($field->id == 1)
            {
                $msg = 'This model does not have ';
                if (count($errors) == 1)
                {
                    $cert = get_term($errors[0]);
                    $msg .= $cert->name . ' certification';
                }
                else
                {
                    $msg .= 'the following certifications:<br>';
                    foreach ($errors as $certID)
                    {
                        $cert = get_term($certID);
                        $msg .= $cert->name . '<br>';
                    }
                }

                $field->failed_validation = true;
                $field->validation_message = $msg;
                break;
            }
        }

        $result['form'] = $form;
        $result['is_valid'] = false;
    }

    return $result;
}
add_filter('gform_validation_7', 'ipema_validate_base_model');
add_filter('gform_validation_13', 'ipema_validate_base_model');

function ipema_create_base_rv($lead, $form)
{
    $documents = json_decode(rgar($lead, 3));
    if ( ! is_array($documents))
    {
        $documents = array();
    }
    foreach ($documents as &$document)
    {
        $document = preg_replace('#^https?://[^/]+#i', '', $document);
    }

    $original_id = null;
    $certifications = ipema_get_certifications(2, $lead, $form);
    $public_id = get_option('rv_autoincrement');
    $public_id++;
    update_option('rv_autoincrement', $public_id);

    $model =  ipema_model_number(array('product' => rgar($lead, 1)));
    $rv_id = wp_insert_post(array(
        'post_type' => 'rv',
        'post_title' => $model,
        'post_content' => '',
        'post_excerpt' => rgar($lead, 4)
    ));

    add_post_meta($rv_id, '_wpcf_belongs_product_id', rgar($lead, 1));
    add_post_meta($rv_id, 'public_id', $public_id);
    add_post_meta($rv_id, 'request_label', 'New');
    foreach ($documents as $url)
    {
        add_post_meta($rv_id, 'documentation', $url);
    }

    wp_set_object_terms($rv_id, $certifications, 'certification');

    $affected = get_posts(array(
        'post_type' => 'product',
        'post_status' => 'any',
        'tax_query' => array(array(
            'taxonomy' => 'base',
            'terms' => $_GET['base']
        )),
        'nopaging' => true,
        'fields' => 'ids'
    ));

    $affectedCount = 0;
    foreach ($affected as $productID)
    {
        $rv = get_posts(array(
            'post_type' => 'rv',
            'post_status' => 'any',
            'meta_query' => array(
                array(
                    'key' => 'affected_id',
                    'value' => $productID
                ),
                array(
                    'key' => 'status',
                    'value' => 'canceled',
                    'compare' => '!='
                )
            ),
            'tax_query' => array(array(
                'taxonomy' => 'request',
                'terms' => 'test',
                'field' => 'slug'
            )),
            'posts_per_page' => 1,
            'fields' => 'ids'
        ));

        if (count($rv) == 0)
        {
            add_post_meta($rv_id, 'affected_id', $productID);
            $affectedCount++;
        }
    }

    if (count($affected) == $affectedCount)
    {
        wp_set_post_terms($rv_id, 'test', 'request');
    }
    elseif (ipema_is_new(rgar($lead, 1)))
    {
        wp_set_post_terms($rv_id, 'test', 'request');
    }
    else
    {
        wp_set_post_terms($rv_id, 'add-model', 'request');
    }

    if ($affectedCount == 0)
    {
        $user = wp_get_current_user();
        add_post_meta($rv_id, 'affected_id', 0);
        add_post_meta($rv->ID, 'reviewer', $user->ID);
        add_post_meta($rv->ID, 'status', 'canceled');

        return;
    }

    ipema_rv_email($rv_id);
}
add_action('gform_after_submission_7', 'ipema_create_base_rv', 10, 2);
add_action('gform_after_submission_13', 'ipema_create_base_rv', 10, 2);

$ipema_thk_ht_fields = array(
    10 => 10,
    11 => 7,
    42 => 10,
    43 => 8
);
function ipema_thk_to_ht_ratio_field($html, $field, $value, $entry_id, $form_id)
{
    global $ipema_thk_ht_fields;

    if (array_key_exists($form_id, $ipema_thk_ht_fields) && $field->id == $ipema_thk_ht_fields[$form_id])
    {
        preg_match('/<input[^>]+>/', $html, $matches);

        $new_field = str_replace(
            "{$ipema_thk_ht_fields[$form_id]}'",
            "{$ipema_thk_ht_fields[$form_id]}b'",
            $matches[0]
        );

        $new_field = preg_replace(
            '/value=[\'"][^\'"]*[\'"]/',
            'value="' . rgpost('input_' . $field->id . 'b') .'"',
            $new_field
        );

        $new_input = $matches[0] . ' inches for ' . $new_field . ' feet';
        $html = str_replace($matches[0], $new_input, $html);
    }

    return $html;
}
add_filter('gform_field_content', 'ipema_thk_to_ht_ratio_field', 10, 5);

function ipema_validate_thk_to_ht_ratio($result, $value, $form, $field)
{
    global $ipema_thk_ht_fields;

    if ( ! $result['is_valid'])
    {
        return $result;
    }

    $field_id = $ipema_thk_ht_fields[$form['id']];
    if ( ! is_numeric($_POST["input_{$field_id}b"]))
    {
        $result['is_valid'] = false;
        $result['message'] = 'Height in feet must be a number';
    }
    elseif ($value <= 0)
    {
        $result['is_valid'] = false;
        $result['message'] = 'Thickness in inches must be positive';
    }
    elseif ($_POST["input_{$field_id}b"] <= 0)
    {
        $result['is_valid'] = false;
        $result['message'] = 'Height in feet must be positive';
    }

    return $result;
}
add_filter('gform_field_validation_10_10', 'ipema_validate_thk_to_ht_ratio', 10, 4);
add_filter('gform_field_validation_11_7', 'ipema_validate_thk_to_ht_ratio', 10, 4);
add_filter('gform_field_validation_42_10', 'ipema_validate_thk_to_ht_ratio', 10, 4);
add_filter('gform_field_validation_43_8', 'ipema_validate_thk_to_ht_ratio', 10, 4);

function ipema_record_thk_to_ht_ratio($post_data, $form, $entry)
{
    if ($form['id'] != 10)
    {
        return $post_data;
    }

    $thk_to_ht = "{$entry[10]}:{$_POST['input_10b']}";
    $post_data['post_custom_fields']['thickness_to_height'] = $thk_to_ht;

    return $post_data;
}
add_filter('gform_post_data', 'ipema_record_thk_to_ht_ratio', 10, 3);

function ipema_show_renew_model($form)
{
    $product = ipema_product_display_name($_GET['model']);
    $base = ipema_get_product_base($_GET['model']);

    foreach ($form['fields'] as &$field)
    {
        if ($field->id == 5)
        {
            if ($base == false)
            {
                $field->visibility = 'hidden';
            }
            else
            {
                $field->content = str_replace(
                    array('{model}', '{family-slug}'),
                    array($product, $base->slug),
                    $field->content
                );
            }
        }
    }

    return $form;
}
add_filter('gform_pre_render_16', 'ipema_show_renew_model');
add_filter('gform_pre_validation_16', 'ipema_show_renew_model');
add_filter('gform_pre_submission_filter_16', 'ipema_show_renew_model');
add_filter('gform_pre_render_17', 'ipema_show_renew_model');
add_filter('gform_pre_validation_17', 'ipema_show_renew_model');
add_filter('gform_pre_submission_filter_17', 'ipema_show_renew_model');

function ipema_renewal_rv($entry, $form)
{
    $modelID = $_GET['model'];

    $documents = json_decode(rgar($entry, 3));
    if ( ! is_array($documents))
    {
        $documents = array();
    }
    foreach ($documents as &$document)
    {
        $document = preg_replace('#^https?://[^/]+#i', '', $document);
    }

    $base = get_the_terms($modelID, 'base');
    if ($base == false)
    {
        $public_id = get_option('rv_autoincrement');
        $public_id++;
        update_option('rv_autoincrement', $public_id);

        $rv_id = wp_insert_post(array(
            'post_type' => 'rv',
            'post_title' => ipema_model_number(array('product' => $modelID)),
            'post_content' => '',
            'post_excerpt' => rgar($entry, 4)
        ));

        add_post_meta($rv_id, '_wpcf_belongs_product_id', $modelID);
        add_post_meta($rv_id, 'public_id', $public_id);
        add_post_meta($rv_id, 'affected_id', $modelID);
        add_post_meta($rv_id, 'request_label', 'Retest');

        foreach ($documents as $url)
        {
            add_post_meta($rv_id, 'documentation', $url);
        }

        $productCerts = get_the_terms($modelID, 'certification');
        $certifications = array();
        foreach ($productCerts as $cert)
        {
            $certifications[] = $cert->term_id;
        }

        wp_set_object_terms($rv_id, $certifications, 'certification');
        wp_set_object_terms($rv_id, 'test', 'request');

        ipema_rv_email($rv_id);

        return;
    }

    $affected = get_posts(array(
        'post_type' => 'product',
        'post_status' => 'any',
        'tax_query' => array(
            array(
                'taxonomy' => 'base',
                'terms' => $base[0]->term_id
            ),
            array(
                'taxonomy' => 'certification',
                'operator' => 'EXISTS'
            )
        ),
        'nopaging' => true
    ));

    $certs = array();
    foreach ($affected as $product)
    {
        $productCerts = get_the_terms($product->ID, 'certification');
        foreach ($productCerts as $cert)
        {
            if ( ! array_key_exists($cert->term_id, $certs))
            {
                $certs[$cert->term_id] = array();
            }

            $certs[$cert->term_id][] = $product->ID;
        }
    }

    $rv = array(
        'post_type' => 'rv',
        'post_title' => ipema_model_number(array('product' => $modelID)),
        'post_content' => '',
        'post_excerpt' => rgar($entry, 4),
        'meta_input' => array(
            '_wpcf_belongs_product_id' => $modelID,
            'documentation' => $documents,
            'request_label' => 'Retest'
        ),
        'tax_input' => array(
            'request' => 'test'
        )
    );
    ipema_split_rv($certs, $rv);
}
add_action('gform_after_submission_16', 'ipema_renewal_rv', 10, 2);
add_action('gform_after_submission_17', 'ipema_renewal_rv', 10, 2);

function ipema_user_company_active($type='active')
{
    $user = wp_get_current_user();
    if ($user === false || ! $user->company_id)
    {
        return false;
    }

    $year = get_post_meta($user->company_id, $type, true);

    return $year >= ipema_current_year();
}

function ipema_user_controls_product($product_id)
{
    $model = get_post($product_id);
    if ($model !== NULL)
    {
        $user = wp_get_current_user();
        $product_company_id = get_post_meta(
            $model->ID,
            '_wpcf_belongs_company_id',
            true
        );
        if ($user->company_id == $product_company_id)
        {
            return true;
        }
    }

    return false;
}

function ipema_allowed($page)
{
    if (current_user_can('manage_options') && $_GET['preview'] == 'true')
    {
        return true;
    }
    switch ($page->post_name) {
        case 'join':
            if (is_user_logged_in())
            {
                return false;
            }
            break;

        case 'members':
            if ( ! is_user_logged_in())
            {
                return false;
            }

            $user = wp_get_current_user();
            if ($user === false || ! $user->company_id)
            {
                return false;
            }

            if (current_user_can('can_validate_products'))
            {
                return false;
            }

            break;

        case 'manage':
            if (is_numeric($_GET['model']))
            {
                if ( ! ipema_user_controls_product($_GET['model']))
                {
                    return false;
                }
            }
            elseif (is_numeric($_GET['request']))
            {
                $modelID = get_post_meta(
                    $_GET['request'],
                    '_wpcf_belongs_product_id',
                    true
                );
                if ( ! ipema_user_controls_product($modelID))
                {
                    return false;
                }
            }
            elseif (is_numeric($_GET['base']))
            {
                $products = get_posts(array(
                    'post_status' => 'any',
                    'post_type' => 'product',
                    'tax_query' => array(array(
                        'taxonomy' => 'base',
                        'field' => 'term_id',
                        'terms' => $_GET['base']
                    )),
                    'posts_per_page' => 1
                ));

                if (count($products) == 0)
                {
                    return false;
                }
                elseif ( ! ipema_user_controls_product($products[0]->ID))
                {
                    return false;
                }
            }
            else
            {
                return false;
            }

            break;


        case 'products':
            $allowed = false;
            if (ipema_user_company_active('equipment') || ipema_user_company_active('surfacing'))
            {
                if (current_user_can('can_manage_products'))
                {
                    $allowed = true;
                }
                if (current_user_can('can_validate_products'))
                {
                    return true;
                }
            }

            if ( ! $allowed)
            {
                return false;
            }
            break;

        case 'equipment':
        case 'structure':
        case 'equipment-base':
            if ( ! ipema_user_company_active('equipment'))
            {
                return false;
            }
            break;

        case 'surfacing':
        case 'surfacing-base':
            if ( ! ipema_user_company_active('surfacing'))
            {
                return false;
            }
            break;

        case 'rvs':
            if ( ! current_user_can('can_validate_products'))
            {
                return false;
            }
            break;

        case 'account':
            if ( ! current_user_can('can_manage_account'))
            {
                return false;
            }
            $user = wp_get_current_user();
            $type = get_the_terms($user->company_id, 'account-type')[0];
            if ($type->slug == 'personal')
            {
                return false;
            }
            break;

        case 'set-password':
            $user = wp_get_current_user();
            if ($user === false || $user->wp_default_password_nag != true)
            {
                return false;
            }
            break;

        case 'insurance':
            if (current_user_can('manage_ipema') ||
                current_user_can('manage_options'))
            {
                break;
            }
            $user = wp_get_current_user();
            if ($user === false || ! $user->company_id)
            {
                return false;
            }
            $pending = get_post_meta(
                $user->company_id,
                'pending_insurance',
                true
            );
            if ($pending)
            {
                return false;
            }
            break;

        case 'renew':
            $user = wp_get_current_user();
            if ($user === false || ! $user->company_id)
            {
                return false;
            }
            $company = get_post($user->company_id);
            if ($company->rejected_equipment_agreement
                || $company->rejected_surfacing_agreement
            )
            {
                return false;
            }
            break;

        case 'renew-membership':
            $user = wp_get_current_user();
            if ($user === false || ! $user->company_id)
            {
                return false;
            }
            $type = get_the_terms($user->company_id, 'account-type')[0];
            if ($type->slug != 'personal')
            {
                return false;
            }
            break;

        case 'agreement':
            $user = wp_get_current_user();
            if ($user === false || ! $user->company_id)
            {
                return false;
            }

            if ($_SERVER['REQUEST_METHOD'] == 'POST')
            {
                break;
            }

            $rejected_equipment = get_post_meta(
                $user->company_id,
                'rejected_equipment_agreement',
                true
            );
            $rejected_surfacing = get_post_meta(
                $user->company_id,
                'rejected_surfacing_agreement',
                true
            );
            if ( ! $rejected_equipment && ! $rejected_surfacing)
            {
                return false;
            }
            break;

        case 'certify':
            $user = wp_get_current_user();
            if ($user === false || ! $user->company_id)
            {
                return false;
            }

            $type = get_the_terms($user->company_id, 'account-type')[0];
            if ($type->slug != 'manufacturer')
            {
                return false;
            }

            if ($_SERVER['REQUEST_METHOD'] == 'POST')
            {
                break;
            }

            $type = get_the_terms($user->company_id, 'product-type');
            if (count($type) > 1)
            {
                return false;
            }
            break;

        case 'admin':
            if ( ! current_user_can('manage_options')
                && ! current_user_can('manage_ipema'))
            {
                return false;
            }
            break;

        case 'join-webinar':
        case 'member-documents':
            if ( ! ipema_user_company_active())
            {
                return false;
            }
            break;

        case 'certificates':
            if ( ! ipema_user_company_active('equipment')
                && ! ipema_user_company_active('surfacing')
            )
            {
                return false;
            }
            break;

        case 'help':
        case 'my-information':
            if (current_user_can('manage_ipema')
                || current_user_can('can_validate_products'))
            {
                return false;
            }
            break;

        case 'finalize':
            $user = wp_get_current_user();
            $moving = get_user_meta($user->ID, 'move');
            if (count($moving) == 0 && $_SERVER['REQUEST_METHOD'] != 'POST')
            {
                return false;
            }

            // Otherwise we hit the no model # redirect rule in 'manage'
            return true;
            break;

        case 'expiration':
        case 'whitelabel':
            if ( ! current_user_can('can_validate_products'))
            {
                return false;
            }
            break;

        case 'printable':
            return true;
    }
    if ($page->post_parent)
    {
        return ipema_allowed(get_post($page->post_parent));
    }

    return true;
}

function ipema_page_access_control()
{
    global $post;

    $can_view_page = ipema_allowed($post);
    if ( ! $can_view_page && ! is_user_logged_in())
    {
        wp_redirect(home_url('/wp-login.php'));
        exit();
    }
    if ( ! $can_view_page)
    {
        if ($post->post_parent)
        {
            wp_redirect(get_permalink($post->post_parent));
            exit();
        }
        if (current_user_can('can_validate_products'))
        {
            wp_redirect(home_url('/rvs/'));
        }
        elseif (current_user_can('manage_ipema'))
        {
            wp_redirect(home_url('/admin/'));
        }
        else
        {
            wp_redirect(home_url('/members/'));
        }
        exit();
    }
}
add_action('template_redirect', 'ipema_page_access_control', 11);

// https://www.gravityhelp.com/documentation/article/gform_pre_render/#2-populate-choices-checkboxes
function ipema_checkbox_inputs(&$field)
{
    $inputs = array();
    $id = 1;
    foreach ($field->choices as $choice)
    {
        $inputs[] = array(
            'id' => "{$field->id}.$id",
            'label' => $choice['text']
        );

        $id++;
        if ($id % 10 == 0)
        {
            $id++;
        }
    }
    $field->inputs = $inputs;
}

function ipema_product_display_name($productID)
{
    $fullName = ipema_model_number(
        array('product' => $productID)
    );
    $name = ipema_brand_name(
        array('product' => $productID)
    );

    if ($name)
    {
        $user = wp_get_current_user();
        if ($user && $user->company_id)
        {
            if (get_post_meta($user->company_id, 'show_rv_description', true))
            {
                return "$fullName - $name";
            }
        }
        return "$name ($fullName)";
    }

    return $fullName;
}

function ipema_split_rv($rvProducts, $rv)
{
    foreach ($rvProducts as $termID => $affected)
    {
        if (count($affected) == 0)
        {
            unset($rvProducts[$termID]);
        }
    }

    $rv['post_type'] = 'rv';
    if (array_key_exists('tax_input', $rv))
    {
        $taxonomies = $rv['tax_input'];
        unset($rv['tax_input']);
    }
    if ( ! array_key_exists('meta_input', $rv))
    {
        $rv['meta_input'] = array();
    }

    $metaArrays = array();
    foreach ($rv['meta_input'] as $key => $value)
    {
        if (is_array($value))
        {
            $metaArrays[$key] = $value;
            unset($rv['meta_input'][$key]);
        }
    }

    if ( ! array_key_exists('post_date', $rv) || ! $rv['post_date'])
    {
        $rv['post_date'] = current_time('mysql');
    }

    while (count($rvProducts) > 0)
    {
        $rvCerts = array();
        $current = reset($rvProducts);
        foreach ($rvProducts as $termID => $affected)
        {
            if ($current == $affected)
            {
                $rvCerts[] = $termID;
            }
        }

        $public_id = get_option('rv_autoincrement');
        $public_id++;
        update_option('rv_autoincrement', $public_id);

        $rv['meta_input']['public_id'] = $public_id;
        $rv_id = wp_insert_post($rv);

        wp_set_object_terms($rv_id, $rvCerts, 'certification');
        foreach ($taxonomies as $taxonomy => $terms)
        {
            wp_set_object_terms($rv_id, $terms, $taxonomy);
        }
        foreach ($metaArrays as $key => $values)
        {
            foreach ($values as $value)
            {
                add_post_meta($rv_id, $key, $value);
            }
        }

        foreach ($current as $affected_id)
        {
            add_post_meta($rv_id, 'affected_id', $affected_id);
        }

        if ( ! array_key_exists('post_status', $rv)
            || $rv['post_status'] != 'publish')
        {
            ipema_rv_email($rv_id);
        }

        foreach ($rvCerts as $termID)
        {
            unset($rvProducts[$termID]);
        }
    }
}

function ipema_populate_remove_certs($form)
{
    $base = false;
    if (array_key_exists('base', $_GET))
    {
        $base = get_the_terms($_GET['model'], 'base');
    }

    if ($base === false)
    {
        $certs = get_the_terms($_GET['model'], 'certification');
    }
    else
    {
        $affected = get_posts(array(
            'post_type' => 'product',
            'tax_query' => array(
                array(
                    'taxonomy' => 'base',
                    'terms' => $base[0]->term_id
                ),
                array(
                    'taxonomy' => 'certification',
                    'operator' => 'EXISTS'
                )
            ),
            'nopaging' => true
        ));

        $certs = array();
        $products = array();
        foreach ($affected as $product)
        {
            $productCerts = get_the_terms($product->ID, 'certification');
            foreach ($productCerts as $cert)
            {
                $certs[$cert->term_id] = $cert;
            }
            $fullName = ipema_product_display_name($product->ID);
            $products[] = array(
                'value' => $product->ID,
                'text' => $fullName
            );
        }
        usort($products, 'ipema_sort_models');
    }

    if ($certs == false)
    {
        $certs = array();
    }

    foreach ($form['fields'] as &$field)
    {
        if ($field->id == 1)
        {
            $choices = array();
            foreach ($certs as $cert)
            {
                $choices[] = array(
                    'value' => $cert->term_id,
                    'text' => $cert->name
                );
            }
            if (count($certs) == 1)
            {
                $choices[0]['isSelected'] = true;
            }
            $field->choices = $choices;
            ipema_checkbox_inputs($field);
        }
        elseif ($field->id == 2)
        {
            if ($base === false)
            {
                $field->adminOnly = true;
            }
        }
        elseif ($field->id == 3)
        {
            if ($base !== false)
            {
                $field->choices = $products;
                ipema_checkbox_inputs($field);
            }
        }
    }

    return $form;
}
add_filter('gform_pre_render_39', 'ipema_populate_remove_certs');
add_filter('gform_pre_validation_39', 'ipema_populate_remove_certs');
add_filter('gform_pre_submission_filter_39', 'ipema_populate_remove_certs');

function ipema_remove_certs($entry, $form)
{
    global $wpdb;

    if (array_key_exists('base', $_GET))
    {
        if (rgar($entry, 2) == 'all')
        {
            $base = get_the_terms(rgar($entry, 4), 'base');
            $affected_ids = get_posts(array(
                'post_type' => 'product',
                'tax_query' => array(
                    array(
                        'taxonomy' => 'base',
                        'terms' => $base[0]->term_id
                    ),
                    array(
                        'taxonomy' => 'certification',
                        'operator' => 'EXISTS'
                    )
                ),
                'nopaging' => true,
                'fields' => 'ids'
            ));
        }
        else
        {
            $affected_ids = array();
            foreach ($entry as $key => $value)
            {
                if (strpos($key, '3.') === 0 && $value)
                {
                    $affected_ids[] = $value;
                }
            }
        }
    }
    else
    {
        $affected_ids = array($entry[4]);
    }

    $certs = array();
    foreach ($entry as $key => $value)
    {
        if (strpos($key, '1.') === 0 && $value)
        {
            $certs[(int)$value] = array();
        }
    }

    foreach ($affected_ids as $productID)
    {
        $productCerts = get_the_terms($productID, 'certification');
        foreach ($productCerts as $cert)
        {
            if (array_key_exists($cert->term_id, $certs))
            {
                $certs[$cert->term_id][] = $productID;
            }
        }
    }

    $rv = array(
        'post_type' => 'rv',
        'post_title' => ipema_model_number(array('product' => rgar($entry, 4))),
        'post_content' => '',
        'meta_input' => array(
            '_wpcf_belongs_product_id' => rgar($entry, 4),
        ),
        'tax_input' => array(
            'request' => 'remove'
        )
    );

    $affected = "'" . join("','", $affected_ids) . "'";
    $sql = "SELECT
              1
            FROM
              {$wpdb->postmeta}
            WHERE
              meta_key = 'whitelabels'
            AND
              meta_value IN ($affected)
            LIMIT 1";
    $has_whitelabels = $wpdb->get_var($sql);

    if ( ! $has_whitelabels)
    {
        $rv['post_status'] = 'publish';
        $rv['meta_input']['status'] = 'processed';
        $rv['meta_input']['new'] = true;
    }


    ipema_split_rv($certs, $rv);
}
add_action('gform_after_submission_39', 'ipema_remove_certs', 10, 2);

function ipema_populate_add_certs($form)
{
    $validCerts = ipema_valid_certs($_GET['model']);

    $base = false;
    if (array_key_exists('base', $_GET))
    {
        $base = get_the_terms($_GET['model'], 'base');
    }

    if ($base === false)
    {
        $certs = get_the_terms($_GET['model'], 'certification');
        if ($certs != false)
        {
            foreach ($validCerts as $key => $validCert)
            {
                foreach ($certs as $cert)
                {
                    if ($validCert->term_id == $cert->term_id)
                    {
                        unset($validCerts[$key]);
                        continue 2;
                    }
                }
            }
        }
    }
    else
    {
        $affected = get_posts(array(
            'post_type' => 'product',
            'post_status' => 'any',
            'tax_query' => array(
                array(
                    'taxonomy' => 'base',
                    'terms' => $base[0]->term_id
                )
            ),
            'fields' => 'ids',
            'nopaging' => true
        ));

        $missingCerts = array();
        $products = array();
        foreach ($affected as $productID)
        {
            $productCerts = get_the_terms($productID, 'certification');
            if ($productCerts != false)
            {
                $matched = 0;
                foreach ($validCerts as $validCert)
                {
                    foreach ($productCerts as $cert)
                    {
                        if ($validCert->term_id == $cert->term_id)
                        {
                            $matched++;
                            continue 2;
                        }
                    }
                    $missingCerts[$validCert->term_id] = true;
                }

                if (count($validCerts) == $matched)
                {
                    continue;
                }
            }
            else
            {
                foreach ($validCerts as $cert)
                {
                    $missingCerts[$cert->term_id] = true;
                }
            }

            $fullName = ipema_product_display_name($productID);
            $products[] = array(
                'value' => $productID,
                'text' => $fullName
            );
        }
        usort($products, 'ipema_sort_models');

        foreach ($validCerts as $key => $validCert)
        {
            if ( ! $missingCerts[$validCert->term_id])
            {
                unset($validCerts[$key]);
            }
        }
    }

    foreach ($form['fields'] as &$field)
    {
        if ($field->id == 1)
        {
            $choices = array();
            foreach ($validCerts as $cert)
            {
                $choices[] = array(
                    'value' => $cert->term_id,
                    'text' => $cert->name
                );
            }
            if (count($validCerts) == 1)
            {
                $choices[0]['isSelected'] = true;
            }
            $field->choices = $choices;
            ipema_checkbox_inputs($field);
        }
        elseif ($field->id == 2)
        {
            if ($base === false)
            {
                $field->adminOnly = true;
            }
        }
        elseif ($field->id == 3)
        {
            if ($base !== false)
            {
                $field->choices = $products;
                ipema_checkbox_inputs($field);
            }
        }
        elseif ($field->id == 4)
        {
            $type = get_the_terms($_GET['model'], 'product-type');
            $type = $type[0]->slug;
            preg_match_all(
                '/<p class="(\w+)">.*?<\/p>/',
                $field->description,
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $match)
            {
                if ($match[1] != $type)
                {
                    $field->description = str_replace(
                        $match[0],
                        '',
                        $field->description
                    );
                }
            }
        }
    }

    return $form;
}
add_filter('gform_pre_render_40', 'ipema_populate_add_certs');
add_filter('gform_pre_validation_40', 'ipema_populate_add_certs');
add_filter('gform_pre_submission_filter_40', 'ipema_populate_add_certs');

add_filter('gform_validation_40', function($result) {
    if ( ! $result['is_valid'])
    {
        return $result;
    }

    $modelID = rgpost('input_6');
    $base = get_the_terms($modelID, 'base');
    if (array_key_exists('base', $_GET))
    {
        $affectedIDs = array();
        $selection = rgpost('input_2');
        if ($selection == 'selected')
        {
            foreach ($_POST as $key => $value)
            {
                if (strpos($key, 'input_3_') === 0 && $value)
                {
                    $affectedIDs[] = $value;
                }
            }
        }
        else
        {
            $search = array(
                'post_type' => 'product',
                'post_status' => 'any',
                'tax_query' => array(array(
                    'taxonomy' => 'base',
                    'terms' => $base[0]->term_id
                )),
                'nopaging' => true,
                'fields' => 'ids'
            );
            if ($selection != 'all')
            {
                $search['post_status'] = 'publish';
                $search['tax_query'][] = array(
                    'taxonomy' => 'certification',
                    'operator' => 'EXISTS'
                );
            }

            $affectedIDs = get_posts($search);
        }
    }
    else
    {
        $affectedIDs = array($modelID);
    }

    $certs = array();
    $retested = array();
    foreach ($_POST as $key => $value)
    {
        if (strpos($key, 'input_1_') === 0 && $value)
        {
            $certs[(int)$value] = array();
            $retested[(int)$value] = 0;

            if ($base != false)
            {
                $cert = get_term($value);

                if (get_term_meta($cert->term_id, 'canadian', true))
                {
                    update_term_meta($base[0]->term_id, 'canadian', true);
                }

                $newest = get_posts(array(
                    'post_type' => 'product',
                    'tax_query' => array(array(
                        'taxonomy' => 'base',
                        'terms' => $base[0]->term_id
                    )),
                    'meta_key' => $cert->slug,
                    'orderby' => 'meta_value',
                    'fields' => 'ids',
                    'posts_per_page' => 1
                ));

                if (count($newest) == 0)
                {
                    continue;
                }

                $rv = get_posts(array(
                    'post_type' => 'rv',
                    'tax_query' => array(
                        array(
                            'taxonomy' => 'request',
                            'terms' => 'test',
                            'field' => 'slug'
                        ),
                        array(
                            'taxonomy' => 'certification',
                            'terms' => $cert->term_id
                        )
                    ),
                    'meta_query' => array(
                        array(
                            'key' => 'status',
                            'value' => 'approved'
                        ),
                        array(
                            'key' => 'affected_id',
                            'value' => $newest[0]
                        )
                    ),
                    'posts_per_page' => 1,
                    'fields' => 'ids'
                ));

                if (count($rv) == 1)
                {
                    $retested[(int)$value] = get_post_meta(
                        $rv[0],
                        '_wpcf_belongs_product_id',
                        true
                    );
                }
            }
        }
    }

    foreach ($affectedIDs as $productID)
    {
        $prodCerts = get_the_terms($productID, 'certification');
        if ($prodCerts == false)
        {
            foreach ($certs as $termID => $affected)
            {
                $certs[$termID][] = $productID;
            }
            continue;
        }

        foreach ($certs as $termID => $affected)
        {
            foreach ($prodCerts as $prodCert)
            {
                if ($termID == $prodCert->term_id)
                {
                    continue 2;
                }
            }

            $certs[$termID][] = $productID;
        }
    }

    $emptyCerts = array();
    $sets = array();
    foreach ($certs as $termID => $affected)
    {
        if (count($affected) == 0)
        {
            $result['is_valid'] = false;
            $emptyCerts[] = $termID;
        }
        if ( ! array_key_exists($retested[$termID], $sets))
        {
            $sets[$retested[$termID]] = array();
        }
        $sets[$retested[$termID]][$termID] = $affected;
    }

    $notIncluded = array();
    if (array_key_exists(0, $sets))
    {
        foreach ($sets[0] as $termID => $affected)
        {
            if ( ! in_array(rgpost('input_6'), $affected))
            {
                $result['is_valid'] = false;
                $notIncluded[] = $termID;
            }
        }
    }

    if ( ! $result['is_valid'])
    {
        $form = $result['form'];

        foreach ($form['fields'] as &$field)
        {
            if ($field->id == 1 && count($emptyCerts) > 0)
            {
                if (count($emptyCerts) == 1)
                {
                    $term = get_term($emptyCerts[0]);
                    $msg = 'All selected products already have '
                        . "{$term->name} certification";
                }
                else
                {
                    $msg = '<p>The selected products already have the '
                        . ' requested certifications:</p><ul>';
                    foreach ($emptyCerts as $cert)
                    {
                        $term = get_term($cert);
                        $msg .= "<li>{$term->name}</li>";
                    }
                    $msg .= '</ul>';
                }
                $field->failed_validation = true;
                $field->validation_message = $msg;
            }
            elseif ($field->id == 2 && count($notIncluded) > 0)
            {
                $family = $base[0]->slug;
                $msg = '<p>Requested new certification from '
                    . ipema_product_display_name($modelID) . ' but it is not '
                    . 'selected or has expired.</p>'
                    . '<p><a href="../../models/?family=' . $family . '">Change'
                    . ' model</a></p>';
                $field->failed_validation = true;
                $field->validation_message = $msg;
            }
        }

        $result['form'] = $form;
    }

    $GLOBALS['newCertSets'] = $sets;

    return $result;
});

add_action('gform_after_submission_40', function($entry, $form) {
    $documents = json_decode(rgar($entry, 4));
    if ( ! is_array($documents))
    {
        $documents = array();
    }
    foreach ($documents as &$document)
    {
        $document = preg_replace('#^https?://[^/]+#i', '', $document);
    }

    $rv = array(
        'post_type' => 'rv',
        'post_title' => ipema_model_number(array('product' => rgar($entry, 6))),
        'post_date' => current_time('mysql'),
        'post_content' => '',
        'post_excerpt' => rgar($entry, 5),
        'meta_input' => array(
            '_wpcf_belongs_product_id' => rgar($entry, 6),
            'documentation' => $documents
        ),
        'tax_input' => array(
            'request' => 'test'
        )
    );

    $sets = $GLOBALS['newCertSets'];
    if (array_key_exists(0, $sets))
    {
        $rv['meta_input']['request_label'] = 'Add Certification';
        ipema_split_rv($sets[0], $rv);
        unset($sets[0]);
    }

    $rv['tax_input']['request'] = 'add-certification';
    foreach ($sets as $modelID => $certs)
    {
        $rv['post_title'] = ipema_model_number(array('product' => $modelID));
        $rv['meta_input']['_wpcf_belongs_product_id'] = $modelID;

        ipema_split_rv($certs, $rv);
    }
}, 10, 2);

function ipema_generate_code($length)
{
    $all = array_merge(range(0, 9), range('a', 'z'));
    $max = count($all) - 1;

    $code = '';
    while (mb_strlen($code) < $length)
    {
        $code .= $all[mt_rand(0, $max)];
    }

    return $code;
}

add_action('template_redirect', function() {
    if ( ! is_page('generate-certificate'))
    {
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST')
    {
        wp_redirect('/certified-product');
        exit();
    }

    $name = trim($_POST['cert-name']);
    $email = trim($_POST['cert-email']);
    $project = trim($_POST['cert-project']);
    $spam = true;

    if ($name == '' or $project == '' || $email == '')
    {
        wp_redirect('/certified-product');
        exit();
    }

    if (count($_POST['cert-products']) == 0)
    {
        wp_redirect('/certified-product');
        exit();
    }

    if (preg_match('#https?://#i', $name.$project))
    {
        wp_redirect('/certified-product');
        exit();
    }

    $authorID = wp_get_current_user()->ID;
    if ($authorID == 0)
    {
        $authorID = 19; // Unknown User
    }

    do {
        $code = ipema_generate_code(4);

        $matches = get_posts(array(
            'post_type' => 'certificate',
            'name' => $code
        ));
    } while(count($matches) > 0);

    $certificateID = wp_insert_post(array(
        'post_author' => $authorID,
        'post_title' => $project,
        'post_status' => 'publish',
        'post_type' => 'certificate',
        'post_name' => $code,
        'meta_input' => array(
            'name' => $name,
            'email' => $email
        )
    ));

    $manufacturers = array();
    foreach ($_POST['cert-products'] as $product_id)
    {
        add_post_meta($certificateID, 'product', $product_id);
        $manufacturers[] = get_post_meta(
            $product_id,
            '_wpcf_belongs_company_id',
            true
        );
    }

    $manufacturers = array_unique($manufacturers);
    foreach ($manufacturers as $ID)
    {
        wp_insert_post(array(
            'post_author' => $authorID,
            'post_title' => $project,
            'post_status' => 'publish',
            'post_type' => 'cert-company',
            'post_name' => "$code-$ID",
            'meta_input' => array(
                '_wpcf_belongs_company_id' => $ID,
                '_wpcf_belongs_certificate_id' => $certificateID,
                'spam' => $spam
            )
        ));
    }

    $siteURL = site_url();
    wp_mail($email, "$project Certificate", <<<EOT
Hello $name,

The certificate you generated on the IPEMA website is available to view and download at the following link:

$siteURL/certificate/$code

The IPEMA Team
EOT
    );

    wp_redirect("/generate-certificate/verification");
    exit();
});

function ipema_filter_menu($items, $args)
{
    foreach ($items as $key => $item)
    {
        if ($item->type == 'custom' && $item->post_name == 'login' && is_user_logged_in())
        {
            unset($items[$key]);
            continue;
        }
        if ( ! ipema_allowed(get_post($item->object_id)))
        {
            unset($items[$key]);
        }
    }

    return $items;
}
add_filter('wp_nav_menu_objects', 'ipema_filter_menu', 10, 2);

function ipema_login_redirect($user_login, $user)
{
    global $ipema_block_redirect;

    if ($ipema_block_redirect)
    {
        return;
    }

    if ($user->has_cap('can_validate_products'))
    {
        wp_redirect('/rvs/');
        exit();
    }
    elseif ($user->has_cap('manage_ipema'))
    {
        wp_redirect('/admin/');
        exit();
    }
    elseif (! $user->has_cap('edit_posts'))
    {
        delete_user_meta($user->ID, 'family');
        delete_user_meta($user->ID, 'move');

        if ( ! $user->company_id)
        {
            wp_logout();
        }

        wp_redirect('/members/');
        exit();
    }
}
add_action('wp_login', 'ipema_login_redirect', 10, 2);

function ipema_logout_redirect()
{
    wp_redirect('/');
    exit();
}
add_action('wp_logout', 'ipema_logout_redirect');

function ipema_hide_toolbar()
{
    if ( ! current_user_can('edit_posts'))
    {
        show_admin_bar(false);
    }
}
add_filter('show_admin_bar', 'ipema_hide_toolbar');

function logit($handle, $text)
{
    return;
    fwrite($handle, '[' . microtime(true) . "] $text\n");
}

function ipema_update_certified_products()
{
    global $wpdb, $wp, $WPV_Cache;

    /*$log = fopen(__DIR__ . '/product-cron.log', 'a');
    fwrite($log, "-----\n");
    fwrite($log, 'Starting cron run: ' . date('H:i:s.u') . "\n");*/

    $lockfile = __DIR__ . '/product-cron.lock';
    $companyData = __DIR__ . '/company-data.txt';

    // Avoid wiping the transient cache
    remove_action('save_post', array($WPV_Cache, 'delete_transient_meta_keys'));
    remove_action(
        'delete_post',
        array($WPV_Cache, 'delete_transient_meta_keys')
    );
    remove_action(
        'added_post_meta',
        array($WPV_Cache, 'delete_transient_meta_keys')
    );
    remove_action(
        'updated_post_meta',
        array($WPV_Cache, 'delete_transient_meta_keys')
    );
    remove_action(
        'deleted_post_meta',
        array($WPV_Cache, 'delete_transient_meta_keys')
    );
    remove_action(
        'types_fields_group_saved',
        array($WPV_Cache, 'delete_transient_meta_keys')
    );
    remove_action(
        'wpcf_save_group',
        array($WPV_Cache, 'delete_transient_meta_keys')
    );
    remove_action(
        'wpcf_group_updated',
        array($WPV_Cache, 'delete_transient_meta_keys')
    );

    if ( ! array_key_exists('run', $_GET))
    {
        do {
            $_GET['run'] = ipema_generate_code(4);
            $duplicate = get_posts(array(
                'post_type' => 'certified-product',
                'post_status' => 'any',
                'meta_query' => array(array(
                    'key' => 'cron_run',
                    'value' => $_GET['run']
                )),
                'posts_per_page' => 1,
                'fields' => 'ids'
            ));
            if (count($duplicate) == 0)
            {
                $duplicate = get_posts(array(
                    'post_type' => 'manufacturer',
                    'post_status' => 'any',
                    'meta_query' => array(array(
                        'key' => 'cron_run',
                        'value' => $_GET['run']
                    )),
                    'posts_per_page' => 1,
                    'fields' => 'ids'
                ));
            }
        } while (count($duplicate) > 0);
    }
    //logit($log, "Run Identifier: {$_GET['run']}");

    $lock = file_get_contents($lockfile);
    if ($lock)
    {
        list($run, $time) = explode(' ', $lock);
        if ($run != $_GET['run'])
        {
            if (strtotime('5 minutes ago') < $time)
            {
                //logit($log, "Detected running job: $run\n");
                //logit($log, '-----');
                //fclose($log);
                return;
            }
        }
    }
    $now = time();
    file_put_contents($lockfile, "{$_GET['run']} $now");

    if ( ! array_key_exists('offset', $_GET))
    {
        //logit($log, 'First Run, Redirecting');
        //logit($log, '-----');
        //fclose($log);
        wp_redirect("/about-ipema/?cron=products&offset=0&run={$_GET['run']}");
        die();
    }

    $timeout = time() + 60;

    //logit($log, "Current Offset: {$_GET['offset']}");
    $possible = get_posts(array(
        'post_type' => 'product',
        'posts_per_page' => 40,
        'offset' => $_GET['offset'],
        'order' => 'ASC'
    ));
    //logit($log, 'Got ' . count($possible) . ' products');

    $current_year = ipema_current_year();
    if (count($possible) == 0)
    {
        // Erase old rows first so we find all manufacturers without products.
        // See p.ID NOT IN in the $sql query below this one.
        $wpdb->query($wpdb->prepare(
            "DELETE p, pm, tr FROM
              {$wpdb->posts} as p
            LEFT JOIN
              {$wpdb->term_relationships} tr
            ON
              p.ID = tr.object_id
            LEFT JOIN
              {$wpdb->postmeta} pm
            ON
              p.ID = pm.post_id
            WHERE
              p.ID IN (SELECT workaround FROM (
                SELECT
                  p.ID AS workaround
                FROM
                  {$wpdb->posts} AS p
                LEFT JOIN
                  {$wpdb->postmeta} AS pm
                ON
                  p.ID = pm.post_id
                AND
                  pm.meta_key = 'cron_run'
                WHERE
                  p.post_type IN ('certified-product', 'manufacturer')
                AND
                  (pm.meta_value IS NULL OR pm.meta_value != %s)
                ) AS tmpTable)",
            $_GET['run']
        ));

        $sql = '
            SELECT
              p.*
            FROM
              wp_posts AS p
            INNER JOIN
              wp_term_relationships AS tr
            ON
              p.ID = tr.object_id
            INNER JOIN
              wp_postmeta AS pm
            ON
              p.ID = pm.post_id
            AND
              pm.meta_key = "active"
            WHERE
              tr.term_taxonomy_id = 7
            AND
              p.post_type = "company"
            AND
              p.ID NOT IN (
                SELECT
                  meta_value
                FROM
                  wp_postmeta
                WHERE
                  meta_key = "_wpcf_belongs_company_id"
                AND
                  post_id IN (
                    SELECT
                      ID
                    FROM
                      wp_posts
                    WHERE
                      post_type = "manufacturer"
                  )
              )
            AND
              CAST(meta_value AS UNSIGNED) >= ' . ipema_current_year();

        $companies = $wpdb->get_results($sql);
        foreach ($companies as $company)
        {
            $company = new WP_Post($company);
            $importID = get_post_meta($company->ID, 'manufacturer_id', true);
            if ($importID)
            {
                $old = WP_Post::get_instance($importID);
                if ($old)
                {
                    if ($old->post_type == 'manufacturer')
                    {
                        wp_delete_post($importID, true);
                    }
                    else
                    {
                        $importID = NULL;
                    }
                }
            }

            $type = array();
            // Add active company to manfacturers post_type
            $equipment_year = get_post_meta($company->ID, 'equipment', true);
            $revoked = get_post_meta($company->ID, 'equipment_revoked', true);
            if ( ! $revoked && $equipment_year >= $current_year)
            {
                $type[] = 'equipment';
            }

            $surfacing_year = get_post_meta($company->ID, 'surfacing', true);
            $revoked = get_post_meta($company->ID, 'surfacing_revoked', true);
            if ( ! $revoked && $surfacing_year >= $current_year)
            {
                $type[] = 'surfacing';
            }
            $toll_free_number = get_post_meta(
                $company->ID,
                'toll-free',
                true
            );

            $manufacturer_id = wp_insert_post(array(
                'import_id' => $importID,
                'post_author' => $company->post_author,
                'post_date' => $company->post_date,
                'post_status' => 'publish',
                'post_type' => 'manufacturer',
                'post_content' => $company->post_content,
                'post_title' => $company->post_title,
                'meta_input' => array(
                    '_wpcf_belongs_company_id' => $company->ID,
                    '_thumbnail_id' => $company->_thumbnail_id,
                    'phone' => $company->phone,
                    'fax' => $company->fax,
                    'toll-free' => $toll_free_number,
                    'url' => $company->url,
                    'address' => $company->address,
                    'address2' => $company->address2,
                    'city' => $company->city,
                    'state' => $company->state,
                    'zip' => $company->zip,
                    'country' => $company->country,
                    'cron_run' => $_GET['run']
                )
            ));

            wp_set_object_terms($manufacturer_id, $type, 'product-type');

            if ( ! $importID)
            {
                update_post_meta(
                    $company->ID,
                    'manufacturer_id',
                    $manufacturer_id
                );
            }
        }

        unlink($companyData);
        unlink($lockfile);

        die();
    }

    /* A product is certified if its company account is active for its product
     * type and it has at least one active certification.
     */
    $today = strtotime('today');
    $insuranceExp = strtotime('-45 days', $today);
    $companies = array();
    $company_names = array();
    if (file_exists($companyData))
    {
        list($companies, $company_names) = unserialize(
            file_get_contents($companyData)
        );
    }
    $counter = 0;
    foreach ($possible as $product)
    {
        $counter++;
        //logit($log, "Checking product $counter");
        $company_id = get_post_meta($product->ID, '_wpcf_belongs_company_id', true);
        if ( ! array_key_exists($company_id, $companies))
        {
            //logit($log, "Creating new company: $company_id");
            $companies[$company_id] = array();

            $equipment_year = get_post_meta($company_id, 'equipment', true);
            $revoked = get_post_meta($company_id, 'equipment_revoked', true);
            if ( ! $revoked && $equipment_year >= $current_year)
            {
                $companies[$company_id] = array('equipment', 'structure');
            }

            $surfacing_year = get_post_meta($company_id, 'surfacing', true);
            $revoked = get_post_meta($company_id, 'surfacing_revoked', true);
            if ( ! $revoked && $surfacing_year >= $current_year)
            {
                $companies[$company_id][] = 'surfacing';
            }

            $company = get_post($company_id);
            $company_year = get_post_meta($company_id, 'active', true);
            if ($company_year >= $current_year)
            {
                //logit($log, "Making manufacturer record");
                $importID = get_post_meta($company_id, 'manufacturer_id', true);
                if ($importID)
                {
                    $old = WP_Post::get_instance($importID);
                    if ($old)
                    {
                        if ($old->post_type == 'manufacturer')
                        {
                            wp_delete_post($importID, true);
                            //logit($log, "Deleted old manufacturer record");
                        }
                        else
                        {
                            $importID = NULL;
                        }
                    }
                }

                // Add active company to manfacturers post_type
                $type = array_diff($companies[$company_id], array('structure'));
                $toll_free_number = get_post_meta(
                    $company_id,
                    'toll-free',
                    true
                );

                $manufacturer_id = wp_insert_post(array(
                    'import_id' => $importID,
                    'post_author' => $company->post_author,
                    'post_date' => $company->post_date,
                    'post_status' => 'publish',
                    'post_type' => 'manufacturer',
                    'post_content' => $company->post_content,
                    'post_title' => $company->post_title,
                    'meta_input' => array(
                        '_wpcf_belongs_company_id' => $company_id,
                        '_thumbnail_id' => $company->_thumbnail_id,
                        'phone' => $company->phone,
                        'fax' => $company->fax,
                        'toll-free' => $toll_free_number,
                        'url' => $company->url,
                        'address' => $company->address,
                        'address2' => $company->address2,
                        'city' => $company->city,
                        'state' => $company->state,
                        'zip' => $company->zip,
                        'country' => $company->country,
                        'cron_run' => $_GET['run']
                    )
                ));

                wp_set_object_terms($manufacturer_id, $type, 'product-type');

                if ( ! $importID)
                {
                    update_post_meta(
                        $company_id,
                        'manufacturer_id',
                        $manufacturer_id
                    );
                }
                //logit($log, "New manufacturer record complete");
            }

            $company_names[$company_id] = html_entity_decode($company->post_title);

            $insurance_exp = get_post_meta($company_id, 'insurance-exp', true);
            if ($insurance_exp < $insuranceExp)
            {
                $companies[$company_id] = array();
            }
        }

        $type = get_the_terms($product->ID, 'product-type');
        if ( ! in_array($type[0]->slug, $companies[$company_id]))
        {
            continue;
        }

        //logit($log, 'Checking for old product record');
        $importID = get_post_meta($product->ID, 'certified_product_id', true);
        if ($importID)
        {
            $old = WP_Post::get_instance($importID);
            if ($old)
            {
                if ($old->post_type == 'certified-product')
                {
                    wp_delete_post($importID, true);
                    //logit($log, 'Deleted old product record');
                }
                else
                {
                    $importID = NULL;
                }
            }
        }

        //logit($log, 'Checking certifications');
        $certifications = get_the_terms($product->ID, 'certification');
        if ($certifications == false)
        {
            $certifications = array();
        }
        foreach ($certifications as $key => $certification)
        {
            $expiration = get_post_meta($product->ID, $certification->slug, true);
            if (strtotime($expiration) < $today)
            {
                unset($certifications[$key]);
            }
        }

        if (count($certifications) == 0)
        {
            wp_update_post(array(
                'ID' => $product->ID,
                'post_status' => 'draft'
            ), true);

            continue;
        }

        //logit($log, 'Creating new certified product');
        $certification_slugs = array();
        foreach ($certifications as $certification)
        {
            $certification_slugs[] = $certification->slug;
        }

        $product_line = '';
        $product_lines = get_the_terms($product->ID, 'product-line');
        $product_line_slugs = array();
        if ($product_lines != false)
        {
            foreach ($product_lines as $line)
            {
                $product_line_slugs[] = $line->slug;
                $product_line = $line->name;
            }
        }

        $base = ipema_get_product_base($product->ID);
        $content = '';
        $baseFrench = '';
        if ($base)
        {
            $baseFrench = trim(
                get_term_meta($base->term_id, 'french-description', true)
            );
            if ($base->description)
            {
                $content .= trim($base->description);
            }
            if ($content && $product->post_content)
            {
                $content .= "\n\n";
            }
        }
        if ($product->post_content)
        {
            $content .= trim($product->post_content);
        }

        $french = trim(get_post_meta($product->ID, 'french-description', true));

        if ($baseFrench || $french)
        {
            $content = '<div class="english-only">' . $content . '</div>';
            $content .= '<div class="french-only notranslate">';
            if ($baseFrench)
            {
                $content .= $baseFrench;
            }
            if ($baseFrench && $french)
            {
                $content .= "\n\n";
            }
            if ($french)
            {
                $content .= $french;
            }
            $content .= '</div>';
        }

        $product_id = wp_insert_post(array(
            'import_id' => $importID,
            'post_author' => $product->post_author,
            'post_date' => $product->post_date,
            'post_status' => 'publish',
            'post_type' => 'certified-product',
            'post_content' => $content,
            'post_title' => ipema_brand_name(array('product' => $product->ID)),
            'meta_input' => array(
                'model' => ipema_model_number(array('product' => $product->ID)),
                '_wpcf_belongs_company_id' => $company_id,
                '_wpcf_belongs_product_id' => $product->ID,
                'thickness_to_height' => $product->thickness_to_height,
                '_thumbnail_id' => $product->_thumbnail_id,
                'french_name' => $product->french_name,
                'product_line' => $product_line,
                'manufacturer' => $company_names[$company_id],
                'cron_run' => $_GET['run']
            )
        ));

        wp_set_object_terms($product_id, $type[0]->slug, 'product-type');
        wp_set_object_terms($product_id, $certification_slugs, 'certification');
        wp_set_object_terms($product_id, $product_line_slugs, 'product-line');

        if ( ! $importID)
        {
            update_post_meta(
                $product->ID,
                'certified_product_id',
                $product_id
            );
        }
        //logit($log, "Created new certified product: $product_id");

        if (time() > $timeout)
        {
            logit($log, 'Interrupted by timeout');
            break;
        }
    }

    file_put_contents($companyData, serialize(array(
        $companies,
        $company_names
    )));
    /*logit($log, 'Completed run');
    logit($log, '-----');
    fclose($log);*/

    $offset = $_GET['offset'] + $counter;
    wp_redirect("/about-ipema/?cron=products&offset=$offset&run={$_GET['run']}");
    die();
}

function ipema_update_certified_products_count()
{
    $companies = get_posts(array(
        'post_type' => 'company',
        'post_status' => 'any',
        'nopaging' => true,
        'fields' => 'ids'
    ));

    foreach ($companies as $companyID)
    {
        $query = new WP_Query( 
		 array(
		  'post_type' => 'certified-product',
		  'fields' => 'ids',
		  'posts_per_page' => -1,
		  'meta_query' => array(
		    array(
		     'key' => '_wpcf_belongs_company_id', 
		     'value' => $companyID
		    )
		   )
		 )
		);
	
        $count_certified_products = $query->post_count;
        
        update_post_meta(
                $companyID,
                'annual-certified-product-count',
                $count_certified_products
            );
    }
    die();
}

add_action('template_redirect', function() {
    if ( ! $_GET['checker'])
    {
        return;
    }

    $seen = array();
    $companies = get_posts(array(
        'post_type' => 'company',
        'post_status' => 'any',
        'nopaging' => true,
        'fields' => 'ids'
    ));

    foreach ($companies as $companyID)
    {
        $importID = get_post_meta($companyID, 'manufacturer_id', true);

        if ( ! $importID)
        {
            continue;
        }

        if (array_key_exists($importID, $seen))
        {
            print 'Duplicate: ' . $seen[$importID] . ' ' . $companyID;
        }
        else
        {
            $seen[$importID] = $companyID;
        }
    }
    die();
});

function ipema_cron()
{
    /*if ( ! wp_next_scheduled('certified-products-cron'))
    {
        wp_schedule_event(time(), 'hourly', 'certified-products-cron');
    }*/
    if ( ! wp_next_scheduled('webinar-email'))
    {
        wp_schedule_event(strtotime('7:50 AM'), 'daily', 'webinar-email');
    }
    /*if ( ! wp_next_scheduled('expiring-alerts'))
    {
        wp_schedule_event(strtotime('7:55 AM'), 'daily', 'expiring-alerts');
    }*/
}
register_activation_hook(__FILE__, 'ipema_cron');
add_action('certified-products-cron', 'ipema_update_certified_products');

function ipema_trigger_cron()
{
    if ( ! array_key_exists('cron', $_GET))
    {
        return;
    }

    if ($_GET['cron'] == 'products')
    {
        ipema_update_certified_products();
    }
    elseif ($_GET['cron'] == 'count-products')
    {
        ipema_update_certified_products_count();
    }
    elseif ($_GET['cron'] == 'webinar')
    {
        do_action('webinar-email');
    }
    elseif ($_GET['cron'] == 'expire')
    {
        ipema_expiring_alert(true);
    }
    elseif ($_GET['cron'] == 'retest')
    {
        ipema_retest_quotas();
    }
    elseif ($_GET['cron'] == 'index')
    {
        ipema_rebuild_index();
    }
    elseif ($_GET['cron'] == 'digest')
    {
        ipema_approval_digest();
    }
    elseif ($_GET['cron'] == 'rvs')
    {
        ipema_process_rvs();
    }
    /*elseif ($_GET['cron'] == 'ext-equipment')
    {
        require('import/extend-equipment.php');
    }
    elseif ($_GET['cron'] == 'delete-dupes')
    {
        require('import/remove-2019-06-21-products.php');
    }
    elseif ($_GET['cron'] == 'fix')
    {
        require('import/move-expirations.php');
    }
    elseif ($_GET['cron'] == 'superior')
    {
        require('import/fix-superior-rec.php');
    }
    elseif ($_GET['cron'] == 'import_id')
    {
        require('import/user-import-id.php');
    }
    elseif ($_GET['cron'] == 'new_product')
    {
        require('import/import-new-product-rvs.php');
    }*/
}
add_action('template_redirect', 'ipema_trigger_cron');

function ipema_stop_cron()
{
     wp_clear_scheduled_hook('certified-products-cron');
     wp_clear_scheduled_hook('webinar-email');
     wp_clear_scheduled_hook('expiring-alerts');
}
register_deactivation_hook(__FILE__, 'ipema_stop_cron');

function ipema_stop_views_caching($refresh)
{
    return true;
}
add_filter('wpv_filter_disable_caching', 'ipema_stop_views_caching');

add_filter('get_the_archive_title', function($title) {
    if (is_post_type_archive())
    {
        return post_type_archive_title('', false);
    }

    return $title;
});

/*function ipema_handle_modification_rvs($query, $view_settings, $id)
{
    if ($id != 361)
    {
        return $query;
    }

    if (is_numeric($_GET['base']))
    {
        $_GET['model'] = array();
        $products = get_posts(array(
            'post_status' => 'any',
            'post_type' => 'product',
            'tax_query' => array(array(
                'taxonomy' => 'base',
                'field' => 'term_id',
                'terms' => $_GET['base']
            ))
        ));

        foreach ($products as $product)
        {
            $_GET['model'][] = $product->ID;
        }
    }

    return $query;
}
add_filter('wpv_filter_query', 'ipema_handle_modification_rvs', 9, 3);*/

function ipema_is_last_rejection()
{
    $rv = get_post();
    if ($rv->post_status != 'publish')
    {
        return false;
    }
    if ($rv->status != 'rejected')
    {
        return false;
    }

    $search = array(
        'post_type' => 'rv',
        'post_status' => 'any',
        'meta_query' => NULL,
        'date_query' => array(
            'after' => $rv->post_date
        ),
        'posts_per_page' => 1,
        'fields' => 'ids'
    );

    $certs = get_the_terms($rv->ID, 'certification');
    if ($certs != false)
    {
        $certIDs = array();
        foreach ($certs as $cert)
        {
            $certIDs[] = $cert->term_id;
        }

        $search['tax_query'] = array(array(
            'taxonomy' => 'certification',
            'terms' => $certIDs
        ));
    }

    $affected = get_post_meta($rv->ID, 'affected_id');
    foreach ($affected as $productID)
    {
        $search['meta_query'] = array(array(
            'key' => 'affected_id',
            'value' => $productID
        ));
        $newer = get_posts($search);

        if (count($newer) > 0)
        {
            return false;
        }
    }

    foreach ($affected as $productID)
    {
        if (ipema_is_new($productID))
        {
            return true;
        }
    }

    return strtotime($rv->post_modified) > strtotime('30 days ago');
}

function ipema_can_unobsolete()
{
    global $post;

    $request = get_the_terms($post->ID, 'request');
    if ($request == false)
    {
        return false;
    }
    if ($request[0]->slug != 'remove')
    {
        return false;
    }

    // Already undone
    $undoer = get_posts(array(
        'post_type' => 'rv',
        'post_parent' => $post->ID,
        'fields' => 'ids',
        'posts_per_page' => 1
    ));
    if (count($undoer) > 0)
    {
        return false;
    }

    $rvCerts = array();
    $certs = get_the_terms($post->ID, 'certification');
    foreach ($certs as $cert)
    {
        $rvCerts[] = $cert->term_id;
    }

    $affected = get_post_meta($post->ID, 'affected_id');
    foreach ($affected as $productID)
    {
        // Switched families
        $changes = get_posts(array(
            'post_type' => 'rv',
            'post_status' => 'any',
            'meta_query' => array(array(
                'key' => 'affected_id',
                'value' => $productID
            )),
            'tax_query' => array(array(
                'taxonomy' => 'request',
                'terms' => 'family',
                'field' => 'slug'
            )),
            'date_query' => array(array(
                'after' => $post->post_date
            )),
            'fields' => 'ids',
            'posts_per_page' => 1
        ));
        if (count($changes) > 0)
        {
            return false;
        }

        // Tested for a certification we removed
        $changes = get_posts(array(
            'post_type' => 'rv',
            'post_status' => 'any',
            'meta_query' => array(array(
                'key' => 'affected_id',
                'value' => $productID
            )),
            'tax_query' => array(
                array(
                    'taxonomy' => 'request',
                    'terms' => array(
                        'test',
                        'add',
                        'add-model',
                        'add-certification'
                    ),
                    'field' => 'slug'
                ),
                array(
                    'taxonomy' => 'certification',
                    'terms' => $rvCerts
                )
            ),
            'date_query' => array(array(
                'after' => $post->post_date
            )),
            'fields' => 'ids',
            'posts_per_page' => 1
        ));
        if (count($changes) > 0)
        {
            return false;
        }

        // Edited while obsolete
        $changes = get_posts(array(
            'post_type' => 'rv',
            'meta_query' => array(
                array(
                    'key' => 'affected_id',
                    'value' => $productID
                ),
                array(
                    'key' => 'status',
                    'value' => 'processed'
                )
            ),
            'tax_query' => array(array(
                'taxonomy' => 'request',
                'terms' => 'edit',
                'field' => 'slug'
            )),
            'date_query' => array(array(
                'after' => $post->post_date
            )),
            'fields' => 'ids',
            'posts_per_page' => 1
        ));
        if (count($changes) > 0)
        {
            return false;
        }
    }

    // Only allow 30 days to change your mind
    return strtotime($post->post_date) > strtotime('30 days ago');
}

function ipema_allow_edit_company($can_edit)
{
    return 'can_manage_account';
}
add_filter(gform_update_post::PREFIX . '/public_edit', 'ipema_allow_edit_company');

function ipema_populate_address($form)
{
    $user = wp_get_current_user();
    $company = get_post($user->company_id);

    $address = array(
        '4.1' => 'address',
        '4.2' => 'address_2',
        '4.3' => 'city',
        '4.4' => 'state',
        '4.5' => 'zip',
        '4.6' => 'country'
    );

    $account_type = get_the_terms($company->ID, 'account-type')[0];
    $currentContact = get_post_meta(
        $company->ID,
        'quarterly_sales',
        true
    );
    foreach ($form['fields'] as &$field)
    {
        if ($field->id == 4)
        {
            foreach ($field->inputs as &$input)
            {
                $input['defaultValue'] = get_post_meta(
                    $company->ID,
                    $address[$input['id']],
                    true
                );
            }
        }
        elseif ($field->id == 9)
        {
            if ($account_type->slug == 'associate')
            {
                $field->visibility = 'hidden';
            }
            else if ( ! ipema_user_company_active('equipment') &&
                ! ipema_user_company_active('surfacing'))
            {
                $field->visibility = 'hidden';
            }
        }
        elseif ($field->id == 10)
        {
            if ($account_type->slug != 'manufacturer')
            {
                $field->visibility = 'hidden';
            }
            else
            {
                if ($currentContact)
                {
                    $field->choices[0]['isSelected'] = true;
                    $field->defaultValue = $field->choices[0]['value'];
                }
            }
        }
        elseif ($field->id == 11)
        {
            $contacts = get_users(array(
                'meta_key' => 'company_id',
                'meta_value' => $company->ID
            ));

            $choices = array();
            foreach ($contacts as $contact)
            {
                if ($contact->has_cap('manage_ipema'))
                {
                    continue;
                }
                if ($contact->has_cap('can_validate_products'))
                {
                    continue;
                }
                $choices[] = array(
                    'text' => $contact->display_name,
                    'value' => $contact->id
                 );
            }

            $field->choices = $choices;
        }
        elseif ($field->id == 12)
        {
            if ($account_type->slug != 'manufacturer')
            {
                $field->visibility = 'hidden';
            }
        }
    }

    return $form;
}
add_filter('gform_pre_render_18', 'ipema_populate_address');
add_filter('gform_pre_validation_18', 'ipema_populate_address');
add_filter('gform_pre_submission_filter_18', 'ipema_populate_address');

function ipema_store_address($data, $form, $entry)
{
    if ($form['id'] != 18)
    {
        return $data;
    }

    $user = wp_get_current_user();

    $address = array(
        '4.1' => 'address',
        '4.2' => 'address_2',
        '4.3' => 'city',
        '4.4' => 'state',
        '4.5' => 'zip',
        '4.6' => 'country'
    );

    foreach ($address as $field => $label)
    {
        update_post_meta($user->company_id, $label, rgar($entry, $field));
    }

    return $data;
}
add_filter('gform_post_data', 'ipema_store_address', 10, 3);

function ipema_resurrect_user($validation)
{
    if ($validation['is_valid'])
    {
        return $validation;
    }

    foreach ($validation['form']['fields'] as $field)
    {
        if ($field->id == 2)
        {
            if ($field->failed_validation)
            {
                $user = wp_get_current_user();
                $dupe = get_user_by('email', rgpost('input_2'));
                if ($dupe && $dupe->old_company_id == $user->company_id)
                {
                    delete_user_meta($dupe->id, 'old_company_id');
                    update_user_meta(
                        $dupe->id,
                        'company_id',
                        $user->company_id
                    );

                    wp_redirect('/members/account/users/');
                    die();
                }
            }
            break;
        }
    }

    return $validation;
}
add_filter('gform_validation_19', 'ipema_resurrect_user');

function ipema_new_user($user_id, $conf, $entry, $passwd)
{
    if ($entry['form_id'] != 19)
    {
        return;
    }

    $user = new WP_User($user_id);
    if (rgar($entry, '3.1'))
    {
        $user->add_cap('can_manage_products');
    }
    if (rgar($entry, '3.2'))
    {
        $user->add_cap('can_manage_account');
    }
}
add_action('gform_user_registered', 'ipema_new_user', 10, 4);

function ipema_block_password_email($block, $user, $userdata)
{
    if ($user->wp_default_password_nag)
    {
        return true;
    }

    return $block;
}
add_filter('send_password_change_email', 'ipema_block_password_email', 10, 3);

function ipema_password_set($user_id, $feed, $entry, $user_pass)
{
    if ($entry['form_id'] == 20)
    {
        delete_user_meta($user_id, 'wp_default_password_nag');
    }
}
add_action('gform_user_updated', 'ipema_password_set', 10, 4);

function ipema_update_user_permissions()
{
    if ( ! is_page('users') || $_SERVER['REQUEST_METHOD'] != 'POST')
    {
        return;
    }

    if ( ! current_user_can('can_manage_account'))
    {
        wp_redirect('/members');
        exit();
    }

    $user = wp_get_current_user();
    if ( ! $user->company_id)
    {
        wp_redirect('/members');
        exit();
    }
    $members = get_users(array(
        'meta_key' => 'company_id',
        'meta_value' => $user->company_id
    ));

    $hasAdmin = false;
    foreach ($members as $member)
    {
        if (in_array($member->id, $_POST['products']))
        {
            $member->add_cap('can_manage_products');
        }
        else
        {
            $member->remove_cap('can_manage_products');
        }

        if (in_array($member->id, $_POST['account']))
        {
            $member->add_cap('can_manage_account');
            $hasAdmin = true;
        }
        else
        {
            $member->remove_cap('can_manage_account');
        }
    }

    if ( ! $hasAdmin)
    {
        $user->add_cap('can_manage_account');
    }

    if ( ! in_array($user->ID, $_POST['account']) && $hasAdmin)
    {
        wp_redirect('/members');
        exit();
    }
}
add_action('template_redirect', 'ipema_update_user_permissions');

function ipema_delete_user()
{
    if ( ! is_page('delete'))
    {
        return;
    }

    if ( ! current_user_can('can_manage_account'))
    {
        wp_redirect('/members');
        exit();
    }

    if (! is_numeric($_GET['id']))
    {
        wp_redirect('/members/account/users');
        exit();
    }

    $currentUser = wp_get_current_user();
    $deleteUser = get_user_by('id', $_GET['id']);

    if ($currentUser->company_id != $deleteUser->company_id)
    {
        wp_redirect('/members/account/users');
        exit();
    }

    if ($deleteUser->has_cap('can_manage_account'))
    {
        $allUsers = get_users(array(
            'meta_key' => 'company_id',
            'meta_value' => $currentUser->company_id
        ));

        $adminCount = 0;
        foreach ($allUsers as $user)
        {
            if ($user->has_cap('can_manage_account'))
            {
                $adminCount++;
            }
        }

        if ($adminCount <= 1)
        {
            return;
        }
    }

    update_user_meta(
        $deleteUser->id,
        'old_company_id',
        $deleteUser->company_id
    );
    delete_user_meta($deleteUser->id, 'company_id');

    $deleteUser->remove_cap('can_manage_account');
    $deleteUser->remove_cap('can_manage_products');

    if ($currentUser->id == $deleteUser->id)
    {
        wp_logout();
    }

    wp_redirect('/members/account/users');
    exit();
}
add_action('template_redirect', 'ipema_delete_user');

function ipema_require_phone($result, $phone, $form, $field)
{
    if ($result['is_valid'] == false)
    {
        return $result;
    }

    $entry = GFFormsModel::get_current_lead();

    if (rgar($entry, 4) == 'phone' && ! $phone)
    {
        $result['is_valid'] = false;
        $result['message'] = 'Preferred contact method cannot be blank';
    }

    return $result;
}
add_filter('gform_field_validation_21_3', 'ipema_require_phone', 10, 4);

function ipema_validate_password($result, $password, $form, $field)
{
    if ($result['is_valid'] == false)
    {
        return $result;
    }

    $user = wp_get_current_user();
    if ( ! wp_check_password($password, $user->data->user_pass, $user->ID))
    {
        $result['is_valid'] = false;
        $result['message'] = 'Password is incorrect';
    }

    return $result;
}
add_filter('gform_field_validation_22_1', 'ipema_validate_password', 10, 4);

function ipema_unformat_price($price)
{
    return str_replace(
        array(',', '$'),
        '',
        $price
    );
}

function ipema_prorate_price($prorate, $basePrice)
{
    $price = ipema_unformat_price($basePrice);
    return round($price * $prorate / 12);
}

function ipema_prorate_price_v2()
{
    $price = 200;
    
    switch (date("n")) {
      case 1:
        $price = 100;
        break;
      case 2:
        $price = 83.33;
        break;
      case 3:
        $price = 66.67;
        break;
      case 4:
        $price = 50;
        break;
      case 5:
      case 6:
      case 7:
        $price = 200;
        break;
      case 8:
        $price = 183.33;
        break;
      case 9:
        $price = 166.67;
        break;
      case 10:
        $price = 150;
        break;
      case 11:
        $price = 133.33;
        break;
      case 12:
        $price = 116.67;
        break;
    }
    
    return $price;
}

function ipema_populate_company($form, $ajax=FALSE, $field_values=NULL)
{
    $user = wp_get_current_user();
    $company = get_post($user->company_id);

    $address = array(
        '2.1' => 'address',
        '2.2' => 'address_2',
        '2.3' => 'city',
        '2.4' => 'state',
        '2.5' => 'zip',
        '2.6' => 'country'
    );

    $account_type = get_the_terms($company->ID, 'account-type')[0];
    $type = $account_type->slug;
    if ($type != 'associate')
    {
        $products = get_the_terms($company->ID, 'product-type');
        if (count($products) > 1)
        {
            $type = 'both';
        }
        else
        {
            $type = $products[0]->slug;
        }
    }

    $hideCert = false;
    $equipment_certified = get_post_meta($company->ID, 'equipment', true);
    $surfacing_certified = get_post_meta($company->ID, 'surfacing', true);
    if ( ! is_numeric($equipment_certified))
    {
        $hideCert = ! is_numeric($surfacing_certified);
    }
    $lastMember = get_post_meta($company->ID, 'active', true);

    $currentMembership = 2; // Both
    if ( ! $hideCert)
    {
        if (is_numeric($equipment_certified))
        {
            $lastCert = $equipment_certified;
            if (is_numeric($surfacing_certified))
            {
                if ($surfacing_certified > $equipment_certified)
                {
                    $lastCert = $surfacing_certified;
                }
            }
        }
        elseif (is_numeric($surfacing_certified))
        {
            $lastCert = $surfacing_certified;
        }

        if ( ! is_numeric($lastMember))
        {
            $currentMembership = 1; // Certification Only
        }
        elseif ($lastMember > $lastCert)
        {
            $currentMembership = 0; // Member Only
        }
        elseif ($lastCert > $lastMember)
        {
            $currentMembership = 1; // Certification Only
        }
    }

    /*$totalSales = rgar($_POST, 'input_11', 0);
    $equipmentSales = rgar($_POST, 'input_26', 0);
    $surfacingSales = rgar($_POST, 'input_27', 0);
    $prices = ipema_calculate_prices(
        $totalSales,
        $equipmentSales,
        $surfacingSales
    );*/
    
    $prices = ipema_calculate_prices_v2($company->ID);

    $_POST['input_33'] = 0;
    $_POST['input_34'] = 0;
    $_POST['input_35'] = 0;
    if ($type != 'associate')
    {
        if ($hideCert)
        {
            $_POST['input_33'] = $prices['memberOnly'];
        }
        else
        {
            $choice = explode('|', rgpost('input_12'));
            $choice = $choice[0];
            if ($choice == 'member')
            {
                $_POST['input_33'] = $prices['memberOnly'];
            }
            elseif ($choice == 'both')
            {
                $_POST['input_33'] = $prices['memberOnly'];
                if ($prices['certOnly'] > 0)
                {
                    if ($type != 'surfacing')
                    {
                        $_POST['input_34'] = $prices['equipmentCombined'];
                    }
                    if ($type != 'equipment')
                    {
                        $_POST['input_35'] = $prices['surfacingCombined'];
                    }
                }
            }
            else
            {
                if ($prices['certOnly'] > 0)
                {
                    if ($type != 'surfacing')
                    {
                        $_POST['input_34'] = $prices['equipmentOnly'];
                    }
                    if ($type != 'equipment')
                    {
                        $_POST['input_35'] = $prices['surfacingOnly'];
                    }
                }
            }
        }
    }

    /*foreach ($prices as &$price)
    {
        $price = '$' . number_format($price);
    }*/

    foreach ($form['fields'] as &$field)
    {
        if ($field->id == 2)
        {
            foreach ($field->inputs as &$input)
            {
                $input['defaultValue'] = get_post_meta(
                    $company->ID,
                    $address[$input['id']],
                    true
                );
            }
        }
        elseif ($field->id == 9)
        {
            $field->defaultValue = $type;
        }
        elseif ($field->id == 12)
        {
            if ($hideCert && is_a($field, 'GF_Field_Radio'))
            {
                $field = GF_Fields::create(array(
                    'type' => 'product',
                    'id' => $field->id,
                    'label' => 'Renew IPEMA Membership',
                    'adminLabel' => $field->adminLabel,
                    'isRequired' => $field->isRequired,
                    'inputs' => array(
                        array(
                            'id' => "{$field->id}.1",
                            'label' => 'Name',
                            'name' => ''
                        ),
                        array(
                            'id' => "{$field->id}.2",
                            'label' => 'Price',
                            'name' => ''
                        ),
                        array(
                            'id' => "{$field->id}.3",
                            'label' => 'Quantity',
                            'name' => ''
                        )
                    ),
                    'inputType' => 'singleproduct',
                    'formId' => $field->formId,
                    'description' => $field->description,
                    'labelPlacement' => $field->labelPlacement,
                    'descriptionPlacement' => $field->descriptionPlacement,
                    'visibility' => $field->visibility,
                    'conditionalLogic' => $field->conditionalLogic,
                    'productField' => $field->productField,
                    'basePrice' => "{$prices['memberOnly']}.00",
                    'disableQuantity' => true,
                    'pageNumber' => $field->pageNumber
                ));

                $_POST['input_12_2'] = $field->basePrice;
            }

            if (is_a($field, 'GF_Field_Radio'))
            {
                $text = explode('&mdash;', $field->choices[0]['text']);

                $field->choices[0]['price'] = "{$prices['memberOnly']}.00";
                $field->choices[0]['text'] = "$text[0]&mdash; {$prices['memberOnly']}";

                $text = explode('&mdash;', $field->choices[1]['text']);

                $field->choices[1]['price'] = "{$prices['certOnly']}.00";
                $field->choices[1]['text'] = "$text[0]&mdash; {$prices['certOnly']}";

                $text = explode('&mdash;', $field->choices[2]['text']);

                $field->choices[2]['price'] = "{$prices['combined']}.00";
                $field->choices[2]['text'] = "$text[0]&mdash; {$prices['combined']}";

                $field->choices[$currentMembership]['isSelected'] = true;
            }
            else
            {
                $field->basePrice = $prices['memberOnly'];
            }
        }
        elseif ($field->id == 19 && $type == 'associate')
        {
            //$_POST['input_33'] = trim($field->basePrice, '$ ');
            //$_POST['input_19_2'] = $field->basePrice;

            //$price = '$' . number_format($prices['memberOnly'], 2);
            $price = '$500.00';

            $field->basePrice = $price;
            $_POST['input_19_2'] = $field->basePrice;
                
            $_POST['input_33'] = trim($field->basePrice, '$ ');
            
        }
    }

    return $form;
};
add_filter('gform_pre_render_23', 'ipema_populate_company');
add_filter('gform_pre_validation_23', 'ipema_populate_company');
add_filter('gform_pre_submission_filter_23', 'ipema_populate_company');

function ipema_prevent_future_renewal($result, $value, $form, $field)
{
    if ( ! $result['is_valid'])
    {
        return $result;
    }

    $user = wp_get_current_user();
    $companyID = $user->company_id;

    $year = ipema_current_year();

    $tooFar = false;
    if ($field->id == 19)
    {
        if (get_post_meta($companyID, 'active', true) > $year)
        {
            $tooFar = true;
        }
    }
    elseif ($field->id == 12)
    {
        $choice = explode('|', $value)[0];
        if ($choice != 'member')
        {
            $products = get_the_terms($companyID, 'product-type');
            foreach ($products as $product)
            {
                if (get_post_meta($companyID, $product->slug, true) > $year)
                {
                    $tooFar = true;
                }
            }
        }

        if ($choice != 'certification')
        {
            if (get_post_meta($companyID, 'active', true) > $year)
            {
                $tooFar = true;
            }
        }
    }

    if ($tooFar)
    {
        $result['is_valid'] = false;
        $result['message'] = 'This has already been renewed';
    }

    return $result;
}
add_filter('gform_field_validation_23_12', 'ipema_prevent_future_renewal', 10, 4);
add_filter('gform_field_validation_23_19', 'ipema_prevent_future_renewal', 10, 4);

function ipema_do_business_renewal($company, $entry, $currentYear)
{
    $membership = true;
    $certification = true;
    $selection = array(12, 19);
    foreach ($selection as $id)
    {
        if (rgar($entry, "$id.1"))
        {
            $certification = false;
            break;
        }
        elseif (rgar($entry, $id))
        {
            $choice = explode('|', $entry[$id])[0];
            if ($choice == 'member')
            {
                $certification = false;
            }
            elseif ($choice == 'certification')
            {
                $membership = false;
            }
            break;
        }
    }

    if ($membership)
    {
        $activeYear = get_post_meta($company->ID, 'active', true);
        if ($activeYear < $currentYear)
        {
            update_post_meta($company->ID, 'active', $currentYear);
        }
        else
        {
            update_post_meta($company->ID, 'active', $activeYear + 1);
        }
    }
    if ($certification)
    {
        $equipmentYear = get_post_meta($company->ID, 'equipment', true);
        $pending = get_post_meta(
            $company->ID,
            'pending_equipment_agreement',
            true
        );
        $rejected = get_post_meta(
            $company->ID,
            'rejected_equipment_agreement',
            true
        );
        if (is_numeric($equipmentYear) && ! $pending && ! $rejected)
        {
            if ($equipmentYear < $currentYear)
            {
                update_post_meta($company->ID, 'equipment', $currentYear);
            }
            else
            {
                update_post_meta($company->ID, 'equipment', $equipmentYear + 1);
            }
        }

        $surfacingYear = get_post_meta($company->ID, 'surfacing', true);
        $pending = get_post_meta(
            $company->ID,
            'pending_surfacing_agreement',
            true
        );
        $rejected = get_post_meta(
            $company->ID,
            'rejected_surfacing_agreement',
            true
        );
        if (is_numeric($surfacingYear) && ! $pending && ! $rejected)
        {
            if ($surfacingYear < $currentYear)
            {
                update_post_meta($company->ID, 'surfacing', $currentYear);
            }
            else
            {
                update_post_meta($company->ID, 'surfacing', $surfacingYear + 1);
            }
        }
    }
}

function ipema_handle_renewal($entry, $form)
{
    $user = wp_get_current_user();
    $company = get_post($user->company_id);

    $address = array(
        '2.1' => 'address',
        '2.2' => 'address_2',
        '2.3' => 'city',
        '2.4' => 'state',
        '2.5' => 'zip',
        '2.6' => 'country'
    );

    foreach ($address as $field => $label)
    {
        update_post_meta($company->ID, $label, rgar($entry, $field));
    }

    if (rgar($entry, 30))
    {
        update_post_meta(
            $company->ID,
            'pending_equipment_agreement',
            $entry['id']
        );
    }
    if (rgar($entry, 31))
    {
        update_post_meta(
            $company->ID,
            'pending_surfacing_agreement',
            $entry['id']
        );
    }

    $payment = rgar($entry, '23');
    if ($payment == 'Credit Card')
    {
        ipema_do_business_renewal($company, $entry, date('Y'));
    }
    elseif ($payment == 'Check')
    {
        update_post_meta($company->ID, 'pending_renewal', $entry['id']);
    }
}
add_action('gform_after_submission_23', 'ipema_handle_renewal', 10, 2);

add_filter('gform_confirmation_23', function($confirmation, $form, $entry) {
    if ($entry[23] != 'Credit Card')
    {
        return $confirmation;
    }

    $insurance = ipema_user_company_active('equipment');
    if ( ! $insurance)
    {
        $insurance = ipema_user_company_active('surfacing');
    }

    if ( ! $insurance)
    {
        return 'Thank you for your continued support of IPEMA.';
    }

    return $confirmation;

}, 10, 3);

function ipema_validate_insurance($result)
{
    if ($result['is_valid'] == false)
    {
        return $result;
    }
    $entry = GFFormsModel::get_current_lead();

    $errors = array();

    $ipema_insurance = rgar($entry, 1);
    $ipema_exp = rgar($entry, 2);
    $tuv_insurance = rgar($entry, 3);
    $tuv_exp = rgar($entry, 4);

    /*if ($ipema_insurance && ! $ipema_exp)
    {
        $errors[2] = 'Expiration date is required';
    }
    elseif ($ipema_exp)
    {
        if ( ! $ipema_insurance)
        {
            $errors[1] = 'Please attach insurance documentation';
        }
        if (strtotime($ipema_exp) < strtotime('+1 month'))
        {
            $errors[2] = 'Expiration date must be in the future';
        }
    }*/

    if (current_user_can('manage_ipema'))
    {
        if ( ! $ipema_insurance && ! $tuv_insurance)
        {
            $errors[1] = 'At least one insurance document is required';
        }
    }
    else
    {
        if ( ! $ipema_insurance)
        {
            $errors[1] = 'Certificate for IPEMA is required';
        }
        if ( ! $tuv_insurance)
        {
            $errors[3] = 'Certificate for TÜV SÜD America is required';
        }
        if (strtotime($tuv_exp) < strtotime('+1 month'))
        {
            $errors[4] = 'Expiration date must be in the future';
        }
    }

    if (count($errors) > 0)
    {
        $result['is_valid']  = false;

        foreach ($result['form']['fields'] as &$field)
        {
            if (array_key_exists($field->id, $errors))
            {
                $field->failed_validation = true;
                $field->validation_message = $errors[$field->id];
            }
        }
    }

    return $result;
}
add_filter('gform_validation_24', 'ipema_validate_insurance');

function ipema_conditional_insurance_notification($disabled, $notification, $form, $entry)
{
    if ($notification['event'] != 'form_submission')
    {
        return $disabled;
    }

    if (current_user_can('manage_ipema'))
    {
        return true;
    }

    return $disabled;
}
add_filter('gform_disable_notification_24', 'ipema_conditional_insurance_notification', 10, 4);

function ipema_handle_insurance($entry, $form)
{
    $user = wp_get_current_user();
    if ( ! $user->has_cap('manage_ipema'))
    {
        update_post_meta($user->company_id, 'pending_insurance', $entry['id']);
        return;
    }

    if (rgar($entry, 1))
    {
        update_post_meta($_GET['member'], 'ipema-insurance', rgar($entry, 1));
    }
    if (rgar($entry, 3))
    {
        update_post_meta($_GET['member'], 'tuv-insurance', rgar($entry, 3));
    }
    update_post_meta(
        $_GET['member'],
        'insurance-exp',
        strtotime(rgar($entry, 4))
    );
}
add_action('gform_after_submission_24', 'ipema_handle_insurance', 10, 2);

function ipema_admin_insurance_redirect($confirmation, $form, $entry, $is_ajax)
{
    $user = wp_get_current_user();
    if ( ! $user->has_cap('manage_ipema'))
    {
        return $confirmation;
    }

    // Redirect to account details
    $confirmation = array(
        'redirect' => get_permalink(753) . '?member=' . $_GET['member']
    );

    return $confirmation;
}
add_filter('gform_confirmation_24', 'ipema_admin_insurance_redirect', 10, 4);

function ipema_handle_personal_renewal($entry, $form)
{
    $user = wp_get_current_user();

    $payment = rgar($entry, 3);
    if ($payment == 'Credit Card')
    {
        $currentYear = date('Y');
        $activeYear = get_post_meta($user->company_id, 'active', true);
        if ($activeYear < $currentYear)
        {
            update_post_meta($user->company_id, 'active', $currentYear);
        }
        else
        {
            update_post_meta($user->company_id, 'active', $activeYear + 1);
        }
    }
    elseif ($payment == 'Check')
    {
        update_post_meta($user->company_id, 'pending_renewal', $entry['id']);
    }
}
add_action('gform_after_submission_25', 'ipema_handle_personal_renewal', 10, 2);

function ipema_payment_complete_notifications($entry)
{
    global $current_user;
    $form = RGFormsModel::get_form_meta($entry['form_id']);

    $realUser = $current_user;
    $current_user = get_user_by('id', $entry['created_by']);

    GFAPI::send_notifications($form, $entry, 'complete_payment');

    $current_user = $realUser;
}

function ipema_pay_by_check()
{
    global $current_user;

    if ( ! is_page('paid') || $_SERVER['REQUEST_METHOD'] != 'POST')
    {
        return;
    }

    if ( ! $_POST['check-number'])
    {
        return;
    }

    $entry = GFFormsModel::get_lead($_POST['entry']);
    $year = substr($entry['date_created'], 0, 4);

    if ($entry['form_id'] == 23)
    {
        $amount = rgar($entry, 24);
        ipema_do_business_renewal(
            get_post($_POST['company-id']),
            $entry,
            $year
        );
    }
    elseif ($entry['form_id'] == 25)
    {
        $amount = rgar($entry, 2);
        $activeYear = get_post_meta($_POST['company-id'], 'active', true);
        if ($activeYear < $year)
        {
            update_post_meta($_POST['company-id'], 'active', $year);
        }
        else
        {
            update_post_meta($_POST['company-id'], 'active', $activeYear + 1);
        }
    }
    elseif ($entry['form_id'] == 1)
    {
        $amount = rgar($entry, 26);
        $membership = ipema_get_membership_type($entry);
        if ($membership != 'certification')
        {
            $year = ipema_signup_year(strtotime($entry['date_created']));

            update_post_meta($_POST['company-id'], 'active', $year);
        }
    }
    elseif ($entry['form_id'] == 1)
    {
        $amount = rgar($entry, 26);
        $membership = ipema_get_membership_type($entry);
        if ($membership != 'certification')
        {
            $year = ipema_signup_year(strtotime($entry['date_created']));

            update_post_meta($_POST['company-id'], 'active', $year);
        }
    }
    elseif ($entry['form_id'] == 34)
    {
        $amount = rgar($entry, 33);
    }
    delete_post_meta($_POST['company-id'], 'pending_renewal');

    $entry['payment_method'] = 'check';
    $entry['payment_date'] = 'now';
    $entry['payment_amount'] = $amount;
    $entry['transaction_id'] = $_POST['check-number'];
    GFAPI::update_entry($entry);

    ipema_payment_complete_notifications($entry);

    wp_redirect('./../');
    die();
}
add_action('template_redirect', 'ipema_pay_by_check');

function ipema_populate_insurance($form)
{
    $entry = GFFormsModel::get_lead($_GET['insurance']);
    $company = get_posts(array(
        'post_type' => 'company',
        'meta_query' => array(array(
            'key' => 'pending_insurance',
            'value' => $_GET['insurance']
        ))
    ));
    if (count($company) != 1)
    {
        return $form;
    }
    $company_id = $company[0]->ID;
    if ($entry['form_id'] == 1)
    {
        $ipema_url = $entry[25];
        $ipema_date = $entry[60];
        $tuv_url = $entry[59];
        $tuv_date = $entry[61];
        $email = $entry[13];
        //$company_id = $entry['post_id'];
    }
    elseif ($entry['form_id'] == 24)
    {
        $ipema_url = $entry[1];
        $ipema_date = $entry[2];
        $tuv_url = $entry[3];
        $tuv_date = $entry[4];
        $user = get_user_by('ID', $entry['created_by']);
        $email = $user->user_email;
    }
    elseif ($entry['form_id'] == 34)
    {
        $ipema_url = $entry[10];
        $ipema_date = $entry[11];
        $tuv_url = $entry[12];
        $tuv_date = $entry[13];
        $user = get_user_by('ID', $entry['created_by']);
        $email = $user->user_email;
    }

    foreach ($form['fields'] as &$field)
    {
        if ($field->id == 1)
        {
            $field->content = '<p><a href="' . $ipema_url . '" target="_blank">'
                . 'View Uploaded IPEMA Insurance Document</a></p>';
        }
        elseif ($field->id == 2)
        {
            $field->defaultValue = $ipema_date;
        }
        elseif ($field->id == 3)
        {
            $field->content = '<p><a href="' . $tuv_url . '" target="_blank">'
                . 'View Uploaded TUV Insurance Document</a></p>';
        }
        elseif ($field->id == 4)
        {
            $field->defaultValue = $tuv_date;
        }
        elseif ($field->id == 7)
        {
            $field->defaultValue = $email;
        }
        elseif ($field->id == 8)
        {
            $field->defaultValue = $company_id;
        }
    }

    return $form;
}
add_filter('gform_pre_render_26', 'ipema_populate_insurance');
add_filter('gform_pre_validation_26', 'ipema_populate_insurance');
add_filter('gform_pre_submission_filter_26', 'ipema_populate_insurance');

function ipema_update_insurance($entry, $form)
{
    $company_id = rgar($entry, 8);

    if (rgar($entry, 5) == 'Approved')
    {
        $submission = GFFormsModel::get_lead($_GET['insurance']);
        if ($submission['form_id'] == 1)
        {
            $ipema_insurance = $submission[25];
            $tuv_insurance = $submission[59];
        }
        elseif ($submission['form_id'] == 24)
        {
            $ipema_insurance = $submission[1];
            $tuv_insurance = $submission[3];
        }
        elseif ($submission['form_id'] == 34)
        {
            $ipema_insurance = $submission[10];
            $tuv_insurance = $submission[12];
        }

        update_post_meta($company_id, 'ipema-insurance', $ipema_insurance);
        update_post_meta($company_id, 'tuv-insurance', $tuv_insurance);
        update_post_meta(
            $company_id,
            'insurance-exp',
            strtotime(rgar($entry, 4))
        );
    }

    delete_post_meta($company_id, 'pending_insurance');

    $types = get_the_terms($company_id, 'product-type');
    foreach ($types as $type)
    {
        if (get_post_meta($company_id, $type->slug, true))
        {
            continue;
        }

        if (get_post_meta($company_id, "pending_{$type->slug}_agreement", true))
        {
            continue;
        }

        if (get_post_meta($company_id, "rejected_{$type->slug}_agreement", true))
        {
            continue;
        }

        ipema_new_manufacturer_email($company_id, $type->slug);
    }
}
add_action('gform_after_submission_26', 'ipema_update_insurance', 10, 2);

function ipema_wants_certification($company=NULL)
{
    $company = get_post($company);

    $types = get_the_terms($company->ID, 'product-type');

    if ($types == false)
    {
        return false;
    }
    foreach ($types as $type)
    {
        $isApproved = get_post_meta($company->ID, $type->slug, true);
        if ( ! $isApproved)
        {
            return true;
        }
    }

    return false;
    /*$entries = get_post_meta($company->ID, '_gform-entry-id');

    if (count($entries) == 0)
    {
        return false;
    }

    $entry_id = array_pop($entries);
    foreach ($entries as $entry)
    {
        if ($entry < $entry_id)
        {
            $entry_id = $entry;
        }
    }

    $entry = GFFormsModel::get_lead($entry_id);

    if ($entry['form_id'] != 1)
    {
        return false;
    }

    $type = ipema_get_membership_type($entry);

    return $type != 'member';*/
}

function ipema_verify_manufacturer()
{
    if ( ! is_page('approve') || $_SERVER['REQUEST_METHOD'] != 'POST')
    {
        return;
    }

    $company = get_post($_GET['id']);
    if ($company == null)
    {
        return;
    }

    $entries = get_post_meta($company->ID, '_gform-entry-id');
    $entry_id = array_pop($entries);
    foreach ($entries as $entry)
    {
        if ($entry < $entry_id)
        {
            $entry_id = $entry;
        }
    }

    $entry = GFFormsModel::get_lead($entry_id);

    //$year = ipema_signup_year(strtotime($entry['date_created']));
    $year = ipema_current_year();

    if ($year < $company->active)
    {
        $year++;
    }

    $user = wp_get_current_user();
    $products = get_the_terms($company, 'product-type');
    foreach ($products as $product)
    {
        $productStart = get_post_meta(
            $company->ID,
            $product->slug . '_start',
            true
        );
        if ($productStart)
        {
            $productYear = ipema_signup_year($productStart);
            if ($productYear < $company->active)
            {
                $productYear++;
            }

            update_post_meta($company->ID, $product->slug, $productYear);
        }
        else
        {
            update_post_meta($company->ID, $product->slug, $year);
        }

        update_post_meta(
            $company->ID,
            $product->slug . '_approved',
            date('Y-m-d H:i:s')
        );
        update_post_meta(
            $company->ID,
            $product->slug . '_approved_by',
            $user->ID
        );
    }

    wp_mail($entry[13], 'Product Upload Approval', <<<EOT
Hello {$company->title},

Your request to upload products for certification has been approved. Please log
into your account to begin the process.

Thank you for your support,
The IPEMA Team
EOT
    );

    wp_redirect('./../');
    die();
}
add_action('template_redirect', 'ipema_verify_manufacturer');

function ipema_offline_renewal($form)
{
    foreach ($form['fields'] as &$field)
    {
        if ($field->id == 1)
        {
            $accountType = get_the_terms($_GET['member'], 'account-type');
            if ($accountType[0]->slug != 'manufacturer')
            {
                $field->adminOnly = true;
            }
            else
            {
                $certification = get_post_meta(
                    $_GET['member'],
                    'equipment',
                    true
                );
                if ( ! $certification)
                {
                    $certification = get_post_meta(
                        $_GET['member'],
                        'surfacing',
                        true
                    );
                }
                if ( ! $certification)
                {
                    $field->adminOnly = true;
                }
            }
        }
    }

    return $form;
}
add_filter('gform_pre_render_29', 'ipema_offline_renewal');
add_filter('gform_pre_validation_29', 'ipema_offline_renewal');
add_filter('gform_pre_submission_filter_29', 'ipema_offline_renewal');

function ipema_process_offline_renewal($entry, $form)
{
    if ( ! current_user_can('manage_ipema'))
    {
        return;
    }

    $membership = true;
    $certification = true;
    $type = rgar($entry, 1);
    if ( ! $type || $type == 'membership')
    {
        $certification = false;
    }
    elseif ($type == 'certification')
    {
        $membership = false;
    }

    $member_amount = rgar($entry, 2, 0);
    $cert_amount = rgar($entry, 8, 0);

    $fields = array(
        'check' => 5,
        'bank account' => 6,
        'offline credit card' => 7
    );
    $entry['payment_date'] = current_time('mysql');
    $entry['payment_amount'] = $member_amount + $cert_amount;
    $entry['payment_method'] = rgar($entry, 3);
    $entry['transaction_id'] = rgar($entry, $fields[$entry['payment_method']]);
    $entry['post_id'] = rgar($entry, 4);

    GFAPI::update_entry($entry);

    $companyID = rgar($entry, 4);
    $currentYear = current_time('Y');
    if ($membership)
    {
        $activeYear = get_post_meta($companyID, 'active', true);
        if ($activeYear < $currentYear)
        {
            update_post_meta($companyID, 'active', $currentYear);
        }
        else
        {
            update_post_meta($companyID, 'active', $activeYear + 1);
        }
    }
    if ($certification)
    {
        $equipmentYear = get_post_meta($companyID, 'equipment', true);
        if (is_numeric($equipmentYear))
        {
            if ($equipmentYear < $currentYear)
            {
                update_post_meta($companyID, 'equipment', $currentYear);
            }
            else
            {
                update_post_meta($companyID, 'equipment', $equipmentYear + 1);
            }
        }

        $surfacingYear = get_post_meta($companyID, 'surfacing', true);
        if (is_numeric($surfacingYear))
        {
            if ($surfacingYear < $currentYear)
            {
                update_post_meta($companyID, 'surfacing', $currentYear);
            }
            else
            {
                update_post_meta($companyID, 'surfacing', $surfacingYear + 1);
            }
        }
    }
}
add_action('gform_after_submission_29', 'ipema_process_offline_renewal', 10, 2);

function ipema_active_column($value, $year)
{
    if (is_numeric($value))
    {
        if ($value < $year)
        {
            return "Expired $value";
        }
        return "Through $value";
    }

    return 'No';
}

function ipema_get_latest_payment($postID, $formIDs, $memberOnly=false)
{
    global $wpdb, $receiptFields;
    $formIDs = (array)$formIDs;

    $payments = array(
        'member_amount' => NULL,
        'member_date' => NULL,
        'member_method' => NULL,
        'cert_amount' => NULL,
        'cert_date' => NULL,
        'cert_method' => NULL
    );

    $sql = "
        SELECT
          id
        FROM
          {$wpdb->prefix}gf_entry
        WHERE
          post_id = %d
        AND
          form_id IN (%d" . str_repeat(', %d', count($formIDs)) . ')
        AND
          payment_date IS NOT NULL
        ORDER BY
          payment_date DESC';

    $allPayments = $wpdb->get_col(
        $wpdb->prepare($sql, array_merge(array($postID, 29), $formIDs))
    );

    foreach ($allPayments as $entryID)
    {
        $payment = GFFormsModel::get_lead($entryID);

        if ($payment['form_id'] == 29)
        {
            if ($payment[2] && ! $payments['member_date'])
            {
                $payments['member_amount'] = $payment[2];
                $payments['member_date'] = $payment['payment_date'];
                $payments['member_method'] = $payment['payment_method'];
            }
            if ($payment[8] && ! $payments['cert_date'])
            {
                $payments['cert_amount'] = $payment[8];
                $payments['cert_date'] = $payment['payment_date'];
                $payments['cert_method'] = $payment['payment_method'];
            }
        }
        else
        {
            $fields = $receiptFields[$payment['form_id']];
            if ( ! $payments['member_date']
                && $fields['membership']
                && $payment[$fields['membership']]
            )
            {
                $payments['member_amount'] = $payment[$fields['membership']];
                $payments['member_date'] = $payment['payment_date'];
                $payments['member_method'] = $payment['payment_method'];
            }
            if ( ! $payments['cert_date']
                && ($fields['surfacing'] || $fields['equipment'])
            )
            {
                $amount = 0;
                $amount += rgar($payment, $fields['equipment'], 0);
                $amount += rgar($payment, $fields['surfacing'], 0);

                if ($amount > 0)
                {
                    $payments['cert_amount'] = $amount;
                    $payments['cert_date'] = $payment['payment_date'];
                    $payments['cert_method'] = $payment['payment_method'];
                }
            }
        }

        if ($memberOnly)
        {
            if ($payments['member_date'])
            {
                break;
            }
        }
        else
        {
            if ($payments['member_date'] && $payments['cert_date'])
            {
                break;
            }
        }

    }

    return $payments;
}

function ipema_company_notes($companyID, $source)
{
    $notes = get_comments(array(
        'post_id' => $companyID,
        'meta_key' => 'source',
        'meta_value' => $source
    ));

    $text = '';

    foreach ($notes as $note)
    {
        $text .= date('M jS, Y', strtotime($note->comment_date));
        $text .= ": {$note->comment_author}\n";
        $text .= "{$note->comment_content}\n\n";
    }

    return trim(html_entity_decode($text));
}

function ipema_download_report()
{
    if ( ! is_page('reports') || ! $_GET['download'])
    {
        return;
    }

    $filename = $_GET['download'];

    $output = fopen("php://output",'w') or die("Can't open php://output");
    header("Content-Type:application/csv");
    header("Content-Disposition:attachment;filename=$filename.csv");

    fputcsv($output, array(
        'Company',
        'Account Type',
        'Expected Dues',
        'Joined',
        'Address',
        'Address 2',
        'City',
        'State',
        'Zip Code',
        'Country',
        'Business Phone',
        'Alternate Phone',
        'Toll-Free Number',
        'Fax',
        'Website',
        'Notes',
        'IPEMA Member',
        'Certified Equipment',
        'Certified Surfacing',
        'Insurance Expires',
        'Membership Payment',
        'Payment Method',
        'Payment Date',
        'Certification Payment',
        'Payment Method',
        'Payment Date',
        'EIN',
        'Quarterly Sales',
        'First Name',
        'Last Name',
        'Username',
        'Role',
        'Main Contact',
        'Phone',
        'Email',
        'Contact Method',
        'IPEMA Leadership'
    ));

    $year = ipema_current_year();

    $criteria = array(
        'post_type' => 'company',
        'orderby' => 'post_title',
        'order' => 'ASC',
        'tax_query' => array(),
        'meta_query' => array(),
        'nopaging' => true
    );

    if ($_GET['member'])
    {
        if ($_GET['member'] == 'yes')
        {
            $criteria['meta_query'][] = array(
                'key' => 'active',
                'value' => $year,
                'compare' => '>=',
                'type' => 'NUMERIC'
            );
        }
        elseif ($_GET['member'] == 'no')
        {
            $criteria['meta_query'][] = array(
                'key' => 'active',
                'value' => $year,
                'compare' => '<',
                'type' => 'NUMERIC'
            );
        }
        elseif ($_GET['member'] == 'expired')
        {
            $criteria['meta_query'][] = array(
                'key' => 'active',
                'value' => array(1, $year - 1),
                'compare' => 'BETWEEN',
                'type' => 'NUMERIC'
            );
        }
    }

    if ($_GET['account'])
    {
        $criteria['tax_query'][] = array(
            'taxonomy' => 'account-type',
            'field' => 'slug',
            'terms' => $_GET['account']
        );
    }

    $checkExpiredCert = false;
    if ($_GET['certification'])
    {
        if ($_GET['certification'] == 'expired')
        {
            $checkExpiredCert = true;
        }
        else
        {
            if ($_GET['certification'] == 'both')
            {
                $_GET['certification'] = array('equipment', 'surfacing');
            }
            $criteria['tax_query'][] = array(
                'taxonomy' => 'product-type',
                'field' => 'slug',
                'terms' => $_GET['certification'],
                'operator' => 'AND'
            );
        }

    }

    $checkExpiredIns = false;
    $checkSoon = false;
    if ($_GET['insurance'])
    {
        $now = strtotime('midnight');
        if ($_GET['insurance'] == 'current')
        {
            $criteria['tax_query'][] = array(
                'key' => 'insurance-exp',
                'value' => $now,
                'compare' => '>=',
                'type' => 'NUMERIC'
            );
        }
        elseif ($_GET['insurance'] == 'expired')
        {
            $checkExpiredIns = true;
        }
        elseif ($_GET['insurance'] == 'soon')
        {
            $checkSoon = true;
            $cutoff = strtotime('-1 month', $now);
        }
    }

    $companies = get_posts($criteria);
    foreach ($companies as $company)
    {
        $expiration = '';
        if ($checkExpiredIns)
        {
            $expiration = get_post_meta(
                $company->ID,
                'insurance-exp',
                true
            );

            if (is_numeric($expiration) && $now <= $expiration)
            {
               continue;
            }
        }
        if ($checkSoon)
        {
            $expiration = get_post_meta(
                $company->ID,
                'insurance-exp',
                true
            );
            if ($expiration < $now || $cutoff <= $expiration)
            {
                continue;
            }
        }
        if ($checkExpiredCert)
        {
            $skip = true;
            if (is_numeric($company->equipment) && $company->equipment < $year)
            {
                $skip = false;
            }
            if (is_numeric($company->surfacing) && $company->surfacing < $year)
            {
                $skip = false;
            }

            if ($skip)
            {
                continue;
            }
        }

        $accountType = get_the_terms($company, 'account-type');
        $accountType = $accountType[0];

        $dues = '';
        if ($accountType->slug == 'associate')
        {
            $dues = '$500';
        }

        $active = ipema_active_column($company->active, $year);

        $equipment = '';
        $surfacing = '';
        if ($accountType->slug == 'manufacturer')
        {
            $equipment = ipema_active_column($company->equipment, $year);
            $surfacing = ipema_active_column($company->surfacing, $year);
            if ($expiration == '')
            {
                $expiration = get_post_meta(
                    $company->ID,
                    'insurance-exp',
                    true
                );
            }

            if (is_numeric($expiration))
            {
                $expiration = date('m/d/Y', $expiration);
            }

            $sales = get_post_meta($company->ID, 'sales', true);
            if ($sales)
            {
                if ($sales >= 3000000)
                {
                    $dues = '$1,150';
                }
                else
                {
                    $dues = '$1,150'; //dues are now equal
                }
            }
            else
            {
                $dues = 'Please enter gross sales';
            }
        }

        $paymentForms = array(23, 1, 34);
        if ($accountType->slug == 'personal')
        {
            $paymentForms = array(25, 2);
            $dues = '$200';
        }
        $lastPayments = ipema_get_latest_payment(
            $company->ID,
            $paymentForms,
            $accountType->slug != 'manufacturer'
        );
        if ($lastPayments['member_date'])
        {
            $lastPayments['member_date'] = date(
                'm/d/Y',
                strtotime($lastPayments['member_date'])
            );
        }
        if ($lastPayments['cert_date'])
        {
            $lastPayments['cert_date'] = date(
                'm/d/Y',
                strtotime($lastPayments['cert_date'])
            );
        }

        if ($lastPayments['member_amount'])
        {
            $lastPayments['member_amount'] = '$' . number_format(
                $lastPayments['member_amount'],
                2
            );
        }
        if ($lastPayments['cert_amount'])
        {
            $lastPayments['cert_amount'] = '$' . number_format(
                $lastPayments['cert_amount'],
                2
            );
        }

        $salesContact = '';
        if ($company->quarterly_sales)
        {
            $sales = get_user_by('id', $company->quarterly_sales);
            $salesContact = "{$sales->first_name} {$sales->last_name} <"
                . "{$sales->user_email}>";
        }

        if ($company->active < $year)
        {
            $dues = '';
        }

        $row = array(
            html_entity_decode($company->post_title),
            $accountType->name,
            $dues,
            date('m/d/Y', strtotime($company->post_date)),
            $company->address,
            $company->address_2,
            $company->city,
            $company->state,
            $company->zip,
            $company->country,
            $company->phone,
            $company->alt_phone,
            $company->toll_free_number,
            $company->fax,
            $company->url,
            ipema_company_notes($company->ID, 'ipema'),
            $active,
            $equipment,
            $surfacing,
            $expiration,
            $lastPayments['member_amount'],
            $lastPayments['member_method'],
            $lastPayments['member_date'],
            $lastPayments['cert_amount'],
            $lastPayments['cert_method'],
            $lastPayments['cert_date'],
            $company->ein,
            $salesContact
        );

        $first = true;
        $padding = array_fill(0, count($row), '');
        $users = get_users(array(
            'meta_key' => 'company_id',
            'meta_value' => $company->ID
        ));
        foreach ($users as $user)
        {
            $role = 'Basic';

            if ($user->has_cap('can_manage_account'))
            {
                $role = 'Account Admin';
                if ($user->has_cap('can_manage_products'))
                {
                    $role = 'Full Admin';
                }
            }
            elseif ($user->has_cap('can_manage_products'))
            {
                $role = 'Product Admin';
            }

            $contact_method = get_user_meta($user->ID, 'contact-method', true);
            if ($contact_method == 'user_email')
            {
                $contact_method = 'Email';
            }

            $leadership = get_user_meta($user->ID, 'leadership-interest', true);
            $mainContact = get_user_meta($user->ID, 'main_contact', true);

            $info = array(
                $user->first_name,
                $user->last_name,
                $user->user_login,
                $role,
                $mainContact ? 'Yes' : 'No',
                $user->phone,
                $user->user_email,
                ucfirst($contact_method),
                $leadership ? 'Yes' : 'No'
            );

            /*if ($first)
            {
                fputcsv($output, array_merge($row, $info));
                $first = false;
            }
            else
            {
                fputcsv($output, array_merge($padding, $info));
            }*/
            fputcsv($output, array_merge($row, $info));
        }
    }
    die();
}
add_action('template_redirect', 'ipema_download_report');

function ipema_create_webinar($data, $form, $entry)
{
    $publish = date('Y-m-d H:i:s', strtotime("$entry[1] $entry[2]"));
    $data['post_type'] = 'webinar';
    $data['post_category'] = array();
    $data['post_date'] = $publish;

    add_filter('wp_insert_post_data', function($data, $postarr=NULL) {
        $data['post_status'] = 'future';

        return $data;
    });

    return $data;
}
add_filter('gform_post_data_30', 'ipema_create_webinar', 10, 3);

function ipema_webinar_signup()
{
    if ($_SERVER['REQUEST_METHOD'] != 'POST' || ! is_singular('webinar'))
    {
        return;
    }

    $post = get_post();
    $user = wp_get_current_user();

    add_post_meta($post->ID, 'attendee', $user->ID);
}
add_action('template_redirect', 'ipema_webinar_signup');

function ipema_registered_for_webinar()
{
    $post = get_post();
    $user = wp_get_current_user();
    return in_array($user->ID, get_post_meta($post->ID, 'attendee'));
}

function ipema_populate_webinar($form)
{
    $webinar = get_post($_GET['id']);
    $date = strtotime($webinar->post_date);

    foreach ($form['fields'] as &$field)
    {
        if ($field->id == 1)
        {
            $field->defaultValue = date('m/d/Y', $date);
        }
        elseif ($field->id == 2)
        {
            foreach ($field->inputs as &$input)
            {
                if ($input['label'] == 'HH')
                {
                    $input['defaultValue'] = date('G', $date);
                }
                elseif ($input['label'] == 'MM')
                {
                    $input['defaultValue'] = date('i', $date);
                }
                else
                {
                    $input['defaultValue'] = date('A', $date);
                }
            }
        }
        elseif ($field->id == 3)
        {
            $field->defaultValue = $webinar->post_title;
        }
        elseif ($field->id == 4)
        {
            $field->defaultValue = $webinar->post_content;
        }
        elseif ($field->id == 5)
        {
            $field->defaultValue = $webinar->post_date;
        }
    }

    return $form;
}
add_filter('gform_pre_render_31', 'ipema_populate_webinar');
add_filter('gform_pre_validation_31', 'ipema_populate_webinar');
add_filter('gform_pre_submission_filter_31', 'ipema_populate_webinar');

function ipema_webinar_update_validation($result, $value, $form, $field)
{
    if ( ! $result['is_valid'])
    {
        return $result;
    }
    if (strtotime($value) < strtotime('tomorrow'))
    {
        return array(
            'is_valid' => false,
            'message' => 'Date must be at least one day in the future'
        );
    }

    return $result;
}
add_filter('gform_field_validation_31_1', 'ipema_webinar_update_validation', 10, 4);

function ipema_update_webinar($entry, $form)
{
    $date = date('Y-m-d H:i:s', strtotime("$entry[1] $entry[2]"));
    wp_update_post(array(
        'ID' => $_GET['id'],
        'post_title' => rgar($entry, 3),
        'post_content' => rgar($entry, 4),
        'post_date' => $date
    ));

    if ($date != rgar($entry, 5))
    {
        $webinar = get_post($_GET['id']);

        $oldTimestamp = strtotime(rgar($entry, 5));
        $oldDate = date('l, F jS', $oldTimestamp);
        $oldTime = date('g:i A', $oldTimestamp);

        $newTimestamp = strtotime($date);
        $newDate = date('l, F jS', $newTimestamp);
        $newTime = date('g:i A', $newTimestamp);

        if ($oldDate == $newDate)
        {
            $change = "$oldTime to $newTime Eastern on $newDate";
        }
        else
        {
            $change = "$oldDate at $oldTime to $newDate at $newTime Eastern";
        }

        $attendees = get_post_meta($_GET['id'], 'attendee');
        foreach ($attendees as $attendee)
        {
            $user = get_user_by('id', $attendee);
            wp_mail(
                "\"{$user->display_name}\" <{$user->user_email}>",
                'Webinar Schedule Change',
                <<<EOT
Hello {$user->display_name},

This message is to inform you that the webinar you had signed up for titled
"{$webinar->post_title}" has been rescheduled from $change.
We apologize for any inconvenience this may cause.

Sincerely,
The IPEMA Team
EOT
            );
        }
    }
}
add_action('gform_after_submission_31', 'ipema_update_webinar', 10, 2);

add_action('webinar-email', function() {
    $webinars = get_posts(array(
        'nopaging' => true,
        'post_type' => 'webinar',
        'post_status' => 'future',
        'date_query' => array(
            'after' => 'now',
            'before' => 'tomorrow'
        )
    ));

    foreach ($webinars as $webinar)
    {
        $time = date('g:i A', strtotime($webinar->post_date));
        $webinarURL = site_url("/members/join-webinar/?id={$webinar->ID}");
        $attendees = get_post_meta($webinar->ID, 'attendee');
        foreach ($attendees as $attendee)
        {
            $user = get_user_by('id', $attendee);
            wp_mail(
                "\"{$user->display_name}\" <{$user->user_email}>",
                'IPEMA Webinar Today',
                <<<EOT
Hello {$user->display_name},

We would like to remind you that the webinar you signed up for, titled
"{$webinar->post_title}" will be starting today at $time Eastern.
You can visit the IPEMA website or follow the link below to join the webinar when
it starts.

$webinarURL

Sincerely,
The IPEMA Team
EOT
            );
        }
    }
});

function ipema_filter_webinars($args, $settings, $id)
{
    if ($id == 862)
    {
        $args['date_query'] = array(
            'after' => '+15 minutes'
        );
    }
    elseif ($id == 866)
    {
        $args['date_query'] = array(
            'after' => '30 minutes ago',
            'before' => '+15 minutes'
        );
    }

    return $args;
}
add_filter('wpv_filter_query', 'ipema_filter_webinars', 10, 3);

function ipema_ongoing_webinar()
{
    if ( ! is_page('join-webinar'))
    {
        return;
    }

    $webinars = get_posts(array(
        'post_type' => 'webinar',
        'p' => $_GET['id'],
        'post_status' => array('publish', 'future'),
        'date_query' => array(
            'after' => '15 minutes ago',
            'before' => '+30 minutes'
        ),
        'posts_per_page' => 1
    ));

    if (count($webinars) == 0)
    {
        return;
    }

    $user = wp_get_current_user();

    if ( ! in_array($user->ID, (array)get_post_meta($_GET['id'], 'attendee')))
    {
        add_post_meta($_GET['id'], 'attendee', $user->ID);
    }

    $post = get_post();
    wp_redirect(get_post_meta($post->ID, 'webinar-url', true));
    die();
}
add_action('template_redirect', 'ipema_ongoing_webinar');

function ipema_expiring_within($offset)
{
    if (ipema_next_renew_date() > strtotime("+$offset"))
    {
        return false;
    }
    $user = wp_get_current_user();

    $active = get_post_meta($user->company_id, 'active', true);
    $equipment = get_post_meta($user->company_id, 'equipment', true);
    $surfacing = get_post_meta($user->company_id, 'surfacing', true);

    if ($equipment > $active)
    {
        $active = $equipment;
    }
    if ($surfacing > $active)
    {
        $active = $surfacing;
    }

    return $active == ipema_current_year();
}

// We can't use strtotime because Views passes 'posts' to optional args
function ipema_strtotime($time)
{
    return strtotime($time);
}

function ipema_product_table($title, $rows)
{
    return <<<EOT
<h3>$title:</h3>
<table>
    <thead>
        <tr>
            <th>Expires</th>
            <th>Product</th>
            <th>Certification</th>
        </tr>
    </thead>
    <tbody>
        $rows
    </tbody>
</table>
EOT;
}

function ipema_expiring_alert($force=false)
{
    if ( ! $force && date('F') != 'Monday')
    {
        return;
    }

    $certs = array(
        'equipment' => array(),
        'surfacing' => array()
    );
    $certifications = get_terms(array(
        'taxonomy' => 'certification'
    ));
    foreach ($certifications as $certification)
    {
        if (ipema_certification_product_type($certification->term_id, 'equipment'))
        {
            $certs['equipment'][] = $certification;
        }
        elseif (ipema_certification_product_type($certification->term_id, 'surfacing'))
        {
            $certs['surfacing'][] = $certification;
        }
    }

    $year = ipema_current_year();
    $windowTS = strtotime('+4 months');
    $insWindowTS = strtotime('+1 month');
    $window = date('Y-m-d', $windowTS);
    $twoWeeks = strtotime('+2 weeks');
    $oneWeek = strtotime('+1 week');
    $nowTS = time();
    $now = date('Y-m-d');
    $site = site_url();

    add_filter('wp_mail_content_type', function() { return 'text/html'; });

    $offset = 0;
    if (array_key_exists('offset', $_GET))
    {
        $offset = $_GET['offset'];
    }

    $manufacturers = get_posts(array(
        'post_type' => 'company',
        'tax_query' => array(array(
            'taxonomy' => 'account-type',
            'field' => 'slug',
            'terms' => 'manufacturer'
        )),
        'offset' => $offset,
        'posts_per_page' => 1
    ));

    if (count($manufacturers) == 0)
    {
        die();
    }

    foreach ($manufacturers as $manufacturer)
    {
        $warnings = array();
        $isActive = false;
        foreach (array('equipment', 'surfacing') as $type)
        {
            $expires = get_post_meta($manufacturer->ID, $type, true);
            if ($expires < $year)
            {
                continue;
            }

            $isActive = true;

            foreach ($certs[$type] as $cert)
            {
                $expiring = get_posts(array(
                    'post_type' => 'product',
                    'nopaging' => true,
                    'meta_query' => array(
                        array(
                            'key' => '_wpcf_belongs_company_id',
                            'value' => $manufacturer->ID
                        ),
                        array(
                            'key' => $cert->slug,
                            'value' => array($now, $window),
                            'type' => 'DATE',
                            'compare' => 'BETWEEN'
                        )
                    ),
                    'tax_query' => array(array(
                        'taxonomy' => 'certification',
                        'terms' => $cert->term_id
                    ))
                ));

                foreach ($expiring as $product)
                {
                    $rvs = get_posts(array(
                        'post_type' => 'rv',
                        'post_status' => 'draft',
                        'meta_query' => array(array(
                            'key' => 'affected_id',
                            'value' => $product->ID
                        )),
                        'tax_query' => array(array(
                            'taxonomy' => 'certification',
                            'field' => 'slug',
                            'terms' => $cert->slug
                        ))
                    ));

                    if (count($rvs) == 0)
                    {
                        $expiration = get_post_meta(
                            $product->ID,
                            $cert->slug,
                            true
                        );
                        $warnings[] = array(
                            'expires' => strtotime($expiration),
                            'product' => $product,
                            'cert' => $cert
                        );
                    }
                }
            }
        }

        if ($isActive)
        {
            $pendingInsurance = get_post_meta(
                $manufacturer->ID,
                'pending_insurance',
                true
            );
            $insuranceExp = get_post_meta(
                $manufacturer->ID,
                'insurance-exp',
                true
            );

            $expDate = '';
            if ( ! $pendingInsurance)
            {
                if ($nowTS < $insuranceExp && $insuranceExp < $insWindowTS)
                {
                    $expDate = date('M jS, Y', $insuranceExp);
                }
            }

            if ($expDate)
            {
                $msg = '<p>This email is to alert you that the insurance '
                    . 'certificates you provided for IPEMA and TUV will '
                    . "expire on $expDate</p>"
                    . '<p>Certificates of liability insurance listing IPEMA '
                    . 'and TUV as additional insured on your general liability '
                    . 'policy must be uploaded prior to the expiration date to '
                    . 'ensure your certified product(s) remain listed on the '
                    . 'IPEMA website. If updated certificates are not provided,'
                    . ' your products will be removed from the website.</p>'
                    . 'Please note: When new insurance documents are uploaded, '
                    . 'IPEMA staff will be notified and they must confirm the '
                    . 'certificates meet program requirements. Upon approval '
                    . 'from staff, it may take up to 24 hours for the insurance'
                    . ' update to take effect on the website.</p>'
                    . 'Please log into your account to upload new certificates '
                    . 'of liability insurance.</p>'
                    . '<p>Sincerely,<br>The IPEMA Team</p>';

                $users = get_users(array(
                    'meta_key' => 'company_id',
                    'meta_value' => $manufacturer->ID
                ));

                foreach ($users as $user)
                {
                    if ( ! $user->has_cap('can_manage_account'))
                    {
                        continue;
                    }
                    if ($user->has_cap('manage_ipema'))
                    {
                        continue;
                    }

                    wp_mail(
                        "{$user->display_name} <{$user->user_email}>",
                        'IPEMA Insurance Expiration Alert',
                        "<p>Hi {$user->display_name},</p>$msg"
                    );
                }
            }
        }

        if (count($warnings) == 0)
        {
            continue;
        }

        usort($warnings, function($a, $b) {
            if ($a['expires'] == $b['expires'])
            {
                return 0;
            }
            return ($a['expires'] < $b['expires']) ? -1 : 1;
        });

        $msg = <<<EOT
<p>The following products will expire on May 31st if you do not
submit updated requests for validation.</p>
EOT;

        $rows = '';
        foreach ($warnings as $key => $warning)
        {
            if ($warning['expires'] > $oneWeek)
            {
                break;
            }

            $day = date('l', $warning['expires']);
            $fullName = ipema_product_display_name($warning['product']->ID);

            $rows .= <<<EOT
        <tr>
            <td>$day</td>
            <td><a href="$site/members/products/manage/?model={$warning['product']->ID}">$fullName</a></td>
            <td>{$warning['cert']->name}</td>
        </tr>
EOT;

            unset($warnings[$key]);
        }

        if (strlen($rows) > 0)
        {
            $msg .= ipema_product_table('Expiring This Week', $rows);
        }

        $rows = '';
        foreach ($warnings as $key => $warning)
        {
            if ($warning['expires'] > $twoWeeks)
            {
                break;
            }

            $day = date('l', $warning['expires']);
            $fullName = ipema_product_display_name($warning['product']->ID);

            $rows .= <<<EOT
        <tr>
            <td>Next $day</td>
            <td><a href="$site/members/products/manage/?model={$warning['product']->ID}">$fullName</a></td>
            <td>{$warning['cert']->name}</td>
        </tr>
EOT;

            unset($warnings[$key]);
        }

        if (strlen($rows) > 0)
        {
            $msg .= ipema_product_table('Expiring Next Week', $rows);
        }

        $rows = '';
        foreach ($warnings as $warning)
        {

            $date = date('F j', $warning['expires']);
            $date .= '<sup>';
            $date .= date('S', $warning['expires']);
            $date .= '</sup>';
            $fullName = ipema_product_display_name($warning['product']->ID);

            $rows .= <<<EOT
        <tr>
            <td>$date</td>
            <td><a href="$site/members/products/manage/?model={$warning['product']->ID}">$fullName</a></td>
            <td>{$warning['cert']->name}</td>
        </tr>
EOT;
        }

        if (strlen($rows) > 0)
        {
            $msg .= ipema_product_table('Expiring Soon', $rows);
        }

        $msg .= '<p>If all current certifications for a product have expired, ';
        $msg .= 'it will no longer be listed on the IPEMA website.</p>';
        $msg .= '<p>Sincerely,<br>The IPEMA Team</p>';

        $users = get_users(array(
            'meta_key' => 'company_id',
            'meta_value' => $manufacturer->ID
        ));

        foreach ($users as $user)
        {
            if ( ! $user->has_cap('can_manage_products'))
            {
                continue;
            }
            if ($user->has_cap('can_validate_products')
                || $user->has_cap('manage_ipema'))
            {
                continue;
            }

            wp_mail(
                "{$user->display_name} <{$user->user_email}>",
                'IPEMA Product Expiration Alert',
                "<p>Hi {$user->display_name},</p>$msg"
            );
        }
    }

    wp_redirect('/about-ipema/?cron=expire&offset=' . ($offset + 1));
    die();
}
add_action('expiring-alerts', 'ipema_expiring_alert');

function ipema_relevanssi_post_status($args)
{
    if (is_array($args['post_status']) && in_array('any', $args['post_status']))
    {
        unset($args['post_status']);
    }

    return $args;
}
add_filter('relevanssi_search_filters', 'ipema_relevanssi_post_status');

function ipema_populate_agreements($form)
{
    $companyID = $_GET['member'];
    $entryID = get_post_meta(
        $companyID,
        'pending_equipment_agreement',
        true
    );
    if ($entryID)
    {
        $entry = GFFormsModel::get_lead($entryID);
        if ($entry['form_id'] == 1)
        {
            $equipment_url = $entry['73'];
            $email = $entry[13];
        }
        elseif ($entry['form_id'] == 23)
        {
            $equipment_url = $entry['30'];
            $user = get_user_by('ID', $entry['created_by']);
            $email = $user->user_email;
        }
        elseif ($entry['form_id'] == 33)
        {
            $equipment_url = $entry['1'];
            $user = get_user_by('ID', $entry['created_by']);
            $email = $user->user_email;
        }
        elseif ($entry['form_id'] == 34)
        {
            $equipment_url = $entry['7'];
            $user = get_user_by('ID', $entry['created_by']);
            $email = $user->user_email;
        }
    }

    $entryID = get_post_meta(
        $companyID,
        'pending_surfacing_agreement',
        true
    );
    if ($entryID)
    {
        $entry = GFFormsModel::get_lead($entryID);
        if ($entry['form_id'] == 1)
        {
            $surfacing_url = $entry['74'];
            $email = $entry[13];
        }
        elseif ($entry['form_id'] == 23)
        {
            $surfacing_url = $entry['31'];
            $user = get_user_by('ID', $entry['created_by']);
            $email = $user->user_email;
        }
        elseif ($entry['form_id'] == 33)
        {
            $surfacing_url = $entry['2'];
            $user = get_user_by('ID', $entry['created_by']);
            $email = $user->user_email;
        }
        elseif ($entry['form_id'] == 34)
        {
            $surfacing_url = $entry['8'];
            $user = get_user_by('ID', $entry['created_by']);
            $email = $user->user_email;
        }
    }

    foreach ($form['fields'] as &$field)
    {
        if ($field->id == 1)
        {
            if ($equipment_url)
            {
                $field->content = '<p><a href="' . $equipment_url . '" target="_blank">'
                    . 'View Uploaded Equipment Certification Document</a></p>';
            }
            else
            {
                $field->visibility = 'hidden';
            }
        }
        elseif ($field->id == 2)
        {
            if ( ! $equipment_url)
            {
                $field->adminOnly = true;
            }
        }
        elseif ($field->id == 3)
        {
            if ($surfacing_url)
            {
                $field->content = '<p><a href="' . $surfacing_url . '" target="_blank">'
                    . 'View Uploaded Surfacing Certification Document</a></p>';
            }
            else
            {
                $field->visibility = 'hidden';
            }
        }
        elseif ($field->id == 4)
        {
            if ( ! $surfacing_url)
            {
                $field->adminOnly = true;
            }
        }
        elseif ($field->id == 6)
        {
            $field->defaultValue = $email;
        }
        elseif ($field->id == 7)
        {
            if ( ! ($equipment_url && $surfacing_url))
            {
                $field->visibility = 'hidden';
            }
        }
    }

    return $form;
}
add_filter('gform_pre_render_32', 'ipema_populate_agreements');
add_filter('gform_pre_validation_32', 'ipema_populate_agreements');
add_filter('gform_pre_submission_filter_32', 'ipema_populate_agreements');

function ipema_process_renewal($entry, $type, $companyID)
{
    if ($entry['form_id'] == 33)
    {
        if ($type == 'equipment')
        {
            $entry = GFFormsModel::get_lead($entry['3']);
        }
        elseif ($type == 'surfacing')
        {
            $entry = GFFormsModel::get_lead($entry['4']);
        }
    }

    if ($entry['form_id'] != 23)
    {
        // Not a renewal
        if ($entry['form_id'] == 1)
        {
            $companyID = $entry['post_id'];
        }
        if ( ! get_post_meta($companyID, 'pending_insurance', true))
        {
            ipema_new_manufacturer_email($companyID, $type);
        }
        return;
    }

    $payment_needed = get_post_meta(
        $companyID,
        'pending_renewal',
        true
    );

    if ($payment_needed)
    {
        return;
    }

    $productYear = get_post_meta($companyID, $type, true);
    $submitYear = substr($entry['date_created'], 0, 4);
    if (is_numeric($productYear))
    {
        if ($productYear < $submitYear)
        {
            update_post_meta($companyID, $type, $submitYear);
        }
        else
        {
            update_post_meta($companyID, $type, $productYear + 1);
        }
    }
}

function ipema_update_agreements($entry, $form)
{
    $company_id = $_GET['member'];

    $equipmentEntry = get_post_meta(
        $company_id,
        'pending_equipment_agreement',
        true
    );

    if ($equipmentEntry)
    {
        delete_post_meta($company_id, 'pending_equipment_agreement');
        $equipmentEntry = GFFormsModel::get_lead($equipmentEntry);

        if (rgar($entry, 2) == 'Accept')
        {
            if ($equipmentEntry['form_id'] == 1)
            {
                $url = $equipmentEntry['73'];
            }
            elseif ($equipmentEntry['form_id'] == 23)
            {
                $url = $equipmentEntry['30'];
            }
            elseif ($equipmentEntry['form_id'] == 33)
            {
                $url = $equipmentEntry['1'];
            }
            elseif ($equipmentEntry['form_id'] == 34)
            {
                $url = $equipmentEntry['7'];
            }

            update_post_meta(
                $company_id,
                'equipment-certification-agreement',
                $url
            );
            update_post_meta(
                $company_id,
                'equip-agreement-date',
                time()
            );

            ipema_process_renewal($equipmentEntry, 'equipment', $company_id);
        }
        else
        {
            if ($equipmentEntry['form_id'] == 33)
            {
                add_post_meta(
                    $company_id,
                    'rejected_equipment_agreement',
                    $equipmentEntry['3']
                );
            }
            else
            {
                add_post_meta(
                    $company_id,
                    'rejected_equipment_agreement',
                    $equipmentEntry['id']
                );
            }
        }
    }

    $surfacingEntry = get_post_meta(
        $company_id,
        'pending_surfacing_agreement',
        true
    );

    if ($surfacingEntry)
    {
        delete_post_meta($company_id, 'pending_surfacing_agreement');
        $surfacingEntry = GFFormsModel::get_lead($surfacingEntry);

        if (rgar($entry, 4) == 'Accept')
        {
            if ($surfacingEntry['form_id'] == 1)
            {
                $url = $surfacingEntry['74'];
            }
            elseif ($surfacingEntry['form_id'] == 23)
            {
                $url = $surfacingEntry['31'];
            }
            elseif ($surfacingEntry['form_id'] == 33)
            {
                $url = $surfacingEntry['1'];
            }
            elseif ($surfacingEntry['form_id'] == 34)
            {
                $url = $surfacingEntry['8'];
            }

            update_post_meta(
                $company_id,
                'surface-certification-agreement',
                $url
            );
            update_post_meta(
                $company_id,
                'surfacing-agreement-date',
                time()
            );

            ipema_process_renewal($surfacingEntry, 'surfacing', $company_id);
        }
        else
        {
            if ($surfacingEntry['form_id'] == 33)
            {
                add_post_meta(
                    $company_id,
                    'rejected_surfacing_agreement',
                    $surfacingEntry['4']
                );
            }
            else
            {
                add_post_meta(
                    $company_id,
                    'rejected_surfacing_agreement',
                    $surfacingEntry['id']
                );
            }
        }
    }
}
add_action('gform_after_submission_32', 'ipema_update_agreements', 10, 2);

function ipema_retry_agreements($form)
{
    $user = wp_get_current_user();

    $rejected_equipment = get_post_meta(
        $user->company_id,
        'rejected_equipment_agreement',
        true
    );
    $rejected_surfacing = get_post_meta(
        $user->company_id,
        'rejected_surfacing_agreement',
        true
    );

    foreach ($form['fields'] as &$field)
    {
        if ($field->id == 1)
        {
            if ( ! $rejected_equipment)
            {
                $field->adminOnly = true;
            }
        }
        elseif ($field->id == 2)
        {
            if ( ! $rejected_surfacing)
            {
                $field->adminOnly = true;
            }
        }
        elseif ($field->id == 3)
        {
            $field->defaultValue = $rejected_equipment;
        }
        elseif ($field->id == 4)
        {
            $field->defaultValue = $rejected_surfacing;
        }
    }

    return $form;
}
add_filter('gform_pre_render_33', 'ipema_retry_agreements');
add_filter('gform_pre_validation_33', 'ipema_retry_agreements');
add_filter('gform_pre_submission_filter_33', 'ipema_retry_agreements');

function ipema_reload_agreements($entry, $form)
{
    $user = wp_get_current_user();

    if (rgar($entry, 1))
    {
        update_post_meta(
            $user->company_id,
            'pending_equipment_agreement',
            $entry['id']
        );
    }
    if (rgar($entry, 2))
    {
        update_post_meta(
            $user->company_id,
            'pending_surfacing_agreement',
            $entry['id']
        );
    }

    delete_post_meta($user->company_id, 'rejected_equipment_agreement');
    delete_post_meta($user->company_id, 'rejected_surfacing_agreement');
}
add_action('gform_after_submission_33', 'ipema_reload_agreements', 10, 2);

function ipema_populate_certify($form)
{
    $user = wp_get_current_user();

    $type = '';
    $types = get_the_terms($user->company_id, 'product-type');
    if (count($types) == 1)
    {
        $type = $types[0]->slug;
    }

    $prices = ipema_calculate_prices(
        0,
        rgar($_POST, 'input_4', 0),
        rgar($_POST, 'input_5', 0)
    );

    $_POST['input_40'] = 0;
    $_POST['input_41'] = 0;
    if (ipema_user_company_active('active'))
    {
        if ($_POST['input_2_1'])
        {
            $_POST['input_40'] = $prices['equipmentCombined'];
        }
        if ($_POST['input_2_2'])
        {
            $_POST['input_41'] = $prices['surfacingCombined'];
        }
    }
    else
    {
        if ($_POST['input_2_1'])
        {
            $_POST['input_40'] = $prices['equipmentOnly'];
        }
        if ($_POST['input_2_2'])
        {
            $_POST['input_41'] = $prices['surfacingOnly'];
        }
    }

    foreach ($form['fields'] as &$field)
    {
        if (in_array($field->id, array(1, 2, 6, 9)))
        {
            if ($type)
            {
                $field->visibility = 'hidden';
            }
        }
        elseif (in_array($field->id, array(10, 11, 12, 13)))
        {
            if ($type)
            {
                $field->adminOnly = true;
            }
        }

        if ($field->id == 2)
        {
            if ($type)
            {
                foreach ($field->choices as &$choice)
                {
                    if ($choice['value'] != $type)
                    {
                        $choice['isSelected'] = true;
                        $field->defaultValue = $choice['value'];
                    }
                }
            }
        }
        elseif ($field->id == 32)
        {
            if (ipema_user_company_active('active'))
            {
                $amount = $prices['combined'] - $prices['memberOnly'];
                $field->basePrice = '$' . number_format($amount, 2);
            }
            else
            {
                $field->basePrice = '$' . number_format($prices['certOnly'], 2);
            }

            $_POST['input_32_2'] = $field->basePrice;
        }
        elseif ($field->id == 36)
        {
            $address = array(
                '36.1' => 'address',
                '36.2' => 'address_2',
                '36.3' => 'city',
                '36.4' => 'state',
                '36.5' => 'zip',
                '36.6' => 'country'
            );
            foreach ($field->inputs as &$input)
            {
                $input['defaultValue'] = get_post_meta(
                    $user->company_id,
                    $address[$input['id']],
                    true
                );
            }
        }
    }

    return $form;
}
add_filter('gform_pre_render_34', 'ipema_populate_certify');
add_filter('gform_pre_validation_34', 'ipema_populate_certify');
add_filter('gform_pre_submission_filter_34', 'ipema_populate_certify');

function ipema_certify_insurance_notice($disabled, $notification, $form, $entry)
{
    if ($notification['id'] != '5931a8c2b83b2')
    {
        return $disabled;
    }

    if ( ! rgar($entry, 13))
    {
        return true;
    }

    return $disabled;
}
add_filter('gform_disable_notification_34', 'ipema_certify_insurance_notice', 10, 4);

function ipema_start_certify($entry, $form)
{
    $user = wp_get_current_user();

    if (rgar($entry, 7))
    {
        update_post_meta(
            $user->company_id,
            'pending_equipment_agreement',
            $entry['id']
        );
        wp_set_object_terms(
            $user->company_id,
            'equipment',
            'product-type',
            true
        );
        update_post_meta(
            $user->company_id,
            'equipment_start',
            time()
        );

    }
    if (rgar($entry, 8))
    {
        update_post_meta(
            $user->company_id,
            'pending_surfacing_agreement',
            $entry['id']
        );
        wp_set_object_terms(
            $user->company_id,
            'surfacing',
            'product-type',
            true
        );
        update_post_meta(
            $user->company_id,
            'surfacing_start',
            time()
        );
    }

    if (rgar($entry, 10))
    {
        update_post_meta(
            $user->company_id,
            'pending_insurance',
            $entry['id']
        );
    }

    if (rgar($entry, 34) == 'Check')
    {
        update_post_meta(
            $user->company_id,
            'pending_renewal',
            $entry['id']
        );
    }

    $entry['post_id'] = $user->company_id;

    GFAPI::update_entry($entry);
}
add_action('gform_after_submission_34', 'ipema_start_certify', 10, 2);

add_action('template_redirect', function() {
    if (current_user_can('manage_options') && $_GET['do'] == 'import_all')
    {
        require_once('import/import.php');
        die('<p><strong>Import Completed</strong></p>');
    }
    if (current_user_can('manage_options') && $_GET['do'] == 'fix_import')
    {
        require_once('import/fix.php');
        die('<p><strong>Fix Completed</strong></p>');
    }
    if ($_GET['do'] == 'fix_bases')
    {
        require_once('import/fix-bases.php');
        die('<p><strong>Fix Complete</strong></p>');
    }
    if ($_GET['do'] == 'fix_rvs')
    {
        require_once('import/fix-rvs.php');
        die('<p><strong>Fix Complete</strong></p>');
    }
    if ($_GET['do'] == 'fix_accounts')
    {
        require_once('import/fix-accounts.php');
        die('<p><strong>Fix Complete</strong></p>');
    }
});

function ipema_handle_revoke($entry, $form)
{
    $user = wp_get_current_user();
    if ( ! $user->has_cap('can_validate_products'))
    {
        return;
    }

    $companyID = rgar($entry, 1);
    $productType = rgar($entry, 2);
    $reason = rgar($entry, 3);

    if (get_post_meta($companyID, "{$productType}_revoked", true))
    {
        return;
    }

    $valid = false;
    $types = get_the_terms($companyID, 'product-type');
    foreach ($types as $type)
    {
        if ($type->slug == $productType)
        {
            $valid = true;
            break;
        }
    }
    if ( ! $valid)
    {
        return;
    }

    update_post_meta($companyID, "{$productType}_revoked", $entry['id']);

    $names = array(
        'equipment' => 'Play Equipment',
        'surfacing' => 'Surfacing Materials'
    );
    $type = strtolower($names[$productType]);

    $msg = <<<EOL
This message is to inform you that your $type certification has been revoked for the following reason:

$reason

Your $type will be removed from the IPEMA website until you have corrected this. If you have any questions, please feel free to contact us.

The IPEMA Team
EOL;

    $users = get_users(array(
        'meta_key' => 'company_id',
        'meta_value' => $companyID
    ));

    foreach ($users as $user)
    {
        if ( ! $user->has_cap('can_manage_products'))
        {
            continue;
        }
        if ($user->has_cap('can_validate_products')
            || $user->has_cap('manage_ipema'))
        {
            continue;
        }

        wp_mail(
            "{$user->display_name} <{$user->user_email}>",
            "IPEMA {$names[$productType]} Certification Revoked",
            "Hi {$user->display_name},\n\n$msg"
        );
    }
}
add_action('gform_after_submission_35', 'ipema_handle_revoke', 10, 2);

function ipema_handle_restore()
{
    if ( ! is_page('restore') || ! array_key_exists('member', $_GET))
    {
        return;
    }

    if ( ! is_numeric($_GET['member']))
    {
        return;
    }

    $names = array(
        'equipment' => 'Play Equipment',
        'surfacing' => 'Surfacing Materials'
    );

    if (array_key_exists($_GET['type'], $names) &&
        current_user_can('can_validate_products')
    )
    {
        delete_post_meta($_GET['member'], "{$_GET['type']}_revoked");
        $type = strtolower($names[$_GET['type']]);

        $msg = <<<EOL
Your $type certification is no longer revoked. Your products will return to the IPEMA website shortly.

The IPEMA Team
EOL;

        $users = get_users(array(
            'meta_key' => 'company_id',
            'meta_value' => $_GET['member']
        ));

        foreach ($users as $user)
        {
            if ( ! $user->has_cap('can_manage_products'))
            {
                continue;
            }
            if ($user->has_cap('can_validate_products')
                || $user->has_cap('manage_ipema'))
            {
                continue;
            }

            wp_mail(
                "{$user->display_name} <{$user->user_email}>",
                "IPEMA {$names[$_GET['type']]} Certification Restored",
                "Hi {$user->display_name},\n\n$msg"
            );
        }
    }

    wp_redirect('/rvs/manufacturers/details/?member=' . $_GET['member']);
    die();
}
add_action('template_redirect', 'ipema_handle_restore');

function ipema_populate_reviewer_notices($form)
{
    $user = wp_get_current_user();
    $notices = get_user_meta($user->ID, 'notify');

    foreach ($form['fields'] as &$field)
    {
        if ($field->id == 5)
        {
            foreach ($field->choices as &$choice)
            {
                if (in_array($choice['value'], $notices))
                {
                    $choice['isSelected'] = true;
                }
            }
        }
    }

    return $form;
}
add_filter('gform_pre_render_36', 'ipema_populate_reviewer_notices');
add_filter('gform_pre_validation_36', 'ipema_populate_reviewer_notices');
add_filter('gform_pre_submission_filter_36', 'ipema_populate_reviewer_notices');

function ipema_reviewer_notices($entry, $form)
{
    $user = wp_get_current_user();
    delete_user_meta($user->ID, 'notify');

    if (rgar($entry, '5.1'))
    {
        add_user_meta($user->ID, 'notify', $entry['5.1']);
    }
    if (rgar($entry, '5.2'))
    {
        add_user_meta($user->ID, 'notify', $entry['5.2']);
    }
    if (rgar($entry, '5.3'))
    {
        add_user_meta($user->ID, 'notify', $entry['5.3']);
    }
}
add_action('gform_after_submission_36', 'ipema_reviewer_notices', 10, 2);

function ipema_get_validator_emails($type)
{
    $validators = get_users(array(
        'meta_key' => 'notify',
        'meta_value' => $type
    ));

    $emails = [];
    foreach ($validators as $validator)
    {
        if (user_can($validator, 'can_validate_products'))
        {
            $emails[] = $validator->display_name . ' <'
                . $validator->user_email . '>';
        }
    }

    return implode(', ', $emails);
}

function ipema_validator_emails($notification, $form, $lead)
{
    $tag = '/{\s*validators:\s*(\w+)\s*}/i';
    if (preg_match($tag, $notification['to'], $matches))
    {
        $notification['to'] = str_replace(
            $matches[0],
            ipema_get_validator_emails($matches[1]),
            $notification['to']
        );
    }

    return $notification;
}
add_filter('gform_notification', 'ipema_validator_emails', 10, 3);

function ipema_rv_email($rvID)
{
    $rv = get_post($rvID);
    if ($rv == NULL)
    {
        return;
    }

    $product = get_post(get_post_meta($rvID, '_wpcf_belongs_product_id', true));
    if ($product == NULL)
    {
        return;
    }

    $company = get_post(
        get_post_meta($product->ID, '_wpcf_belongs_company_id', true)
    );
    if ($company == NULL)
    {
        return;
    }

    $type = get_the_terms($product->ID, 'product-type');
    if ($type == false)
    {
        return;
    }
    $typeName = $type[0]->name;
    $type = $type[0]->slug;
    $rvType = ipema_get_pending_rv_type($rv);

    $fullName = ipema_product_display_name($product->ID);

    $subject = "New $typeName RV by {$company->post_title}";
    if ($rv->post_parent)
    {
        $subject = "Updated RV for $fullName by {$company->post_title}";
    }

    $body = <<<EOF
RV #{$rv->public_id}
Manufacturer: {$company->post_title}
Product: $fullName
Type: $rvType

EOF;

    $surfacingType = get_the_terms($product->ID, 'material');
    if ($surfacingType)
    {
        $body .= "Surfacing: {$surfacingType[0]->name}\n";
    }

    $certifications = array();
    $certs = get_the_terms($rv->ID, 'certification');
    if ($certs != false)
    {
        foreach ($certs as $cert)
        {
            $certifications[] = $cert->name;
        }
        $certifications = implode(', ', $certifications);

        $body .= "Certifications: $certifications\n";
    }

    $user = wp_get_current_user();
    $contactMethod = get_user_meta($user->ID, 'contact-method');
    $contact = $user->user_email;
    if ($contactMethod == 'phone')
    {
        $contact = $user->phone;
    }

    $date = date('m/d/Y');
    $siteURL = site_url();

    $body .= <<<EOF

Submitted By: {$user->display_name}
Contact: $contact
Date: $date

Review: $siteURL/rvs/review/?request=$rvID
EOF;

    wp_mail(
        ipema_get_validator_emails($type),
        $subject,
        $body
    );
}

function ipema_new_manufacturer_email($companyID, $type)
{
    $company = get_post($companyID);
    if ($company == NULL)
    {
        return;
    }

    $created = date('m/d/Y', strtotime($company->post_date));
    $today = date('m/d/Y');

    $siteURL = site_url();

    $subject = 'New ' . ucfirst($type) . ' Manufacturer';
    $body = <<<EOF
Manufacturer: {$company->post_title}
Account Created: {$created}
Paperwork Approved: {$today}

Details: $siteURL/rvs/new-manufacturers/approve/?id=$companyID
EOF;

    wp_mail(
        ipema_get_validator_emails($type),
        html_entity_decode($subject),
        html_entity_decode($body)
    );
}

function ipema_member_populate_states($settings, $id)
{
    global $US_STATES;
    if ($id != 487)
    {
        return $settings;
    }

    $filter = $settings['filter_meta_html'];
    if (preg_match('/\[wpv-control-postmeta[^\]]+field="state"[^\]]+\]/', $filter, $matches))
    {
        $states = implode(',', $US_STATES);
        $html = str_replace(
            'display_values="Any"',
            'display_values="Any,' . $states . '"',
            $matches[0]
        );

        $abbrvs = implode(',', array_keys($US_STATES));
        $html = str_replace(
            'values=""',
            'values=",' . $abbrvs . '"',
            $html
        );

        $settings['filter_meta_html'] = str_replace(
            $matches[0],
            $html,
            $filter
        );
    }

    return $settings;
}
add_filter('wpv_view_settings', 'ipema_member_populate_states', 10, 2);

function ipema_member_state_filter($query)
{
    if ( ! is_post_type_archive('manufacturer'))
    {
        return $query;
    }
    if ( ! array_key_exists('meta_query', $query->query_vars))
    {
        return $query;
    }

    foreach ($query->query_vars['meta_query'] as $meta)
    {
        if (is_array($meta))
        {
            if ($meta['key'] == 'state' && $meta['value'])
            {
                $query->query_vars['meta_query'][] = array(
                    'key' => 'country',
                    'value' => 'United States',
                    'type' => 'CHAR',
                    'compare' => '='
                );

                break;
            }
        }
    }

    return $query;
}
add_filter('pre_get_posts', 'ipema_member_state_filter', 100);

function ipema_retested_count($companyID, $type, $year=NULL)
{
    if ( ! is_numeric($year))
    {
        $year = ipema_retest_year();
    }

    if ($type == 'equipment')
    {
        $type = array('equipment', 'structure');
    }

    $products = get_posts(array(
        'post_type' => 'product',
        'post_status' => 'any',
        'meta_query' => array(
            array(
                'key' => '_wpcf_belongs_company_id',
                'value' => $companyID
            ),
            array(
                'key' => 'retest_year',
                'value' => $year
            )
        ),
        'tax_query' => array(array(
            'taxonomy' => 'product-type',
            'field' => 'slug',
            'terms' => $type
        )),
        'nopaging' => true
    ));

    return count($products);
}

function ipema_populate_retests($form, $ajax, $field_values)
{
    $company = get_post($_GET['member']);
    if ( ! $company || $company->post_type != 'company')
    {
        return $form;
    }

    $show = array();
    $types = get_the_terms($company, 'product-type');
    foreach ($types as $type)
    {
        $show[] = $type->slug;
    }

    foreach ($form['fields'] as &$field)
    {
        if ($field->id == 1)
        {
            if (in_array('equipment', $show))
            {
                $goal = $company->equipment_retest_goal;
                if ( ! $goal)
                {
                    $goal = 0;
                }
                $field->defaultValue = $goal;
            }
            else
            {
                $field->adminOnly = true;
            }
        }
        else if ($field->id == 2)
        {
            if (in_array('surfacing', $show))
            {
                $goal = $company->surfacing_retest_goal;
                if ( ! $goal)
                {
                    $goal = 0;
                }
                $field->defaultValue = $goal;
            }
            else
            {
                $field->adminOnly = true;
            }
        }
    }

    return $form;
}
add_filter('gform_pre_render_37', 'ipema_populate_retests', 10, 3);
add_filter('gform_pre_validation_37', 'ipema_populate_retests', 10, 3);
add_filter('gform_pre_submission_filter_37', 'ipema_populate_retests', 10, 3);

function ipema_update_retests($entry, $form)
{
    $company = get_post(rgar($entry, '3'));
    if ( ! $company || $company->post_type != 'company')
    {
        return;
    }

    $equipmentGoal = rgar($entry, 1);
    $surfacingGoal = rgar($entry, 2);

    if ($equipmentGoal > 0)
    {
        update_post_meta($company->ID, 'equipment_retest_goal', $equipmentGoal);
    }
    else
    {
        delete_post_meta($company->ID, 'equipment_retest_goal');
    }

    if ($surfacingGoal > 0)
    {
        update_post_meta($company->ID, 'surfacing_retest_goal', $surfacingGoal);
    }
    else
    {
        delete_post_meta($company->ID, 'surfacing_retest_goal');
    }
}
add_action('gform_after_submission_37', 'ipema_update_retests', 10, 2);


function ipema_retest_quotas()
{
    $certs = array(
        'equipment' => array(),
        'surfacing' => array()
    );
    $certifications = get_terms(array(
        'taxonomy' => 'certification'
    ));
    foreach ($certifications as $certification)
    {
        if (ipema_certification_product_type($certification->term_id, 'equipment'))
        {
            $certs['equipment'][] = $certification;
        }
        elseif (ipema_certification_product_type($certification->term_id, 'surfacing'))
        {
            $certs['surfacing'][] = $certification;
        }
    }

    $year = date('Y');
    $nextYear = $year + 1;
    $start = strtotime("$year-" . CERTIFICATION_RENEWAL_DATE);
    $start = strtotime('+1 day', $start);
    $range = array(
        date('Y-m-d', $start),
        "$nextYear-" . CERTIFICATION_RENEWAL_DATE
    );
    $memberYear = ipema_current_year();
    $site = site_url();

    add_filter('wp_mail_content_type', function() { return 'text/html'; });

    $offset = 0;
    if (array_key_exists('offset', $_GET))
    {
        $offset = $_GET['offset'];
    }

    $manufacturers = get_posts(array(
        'post_type' => 'company',
        'tax_query' => array(array(
            'taxonomy' => 'account-type',
            'field' => 'slug',
            'terms' => 'manufacturer'
        )),
        'offset' => $offset,
        'posts_per_page' => 1,
        'orderby' => 'title',
        'order' => 'ASC'
    ));

    if (count($manufacturers) == 0)
    {
        $msg = '<p>The following companies did not meet their retesting'
            . " obligations for $year</p>";

        $msg .= '<ul>';
        $f = fopen(__DIR__ . '/retests.csv', 'r');
        while (($row = fgetcsv($f)) != false)
        {
            $msg .= "<li><a href=\"$site/rvs/manufacturers/details/"
                . "?member={$row[1]}\">$row[0]</a> - {$row[2]} Retests: "
                . "{$row[4]} of {$row[3]}</li>";
        }
        $msg .= '</ul>';
        fclose($f);
        unlink(__DIR__ . '/retests.csv');

        wp_mail(
            array(
                'David Splane <dsplane@tuvam.com>',
                'Tim Fouchia <Tim.Fouchia@tuvsud.com>',
            ),
            'Annual Retest Report',
            $msg
        );
        die();
    }

    foreach ($manufacturers as $manufacturer)
    {
        $warnings = array();
        $msg = '';
        foreach (array('equipment', 'surfacing') as $type)
        {
            $expires = get_post_meta($manufacturer->ID, $type, true);
            if ($expires < $memberYear)
            {
                delete_post_meta($manufacturer->ID, "{$type}_retest_goal");
                continue;
            }

            $goal = get_post_meta(
                $manufacturer->ID,
                "{$type}_retest_goal",
                true
            );
            if ($goal)
            {
                $completed = ipema_retested_count(
                    $manufacturer->ID,
                    $type,
                    $year
                );

                if ($completed < $goal)
                {
                    $f = fopen(__DIR__ . '/retests.csv', 'a');
                    fputcsv($f, array(
                        $manufacturer->post_title,
                        $manufacturer->ID,
                        ucfirst($type),
                        $goal,
                        $completed
                    ));
                    fclose($f);

                    $products = 'product';
                    if ($completed == 0)
                    {
                        $words = 'do not show any retests';
                    }
                    else
                    {
                        $words = "only show $completed retest";
                        if ($completed > 1)
                        {
                            $words .= 's';
                            $products .= 's';
                        }
                    }

                    $msg .= <<<EOF
<p>According to our records, you did not have the required number of $type
products retested in the previous year. You should have had $goal $products
retested, but our records $words completed. We will be in touch
to work with you on correcting this.</p>
EOF;
                }
                delete_post_meta($manufacturer->ID, "{$type}_retest_goal");
            }

            foreach ($certs[$type] as $cert)
            {
                $expiring = get_posts(array(
                    'post_type' => 'product',
                    'nopaging' => true,
                    'meta_query' => array(
                        array(
                            'key' => '_wpcf_belongs_company_id',
                            'value' => $manufacturer->ID
                        ),
                        array(
                            'key' => $cert->slug,
                            'value' => $range,
                            'type' => 'DATE',
                            'compare' => 'BETWEEN'
                        )
                    ),
                    'tax_query' => array(array(
                        'taxonomy' => 'certification',
                        'terms' => $cert->term_id
                    ))
                ));

                foreach ($expiring as $product)
                {
                    $rvs = get_posts(array(
                        'post_type' => 'rv',
                        'post_status' => 'draft',
                        'meta_query' => array(array(
                            'key' => 'affected_id',
                            'value' => $product->ID
                        )),
                        'tax_query' => array(array(
                            'taxonomy' => 'product-type',
                            'field' => 'slug',
                            'terms' => $cert->slug
                        ))
                    ));

                    if (count($rvs) == 0)
                    {
                        $expiration = get_post_meta(
                            $product->ID,
                            $cert->slug,
                            true
                        );

                        if (array_key_exists($product->ID, $warnings))
                        {
                            $expiration = strtotime($expiration);
                            if ($expiration < $warnings[$product->ID]['expires'])
                            {
                                $warnings[$product->ID]['expires'] = $expiration;
                                $warnings[$product->ID]['cert'] = $cert;
                            }

                            continue;
                        }

                        $warnings[$product->ID] = array(
                            'expires' => strtotime($expiration),
                            'product' => $product,
                            'cert' => $cert
                        );
                    }
                }
            }

            $terms = array($type);
            if ($type == 'equipment')
            {
                $terms[] = 'structure';
            }
            $active = get_posts(array(
                'post_type' => 'product',
                'meta_query' => array(array(
                    'key' => '_wpcf_belongs_company_id',
                    'value' => $manufacturer->ID
                )),
                'tax_query' => array(array(
                    'taxonomy' => 'product-type',
                    'field' => 'slug',
                    'terms' => $terms
                )),
                'nopaging' => true
            ));
            $activeCount = count($active);

            if ($activeCount > 0)
            {
                $retestPercent = 0.2;
                if ($type == 'equipment')
                {
                    $retestPercent = 0.15;
                }
                $newGoal = ceil($activeCount * $retestPercent);
                update_post_meta(
                    $manufacturer->ID,
                    "{$type}_retest_goal",
                    $newGoal
                );

                $products = 'product';
                if ($activeCount > 1)
                {
                    $products .= 's';
                }

                $msg .= <<<EOF
<p>Your retest goal for $type products in $nextYear is $newGoal, based on your total
of $activeCount active $products currently certified with us. If you have any
questions about how we arrived at that number, please contact us.</p>
EOF;
            }

            if (count($warnings) == 0)
            {
                continue;
            }

            $msg .= <<<EOF
<p>To help you plan which models to retest this year, here are all of the
$type products you have that will expire next year:</p>
<table>
    <thead>
        <tr>
            <td>Product</td>
            <td>Product Line</td>
            <td>Expiration</td>
        </tr>
    </thead>
    <tbody>
EOF;

            usort($warnings, function($a, $b) {
                if ($a['expires'] == $b['expires'])
                {
                    return 0;
                }
                return ($a['expires'] < $b['expires']) ? -1 : 1;
            });

            foreach ($warnings as $warning)
            {
                $fullName = ipema_product_display_name(
                    $warning['product']->ID
                );

                $productLine = get_the_terms(
                    $warning['product'],
                    'product-line'
                );
                if ($productLine)
                {
                    $productLine = $productLine[0]->name;
                }
                else
                {
                   $productLine = '';
                }

                $expirationDate = date('M. j', $warning['expires']);

                $msg .= "<tr><td><a href=\"$site/members/products/manage/"
                    . "?model={$warning['product']->ID}\">$fullName</a></td>"
                    . "<td>$productLine</td><td>$expirationDate</td></tr>";
            }

            $msg .= '</tbody></table>';
        }

        if ($msg == '')
        {
            continue;
        }

        $msg = <<<EOF
<p>Thank you for your support.</p>
$msg
<p>All the best,<br>
The IPEMA Team</p>
EOF;

        $users = get_users(array(
            'meta_key' => 'company_id',
            'meta_value' => $manufacturer->ID
        ));

        foreach ($users as $user)
        {
            if ( ! $user->has_cap('can_manage_products'))
            {
                continue;
            }
            if ($user->has_cap('can_validate_products')
                || $user->has_cap('manage_ipema'))
            {
                continue;
            }

            wp_mail(
                "{$user->display_name} <{$user->user_email}>",
                'IPEMA Annual Retest Goal',
                "<p>Hi {$user->display_name},</p>$msg"
            );
        }
    }

    wp_redirect('/about-ipema/?cron=retest&offset=' . ($offset + 1));
    die();
}

function ipema_shorten_index_sql($sql)
{
    $found = preg_match(
        '/AND post.post_type (IN \(([\w\' ,-]+)\))/',
        $sql,
        $matches
    );

    if ($found)
    {
        if ( ! array_key_exists('type', $_GET))
        {
            $types = str_replace(array(' ', "'"), '', $matches[2]);
            $types = explode(',', $types);
            $_GET['type'] = $types[0];
        }
        $replacement = str_replace(
            $matches[1],
            "= '{$_GET['type']}'",
            $matches[0]
        );
        $sql = str_replace($matches[0], $replacement, $sql);

        $GLOBALS['post_types'] = $matches[2];
        remove_filter('query', 'ipema_shorten_index_sql');
    }

    return $sql;
}

function ipema_rebuild_index()
{
    $cache = 0;
    if (array_key_exists('cache', $_GET))
    {
        $cache = $_GET['cache'];
    }
    ini_set('memory_limit', '2G');
    remove_filter('the_posts', array('WPCF_Loader', 'wpcf_cache_complete_postmeta'));
    add_filter('query', 'ipema_shorten_index_sql');
    $result = relevanssi_build_index(true, false, 1000);
    if ( ! $result[0])
    {
        $cache++;
        $type = $_GET['type'];
        wp_redirect("/about-ipema/?cron=index&cache=$cache&type=$type");
        die();
    }
    else
    {
        $types = explode(',', $GLOBALS['post_types']);
        $isNext = false;
        foreach ($types as $type)
        {
            if ($isNext)
            {
                wp_redirect("/about-ipema/?cron=index&cache=$cache&type=$type");
                die();
            }
            $type = trim($type, "' ");
            if ($type == $_GET['type'])
            {
                $isNext = true;
            }
        }
    }
}

// Attempt to prevent ridiculously long Term queries
/*add_action('pre_get_terms', function($query) {
    $args = $query->query_vars;
    if ( ! array_key_exists('object_ids', $args))
    {
        return;
    }
    if ( ! is_array($args['object_ids']) || count($args['object_ids']) <= 200)
    {
        return;
    }

    $GLOBALS['term_default_keys'] = array_keys($query->query_var_defaults);
    $GLOBALS['current_term_args'] = $query->query_vars;
});

add_filter('terms_clauses', function($clauses, $taxonomies, $args) {
    global $wpdb;

    if ( ! array_key_exists('term_default_keys', $GLOBALS))
    {
        return $clauses;
    }

    // Copied from WP_Term_Query::get_terms#L656
    $fields = isset( $clauses[ 'fields' ] ) ? $clauses[ 'fields' ] : '';
    $join = isset( $clauses[ 'join' ] ) ? $clauses[ 'join' ] : '';
    $where = isset( $clauses[ 'where' ] ) ? $clauses[ 'where' ] : '';
    $distinct = isset( $clauses[ 'distinct' ] ) ? $clauses[ 'distinct' ] : '';
    $orderby = isset( $clauses[ 'orderby' ] ) ? $clauses[ 'orderby' ] : '';
    $order = isset( $clauses[ 'order' ] ) ? $clauses[ 'order' ] : '';
    $limits = isset( $clauses[ 'limits' ] ) ? $clauses[ 'limits' ] : '';

    if ( $where ) {
        $where = "WHERE $where";
    }
    $orderby = $orderby ? "$orderby $order" : '';

    $sql = "SELECT $distinct $fields FROM {$wpdb->terms} AS t $join $where "
        . "$orderby $limits";

    $key = md5(
        serialize(
            wp_array_slice_assoc(
                $args,
                $GLOBALS['term_default_keys']
            )
        ) .
        serialize( $taxonomies ) . $sql
    );
    $last_changed = wp_cache_get_last_changed( 'terms' );
    $cache_key = "get_terms:$key:$last_changed";

    unset($GLOBALS['term_default_keys']);

    $found = wp_cache_get($cache_key, 'terms');
    if ($found === false)
    {
        $args = $GLOBALS['current_term_args'];
        $seen = array();
        $newQuery = new WP_Term_Query();
        $terms = array();
        $idSet = array_chunk($args['object_ids'], 200);
        foreach ($idSet as $ids)
        {
            $args['object_ids'] = $ids;
            $part = $newQuery->query($args);
            foreach ($part as $term)
            {
                if ( ! in_array($term->term_id, $seen))
                {
                    $terms[] = $term;
                    $seen[] = $term->term_id;
                }
            }
        }
        wp_cache_add($cache_key, $terms, 'terms', DAY_IN_SECONDS);
    }

    unset($GLOBALS['current_term_args']);

    return $clauses;
}, 100, 3);

add_filter('relevanssi_query_filter', function($sql) {
    return $sql . ' ORDER BY tf DESC LIMIT 200';
}, 100);

add_filter('relevanssi_remove_punctuation', function($str) {
    if (!is_string($str)) return "";

    $str = preg_replace('/([\w\d])\.([\w\d])/', '$1zPeRIoDm$2', $str);
    return preg_replace('/([\w\d])-([\w\d])/', '$1zHyPHeNm$2', $str);
}, 9);

add_filter('relevanssi_remove_punctuation', function($str) {
    $str = str_replace('zHyPHeNm', '-', $str);
    return str_replace('zPeRIoDm', '.', $str);
}, 11);*/

add_filter('relevanssi_post_ok', function($post_ok, $doc) {
    if ( ! $post_ok && relevanssi_get_post_type($doc) == 'product')
    {
        if (relevanssi_get_post_status($doc) == 'draft')
        {
            return true;
        }
    }

    return $post_ok;
}, 11, 2);

// See wp-views/embedded/inc/wpv-archive-loop.php
// WPV_WordPress_Archive_Frontend::force_disable_404
// Prevents WP_Query::init_query_flags from being run on paging without any hits
add_filter('pre_handle_404', function($stop, $wp_query=NULL) {
    global $WPV_view_archive_loop;

    if ($WPV_view_archive_loop && $WPV_view_archive_loop->wpa_id)
    {
        status_header( 200 );
        return true;
    }

    return $stop;
});

function ipema_product_row($certifications, $product, $active=true)
{
    $row = array();
    $row[] = html_entity_decode($product->post_title);
    $row[] = get_post_meta($product->ID, 'french_name', true);
    $row[] = $product->model;

    $line = get_the_terms($product->ID, 'product-line');
    if ($line == false)
    {
        $line = '';
    }
    else
    {
        $line = html_entity_decode($line[0]->name);
    }

    $row[] = $line;

    $row[] = $product->post_content;
    $row[] = get_post_meta($product->ID, 'french-description', true);

    $row[] = explode(' ', $product->post_date)[0];

    $retest = get_posts(array(
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
        'tax_query' => array(array(
            'taxonomy' => 'request',
            'field' => 'slug',
            'terms' => 'test'
        )),
        'posts_per_page' => 1
    ));

    if (count($retest) == 0)
    {
        $row[] = '';
    }
    else
    {
        $prev = get_posts(array(
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
            'tax_query' => array(array(
                'taxonomy' => 'request',
                'field' => 'slug',
                'terms' => array('add-model', 'test')
            )),
            'date_query' => array(
                'before' => $retest[0]->post_date
            ),
            'posts_per_page' => 1,
            'fields' => 'ids'
        ));

        if (count($prev) == 0)
        {
            $row[] = '';
        }
        else
        {
             $row[] = explode(' ', $retest[0]->post_modified)[0];
        }
    }

    $showCerts = array();
    $activeCerts = get_the_terms($product->ID, 'certification');
    if ($activeCerts)
    {
        foreach ($activeCerts as $cert)
        {
            $showCerts[$cert->slug] = true;
        }
    }

    $certs = array();
    $expDate = '';
    $expDatePassed = '';
    foreach ($certifications as $slug)
    {
        if ( ! (array_key_exists($slug, $showCerts) && $showCerts[$slug]))
        {
            continue;
        }
        $exp = get_post_meta($product->ID, $slug, true);
        if ($exp)
        {
            $expTime = strtotime($exp);
            if ($expTime > time())
            {
                if ($expDate == '' || $expTime < $expDate)
                {
                    $expDate = $expTime;
                }
                if ($active)
                {
                    $certs[$slug] = 'Certified';
                }
                else
                {
                    $certs[$slug] = 'Inactive';
                }
            }
            else
            {
                if ($expDatePassed == '' || $expTime > $expDatePassed)
                {
                    $expDatePassed = $expTime;
                }
                $certs[$slug] = 'Expired ' . date('Y-m-d', $expTime);
            }
        }
    }
    if ($expDate)
    {
        $expDate = date('Y-m-d', $expDate);
    }
    elseif ($expDatePassed)
    {
        $expDate = date('Y-m-d', $expDatePassed);
    }

    $row[] = $expDate;

    $type = get_the_terms($product->ID, 'product-type');
    if ($type && $type[0]->slug == 'surfacing')
    {
        $row[] = ipema_thk_to_ht_shortcode(
            array('product' => $product->ID)
        );

        $material = get_the_terms($product->ID, 'material');
        if ($material)
        {
            $row[] = $material[0]->name;
        }
        else
        {
            $row[] = '';
        }
    }
    else
    {
        $row[] = '';
        $row[] = '';
    }


    foreach ($certifications as $cert)
    {
        if (array_key_exists($cert, $certs))
        {
            if ($showCerts[$cert])
            {
                $row[] = $certs[$cert];
            }
            else
            {
                $row[] = '';
            }
        }
        else
        {
            $row[] = '';
        }
    }

    return $row;
}

function ipema_log($output, $clear=false)
{
    if ($clear)
    {
        unlink(__DIR__ . '/log.txt');
    }

    if (is_object($output))
    {
        ob_start();
        var_dump($output);
        $output = ob_get_clean();
    }
    elseif (is_array($output))
    {
        $output = print_r($output, true);
    }

    file_put_contents(__DIR__ . '/log.txt', "$output\n", FILE_APPEND);
}

function ipema_active_certs()
{
    return get_terms(array(
        'taxonomy' => 'certification',
        'hide_empty' => false,
        'meta_query' => array(array(
            'key' => 'obsolete',
            'compare' => 'NOT EXISTS'
        )),
        'orderby' => 'meta_value',
        'meta_key' => 'order'
    ));
}

add_action('template_redirect', function() {
    if ( ! is_page('all-products'))
    {
        return;
    }

    $timeout = 35;
    $startTime = time();

    $user = wp_get_current_user();
    if ( ! $user->has_cap('manage_ipema'))
    {
        return;
    }

    $certifications = ipema_active_certs();
    $current_year = ipema_current_year();

    if (array_key_exists('t', $_GET))
    {
        $output = fopen(__DIR__ . "/productreport-{$_GET['t']}.csv", 'a');
        if ( ! $output)
        {
            die('Could not find spreadsheet file');
        }

        $certSlugs = array();
        foreach ($certifications as $certification)
        {
            $certSlugs[] = $certification->slug;
        }
    }
    else
    {
        $_GET['t'] = $startTime;
        $output = fopen(__DIR__ . "/productreport-{$_GET['t']}.csv", 'w');
        if ( ! $output)
        {
            die('Could not create spreadsheet file');
        }

        $header = array(
            'Manufacturer',
            'Name',
            'French Name',
            'Model Number',
            'Product Line',
            'Created',
            'Expiration',
            'Thickness to Height Ratio',
            'Material'
        );

        $certSlugs = array();
        foreach ($certifications as $certification)
        {
            $certSlugs[] = $certification->slug;
            $header[] = $certification->name;
        }

        fputcsv($output, $header);
    }

    $offset = 0;
    if (array_key_exists('offset', $_GET))
    {
        $offset = $_GET['offset'];
    }
    $page = 1;
    if (array_key_exists('pg', $_GET))
    {
        $page = $_GET['pg'];
    }

    if ( ! array_key_exists('single', $_GET))
    {
        $families = get_terms(array(
            'taxonomy' => 'base',
            'hide_empty' => false,
            'offset' => $offset,
            'number' => 10000 // Required to use offset
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

            if (time() - $startTime > $timeout)
            {
                // https://wordpress.stackexchange.com/questions/83999
                $path = add_query_arg(array(
                    'offset' => $offset,
                    't' => $_GET['t'],
                    'r' => false
                ));
                $counter = -1;
                if (array_key_exists('r', $_GET))
                {
                    $counter = $_GET['r'];
                }
                $counter++;
                if ($counter > 10)
                {
                    $script = <<<EOF
<span id="continue">
<a href="$path" id="link">
    Download Product Report
</a>
</span>
<script type="text/javascript">
    var link = document.getElementById('link');
    window.location = link.href;
    var span = document.getElementById('continue');
    span.innerHTML = 'Generating spreadsheet...<br>Please wait';
</script>
EOF;
                    die($script);
                }
                wp_redirect(
                    $path . "&r=$counter"
                );
                die();
            }

            $namePrefix = get_term_meta($family->term_id, 'name', true);
            if ($namePrefix)
            {
                $namePrefix .= ' ';
            }

            $frenchPrefix = get_term_meta(
                $family->term_id,
                'french_prefix',
                true
            );
            if ($frenchPrefix)
            {
                $frenchPrefix .= ' ';
            }

            $products = get_posts(array(
                'post_type' => 'product',
                'post_status' => 'any',
                'tax_query' => array(array(
                    'taxonomy' => 'base',
                    'terms' => $family->term_id
                )),
                'paged' => $page
            ));

            while (count($products) > 0)
            {
                if (time() - $startTime > $timeout)
                {
                    // https://wordpress.stackexchange.com/questions/83999
                    $path = add_query_arg(array(
                        'offset' => $offset,
                        't' => $_GET['t'],
                        'r' => false,
                        'pg' => $page
                    ));
                    $counter = -1;
                    if (array_key_exists('r', $_GET))
                    {
                        $counter = $_GET['r'];
                    }
                    $counter++;
                    if ($counter > 10)
                    {
                        $script = <<<EOF
<span id="continue">
<a href="$path" id="link">
    Download Product Report
</a>
</span>
<script type="text/javascript">
    var link = document.getElementById('link');
    window.location = link.href;
    var span = document.getElementById('continue');
    span.innerHTML = 'Generating spreadsheet...<br>Please wait';
</script>
EOF;
                        die($script);
                    }
                    wp_redirect(
                        $path . "&r=$counter"
                    );
                    die();
                }
                $manufacturer = NULL;
                $active = false;
                foreach ($products as $product)
                {
                    if ( ! $manufacturer)
                    {
                        $manufacturer = get_post(
                            $product->_wpcf_belongs_company_id
                        );
                        $active = false;
                        $product_type = get_the_terms($product->ID, 'product-type');
                        if ($product_type !== false)
                        {
                            $expires = get_post_meta(
                                $manufacturer->ID,
                                $product_type[0]->slug,
                                true
                            );
                            $active = ($expires >= $current_year);
                        }
                    }
                    $row = array(
                        html_entity_decode($manufacturer->post_title),
                    );
                    $row = array_merge(
                        $row,
                        ipema_product_row($certSlugs, $product, $active)
                    );

                    $row[2] = $namePrefix . $row[2];
                    $row[3] = $frenchPrefix . $row[3];
                    $row[4] = $modelPrefix . $row[4];

                    array_splice($row, 8, 1);
                    array_splice($row, 6, 1);
                    array_splice($row, 5, 1);

                    fputcsv($output, $row);
                }

                $page++;
                $products = get_posts(array(
                    'post_type' => 'product',
                    'post_status' => 'any',
                    'tax_query' => array(array(
                        'taxonomy' => 'base',
                        'terms' => $family->term_id
                    )),
                    'meta_query' => array(array(
                        'key' => '_wpcf_belongs_company_id',
                        'value' => $manufacturer->ID
                    )),
                    'paged' => $page
                ));
            }

            $page = 1;

            $offset++;
        }

        $offset = 0;
    }

    // TODO: Put this in a loop so we don't have to get 10000 products to avoid
    // it finishing before our timeout check triggers a redirect
    $products = get_posts(array(
        'post_type' => 'product',
        'post_status' => 'any',
        'tax_query' => array(array(
            'taxonomy' => 'base',
            'operator' => 'NOT EXISTS'
        )),
        'posts_per_page' => 10000, // Required to use offset
        'offset' => $offset
    ));

    foreach ($products as $product)
    {
        if (time() - $startTime > $timeout)
        {
            // https://wordpress.stackexchange.com/questions/83999
            $path = add_query_arg(array(
                'single' => 1,
                't' => $_GET['t'],
                'offset' => $offset,
                'r' => false

            ));

            $counter = 0;
            if (array_key_exists('r', $_GET))
            {
                $counter = $_GET['r'];
            }
            $counter++;
            if ($counter > 10)
            {
                $script = <<<EOF
<span id="continue">
<a href="$path" id="link">
    Download Product Report
</a>
</span>
<script type="text/javascript">
    var link = document.getElementById('link');
    window.location = link.href;
    var span = document.getElementById('continue');
    span.innerHTML = 'Generating spreadsheet...<br>Please wait';
</script>
EOF;
                die($script);
            }
            wp_redirect($path . "&r=$counter");
            die();
        }

        $offset++;

        $manufacturer = get_post($product->_wpcf_belongs_company_id);
        $active = false;
        $product_type = get_the_terms($product->ID, 'product-type');
        if ($product_type !== false)
        {
            $expires = get_post_meta(
                $manufacturer->ID,
                $product_type[0]->slug,
                true
            );
            $active = ($expires >= $current_year);
        }
        $row = array(
            html_entity_decode($manufacturer->post_title)
        );
        $row = array_merge(
            $row,
            ipema_product_row($certSlugs, $product, $active)
        );

        array_splice($row, 8, 1);
        array_splice($row, 6, 1);
        array_splice($row, 5, 1);

        fputcsv($output, $row);
    }

    $filename = 'IPEMA-Products.csv';

    rename(
        __DIR__ . "/productreport-{$_GET['t']}.csv",
        __DIR__ . "/../../uploads/products/$filename"
    );

    $filename = rawurlencode($filename);
    wp_redirect("/wp-content/uploads/products/$filename");
    die();
});

function ipema_product_report()
{
    if ( ! is_page('download-report'))
    {
        return;
    }

    $timeout = 35;
    $startTime = time();

    $user = wp_get_current_user();
    $manufacturer = get_post($user->company_id);
    $companyName = html_entity_decode($manufacturer->post_title);
    $url = site_url('/members/products/manage/') . "?model=";

    $certifications = ipema_active_certs();

    if (array_key_exists('t', $_GET))
    {
        $output = fopen(__DIR__ . "/{$user->company_id}-{$_GET['t']}.csv", 'a');
        if ( ! $output)
        {
            die('Could not find spreadsheet file');
        }

        $certSlugs = array();
        foreach ($certifications as $certification)
        {
            $certSlugs[] = $certification->slug;
        }
    }
    else
    {
        $_GET['t'] = $startTime;
        $output = fopen(__DIR__ . "/{$user->company_id}-{$_GET['t']}.csv", 'w');
        if ( ! $output)
        {
            die('Could not create spreadsheet file');
        }

        $header = array(
            'Manufacturer',
            'Family',
            'Name',
            'French Name',
            'Model Number',
            'Product Line',
            'Description',
            'French Description',
            'Created',
            'Last Retested',
            'Expiration',
            'Thickness to Height Ratio',
            'Material'
        );

        $certSlugs = array();
        foreach ($certifications as $certification)
        {
            $certSlugs[] = $certification->slug;
            $header[] = $certification->name;
        }
        $header[] = 'Manage';

        fputcsv($output, $header);
    }

    $offset = 0;
    if (array_key_exists('offset', $_GET))
    {
        $offset = $_GET['offset'];
    }
    $page = 1;
    if (array_key_exists('pg', $_GET))
    {
        $page = $_GET['pg'];
    }

    if ( ! array_key_exists('single', $_GET))
    {
        $families = get_terms(array(
            'taxonomy' => 'base',
            'meta_query' => array(
                array(
                    'key' => 'company_id',
                    'value' => $user->company_id
                )
            ),
            'hide_empty' => false,
            'offset' => $offset,
            'number' => 5000 // Required to use offset
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

            if (time() - $startTime > $timeout)
            {
                // https://wordpress.stackexchange.com/questions/83999
                $path = add_query_arg(array(
                    'offset' => $offset,
                    't' => $_GET['t'],
                    'r' => false
                ));
                $counter = -1;
                if (array_key_exists('r', $_GET))
                {
                    $counter = $_GET['r'];
                }
                $counter++;
                if ($counter > 10)
                {
                    $script = <<<EOF
<span id="continue">
<a href="$path" id="link">
    Download Product Report
</a>
</span>
<script type="text/javascript">
    var link = document.getElementById('link');
    window.location = link.href;
    var span = document.getElementById('continue');
    span.innerHTML = 'Generating spreadsheet...<br>Please wait';
</script>
EOF;
                    die($script);
                }
                wp_redirect(
                    $path . "&r=$counter"
                );
                die();
            }

            $namePrefix = get_term_meta($family->term_id, 'name', true);
            if ($namePrefix)
            {
                $namePrefix .= ' ';
            }

            $frenchPrefix = get_term_meta(
                $family->term_id,
                'french_prefix',
                true
            );
            if ($frenchPrefix)
            {
                $frenchPrefix .= ' ';
            }

            $en = '';
            if ($family->description)
            {
                $en = "{$family->description}\n\n";
            }
            $fr = get_term_meta($family->term_id, 'french-description', true);
            if ($fr)
            {
                $fr .= "\n\n";
            }
            else
            {
                $fr = '';
            }

            $products = get_posts(array(
                'post_type' => 'product',
                'post_status' => 'any',
                'tax_query' => array(array(
                    'taxonomy' => 'base',
                    'terms' => $family->term_id
                )),
                'meta_query' => array(array(
                    'key' => '_wpcf_belongs_company_id',
                    'value' => $manufacturer->ID
                )),
                'paged' => $page
            ));

            while (count($products) > 0)
            {
                if (time() - $startTime > $timeout)
                {
                    // https://wordpress.stackexchange.com/questions/83999
                    $path = add_query_arg(array(
                        'offset' => $offset,
                        't' => $_GET['t'],
                        'r' => false,
                        'pg' => $page
                    ));
                    $counter = -1;
                    if (array_key_exists('r', $_GET))
                    {
                        $counter = $_GET['r'];
                    }
                    $counter++;
                    if ($counter > 10)
                    {
                        $script = <<<EOF
<span id="continue">
<a href="$path" id="link">
    Download Product Report
</a>
</span>
<script type="text/javascript">
    var link = document.getElementById('link');
    window.location = link.href;
    var span = document.getElementById('continue');
    span.innerHTML = 'Generating spreadsheet...<br>Please wait';
</script>
EOF;
                        die($script);
                    }
                    wp_redirect(
                        $path . "&r=$counter"
                    );
                    die();
                }
                foreach ($products as $product)
                {
                    $row = array(
                        $companyName,
                        $familyID
                    );
                    $row = array_merge(
                        $row,
                        ipema_product_row($certSlugs, $product),
                        array($url . $product->ID)
                    );

                    $row[2] = $namePrefix . $row[2];
                    $row[3] = $frenchPrefix . $row[3];
                    $row[4] = $modelPrefix . $row[4];
                    $row[6] = $en . $row[6];
                    $row[7] = $fr . $row[7];

                    fputcsv($output, $row);
                }

                $page++;
                $products = get_posts(array(
                    'post_type' => 'product',
                    'post_status' => 'any',
                    'tax_query' => array(array(
                        'taxonomy' => 'base',
                        'terms' => $family->term_id
                    )),
                    'meta_query' => array(array(
                        'key' => '_wpcf_belongs_company_id',
                        'value' => $manufacturer->ID
                    )),
                    'paged' => $page
                ));
            }

            $page = 1;

            $offset++;
        }

        $offset = 0;
    }

    $products = get_posts(array(
        'post_type' => 'product',
        'post_status' => 'any',
        'tax_query' => array(array(
            'taxonomy' => 'base',
            'operator' => 'NOT EXISTS'
        )),
        'meta_query' => array(array(
            'key' => '_wpcf_belongs_company_id',
            'value' => $manufacturer->ID
        )),
        'posts_per_page' => 10000, // Required to use offset
        'offset' => $offset
    ));

    foreach ($products as $product)
    {
        if (time() - $startTime > $timeout)
        {
            // https://wordpress.stackexchange.com/questions/83999
            $path = add_query_arg(array(
                'single' => 1,
                't' => $_GET['t'],
                'offset' => $offset,
                'r' => false

            ));

            $counter = 0;
            if (array_key_exists('r', $_GET))
            {
                $counter = $_GET['r'];
            }
            $counter++;
            if ($counter > 10)
            {
                $script = <<<EOF
<span id="continue">
<a href="$path" id="link">
    Download Product Report
</a>
</span>
<script type="text/javascript">
    var link = document.getElementById('link');
    window.location = link.href;
    var span = document.getElementById('continue');
    span.innerHTML = 'Generating spreadsheet...<br>Please wait';
</script>
EOF;
                die($script);
            }
            wp_redirect($path . "&r=$counter");
            die();
        }

        $offset++;

        $row = array(
            $companyName,
            ''
        );
        $row = array_merge(
            $row,
            ipema_product_row($certSlugs, $product),
            array($url . $product->ID)
        );

        fputcsv($output, $row);
    }

    $filename = str_replace('/', '', $companyName) . ' Products-';
    $filename .= substr(strrev($_GET['t']), 0, 5) . '.csv';

    rename(
        __DIR__ . "/{$user->company_id}-{$_GET['t']}.csv",
        __DIR__ . "/../../uploads/products/$filename"
    );

    $filename = rawurlencode($filename);
    wp_redirect("/wp-content/uploads/products/$filename");
    die();
}
add_action('template_redirect', 'ipema_product_report');

add_action('template_redirect', function() {
    if (is_page('rvs') || is_page('admin') || is_page('dashboard'))
    {
        $user = wp_get_current_user();
        delete_user_meta($user->ID, 'company_id');

        $user->remove_cap('can_manage_account');
        $user->remove_cap('can_manage_products');
    }
    elseif (is_page('agent') && array_key_exists('member', $_GET))
    {
        $user = wp_get_current_user();
        update_user_meta($user->ID, 'company_id', $_GET['member']);
        $user->add_cap('can_manage_products');
        if (current_user_can('manage_ipema'))
        {
            $user->add_cap('can_manage_account');
            wp_redirect('/members/account');
            die();
        }

        wp_redirect('/members/products');
        die();
    }
}, 12);

/*add_action('template_redirect', function() {
    if ( ! array_key_exists('fix_obsolete', $_GET))
    {
        return;
    }
    /*$new = get_posts(array(
        'post_type' => 'rv',
        'post_status' => 'any',
        'nopaging' => true,
        'meta_query' => array(array(
            'key' => 'form_id',
            'compare' => 'NOT EXISTS'
        ))
    ));
    print 'New RVs: ' . count($new);
    die();*-/

    $page = 0;
    if (array_key_exists('o', $_GET))
    {
        $page = $_GET['o'];
    }

    $obsolete = get_posts(array(
        'post_type' => 'product',
        'post_status' => 'any',
        'meta_query' => array(array(
            'key' => 'obsolete',
            'compare' => 'EXISTS'
        )),
        'tax_query' => array(array(
            'taxonomy' => 'certification',
            'operator' => 'EXISTS'
        )),
        'posts_per_page' => 100
    ));

    if (count($obsolete) == 0)
    {
        die('Obsoleting completed');
    }

    foreach ($obsolete as $model)
    {
        wp_set_post_terms($model->ID, array(), 'certification');
    }

    $page++;
    wp_redirect('/about-ipema/?fix_obsolete=1&o=' . $page);
    die();
});

add_action('template_redirect', function() {
    if ( ! array_key_exists('fix_new_rvs', $_GET))
    {
        return;
    }

    $page = 1;
    if (array_key_exists('o', $_GET))
    {
        $page = $_GET['o'];
    }

    $new = get_posts(array(
        'post_type' => 'rv',
        'post_status' => 'any',
        'posts_per_page' => 50,
        'paged' => $page,
        'meta_query' => array(array(
            'key' => 'form_id',
            'compare' => 'NOT EXISTS'
        ))
    ));
    if (count($new) == 0)
    {
        die('New records corrected');
    }

    foreach ($new as $rv)
    {
        wp_set_post_terms($rv->ID, 'test', 'request');
        update_post_meta($rv->ID, 'guess', true);
        delete_post_meta($rv->ID, 'affected_id');
        $base = ipema_get_product_base($rv->_wpcf_belongs_product_id);
        if ($base == false)
        {
            add_post_meta(
                $rv->ID,
                'affected_id',
                $rv->_wpcf_belongs_product_id
            );
            continue;
        }

        $products = get_posts(array(
            'post_type' => 'product',
            'post_status' => 'any',
            'nopaging' => true,
            'tax_query' => array(
                array(
                    'taxonomy' => 'base',
                    'terms' => $base->term_id
                ),
                array(
                    'taxonomy' => 'certification',
                    'operator' => 'EXISTS'
                )
            )
        ));

        foreach ($products as $product)
        {
            add_post_meta($rv->ID, 'affected_id', $product->ID);
        }
    }

    $page++;
    wp_redirect('/about-ipema/?fix_new_rvs=1&o=' . $page);
    die();
});*/

function ipema_valid_certs($productID)
{
    $prodType = get_the_terms($productID, 'product-type');
    if ($prodType == false)
    {
        return array();
    }
    $prodType = $prodType[0]->slug;

    $certs = ipema_active_certs();
    foreach ($certs as $key => $cert)
    {
        if ( ! ipema_certification_product_type($cert->term_id, $prodType))
        {
            unset($certs[$key]);
        }
    }

    $material = get_the_terms($productID, $material);
    if ($material != false)
    {
        $acceptable = get_term_meta(
            $material[0]->term_id,
            'additional-certifications'
        );
        foreach ($certs as $key => $cert)
        {
            if (get_term_meta($cert->term_id, 'restricted', true))
            {
                if (! in_array($cert->slug, $acceptable))
                {
                    unset($certs[$key]);
                }
            }
        }
    }

    return $certs;
}

function ipema_can_change_certs($action, $type, $product=NULL)
{
    if ( ! is_numeric($product))
    {
        $product = get_the_ID();
    }

    if ($action == 'add')
    {
        $certs = ipema_valid_certs($product);

        if (count($certs) == 0)
        {
            return false;
        }

        if ($type == 'family')
        {
            $base = ipema_get_product_base($product);
            if ($base == false)
            {
                return false;
            }

            $obsolete = get_posts(array(
                'post_type' => 'product',
                'post_status' => 'any',
                'tax_query' => array(
                    array(
                        'taxonomy' => 'base',
                        'terms' => $base->term_id
                    ),
                    array(
                        'taxonomy' => 'certification',
                        'operation' => 'NOT EXISTS'
                    )
                ),
                'posts_per_page' => 1,
                'fields' => 'ids'
            ));

            if (count($obsolete) > 0)
            {
                return true;
            }

            $affected = get_posts(array(
                'post_type' => 'product',
                'post_status' => 'any',
                'tax_query' => array(array(
                    'taxonomy' => 'base',
                    'terms' => $base->term_id
                )),
                'nopaging' => true,
                'fields' => 'ids'
            ));
        }
        else
        {
            $affected = array($product);
        }

        foreach ($affected as $product)
        {
            $prodCerts = get_the_terms($product, 'certification');
            if ($prodCerts == false)
            {
                return true;
            }
            if (count($prodCerts) < count($certs))
            {
                return true;
            }

            foreach ($certs as $cert)
            {
                foreach ($prodCerts as $prodCert)
                {
                    if ($prodCert->term_id == $cert->term_id)
                    {
                        continue 2;
                    }
                }

                return true;
            }
        }

        return false;
    }
    elseif ($action == 'remove')
    {
        if ($type == 'single')
        {
            $certs = get_the_terms($product, 'certification');
            return $certs !== false;
        }
        elseif ($type == 'family')
        {
            $base = get_the_terms($product, 'base');
            if ($base === false)
            {
                return false;
            }

            $siblings = get_posts(array(
                'post_type' => 'product',
                'tax_query' => array(
                    array(
                        'taxonomy' => 'base',
                        'terms' => $base[0]->term_id
                    ),
                    array(
                        'taxonomy' => 'certification',
                        'operator' => 'EXISTS'
                    )
                ),
                'fields' => 'ids',
                'posts_per_page' => 1
            ));

            return count($siblings) > 0;
        }
    }

    return false;
}

add_action('template_redirect', function() {
    if ( ! is_page('undo'))
    {
        return;
    }

    $user = wp_get_current_user();
    $affected = get_post_meta($_GET['request'], 'affected_id');
    foreach ($affected as $productID)
    {
        $companyID = get_post_meta(
            $productID,
            '_wpcf_belongs_company_id',
            true
        );

        if ($companyID != $user->company_id)
        {
            return;
        }
    }

    $certs = array();
    $rvCerts = get_the_terms($_GET['request'], 'certification');
    foreach ($rvCerts as $cert)
    {
        $certs[] = $cert->term_id;
    }

    $public_id = get_option('rv_autoincrement');
    $public_id++;
    update_option('rv_autoincrement', $public_id);

    $modelID = get_post_meta(
        $_GET['request'],
        '_wpcf_belongs_product_id',
        true
    );
    $model =  ipema_model_number(array('product' => $modelID));
    $rv_id = wp_insert_post(array(
        'post_type' => 'rv',
        'post_title' => $model,
        'post_status' => 'publish',
        'post_parent' => $_GET['request'],
        'post_content' => '',
        'meta_input' => array(
            '_wpcf_belongs_product_id' => $modelID,
            'public_id' => $public_id,
            'status' => 'processed',
            'new' => true
        )
    ));

    wp_set_object_terms($rv_id, $certs, 'certification');
    wp_set_object_terms($rv_id, 'restore', 'request');

    foreach ($affected as $affected_id)
    {
        add_post_meta($rv_id, 'affected_id', $affected_id);
    }

    wp_redirect('../review/?request=' . $_GET['request']);
    die();
});

add_filter('relevanssi_indexing_data', function($data, $post) {
    if ($post->post_type != 'product')
    {
        return $data;
    }
    $base = ipema_get_product_base($post->ID);
    if ($base === false)
    {
        return $data;
    }

    if ($base->name == '~Unnamed~')
    {
        return $data;
    }

    $model = ipema_model_number(array('product' => $post->ID));
    if (is_numeric($model))
    {
        $model = " $model"; // Relevanssi convention for numbers
    }

    $data[$model] = array('customfield' => 1);

    return $data;
}, 10, 2);

function ipema_move_family($action) {
    $product = get_post();
    $user = wp_get_current_user();
    if ($action == 'active')
    {
        return get_user_meta($user->ID, 'family', true);
    }
    elseif ($action == 'moveable')
    {
        $target = get_user_meta($user->ID, 'family', true);

        $productCompany = get_post_meta(
            $target,
            '_wpcf_belongs_company_id',
            true
        );
        if ($productCompany != $user->company_id)
        {
            return false;
        }

        $type = get_the_terms($product->ID, 'product-type');
        $targetType = get_the_terms($target, 'product-type');

        if ($type[0]->term_id != $targetType[0]->term_id)
        {
            return false;
        }

        $base = ipema_get_product_base($product->ID);
        if ($base)
        {
            $targetBase = ipema_get_product_base($target);

            return $base->term_id != $targetBase->term_id;
        }

        return true;
    }
    elseif ($action == 'target_family')
    {
        return $product->ID == get_user_meta($user->ID, 'family', true);
    }
}

function ipema_create_family($modelID)
{
    $user = wp_get_current_user();
    $slug = $user->company_id . '-' . ipema_generate_code(4);
    while (get_term_by('slug', $slug, 'base') !== false)
    {
        $slug = $user->company_id . '-' . ipema_generate_code(4);
    }

    $term = wp_insert_term('~Unnamed~', 'base', array(
        'slug' => $slug,
        'description' => ''
    ));

    $product_line = get_the_terms($modelID, 'product-line');

    if ($product_line != false)
    {
        add_term_meta(
            $term['term_id'],
            'product_line',
            $product_line[0]->term_id
        );
    }
    add_term_meta($term['term_id'], 'name', '');
    add_term_meta($term['term_id'], 'company_id', $user->company_id);

    $certs = get_the_terms($modelID, 'certification');
    foreach ($certs as $cert)
    {
        if (get_term_meta($cert->term_id, 'canadian'))
        {
            add_term_meta($term['term_id'], 'canadian', true);
            add_term_meta($term['term_id'], 'french-description', '');
            break;
        }
    }

    $material = get_the_terms($modelID, 'material');
    if ($material != false)
    {
        add_term_meta(
            $term['term_id'],
            'material',
            $material[0]->term_id
        );
    }

    $thkToHt = get_post_meta(
        $modelID,
        'thickness_to_height',
        true
    );
    if ($thkToHt)
    {
        add_term_meta(
            $term['term_id'],
            'thickness_to_height',
            $thkToHt
        );
    }

    wp_set_object_terms($modelID, $term['term_id'], 'base');

    return get_term($term['term_id']);
}

function ipema_duplicate_model_number($modelID, $family)
{
    $oldPrefix = '~Unnamed~';
    $oldFamily = ipema_get_product_base($modelID);
    if ($oldFamily != false)
    {
        $oldPrefix = $oldFamily->name;
    }
    if ($oldPrefix != $family->name)
    {
        $newModel = '';
        if ($family->name != '~Unnamed~')
        {
            $newModel = $family->name;
        }
        $newModel .= get_post_meta($product->ID, 'model', true);

        $result = ipema_force_unique_model(
            array('is_valid' => true),
            $newModel,
            NULL,
            NULL
        );

        return ! $result['is_valid'];
    }

    return false;
}

add_action('template_redirect', function() {
    if ( ! is_page('family'))
    {
        return;
    }

    $user = wp_get_current_user();
    if ($_SERVER['REQUEST_METHOD'] == 'POST')
    {
        return;
    }

    if (array_key_exists('action', $_GET))
    {
        if ($_GET['action'] == 'move')
        {
            $error = NULL;

            $page = $GLOBALS['post'];
            $product = get_post($_GET['model']);
            $GLOBALS['post'] = $product;
            if ( ! ipema_move_family('moveable'))
            {
                $error = 'Model not eligible to move to this family';
            }
            $GLOBALS['post'] = $page;

            $target = get_user_meta($user->ID, 'family', true);
            $family = ipema_get_product_base($target);

            $material = get_the_terms($product->ID, 'material');
            if ($material)
            {
                $targetMaterial = get_term_meta(
                    $family->term_id,
                    'material',
                    true
                );
                if ($targetMaterial && $material[0]->term_id != $targetMaterial)
                {
                    $error = 'Cannot move ' . $material[0]->name . ' to family of '
                        . get_term($targetMaterial)->name;
                }
                elseif ( ! $targetMaterial)
                {
                    $siblings = get_posts(array(
                        'post_type' => 'product',
                        'post_status' => 'any',
                        'tax_query' => array(
                            array(
                                'taxonomy' => 'base',
                                'terms' => $family->term_id
                            ),
                            array(
                                'taxonomy' => 'material',
                                'operator' => 'EXISTS'
                            )
                        ),
                        'nopaging' => true,
                        'fields' => 'ids'
                    ));

                    foreach ($siblings as $siblingID)
                    {
                        $siblingMaterial = get_the_terms($siblingID, 'material');
                        if ($siblingMaterial->term_id != $material[0]->term_id)
                        {
                            $error = 'Cannot move ' . $material[0]->name . ' to '
                                . 'family with ' . $siblingMaterial->name;
                        }
                    }
                }
            }

            $restrictions = array();
            if ( ! $error)
            {
                $restrictedCert = false;
                $seen = array();
                $certs = get_the_terms($product->ID, 'certification');
                if ($certs == false)
                {
                    $certs = array();
                }
                foreach ($certs as $cert)
                {
                    if (get_term_meta($cert->term_id, 'restricted', true) == 1)
                    {
                        $materials = get_terms(array(
                            'taxonomy' => 'material',
                            'meta_query' => array(array(
                                'key' => 'additional-certification',
                                'value' => $cert->slug
                            ))
                        ));

                        foreach ($materials as $material)
                        {
                            $restrictions[] = $material->term_id;
                        }
                        $restrictedCert = $cert->name;
                    }

                    $seen[] = $cert->term_id;
                }
            }
            if (count($restrictions) > 0)
            {
                $targetMaterial = get_term_meta(
                    $family->term_id,
                    'material',
                    true
                );
                if ($targetMaterial)
                {
                    if (! in_array($targetMaterial, $restrictions))
                    {
                        $error = 'Cannot move model with ' . $restrictedCert
                            . ' certification to family of '
                            . get_term($targetMaterial)->name;
                    }
                }
                else
                {
                    $siblings = get_posts(array(
                        'post_type' => 'product',
                        'post_status' => 'any',
                        'tax_query' => array(
                            array(
                                'taxonomy' => 'base',
                                'terms' => $family->term_id
                            ),
                            array(
                                'taxonomy' => 'certification',
                                'operator' => 'EXISTS'
                            )
                        ),
                        'nopaging' => true,
                        'fields' => 'ids'
                    ));

                    foreach ($siblings as $productID)
                    {
                        $certs = get_the_terms($productID, 'certification');

                        foreach ($certs as $cert)
                        {
                            if (in_array($cert->term_id, $seen))
                            {
                                continue;
                            }

                            $restricted = get_term_meta(
                                $cert->term_id,
                                'restricted',
                                true
                            );
                            if ($restricted)
                            {
                                $materials = get_terms(array(
                                    'taxonomy' => 'material',
                                    'meta_query' => array(array(
                                        'key' => 'additional-certification',
                                        'value' => $cert->slug
                                    ))
                                ));

                                $possible = array();
                                foreach ($materials as $material)
                                {
                                    $possible[] = $material->term_id;
                                }

                                $possible = array_intersect(
                                    $possible,
                                    $restrictions
                                );

                                if (count($possible) == 0)
                                {
                                    $error = 'Cannot move model with '
                                        . $restrictedCert . 'certification to '
                                        . 'family that contains models with '
                                        . $cert->name . ' certification';

                                    break 2;
                                }
                            }

                            $seen[] = $cert->term_id;
                        }
                    }
                }
            }

            // Check for duplicate model numbers
            if (ipema_duplicate_model_number($product->ID, $family))
            {
                $newModel = '';
                if ($family->name != '~Unnamed~')
                {
                    $newModel = $family->name;
                }
                $newModel .= get_post_meta($product->ID, 'model', true);

                $error = 'Moving this model would result in a duplicated '
                    . 'model number ' . $newModel;
            }

            $thkToHt = get_post_meta(
                $product->ID,
                'thickness_to_height',
                true
            );
            if ( ! $error && $thkToHt)
            {
                $targetRatio = get_term_meta(
                    $family->term_id,
                    'thickness_to_height',
                    true
                );

                if ($targetRatio && $targetRatio != $thkToHt)
                {
                    $error = 'Thickness to Height Ratio differs from family';
                }
                elseif ( ! $targetRatio)
                {
                    $siblings = get_posts(array(
                        'post_type' => 'product',
                        'post_status' => 'any',
                        'tax_query' => array(array(
                            'taxonomy' => 'base',
                            'terms' => $family->term_id
                        )),
                        'meta_query' => array(array(
                            'meta_key' => 'thickness_to_height',
                            'compare' => 'EXISTS'
                        )),
                        'nopaging' => true,
                        'fields' => 'ids'
                    ));

                    foreach ($siblings as $siblingID)
                    {
                        $siblingRatio = get_post_meta(
                            $siblingID,
                            'thickness_to_height',
                            true
                        );
                        if ($thkToHt != $siblingRatio)
                        {
                            $error = 'Thickness to Height Ratio does not match '
                                . 'others in family';
                        }
                    }
                }
            }

            if ($error)
            {
                $content = $GLOBALS['post']->post_content;
                $content = "<div class=\"alert\">$error</div>\n\n$content";
                $GLOBALS['post']->post_content = $content;
            }
            else
            {
                add_user_meta($user->ID, 'move', $_GET['model']);
            }
        }
        elseif ($_GET['action'] == 'remove')
        {
            delete_user_meta($user->ID, 'move', $_GET['model']);
        }
        elseif ($_GET['action'] == 'cancel')
        {
            $original = get_user_meta($user->ID, 'family', true);
            if ( ! $original and array_key_exists('model', $_GET))
            {
                $original = $_GET['model'];
            }

            delete_user_meta($user->ID, 'family');
            delete_user_meta($user->ID, 'move');

            if ($original)
            {
                wp_redirect('../?model=' . $original);
            }
            else
            {
                wp_redirect('../../');
            }

            die();
        }
        elseif ($_GET['action'] == 'search')
        {
            update_user_meta($user->ID, 'family', $_GET['model']);
            $name = ipema_product_display_name($_GET['model']);
            $type = get_the_terms($_GET['model'], 'product-type')[0];

            $msg = urlencode(
                'Find the model you want to move and click Manage, then click '
                . "Move to Family with $name"
            );

            wp_redirect("/members/products/models?t={$type->slug}&msg=$msg");
            die();
        }
    }
});

function ipema_models_to_move($form)
{
    $user = wp_get_current_user();
    $modelID = get_user_meta($user->ID, 'family', true);
    if ( ! $modelID && array_key_exists('model', $_GET))
    {
        $modelID = $_GET['model'];
    }

    $model = get_post($modelID);
    $family = get_the_terms($model->ID, 'base');
    if ($family == false)
    {
        $family = ipema_create_family($model->ID);
    }
    else
    {
        $family = $family[0];
    }

    $type = get_the_terms($model->ID, 'product-type')[0];

    $selected = get_user_meta($user->ID, 'move');

    $meta = array(
        array(
            'key' => '_wpcf_belongs_company_id',
            'value' => $model->_wpcf_belongs_company_id
        )
    );
    $taxonomies = array(
        array(
            'taxonomy' => 'product-type',
            'terms' => $type->term_id
        ),
        array(
            'taxonomy' => 'base',
            'terms' => $family->term_id,
            'operator' => 'NOT IN'
        )
    );

    if ($type->slug == 'surfacing')
    {
        $material = get_the_terms($model->ID, 'material');
        if ($material != false)
        {
            $taxonomies[] = array(
                'taxonomy' => 'material',
                'terms' => $material[0]->term_id
            );
        }

        $thkToHt = get_term_meta($family->term_id, 'thickness_to_height', true);

        if ( ! $thkToHt)
        {
            $thkToHt = get_post_meta($model->ID, 'thickness_to_height', true);
        }

        if ($thkToHt)
        {
            $meta[] = array(
                'key' => 'thickness_to_height',
                'value' => $thkToHt
            );
        }
    }

    $choices = get_posts(array(
        'post_type' => 'product',
        'post_status' => 'any',
        'meta_query' => $meta,
        'tax_query' => $taxonomies,
        'meta_key' => 'model',
        'orderby' => 'meta_key',
        'nopaging' => true
    ));
    $products = array();
    foreach ($choices as $choice)
    {
        $products[] = array(
            'value' => $choice->ID,
            'text' => ipema_product_display_name($choice->ID),
            'isSelected' => in_array($choice->ID, $selected)
        );
    }
    foreach ($form['fields'] as $field)
    {
        if ($field->id == 1)
        {
            $field->choices = $products;
        }
        elseif ($field->id == 2)
        {
            $name = ipema_product_display_name($model->ID);
            $html = <<<EOT
<p>You can select the models that you want to move to the family of
<em>$name</em> below by model number or commercial name of product. If you want
to search by other factors or need to differentiate between legacy models with
the same model number, you can
<a href="?action=search&model={$model->ID}">search for it in your product
list</a>. If you do not want to move any models at this time, you can
<a href="?action=cancel&model={$model->ID}">cancel</a> and return to your
model.</p>
EOT;
            $field->content = $html;
        }
    }

    return $form;
}
add_filter('gform_pre_render_52', 'ipema_models_to_move');
add_filter('gform_pre_validation_52', 'ipema_models_to_move');
add_filter('gform_pre_submission_filter_52', 'ipema_models_to_move');

add_filter('gform_field_validation_52_1', function($results, $models) {
    if ( ! $results['is_valid'])
    {
        return $results;
    }

    $user = wp_get_current_user();
    $modelID = get_user_meta($user->ID, 'family', true);
    if ( ! $modelID && array_key_exists('model', $_GET))
    {
        $modelID = $_GET['model'];
    }

    $family = get_the_terms($modelID, 'base')[0];

    $reasons = array();
    foreach ($models as $modelID)
    {
        if (ipema_duplicate_model_number($modelID, $family))
        {
            $reasons[ipema_product_display_name($modelID)] = 'creates a duplicated model number';
            continue;
        }
    }

    if (count($reasons) == 0)
    {
        return $results;
    }

    $results['is_valid'] = false;

    if (count($reasons) == 1)
    {
        foreach ($reasons as $model => $reason)
        {
            $msg = "$model cannot be moved to this family because it $reason";
            break;
        }
    }
    else
    {
        $msg = 'The following models cannot be moved to this family:';
        $msg .= '<ul>';
        foreach ($reasons as $model => $reason)
        {
            $msg .= "<li>$model: $reason</li>";
        }
        $msg .= '</ul>';
    }

    foreach ($results['form']['fields'] as &$field)
    {
        if ($field->id == 1)
        {
            $field->failed_validation = true;
            $field->validation_message = $msg;
        }
    }
}, 10, 2);

add_action('gform_after_submission_52', function($entry, $form) {
    $user = wp_get_current_user();
    $original = get_user_meta($user->ID, 'family', true);
    if ( ! $original)
    {
        update_user_meta($user->ID, 'family', $_GET['model']);
    }

    delete_user_meta($user->ID, 'move');
    $models = json_decode($entry[1]);
    foreach ($models as $modelID)
    {
        add_user_meta($user->ID, 'move', $modelID);
    }
}, 10, 2);

function ipema_move_family_form($form)
{
    $user = wp_get_current_user();

    $targetID = get_user_meta($user->ID, 'family', true);
    foreach ($form['fields'] as &$field)
    {
        if ($field->id == 1)
        {
            $content = '<p>The following models will be moved to the family of';
            $content .= ' <em>' . ipema_product_display_name($targetID);
            $content .= '</em></p>';
            $content .= '<p><strong>Models to Move</strong></p><ul>';

            $choices = array();
            $models = get_user_meta($user->ID, 'move');
            foreach ($models as $modelID)
            {
                $choices[] = array(
                    'value' => $modelID,
                    'text' => ipema_product_display_name($modelID)
                );
            }

            usort($choices, 'ipema_sort_models');

            foreach ($choices as $choice)
            {
                $content .= '<li><a href="../../?model=' . $choice['value']
                    . '" target="_blank">' . $choice['text'] . '</a></li>';
            }

            $content .= '</ul>';

            $field->content = $content;
        }
        elseif ($field->id == 2)
        {
            $type = get_the_terms($targetID, 'product-type');
            $type = $type[0]->slug;
            preg_match_all(
                '/<p class="(\w+)">.*?<\/p>/',
                $field->description,
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $match)
            {
                if ($match[1] != $type)
                {
                    $field->description = str_replace(
                        $match[0],
                        '',
                        $field->description
                    );
                }
            }
        }
    }

    return $form;
}
add_filter('gform_pre_render_41', 'ipema_move_family_form');
add_filter('gform_pre_validation_41', 'ipema_move_family_form');
add_filter('gform_pre_submission_filter_41', 'ipema_move_family_form');

add_action('gform_after_submission_41', function($entry, $form) {
    $documents = json_decode(rgar($entry, 2));
    if ( ! is_array($documents))
    {
        $documents = array();
    }
    foreach ($documents as &$document)
    {
        $document = preg_replace('#^https?://[^/]+#i', '', $document);
    }

    $user = wp_get_current_user();
    $affected = get_user_meta($user->ID, 'move');
    $target = get_user_meta($user->ID, 'family', true);

    if (count($affected) == 0 || ! $target)
    {
        delete_user_meta($user->ID, 'family');
        delete_user_meta($user->ID, 'move');

        return;
    }

    $status = 'publish';
    foreach ($affected as $productID)
    {
        $certs = get_the_terms($productID, 'certification');
        if ($certs != false)
        {
            $status = 'draft';
            break;
        }
    }

    $public_id = get_option('rv_autoincrement');
    $public_id++;
    update_option('rv_autoincrement', $public_id);

    $rv = array(
        'post_type' => 'rv',
        'post_status' => $status,
        'post_title' => ipema_model_number(array('product' => $target)),
        'post_content' => '',
        'post_excerpt' => rgar($entry, 3),
        'meta_input' => array(
            '_wpcf_belongs_product_id' => $target,
            'public_id' => $public_id
        )
    );

    if ($status == 'publish')
    {
        $rv['meta_input']['status'] = 'processed';
    }
    $rvID = wp_insert_post($rv);

    foreach ($affected as $productID)
    {
        add_post_meta($rvID, 'affected_id', $productID);
    }
    foreach ($documents as $document)
    {
        add_post_meta($rvID, 'documentation', $document);
    }

    $family = ipema_get_product_base($target);
    wp_set_object_terms($rvID, 'family', 'request');
    wp_set_object_terms($rvID, $family->term_id, 'base');

    delete_user_meta($user->ID, 'family');
    delete_user_meta($user->ID, 'move');

    if ($status != 'publish')
    {
        ipema_rv_email($rvID);
    }
    else
    {
        add_post_meta($rvID, 'new', true);
    }

}, 10, 2);

add_action('template_redirect', function() {
    if ( ! is_page('leave'))
    {
        return;
    }

    $base = ipema_get_product_base($_GET['model']);
    if ($base === false)
    {
        wp_redirect('../?model=' . $_GET['model']);
        die();
    }

    if ($base->name != '~Unnamed~')
    {
        $result = array('is_valid' => true);
        $result = ipema_force_unique_model(
            $result,
            get_post_meta($_GET['model'], 'model', true),
            NULL,
            NULL
        );

        if ( ! $result['is_valid'])
        {
            $GLOBALS['post']->post_content = '<div class="alert">Removing this '
                . 'model from its family would create a duplicate model number.'
                . '</div>';
            return;
        }
    }

    $shouldReview = false;
    if ($base->name != '~Unnamed~')
    {
        $shouldReview = true;
    }
    if (get_term_meta($base->term_id, 'name', true))
    {
        $shouldReview = true;
    }
    if ($base->description)
    {
        $shouldReview = true;
    }
    if (get_term_meta($base->term_id, 'french-description', true))
    {
        $shouldReview = true;
    }

    $certs = get_the_terms($_GET['model'], 'certification');
    if ($certs == false)
    {
        $shouldReview = false;
    }

    $status = 'publish';
    if ($shouldReview)
    {
        $status = 'draft';
    }

    $public_id = get_option('rv_autoincrement');
    $public_id++;
    update_option('rv_autoincrement', $public_id);

    $rv = array(
        'post_type' => 'rv',
        'post_status' => $status,
        'post_title' => ipema_model_number(array('product' => $_GET['model'])),
        'post_content' => '',
        'post_excerpt' => '',
        'meta_input' => array(
            '_wpcf_belongs_product_id' => $_GET['model'],
            'public_id' => $public_id,
            'affected_id' => $_GET['model']
        )
    );

    if ($status == 'publish')
    {
        $rv['meta_input']['status'] = 'processed';
    }
    $rvID = wp_insert_post($rv);

    wp_set_object_terms($rvID, 'leave', 'request');

    if ($status != 'publish')
    {
        ipema_rv_email($rvID);
    }
    else
    {
        add_post_meta($rvID, 'new', true);
    }

    if ( ! $shouldReview)
    {
        wp_redirect('../?model=' . $_GET['model']);
        die();
    }

    $GLOBALS['post']->post_content = str_replace(
        '{model}',
        $_GET['model'],
        $GLOBALS['post']->post_content
    );
});

function ipema_new_product_change($productID, $author=NULL)
{
    global $wpdb;

    if ( ! $author)
    {
        $author = wp_get_current_user();
        $author = $author->id;
    }

    $sql = "SELECT
              post_content,
              post_title,
              post_excerpt,
              post_parent
            FROM
              {$wpdb->posts}
            WHERE
              ID = %d";

    $fields = $wpdb->get_row($wpdb->prepare($sql, $productID));

    $changeID = wp_insert_post(array(
        'post_type' => 'product-change',
        'post_status' => 'draft',
        'post_author' => $author,
        'post_title' => $fields->post_title,
        'post_content' => $fields->post_content,
        'post_excerpt' => $fields->post_excerpt,
        'post_parent' => $fields->post_parent,
    ));

    $sql = "INSERT INTO
              {$wpdb->postmeta}
              (
                post_id,
                meta_key,
                meta_value
              )
            SELECT
              %d,
              meta_key,
              meta_value
            FROM
              {$wpdb->postmeta}
            WHERE
              post_id = %d";

    $wpdb->query($wpdb->prepare($sql, $changeID, $productID));

    update_post_meta($changeID, '_wpcf_belongs_product_id', $productID);

    $sql = "INSERT INTO
              {$wpdb->term_relationships}
              (
                object_id,
                term_taxonomy_id,
                term_order
              )
            SELECT
              %d,
              term_taxonomy_id,
              term_order
            FROM
              {$wpdb->term_relationships}
            WHERE
              object_id = %d";

    $wpdb->query($wpdb->prepare($sql, $changeID, $productID));

    return $changeID;
}

function ipema_complete_product_change($productChangeID)
{
    global $wpdb;

    $originalID = get_post_meta(
        $productChangeID,
        '_wpcf_belongs_product_id',
        true
    );

    $change = get_post($productChangeID);
    wp_update_post(array(
        'ID' => $productChangeID,
        'post_status' => 'publish',
        'post_type' => 'product-change-cmt',
        'post_date' => $change->post_date,
        'edit_date' => 'no'
    ));
    $change = get_post($productChangeID);

    $sql = "SELECT
              meta_id
            FROM
              {$wpdb->postmeta}
            WHERE
              post_id = %d";

    $originalMeta = $wpdb->get_col($wpdb->prepare($sql, $originalID));
    $changeMeta = $wpdb->get_col($wpdb->prepare($sql, $productChangeID));

    $sql = "UPDATE
              {$wpdb->postmeta}
            SET
              post_id = %d
            WHERE
              meta_id IN (";

    $wpdb->query($wpdb->prepare(
        $sql . implode(', ', $originalMeta) . ')',
        $productChangeID
    ));
    $wpdb->query($wpdb->prepare(
        $sql . implode(', ', $changeMeta) . ')',
        $originalID
    ));

    $sql = "SELECT
              term_taxonomy_id
            FROM
              {$wpdb->term_relationships}
            WHERE
              object_id = %d";

    $originalTerms = $wpdb->get_col($wpdb->prepare($sql, $originalID));
    $changeTerms = $wpdb->get_col($wpdb->prepare($sql, $productChangeID));

    $originalOnly = array_diff($originalTerms, $changeTerms);
    $changeOnly = array_diff($changeTerms, $originalTerms);

    $sql = "UPDATE
              {$wpdb->term_relationships}
            SET
              object_id = %d
            WHERE
              term_taxonomy_id = %d
            AND
              object_id = %d";

    foreach ($originalOnly as $term_id)
    {
        $wpdb->query($wpdb->prepare(
            $sql,
            $productChangeID,
            $term_id,
            $originalID
        ));
    }
    foreach ($changeOnly as $term_id)
    {
        $wpdb->query($wpdb->prepare(
            $sql,
            $originalID,
            $term_id,
            $productChangeID
        ));
    }

    $original = get_post($originalID);

    $sql = "UPDATE
              {$wpdb->posts}
            SET
              post_type = 'product-change',
              post_date = %s,
              post_date_gmt = %s,
              post_modified = %s,
              post_modified_gmt = %s
            WHERE
              ID = %d";

    $wpdb->query($wpdb->prepare(
        $sql,
        $change->post_date,
        $change->post_date_gmt,
        $change->post_modified,
        $change->post_modified_gmt,
        $originalID
    ));

    $sql = "UPDATE
              {$wpdb->posts}
            SET
              post_type = 'product',
              post_date = %s,
              post_date_gmt = %s
            WHERE
              ID = %d
            AND
              post_type = 'product-change-cmt'";

    $wpdb->query($wpdb->prepare(
        $sql,
        $original->post_date,
        $original->post_date_gmt,
        $productChangeID
    ));

    $wpdb->query('START TRANSACTION');
    $sql = "UPDATE
              {$wpdb->posts}
            SET
              ID = %d
            WHERE
              ID = %d";

    $wpdb->query($wpdb->prepare(
        $sql,
        0,
        $originalID
    ));
    $wpdb->query($wpdb->prepare(
        $sql,
        $originalID,
        $productChangeID
    ));
    $wpdb->query($wpdb->prepare(
        $sql,
        $productChangeID,
        0
    ));
    $wpdb->query('COMMIT');
    relevanssi_insert_edit($originalID);

    // Fix feature change RVs
    $sql = "SELECT
              post_id
            FROM
              {$wpdb->postmeta}
            WHERE
              meta_key = 'product'
            AND
              meta_value = %d";

    $affected = $wpdb->get_col($wpdb->prepare($sql, $originalID));

    if (count($affected) > 0)
    {
        $sql = "UPDATE
                  {$wpdb->postmeta}
                SET
                  meta_value = %d
                WHERE
                  meta_key = '_wpcf_belongs_product_id'
                AND
                  post_id IN (" . implode(' ,', $affected) . ")";

        $wpdb->query($wpdb->prepare($sql, $productChangeID));
    }

    // Fix certificates
    $sql = "UPDATE
              {$wpdb->postmeta}
            SET
              meta_value = %d
            WHERE
              meta_key = 'product'
            AND
              meta_value = %d";

    $wpdb->query($wpdb->prepare($sql, $productChangeID, $originalID));

    add_post_meta($productChangeID, '_wpcf_belongs_product_id', $originalID);
    delete_post_meta($originalID, '_wpcf_belongs_product_id');
}

function ipema_new_company($companyID)
{
    $company = get_post($companyID);
    return strtotime($company->post_date) > strtotime('-1 month');
}

function ipema_is_new($productID)
{
    $certs = ipema_active_certs();
    foreach ($certs as $cert)
    {
        if (get_post_meta($productID, $cert->slug, true))
        {
            return false;
        }
    }

    return true;
}

function ipema_active_product($product)
{
    if ($product->post_status == 'draft')
    {
        return false;
    }

    $certs = get_the_terms($product->ID, 'certification');
    if ($certs == false)
    {
        return false;
    }

    foreach ($certs as $cert)
    {
        $exp = get_post_meta($product->ID, $cert->slug, true);
        if ($exp && strtotime($exp) > time())
        {
            return true;
        }
    }

    return false;
}

function ipema_edit_model_form($form)
{
    $product = get_post($_GET['model']);
    if (strpos($product->post_type, 'product') !== 0)
    {
        return $form;
    }

    $companyID = get_post_meta($product->ID, '_wpcf_belongs_company_id', true);
    $user = wp_get_current_user();
    if ($user->company_id != $companyID)
    {
        return $form;
    }

    $isActiveProduct = ipema_active_product($product);

    if ($product->post_type == 'product-change')
    {
        $form['button']['text'] = 'Resubmit Request';
    }
    elseif (ipema_is_new($product->ID))
    {
        $form['button']['text'] = 'Resubmit Request';
    }
    elseif ( ! $isActiveProduct)
    {
        $form['button']['text'] = 'Update Model';
    }

    $productType = get_the_terms($product->ID, 'product-type')[0]->slug;

    foreach ($form['fields'] as $field)
    {
        if ($field->id == 1)
        {
            $field->defaultValue = $product->post_title;
        }
        elseif ($field->id == 2)
        {
            $field->defaultValue = $product->model;
        }
        elseif ($field->id == 3)
        {
            $field->defaultValue = $product->post_content;
        }
        elseif ($field->id == 4)
        {
            $field->defaultValue = get_post_meta(
                $product->ID,
                'french-description',
                true
            );

            $certs = get_the_terms($product->ID, 'certification');
            if ($certs != false)
            {
                foreach ($certs as $cert)
                {
                    if (get_term_meta($cert->term_id, 'canadian', true))
                    {
                        $field->visibility = 'visible';
                        break;
                    }
                }
            }
            elseif ($field->defaultValue)
            {
                $field->visibility = 'visible';
            }
        }
        elseif ($field->id == 5)
        {
            $featured = get_the_post_thumbnail($product);
            if ($featured)
            {
                $featured = "<div id=\"current-photo\">$featured";
                $featured .= '<br><a href="#">Erase Photograph</a></div>';
                $featured .= <<<EOF
<script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#current-photo a').click(function(e) {
            e.preventDefault();

            $('#current-photo').slideUp();
            $('#input_42_7').val(1);
        });
    });
</script>
EOF;
                $field->content = $featured;
            }
        }
        elseif ($field->id == 8)
        {
            $productLine = get_the_terms($product->ID, 'product-line');
            if ($productLine != false)
            {
                $field->defaultValue = $productLine[0]->term_id;
            }
        }
        elseif ($field->id == 9 || $field->id == 10)
        {
            if ($productType != 'surfacing')
            {
                $field->adminOnly = true;
            }

            if ($field->id == 9)
            {
                $materials = get_the_terms($product->ID, 'material');
                if ($materials != false)
                {
                    $field->defaultValue = $materials[0]->term_id;
                }
            }
            elseif ($field->id == 10)
            {
                $thkToHt = get_post_meta(
                    $product->ID,
                    'thickness_to_height',
                    true
                );

                list($thickness, $height) = explode(':', $thkToHt);
                $field->defaultValue = $thickness;

                if ( ! array_key_exists('input_10b', $_POST))
                {
                    $_POST['input_10b'] = $height;
                }
            }
        }
        elseif ($field->id == 11 || $field->id == 12)
        {
            if ( ! $isActiveProduct)
            {
                $field->visibility = 'hidden';
            }
        }
        elseif ($field->id == 14)
        {
            $field->defaultValue = $product->french_name;

            $certs = get_the_terms($product->ID, 'certification');
            if ($certs != false)
            {
                foreach ($certs as $cert)
                {
                    if (get_term_meta($cert->term_id, 'canadian', true))
                    {
                        $field->visibility = 'visible';
                        break;
                    }
                }
            }
            elseif ($field->defaultValue)
            {
                $field->visibility = 'visible';
            }
        }
    }

    return $form;
}
add_filter('gform_pre_render_42', 'ipema_edit_model_form', 11);
add_filter('gform_pre_validation_42', 'ipema_edit_model_form', 11);
add_filter('gform_pre_submission_filter_42', 'ipema_edit_model_form', 11);

add_filter('gform_field_content_42', function($html, $field) {
    $base = ipema_get_product_base($_GET['model']);
    if ($base === false)
    {
        return $html;
    }

    if ($field->id == 1)
    {
        $name = get_term_meta($base->term_id, 'name', true);
        $html = str_replace('<input', "$name <input", $html);
    }
    elseif ($field->id == 2)
    {
        $term = get_term($base->term_id, 'base');
        if ($term->name != '~Unnamed~')
        {
            $html = str_replace('<input', $term->name . '<input', $html);
        }
    }

    return $html;
}, 10, 2);

add_filter('gform_field_validation_42_2', function($result, $value) {
    if ( ! $result['is_valid'])
    {
        return $result;
    }

    $product = get_post($_GET['model']);
    if ($product->model == $value)
    {
        return $result;
    }

    $base = ipema_get_product_base($product->ID);
    if ($base && $base->name != '~Unnamed~')
    {
        $value = $base->name . $value;
    }

    return ipema_force_unique_model($result, $value, NULL, NULL);
}, 10, 2);

add_filter('gform_field_validation_42_9', function($result, $materialID) {
    if ( ! $result['is_valid'])
    {
        return $result;
    }

    $product = get_post($_GET['model']);

    $currentMaterial = get_the_terms($product->id, 'material');
    if ($currentMaterial != false)
    {
        if ($currentMaterial[0]->term_id == $materialID)
        {
            return $result;
        }
    }

    $currentCerts = get_the_terms($product->id, 'certification');
    if ($currentCerts == false)
    {
        return $result;
    }

    $allowedCerts = get_term_meta(
        $materialID,
        'additional-certifications'
    );

    foreach ($currentCerts as $cert)
    {
        if (get_term_meta($cert->term_id, 'restricted', true))
        {
            if ( ! in_array($cert->slug, $allowedCerts))
            {
                $result['is_valid'] = false;
                $result['message'] = $cert->name . ' certification is not '
                    . 'compatible with this surfacing material';

                return $result;
            }
        }
    }

    return $result;
}, 10, 2);

add_action('gform_after_submission_42', function($entry, $form) {
    $product = get_post(rgar($entry, 13));
    if (strpos($product->post_type, 'product') !== 0)
    {
        return;
    }

    $companyID = get_post_meta($product->ID, '_wpcf_belongs_company_id', true);
    $user = wp_get_current_user();
    if ($user->company_id != $companyID)
    {
        return;
    }

    $newID = $product->ID;
    if ( ! ipema_is_new($newID) && $product->post_type != 'product-change')
    {
        $newID = ipema_new_product_change($newID);
    }

    $changed = false;
    $requiresReview = false;
    $updates = array();

    if (rgar($entry, 1) != $product->post_title)
    {
        $changed = true;
        $requiresReview = true;
        $updates['post_title'] = $entry[1];
    }
    if (rgar($entry, 2) != $product->model)
    {
        $changed = true;
        $requiresReview = true;
        update_post_meta($newID, 'model', $entry[2]);
    }
    if (rgar($entry, 3) != $product->post_content)
    {
        $changed = true;
        //$requiresReview = true;
        $updates['post_content'] = $entry[3];
    }

    $french = get_post_meta($product->ID, 'french-description', true);
    if (rgar($entry, 4) != $french)
    {
        $changed = true;
        //$requiresReview = true;
        update_post_meta($newID, 'french-description', $entry[4]);
    }

    if (rgar($entry, 14) != $product->french_name)
    {
        $changed = true;
        $requiresReview = true;
        update_post_meta($newID, 'french_name', $entry[4]);
    }

    if (rgar($entry, 6))
    {
		
		
		
        $changed = true;
        $file = preg_replace('#^https?://[^/]+#i', ABSPATH, $entry[6]);
        $filetype = wp_check_filetype($file);
        $args = array(
            'post_title' => rgar($entry, 2),
            'post_mime_type' => $filetype['type'],
            'post_content' => '',
            'post_status' => 'inherit'
        );
        $attachmentID = wp_insert_attachment($args, $file, $newID);
		if (!function_exists('wp_generate_attachment_metadata')) {
    		require_once ABSPATH . 'wp-admin/includes/image.php';
			
        	$metadata = wp_generate_attachment_metadata($attachmentID, $file);
        	wp_update_attachment_metadata($attachmentID, $metadata);

        	set_post_thumbnail($newID, $attachmentID);
		}
    }
    elseif (rgar($entry, 7))
    {
        $changed = true;
        delete_post_thumbnail($newID);
    }

    $productLine = get_the_terms($product->ID, 'product-line');
    $productLineID = ipema_product_line_id(8, $entry, $form);
    if ($productLine == false)
    {
        if ($productLineID)
        {
            $changed = true;
            $requiresReview = true;
            wp_set_object_terms($newID, (int)$productLineID, 'product-line');
        }
    }
    elseif ( ! $productLineID )
    {
        $changed = true;
        $requiresReview = true;
        wp_delete_object_term_relationships($newID, 'product-line');
    }
    elseif ($productLine[0]->term_id != $productLineID)
    {
        $changed = true;
        $requiresReview = true;
        wp_set_object_terms($newID, (int)$productLineID, 'product-line');
    }

    $material = get_the_terms($product->ID, 'material');
    if ($material != false)
    {
        $material = $material[0]->term_id;
    }
    if ($material != rgar($entry, 9))
    {
        $changed = true;
        $requiresReview = true;
        wp_set_object_terms($newID, (int)$entry[9], 'material');
    }

    $thkToHt = get_post_meta($product->ID, 'thickness_to_height', true);
    if ($thkToHt)
    {
        $thickness = rgar($entry, 10);
        $height = rgpost('input_10b');

        if ($thkToHt != "$thickness:$height")
        {
            $changed = true;
            $requiresReview = true;
            update_post_meta(
                $newID,
                'thickness_to_height',
                "$thickness:$height"
            );
        }
    }

    if ($newID != $product->ID && $changed == false)
    {
        $post = wp_delete_post($newID, true);
        return;
    }

    if (count($updates) > 0)
    {
        $updates['ID'] = $newID;
        wp_update_post($updates);
    }

    if ($newID == $product->ID)
    {
        return;
    }

    $public_id = get_option('rv_autoincrement');
    $public_id++;
    update_option('rv_autoincrement', $public_id);

    $rv = array(
        'post_type' => 'rv',
        'post_title' => rgar($entry, 2),
        'post_content' => '',
        'post_excerpt' => rgar($entry, 12),
        'meta_input' => array(
            '_wpcf_belongs_product_id' => $product->ID,
            '_wpcf_belongs_product-change_id' => $newID,
            'public_id' => $public_id,
            'affected_id' => $product->ID
        )
    );

    if ($requiresReview)
    {
        $requiresReview = ipema_active_product($product);
    }

    if ( ! $requiresReview)
    {
        $rv['post_status'] = 'publish';
        $rv['meta_input']['status'] = 'processed';
        $rv['meta_input']['new'] = true;
    }

    $rvID = wp_insert_post($rv);

    $documents = json_decode(rgar($entry, 11));
    if ( ! is_array($documents))
    {
        $documents = array();
    }
    foreach ($documents as &$document)
    {
        add_post_meta(
            $rvID,
            'documentation',
            preg_replace('#^https?://[^/]+#i', '', $document)
        );
    }

    wp_set_post_terms($rvID, 'edit', 'request');

    if ($requiresReview)
    {
        ipema_rv_email($rvID);
    }
}, 10, 2);

add_filter('gform_confirmation_42', function($confirmation, $form, $entry) {
    $product = get_post(rgar($entry, 13));

    if ($product->post_type == 'product-change')
    {
        $rvs = get_posts(array(
            'post_type' => 'rv',
            'post_status' => 'publish',
            'tax_query' => array(array(
                'taxonomy' => 'request',
                'terms' => 'edit',
                'field' => 'slug'
            )),
            'meta_query' => array(
                array(
                    'key' => '_wpcf_belongs_product-change_id',
                    'value' => $product->ID
                ),
                array(
                    'key' => 'status',
                    'value' => 'rejected'
                )
            ),
            'posts_per_page' => 1,
            'fields' => 'ids'
        ));

        if (count($rvs) > 0)
        {
            $dupe = get_posts(array(
                'post_type' => 'rv',
                'post_parent' => $rvs[0],
                'tax_query' => array(array(
                    'taxonomy' => 'request',
                    'terms' => 'edit',
                    'field' => 'slug'
                )),
                'posts_per_page' => 1,
                'fields' => 'ids'
            ));

            if (count($dupe) == 0)
            {
                return array(
                    'redirect' => '/members/products/manage/'
                        . 'certification-requests/review/?request=' . $rvs[0]
                        . '#resubmit'
                );
            }
        }
    }

    if (ipema_is_new($product->ID))
    {
        $rvs = get_posts(array(
            'post_type' => 'rv',
            'post_status' => 'publish',
            'tax_query' => array(array(
                'taxonomy' => 'request',
                'terms' => 'test',
                'field' => 'slug'
            )),
            'meta_query' => array(
                array(
                    'key' => 'affected_id',
                    'value' => $product->ID
                ),
                array(
                    'key' => 'status',
                    'value' => 'rejected'
                )
            ),
            'posts_per_page' => 1,
            'fields' => 'ids'
        ));

        if (count($rvs) > 0)
        {
            $dupe = get_posts(array(
                'post_type' => 'rv',
                'post_parent' => $rvs[0],
                'posts_per_page' => 1,
                'fields' => 'ids'
            ));

            if (count($dupe) == 0)
            {
                return array(
                    'redirect' => '/members/products/manage/'
                        . 'certification-requests/review/?request=' . $rvs[0]
                        . '#resubmit'
                );
            }
        }
    }

    if ( ! ipema_active_product($product))
    {
        return array(
            'redirect' => '/members/products/manage/?model=' . $product->ID
        );
    }

    return $confirmation;
}, 10, 3);

function ipema_edit_family_form($form)
{
    $base = get_term($_GET['base'], 'base');
    if ( ! $base)
    {
        return $form;
    }

    $user = wp_get_current_user();
    if ($user->company_id != get_term_meta($base->term_id, 'company_id', true))
    {
        return $form;
    }

    $surfacing = get_posts(array(
        'post_type' => 'product',
        'post_status' => 'any',
        'tax_query' => array(
            array(
                'taxonomy' => 'base',
                'terms' => $base->term_id
            ),
            array(
                'taxonomy' => 'product-type',
                'terms' => 'surfacing',
                'field' => 'slug'
            )
        ),
        'posts_per_page' => 1,
        'fields' => 'ids'
    ));

    $showSurfacing = count($surfacing) > 0;

    $active = get_posts(array(
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

    if (count($active) == 0)
    {
        $form['button']['text'] = 'Change Family';
    }

    foreach ($form['fields'] as &$field)
    {
        if ($field->id == 1)
        {
            $field->defaultValue = get_term_meta(
                $base->term_id,
                'name',
                true
            );

            if ( ! $field->defaultValue)
            {
                $field->visibility = 'hidden';
            }
        }
        elseif ($field->id == 2)
        {
            $prefix = $base->name;
            if ($prefix != '~Unnamed~')
            {
                $field->defaultValue = $prefix;
            }
        }
        elseif ($field->id == 3)
        {
            $field->defaultValue = $base->description;
        }
        elseif ($field->id == 4)
        {
            if (get_term_meta($base->term_id, 'canadian', true))
            {
                $field->defaultValue = get_term_meta(
                    $base->term_id,
                    'french-description',
                    true
                );
            }
            else
            {
                $field->visibility = 'hidden';
            }
        }
        elseif ($field->id == 6)
        {
            $productLine = get_term_meta(
                $base->term_id,
                'product_line',
                true
            );

            if (is_numeric($productLine))
            {
                $field->conditionalLogic = array(
                    'actionType' => 'hide',
                    'logicType' => 'any',
                    'rules' => array(array(
                        'fieldId' => '5',
                        'operator' => 'is',
                        'value' => $productLine
                    ))
                );
            }
            else
            {
                $field->conditionalLogic = array(
                    'actionType' => 'show',
                    'logicType' => 'any',
                    'rules' => array(array(
                        'fieldId' => '5',
                        'operator' => '>',
                        'value' => '0'
                    ))
                );
            }
        }
        elseif ($field->id == 7)
        {
            if ($showSurfacing)
            {
                $field->defaultValue = get_term_meta(
                    $base->term_id,
                    'material',
                    true
                );
            }
            else
            {
                $field->adminOnly = true;
            }
        }
        elseif ($field->id == 8)
        {
            if ($showSurfacing)
            {
                list($thickness, $height) = explode(':', get_term_meta(
                    $base->term_id,
                    'thickness_to_height',
                    true
                ));

                $field->defaultValue = $thickness;

                if ( ! array_key_exists('input_8b', $_POST))
                {
                    $_POST['input_8b'] = $height;
                }
            }
            else
            {
                $field->adminOnly = true;
            }
        }
        elseif ($field->id == 9 || $field->id == 10)
        {
            if (count($active) == 0)
            {
                $field->visibility = 'hidden';
            }
        }
    }

    return $form;
}
add_filter('gform_pre_render_43', 'ipema_edit_family_form', 11);
add_filter('gform_pre_validation_43', 'ipema_edit_family_form', 11);
add_filter('gform_pre_submission_filter_43', 'ipema_edit_family_form', 11);

add_filter('gform_field_validation_43_2', function($result, $prefix) {
    if ( ! $result['is_valid'])
    {
        return $result;
    }

    $base = ipema_get_product_base($_GET['base']);
    $oldPrefix = $base->name;
    if ($oldPrefix == '~Unnamed~')
    {
        $oldPrefix = '';
    }

    if ($prefix == $oldPrefix)
    {
        return $result;
    }

    $models = get_posts(array(
        'post_type' => 'product',
        'post_status' => 'any',
        'tax_query' => array(array(
            'taxonomy' => 'base',
            'terms' => $base->term_id
        ))
    ));

    foreach ($models as $model)
    {
        $model = $prefix . $model->model;
        $result = ipema_force_unique_model($result, $model, NULL, NULL);
        if ( ! $result['is_valid'])
        {
            $result['message'] = "Creates duplicate model number $model";
            break;
        }
    }

    return $result;
}, 10, 2);

add_filter('gform_field_validation_43_7', function($result, $value, $form) {
    if ( ! $result['is_valid'])
    {
        return $result;
    }

    $material = get_term($value, 'material');

    $allowedCerts = get_term_meta(
        $material->term_id,
        'additional-certification'
    );

    $forbiddenCerts = array();
    $allCerts = ipema_active_certs();
    foreach ($allCerts as $cert)
    {
        if (get_term_meta($cert->term_id, 'restricted', true) == 1)
        {
            if ( ! in_array($cert->slug, $allowedCerts))
            {
                $forbiddenCerts[] = $cert->term_id;
            }
        }
    }

    if (count($forbiddenCerts) == 0)
    {
        return $result;
    }

    $illegal = get_posts(array(
        'post_type' => 'product',
        'post_status' => 'any',
        'tax_query' => array(
            array(
                'taxonomy' => 'base',
                'terms' => $_GET['base']
            ),
            array(
                'taxonomy' => 'certification',
                'terms' => $forbiddenCerts
            )
        ),
        'posts_per_page' => 1,
        'fields' => 'ids'
    ));

    if (count($illegal) > 0)
    {
        $result['is_valid'] = false;
        $result['message'] = 'This family contains models with certifications '
            . 'that are not compatible with this surfacing material';
    }

    return $result;
}, 10, 3);

add_action('gform_after_submission_43', function($entry, $form) {
    $user = wp_get_current_user();

    $oldBase = get_term($_GET['base'], 'base');
    if ( ! $oldBase)
    {
        return;
    }
    if ($user->company_id != get_term_meta($_GET['base'], 'company_id', true))
    {
        return;
    }

    if ($entry[2])
    {
        $slug = $user->company_id . '-' . sanitize_title($entry[2]);
    }
    else
    {
        $entry[2] = '~Unnamed~';
        $slug = $user->company_id . '-' . ipema_generate_code(4);
    }
    while (get_term_by('slug', $slug, 'base') !== false)
    {
        $slug = $user->company_id . '-' . ipema_generate_code(4);
    }

    $productLine = ipema_product_line_id(5, $entry, $form);

    $changed = false;
    if ($entry[1] != get_term_meta($oldBase->term_id, 'name', true))
    {
        $changed = true;
    }
    if ($entry[2] != $oldBase->name)
    {
        $changed = true;
    }
    if ($entry[3] != $oldBase->description)
    {
        $changed = true;
    }
    if ($entry[4] != get_term_meta($oldBase->term_id, 'french-description', true))
    {
        $changed = true;
    }
    if ($productLine != get_term_meta($oldBase->term_id, 'product_line', true))
    {
        $changed = true;

        $updateProductLine = true;
        if (rgar($entry, '6.1'))
        {
            $updateProductLine = false;
        }
    }
    if ($entry[7] != get_term_meta($oldBase->term_id, 'material', true))
    {
        $changed = true;
    }
    if ($entry[8])
    {
        $thkToHt = get_term_meta(
            $oldBase->term_id,
            'thickness_to_height',
            true
        );

        if ($thkToHt != "{$entry[8]}:{$_POST['input_8b']}")
        {
            $changed = true;
        }
    }

    if ( ! $changed)
    {
        return;
    }

    $term = wp_insert_term($entry[2], 'base', array(
        'slug' => $slug,
        'description' => $entry[3]
    ));

    if (is_numeric($productLine))
    {
        add_term_meta($term['term_id'], 'product_line', $productLine);
    }
    add_term_meta($term['term_id'], 'name', $entry[1]);
    add_term_meta($term['term_id'], 'company_id', $user->company_id);
    if (get_term_meta($_GET['base'], 'canadian', true))
    {
        add_term_meta($term['term_id'], 'canadian', true);
        add_term_meta($term['term_id'], 'french-description', $entry[4]);
    }

    if ($entry[8])
    {
        $thk_to_ht = "{$entry[8]}:{$_POST['input_8b']}";
        add_term_meta($term['term_id'], 'thickness_to_height', $thk_to_ht);
        add_term_meta($term['term_id'], 'material', $entry[7]);
    }

    $public_id = get_option('rv_autoincrement');
    $public_id++;
    update_option('rv_autoincrement', $public_id);

    $rv = array(
        'post_type' => 'rv',
        'post_title' => rgar($entry, 2),
        'post_content' => '',
        'post_excerpt' => rgar($entry, 10),
        'meta_input' => array(
            '_wpcf_belongs_product_id' => rgar($entry, 12),
            'public_id' => $public_id,
            'old-base' => rgar($entry, 11)
        )
    );

    $active = get_posts(array(
        'post_type' => 'product',
        'tax_query' => array(
            array(
                'taxonomy' => 'base',
                'terms' => $oldBase->term_id
            ),
            array(
                'taxonomy' => 'certification',
                'operator' => 'EXISTS'
            )
        ),
        'posts_per_page' => 1,
        'fields' => 'ids'
    ));

    if (count($active) == 0)
    {
        $rv['post_status'] = 'publish';
        $rv['meta_input']['status'] = 'processed';
    }

    if ($updateProductLine)
    {
        $rv['meta_input']['force_product_line'] = true;
    }

    $rvID = wp_insert_post($rv);

    $documents = json_decode(rgar($entry, 9));
    if ( ! is_array($documents))
    {
        $documents = array();
    }
    foreach ($documents as &$document)
    {
        add_post_meta(
            $rvID,
            'documentation',
            preg_replace('#^https?://[^/]+#i', '', $document)
        );
    }

    wp_set_post_terms($rvID, 'shared-features', 'request');
    wp_set_object_terms($rvID, (int)$term['term_id'], 'base');

    $all = get_posts(array(
        'post_type' => 'product',
        'post_status' => 'any',
        'tax_query' => array(array(
            'taxonomy' => 'base',
            'terms' => $oldBase->term_id
        )),
        'nopaging' => true,
        'fields' => 'ids'
    ));

    foreach ($all as $modelID)
    {
        add_post_meta($rvID, 'affected_id', $modelID);
    }

    if (count($active) > 0)
    {
        ipema_rv_email($rvID);
    }
    else
    {
        add_post_meta($rvID, 'new', true);
    }

}, 10, 2);

add_filter('gform_confirmation_43', function($confirmation, $form, $entry) {
    $active = get_posts(array(
        'post_type' => 'product',
        'tax_query' => array(array(
            'taxonomy' => 'base',
            'terms' => rgar($entry, 11)
        ))
    ));

    if (count($active) == 0)
    {
        return array(
            'redirect' => '/members/products/manage/?model=' . rgar($entry, 12)
        );
    }

    return $confirmation;
}, 10, 3);

function ipema_erase_model_name($form) {
    $model = ipema_product_display_name($_GET['model']);

    foreach ($form['fields'] as &$field)
    {
        if ($field->id == 1)
        {
            foreach ($field->choices as &$choice)
            {
                $choice['text'] = str_replace(
                    '{model}',
                    $model,
                    $choice['text']
                );
            }
        }
        elseif($field->id == 2)
        {
            $field->content = str_replace('{model}', $model, $field->content);
        }
    }

    return $form;
}
add_filter('gform_pre_render_44', 'ipema_erase_model_name');
add_filter('gform_pre_validation_44', 'ipema_erase_model_name');
add_filter('gform_pre_submission_filter_44', 'ipema_erase_model_name');

add_action('gform_after_submission_44', function($entry, $form) {
    $user = wp_get_current_user();

    $companyID = get_post_meta(
        rgar($entry, 3),
        '_wpcf_belongs_company_id',
        true
    );

    if ($companyID != $user->company_id)
    {
        return;
    }

    wp_update_post(array(
        'ID' => rgar($entry, 3),
        'post_type' => 'product-change',
        'edit_date' => 'no'
    ));
}, 10, 2);

add_action('template_redirect', function() {
    global $post;

    if ( ! is_page('cancel'))
    {
        return;
    }

    $rv = get_post($_GET['request']);
    if ($rv->post_type != 'rv')
    {
        return;
    }
    if ($rv->post_status != 'draft')
    {
        return;
    }

    $productID = get_post_meta($rv->ID, '_wpcf_belongs_product_id', true);
    $companyID = get_post_meta($productID, '_wpcf_belongs_company_id', true);

    $user = wp_get_current_user();

    if ($user->company_id != $companyID)
    {
        return;
    }

    wp_update_post(array(
        'ID' => $rv->ID,
        'post_date' => $rv->post_date,
        'post_status' => 'publish',
        'edit_date' => 'no'
    ));
    add_post_meta($rv->ID, 'reviewer', $user->ID);
    add_post_meta($rv->ID, 'status', 'canceled');

    wp_redirect('../review/?request=' . $rv->ID);
    die();
});

add_action('template_redirect', function() {
    if ( ! array_key_exists('promote', $_GET))
    {
        return;
    }

    if ( ! array_key_exists('level', $_GET))
    {
        return;
    }

    if ( ! current_user_can('manage_options'))
    {
        return;
    }

    $user = get_user_by('ID', $_GET['promote']);
    if ( ! $user)
    {
        return;
    }
    if ($_GET['level'] == 'ipema')
    {
        $user->add_cap('manage_ipema');
    }
    elseif ($_GET['level'] == 'tuv')
    {
        $user->add_cap('can_validate_products');
    }
});

function ipema_membership_level($form)
{
    $sales = get_post_meta($_GET['member'], 'sales', true);

    if ( ! $sales)
    {
        return $form;
    }

    foreach ($form['fields'] as &$field)
    {
        if ($field->id == 1)
        {
            $selected = 0;
            foreach ($field->choices as $choice)
            {
                if ($choice['value'] <= $sales && $choice['value'] > $selected)
                {
                    $selected = $choice['value'];
                }
            }
            foreach ($field->choices as &$choice)
            {
                if ($choice['value'] == $selected)
                {
                    $choice['isSelected'] = true;
                    break;
                }
            }
            break;
        }
    }

    return $form;
}
add_filter('gform_pre_render_45', 'ipema_membership_level');
add_filter('gform_pre_validation_45', 'ipema_membership_level');
add_filter('gform_pre_submission_filter_45', 'ipema_membership_level');

add_filter('gform_post_data_45', function($data, $form, $entry) {
    $post = get_post($entry[2], ARRAY_A);

    delete_post_meta($post['ID'], 'sales');
    $post['post_custom_fields'] = $data['post_custom_fields'];
    return $post;
}, 10, 3);

function ipema_expiration_date_field($field, $cert)
{
    $field->label = str_replace(
        '{Certification}',
        $cert->name,
        $field->label
    );

    $expiration = get_post_meta(
        $_GET['model'],
        $cert->slug,
        true
    );

    if (array_key_exists('base', $_GET))
    {
        $base = get_the_terms($_GET['model'], 'base');
        if ($base)
        {
            $different = get_posts(array(
                'post_type' => 'product',
                'post_status' => 'any',
                'tax_query' => array(
                    array(
                        'taxonomy' => 'base',
                        'terms' => $base[0]->term_id
                    ),
                    array(
                        'taxonomy' => 'certification',
                        'terms' => $cert->term_id
                    )
                ),
                'meta_query' => array(array(
                    'key' => $cert->slug,
                    'value' => $expiration,
                    'compare' => '!='
                )),
                'posts_per_page' => 1,
                'fields' => 'ids'
            ));
            if (count($different) > 0)
            {
                $expiration = 'Varies';
            }
        }
    }

    if ($expiration != 'Varies')
    {
        $expiration = date('m/d/Y', strtotime($expiration));
    }

    $field->defaultValue = $expiration;
}

function ipema_populate_change_expiration($form)
{
    $base = false;
    if (array_key_exists('base', $_GET))
    {
        $base = get_the_terms($_GET['model'], 'base');
    }

    if ($base === false)
    {
        $certs = get_the_terms($_GET['model'], 'certification');
    }
    else
    {
        $affected = get_posts(array(
            'post_type' => 'product',
            'post_status' => 'any',
            'tax_query' => array(
                array(
                    'taxonomy' => 'base',
                    'terms' => $base[0]->term_id
                ),
                array(
                    'taxonomy' => 'certification',
                    'operator' => 'EXISTS'
                )
            ),
            'nopaging' => true
        ));

        $certs = array();
        $products = array();
        $expiredCount = 0;
        $activeCount = 0;
        foreach ($affected as $product)
        {
            $productCerts = get_the_terms($product->ID, 'certification');
            foreach ($productCerts as $cert)
            {
                $certs[$cert->term_id] = $cert;
            }
            $fullName = ipema_product_display_name($product->ID);
            $products[] = array(
                'value' => $product->ID,
                'text' => $fullName
            );

            if ($product->post_status == 'publish')
            {
                $activeCount++;
            }
            elseif ($product->post_status == 'draft')
            {
                $expiredCount++;
            }
        }
        usort($products, 'ipema_sort_models');
    }

    if ($certs == false)
    {
        $certs = array();
    }

    foreach ($form['fields'] as &$field)
    {
        if ($field->id == 4)
        {
            $first = true;
            $counter = 1;
            foreach ($certs as $cert)
            {
                if ($first)
                {
                    $first = false;
                    continue;
                }

                $current = clone $field;
                $current->id = $field->id + $counter;
                ipema_expiration_date_field($current, $cert);

                $form['fields'][] = $current;

                $counter++;
            }

            ipema_expiration_date_field($field, reset($certs));
        }
        elseif ($field->id == 2)
        {
            if ($base === false)
            {
                $field->adminOnly = true;
            }
            else
            {
                if ($activeCount == 0 || $expiredCount == 0)
                {
                    $remove = array();
                    foreach ($field->choices as $key => $choice)
                    {
                        if (in_array($choice['value'], array('active', 'expired')))
                        {
                            $remove[] = $key;
                        }
                    }

                    $remove = array_reverse($remove);
                    foreach ($remove as $key)
                    {
                        unset($field->choices[$key]);
                    }

                    $field->choices[0]['isSelected'] = true;
                }
            }
        }
        elseif ($field->id == 3)
        {
            if ($base !== false)
            {
                $field->choices = $products;
                ipema_checkbox_inputs($field);
            }
        }
    }

    return $form;
}
add_filter('gform_pre_render_46', 'ipema_populate_change_expiration');
add_filter('gform_pre_validation_46', 'ipema_populate_change_expiration');
add_filter('gform_pre_submission_filter_46', 'ipema_populate_change_expiration');

add_action('gform_after_submission_46', function ($entry, $form)
{
    if (array_key_exists('base', $_GET))
    {
        $affected_ids = array();
        if (rgar($entry, 2) == 'selected')
        {
            foreach ($entry as $key => $value)
            {
                if (strpos($key, '3.') === 0 && $value)
                {
                    $affected_ids[] = $value;
                }
            }
        }
        else
        {
            $status = array(
                'all' => 'any',
                'active' => 'publish',
                'expired' => 'draft'
            );

            $base = get_the_terms(rgar($entry, 1), 'base');
            $affected_ids = get_posts(array(
                'post_type' => 'product',
                'post_status' => $status[$entry[2]],
                'tax_query' => array(
                    array(
                        'taxonomy' => 'base',
                        'terms' => $base[0]->term_id
                    ),
                    array(
                        'taxonomy' => 'certification',
                        'operator' => 'EXISTS'
                    )
                ),
                'nopaging' => true,
                'fields' => 'ids'
            ));
        }
    }
    else
    {
        $affected_ids = array($entry[1]);
    }

    $certs = array();
    foreach ($affected_ids as $productID)
    {
        $productCerts = get_the_terms($productID, 'certification');
        foreach ($productCerts as $cert)
        {
            $certs[$cert->term_id] = $cert;
        }
    }

    $expirations = array();
    $changes = array();
    $counter = 0;
    foreach ($certs as $cert)
    {
        $expirations[$cert->slug] = $entry[4 + $counter];
        $changes[$cert->term_id] = array();
        $counter++;
    }

    $changed = false;
    foreach ($affected_ids as $productID)
    {
        $updates = array();
        foreach ($certs as $cert)
        {
            $expires = get_post_meta($productID, $cert->slug, true);
            if ($expires)
            {
                $expires = date('Y-m-d', strtotime($expires));
                if ($expires != $expirations[$cert->slug])
                {
                    $changes[$cert->term_id][] = $productID;
                    $changed = true;
                }
            }
        }
    }

    if ( ! $changed)
    {
        return;
    }

    $rv = array(
        'post_type' => 'rv',
        'post_title' => ipema_model_number(array('product' => rgar($entry, 1))),
        'post_content' => '',
        'post_status' => 'publish',
        'meta_input' => array(
            '_wpcf_belongs_product_id' => rgar($entry, 1),
            'status' => 'processed',
            'new' => true
        ),
        'tax_input' => array(
            'request' => 'expiration'
        )
    );

    foreach ($expirations as $slug => $expiration)
    {
        $rv['meta_input'][$slug] = $expirations[$slug];
    }

    ipema_split_rv($changes, $rv);
}, 10, 2);

add_action('gform_after_submission_49', function ($entry, $form) {
    if ( ! current_user_can('manage_ipema'))
    {
        return;
    }

    $users = get_users(array(
        'meta_key' => $company_id,
        'meta_value' => $entry[1]
    ));

    foreach ($users as $user)
    {
        if ($user->has_cap('manage_ipema'))
        {
            delete_user_meta($user->ID, 'company_id');
            continue;
        }
        if ($user->has_cap('manage_options'))
        {
            delete_user_meta($user->ID, 'company_id');
            continue;
        }
        if ($user->has_cap('can_validate_products'))
        {
            delete_user_meta($user->ID, 'company_id');
            continue;
        }

        wp_delete_user($user->ID);
    }

    wp_delete_post($entry[1], true);
}, 10, 2);

add_action('template_redirect', function() {
    if ( ! is_page('change-level'))
    {
        return;
    }

    if ( ! current_user_can('manage_ipema'))
    {
        return;
    }

    $level = get_the_terms($_GET['member'], 'account-type');
    if ($level[0]->slug == 'associate')
    {
        $newLevel = get_term_by('slug', 'manufacturer', 'account-type');
    }
    elseif ($level[0]->slug == 'manufacturer')
    {
        $newLevel = get_term_by('slug', 'associate', 'account-type');
    }
    else
    {
        return;
    }

    wp_remove_object_terms($_GET['member'], $level[0]->term_id, 'account-type');
    wp_add_object_terms($_GET['member'], $newLevel->term_id, 'account-type');

    $user = wp_get_current_user();
    $date = date('Y-m-d H:i:s');
    add_post_meta(
        $_GET['member'],
        'account-change',
        "{$user->ID}:$date:{$newLevel->slug}"
    );

    wp_redirect('/admin/accounts/details/?member=' . $_GET['member']);
    die();
});

add_action('template_redirect', function() {
    if ( ! is_page('main-contact'))
    {
        return;
    }

    if ( ! current_user_can('manage_ipema'))
    {
        return;
    }

    $user = get_user_by('ID', $_GET['contact']);
    if ( ! $user->company_id)
    {
        return;
    }
    $currentContacts = get_users(array(
        'meta_query' => array(
            array(
                'key' => 'company_id',
                'value' => $user->company_id
            ),
            array(
                'key' => 'main_contact',
                'compare' => 'EXISTS'
            )
        )
    ));

    foreach ($currentContacts as $contact)
    {
        delete_user_meta($contact->ID, 'main_contact');
    }

    update_user_meta($user->ID, 'main_contact', true);

    wp_redirect('/admin/accounts/details/?member=' . $user->company_id);
    die();
});

function ipema_download_rv($rvID)
{
    update_post_meta($rvID, 'printable', time() + 10);

    $publicID = get_post_meta($rvID, 'public_id', true);
    $output = __DIR__ . "/rv-downloads/{$publicID}.pdf";
    $rvURL = site_url(
        '/members/products/manage/certification-requests/review/printable/'
        . "?request=$rvID"
    );

    $args = array(
        'html' => $rvURL,
        'title' => "RV #$publicID",
        'unstyled' => '1',
        'landscape' => 0,
        'file' => "rv-$publicID"
    );

    $fp = fopen($output, 'w');

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://pngeprma.net/pdf.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($args));
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_exec($ch);
    $st_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    fclose($fp);

    return $output;
}

add_action('template_redirect', function() {
    if ( ! is_page('download-rv'))
    {
        return;
    }

    $rv = get_post($_GET['request']);
    if ($rv->post_type != 'rv')
    {
        return;
    }
    $productID = get_post_meta($rv->ID, '_wpcf_belongs_product_id', true);
    $companyID = get_post_meta($productID, '_wpcf_belongs_company_id', true);

    $user = wp_get_current_user();
    if ($user->company_id != $companyID)
    {
        return;
    }

    $file = ipema_download_rv($_GET['request']);

    header('Content-type:application/pdf');
    header("Content-Disposition:attachment;filename=rv-{$rv->public_id}.pdf");
    readfile($file);

    unlink($file);
    die();
});

function ipema_download_rvs()
{
    $start = time();
    $handle = fopen('approved-rvs.txt', 'r');
    if ( ! $handle)
    {
        return;
    }

    if (array_key_exists('offset', $_GET))
    {
        while (($line = fgets($handle)) !== false)
        {
            if (trim($line) == $_GET['offset'])
            {
                break;
            }
        }
    }

    while (($line = fgets($handle)) !== false)
    {
        $rvID = (int)trim($line);
        ipema_download_rv($rvID);
        if (time() - $start > 30)
        {
            fclose($handle);

            wp_redirect("/about-ipema/?cron=digest&step=download&offset=$rvID");
            die();
        }
    }

    fclose($handle);

    wp_redirect('/about-ipema/?cron=digest&step=split');
    die();
}

function ipema_rvs_by_company()
{
    $start = time();
    $handle = fopen('approved-rvs.txt', 'r');
    if ( ! $handle)
    {
        return;
    }

    $companies = array();
    if (array_key_exists('offset', $_GET))
    {
        $companies = unserialize(file_get_contents('rvs-by-company.txt'));
        while (($line = fgets($handle)) !== false)
        {
            if (trim($line) == $_GET['offset'])
            {
                break;
            }
        }
    }

    while (($line = fgets($handle)) !== false)
    {
        $rvID = (int)trim($line);
        $product = get_post_meta($rvID, '_wpcf_belongs_product_id', true);
        $company = get_post_meta($product, '_wpcf_belongs_company_id', true);

        if ( ! $company)
        {
            continue;
        }

        if (array_key_exists($company, $companies))
        {
            $companies[$company][] = $rvID;
        }
        else
        {
            $companies[$company] = array($rvID);
        }

        if (time() - $start > 30)
        {
            fclose($handle);
            file_put_contents('rvs-by-company.txt', serialize($companies));

            wp_redirect("/about-ipema/?cron=digest&step=split&offset=$rvID");
            die();
        }
    }

    fclose($handle);
    file_put_contents('rvs-by-company.txt', serialize($companies));

    wp_redirect('/about-ipema/?cron=digest&step=email');
    die();
}

function ipema_send_approval_digests()
{
    $start = time();
    $companies = unserialize(file_get_contents('rvs-by-company.txt'));

    $found = ! array_key_exists('offset', $_GET);
    foreach ($companies as $companyID => $rvs)
    {
        if ( ! $found)
        {
            if ($companyID == $_GET['offset'])
            {
                $found = true;
            }
            continue;
        }

        $msg = '<p>The following Requests for Validation have been approved in '
            . 'the past week:</p>'
            . '<table><thead style="text-align:left"><tr><th>RV #</th>'
            . '<th>Product</th><th>Certifications</th></tr></thead><tbody>';

        $rvs = get_posts(array(
            'post__in' => $rvs,
            'post_type' => 'rv'
        ));
        $attachments = array();
        foreach ($rvs as $rv)
        {
            $attachments[] = __DIR__ . "/rv-downloads/{$rv->public_id}.pdf";

            $productID = get_post_meta(
                $rv->ID,
                '_wpcf_belongs_product_id',
                true
            );

            $certNames = array();
            $certs = get_the_terms($rv->ID, 'certification');
            if ($certs)
            {
                foreach ($certs as $cert)
                {
                    $certNames[] = $cert->name;
                }
            }

            $url = site_url(
                '/members/products/manage/certification-requests/review/'
                . "?request={$rv->ID}"
            );

            $msg .= '<tr>';
            $msg .= '<td><a href="' . $url . "\">{$rv->public_id}</a></td>";
            $msg .= '<td>' . ipema_product_display_name($productID) . '</td>';
            $msg .= '<td>' . implode('<br>', $certNames) . '</td>';

            $msg .= '</tr>';
        }

        $msg .= '</tbody></table>';
        $msg .= '<p>Sincerely,<br>The IPEMA Team</p>';

        $users = get_users(array(
            'meta_key' => 'company_id',
            'meta_value' => $companyID
        ));

        foreach ($users as $user)
        {
            if ( ! $user->has_cap('can_manage_products'))
            {
                continue;
            }
            if ($user->has_cap('can_validate_products')
                || $user->has_cap('manage_ipema'))
            {
                continue;
            }

            wp_mail(
                "{$user->display_name} <{$user->user_email}>",
                'IPEMA Approved RVs',
                "<p>Hi {$user->display_name},</p>$msg",
                array('Content-Type: text/html; charset=UTF-8'),
                $attachments
            );
        }

        foreach ($attachments as $attachment)
        {
            unlink($attachment);
        }

        if (time() - $start > 30)
        {
            wp_redirect(
                "/about-ipema/?cron=digest&step=email&offset=$companyID"
            );
            die();
        }
    }

    unlink('approved-rvs.txt');
    unlink('rvs-by-company.txt');
}

function ipema_approval_digest()
{
    if ($_GET['step'] == 'download')
    {
        ipema_download_rvs();
        return;
    }
    elseif ($_GET['step'] == 'split')
    {
        ipema_rvs_by_company();
        return;
    }
    elseif ($_GET['step'] == 'email')
    {
        ipema_send_approval_digests();
        return;
    }

    $end = strtotime('last Sunday');
    $start = strtotime('-1 week', $end);

    $rvs = get_posts(array(
        'post_type' => 'rv',
        'date_query' => array(
            'column' => 'post_modified',
            'after' => date('Y-m-d H:i:s', $start),
            'before' => date('Y-m-d H:i:s', $end)
        ),
        'meta_query' => array(array(
            'key' => 'status',
            'value' => 'approved'
        )),
        'fields' => 'ids',
        'nopaging' => true
    ));

    if (count($rvs) == 0)
    {
        return;
    }

    file_put_contents('approved-rvs.txt', implode("\n", $rvs));

    wp_redirect('/about-ipema/?cron=digest&step=download');
    die();
}

function ipema_show_description() {
    $rv = get_post($_GET['request']);

    if ($rv->post_type != 'rv')
    {
        return false;
    }

    $product = $rv->_wpcf_belongs_product_id;
    if ( ! $product)
    {
        return false;
    }

    $company = get_post_meta($product, '_wpcf_belongs_company_id', true);
    return get_post_meta($company, 'show_rv_description', true);
}

add_filter('gform_notification_50', function($notification, $form, $entry) {
    $users = get_users(array(
        'meta_key' => 'company_id',
        'meta_value' => $entry['3']
    ));

    $emails = array();

    foreach ($users as $user)
    {
        if ( ! $user->has_cap('can_manage_products'))
        {
            continue;
        }
        if ($user->has_cap('manage_ipema'))
        {
            continue;
        }
        if ($user->has_cap('can_validate_products'))
        {
            continue;
        }

        $emails[] = $user->user_email;
    }

    $notification['to'] = join(', ', $emails);

    return $notification;
}, 10, 3);

add_filter('post_types_to_delete_with_user', function($types, $userId) {
    $types[] = 'company';
    return $types;
}, 10, 2);

// Add a white-label source to product
add_action('template_redirect', function() {
    if ( ! is_page('add-whitelabel'))
    {
        return;
    }

    $sources = get_post_meta($_GET['model'], 'whitelabels');
    if ( ! in_array($_GET['parent'], $sources))
    {
        add_post_meta($_GET['model'], 'whitelabels', $_GET['parent']);
    }

    wp_redirect('/members/products/manage/whitelabel/?model=' . $_GET['model']);
    die();
});

// Remove a white-label source from product
add_action('template_redirect', function() {
    if ( ! is_page('remove-whitelabel'))
    {
        return;
    }

    delete_post_meta($_GET['model'], 'whitelabels', $_GET['source']);

    wp_redirect('/members/products/manage/whitelabel/?model=' . $_GET['model']);
    die();
});

// Restrict whitelabel product search by product type
add_action('template_redirect', function() {
    if ( ! is_page('whitelabel'))
    {
        return;
    }

    $product_type = get_the_terms($_GET['model'], 'product-type');

    if (count($product_type) == 0)
    {
        return;
    }

    $_GET['wpvproducttype'] = $product_type[0]->slug;

    $whitelabels = get_post_meta($_GET['model'], 'whitelabels');
    if ( ! $whitelabels)
    {
        $whitelabels = [-1];
    }
    $_GET['product_ids'] = $whitelabels;
});

// Download whitelabel CSV
add_action('template_redirect', function() {
    if ( ! is_page('whitelabel-report'))
    {
        return;
    }

    $output = fopen("php://output",'w') or die("Can't open php://output");
    header("Content-Type:application/csv");
    header("Content-Disposition:attachment;filename=private-label-models.csv");

    fputcsv($output, array(
        'Private Label Model',
        'Private Label Name',
        'Private Label Manufacturer',
        'Source Model',
        'Source Name',
        'Source Manufacturer',
        'Product Type'
    ));

    $private_label = get_posts(array(
        'post_type' => 'product',
        'post_status' => 'any',
        'meta_query' => array(array(
            'key' => 'whitelabels',
            'compare' => 'EXISTS'
        )),
        'nopaging' => true
    ));
    foreach ($private_label as $product)
    {
        $product_manufacturer = get_post($product->_wpcf_belongs_company_id);
        $product_manufacturer = html_entity_decode(
            $product_manufacturer->post_title
        );
        $product_type = get_the_terms($product->ID, 'product-type');
        $product_type = $product_type[0]->name;
        $sources = get_post_meta($product->ID, 'whitelabels');
        foreach ($sources as $source_id)
        {
            $source = get_post($source_id);
            $source_manufacturer = get_post($source->_wpcf_belongs_company_id);
            $source_manufacturer = html_entity_decode(
                $source_manufacturer->post_title
            );
            fputcsv($output, array(
                $product->model,
                $product->post_title,
                $product_manufacturer,
                $source->model,
                $source->post_title,
                $source_manufacturer,
                $product_type
            ));
        }
    }
    die();
});

// http://webdesignandsuch.com/embed-a-wordpress-custom-menu-in-page-content-with-a-shortcode/
function print_menu_shortcode($attrs, $content = null)
{
    $attrs = shortcode_atts(array( 'name' => null, ), $attrs);
    return wp_nav_menu(array('menu' => $attrs['name'], 'echo' => false));
}
add_shortcode('menu', 'print_menu_shortcode');

function ipema_model_number($attrs, $content=null)
{
    $attrs = shortcode_atts(array('product' => get_the_ID()), $attrs);
    $model = get_post_meta($attrs['product'], 'model', true);

    $base = ipema_get_product_base($attrs['product']);
    if ($base !== false)
    {
        $term = get_term((int)$base->term_id, 'base');
        if ($term->name != '~Unnamed~')
        {
            $model = $term->name . $model;
        }
    }

    return $model;
}
add_shortcode('model-number', 'ipema_model_number');

function ipema_brand_name($attrs, $content=null)
{
    $attrs = shortcode_atts(array('product' => get_the_ID()), $attrs);
    $name = get_the_title($attrs['product']);

    $base = ipema_get_product_base($attrs['product']);
    if ($base !== false)
    {
        $basename = get_term_meta((int)$base->term_id, 'name', true);
        $name = "$basename $name";
    }

    return trim($name);
}
add_shortcode('brand-name', 'ipema_brand_name');

function ipema_french_name($attrs, $content=null)
{
    $attrs = shortcode_atts(array('product' => get_the_ID()), $attrs);
    $name = get_post_meta($attrs['product'], 'french_name', true);

    $base = ipema_get_product_base($attrs['product']);
    if ($base !== false)
    {
        $basename = get_term_meta((int)$base->term_id, 'french_prefix', true);
        $name = "$basename $name";
    }

    return trim($name);
}
add_shortcode('french-name', 'ipema_french_name');

function ipema_thk_to_ht_shortcode($attrs, $content=null)
{
    $attrs = shortcode_atts(array('product' => get_the_ID()), $attrs);
    $value = get_post_meta($attrs['product'], 'thickness_to_height', true);

    $value = explode(':', $value);
    return "{$value[0]}\" / {$value[1]}'";
}
add_shortcode('thickness-to-height', 'ipema_thk_to_ht_shortcode');

function ipema_base_model_description($attrs, $content=null)
{
    $attrs = shortcode_atts(array('product' => get_the_ID()), $attrs);

    $base = ipema_get_product_base($attrs['product']);

    if ($base !== false && mb_strlen($base->description) > 0)
    {
        return '<p>' . nl2br($base->description) . '</p>';
    }

    return '';
}
add_shortcode('base-description', 'ipema_base_model_description');

function ipema_french_description($attrs, $content=null)
{
    $attrs = shortcode_atts(array('product' => get_the_ID()), $attrs);

    $french = get_post_meta($attrs['product'], 'french-description', true);
    if ($french)
    {
        $french = '<p>' . nl2br($french) . '</p>';
    }
    $base = ipema_get_product_base($attrs['product']);

    if ($base !== false)
    {
        $description = get_term_meta(
            $base->term_id,
            'french-description',
            true
        );

        if ($description)
        {
            $french = '<p>' . nl2br($base->description) . '</p>' . $french;
        }
    }

    return $french;
}
add_shortcode('french-description', 'ipema_french_description');

function ipema_link_doc($url, $class='')
{
    $filename = preg_replace('#^.*/#', '', $url);

    $html = '<a href="'. $url . '" target="_blank" class="documentation';
    if (mb_strlen($class) > 0)
    {
        $html .= " $class";
    }
    $html .= '">' . $filename . '</a>';

    return $html;
}

function ipema_rv_doc_list($content, $id, $class='')
{
    $docs = get_post_meta($id, 'documentation');

    foreach ($docs as $url)
    {
        $content .= '<li>';
        $content .= ipema_link_doc($url);
        $content .= '</li>';
    }

    $previous = get_post_meta($id, 'previous', true);
    if ($previous)
    {
        $content = ipema_rv_doc_list($content, $previous, 'old');
    }

    return $content;
}

function ipema_rv_docs($attrs, $content=NULL)
{
    $attrs = shortcode_atts(array('rv' => get_the_ID()), $attrs);

    $content = '';
    $content = ipema_rv_doc_list($content, $attrs['rv']);

    if (mb_strlen($content) > 0)
    {
        $base = ipema_get_product_base(
            get_post_meta($attrs['rv'], '_wpcf_belongs_product_id', true)
        );

        $heading = 'Testing Documentation';
        if ($base !== false)
        {
            $heading = 'Model Testing Documentation';
        }
        $content = "<p><strong>$heading:</strong></p><ul>$content</ul>";
    }

    return $content;

}
add_shortcode('documentation', 'ipema_rv_docs');

function ipema_product_status($attrs, $content=NULL)
{
    $attrs = shortcode_atts(array('product' => get_the_ID()), $attrs);

    add_filter('posts_groupby', '__return_false');
    $last_rv = get_posts(array(
        'post_type' => 'rv',
        'post_status' => 'any',
        'meta_query' => array(array(
            'key' => 'affected_id',
            'value' => $attrs['product']
        )),
        'posts_per_page' => 1,
        'suppress_filters' => false
    ));
    remove_filter('posts_groupby', '__return_false');

    if (count($last_rv) == 1)
    {
        $last_rv = $last_rv[0];
        if ($last_rv->post_status == 'draft')
        {
            return 'review';
        }
        elseif ($last_rv->status == 'rejected')
        {
            return 'rejected';
        }
    }

    if (ipema_is_new($attrs['product']))
    {
        return 'draft';
    }

    $certs = get_the_terms($attrs['product'], 'certification');
    if ($certs === false)
    {
        return 'obsolete';
    }
    foreach ($certs as $cert)
    {
        $renewal_date = get_post_meta($attrs['product'], $cert->slug, true);
        if (strtotime($renewal_date) < time())
        {
            return 'expired';
        }
    }

    return 'approved';
}
add_shortcode('product-status', 'ipema_product_status');

function ipema_base_model($attrs, $content)
{
    $term_id = $_GET['base'];

    if ( ! is_numeric($term_id))
    {
        return '';
    }

    $term = get_term((int)$term_id, 'base');
    $name = get_term_meta($term->term_id, 'name', true);

    $html = '<p><strong>Base Model:</strong> ';
    $html .= $term->name;
    if ($name)
    {
        $html .= " ($name)";
    }
    $html .= '</p>';

    return $html;
}
add_shortcode('base-model', 'ipema_base_model');

function ipema_reviewer($attrs, $content)
{
    $attrs = shortcode_atts(array('rv' => get_the_ID()), $attrs);

    $reviewerID = get_post_meta($attrs['rv'], 'reviewer', true);

    return get_user_by('id', $reviewerID)->display_name;
}
add_shortcode('reviewer', 'ipema_reviewer');

function ipema_base_term_id($attrs, $content)
{
    $attrs = shortcode_atts(array('product' => get_the_ID()), $attrs);

    $base = ipema_get_product_base($attrs['product']);

    if ($base !== false)
    {
        return $base->term_id;
    }
    return '';
}
add_shortcode('base-term-id', 'ipema_base_term_id');

add_shortcode('qr-code-url', function($attrs, $content) {
    $default = $_GET['cert'];
    if ( ! $default)
    {
        $default = get_the_ID();
    }
    $attrs = shortcode_atts(array('page' => $default), $attrs);

    $url = get_permalink($attrs['page']);

    return 'https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl='
        . urlencode($url);
});

add_shortcode('company-id', function($attrs, $content) {
    $user = wp_get_current_user();

    return $user->company_id;
});

add_shortcode('submission_amount', function($attrs, $content='') {
    if ( ! is_numeric($attrs['entry_id']))
    {
        return 'No entry specified';
    }

    $fields = array(
        23 => 24,
        25 => 2,
        1 => 26,
        2 => 6,
        34 => 33,
    );

    $entry = GFFormsModel::get_lead($attrs['entry_id']);
    $amount = $entry[$fields[$entry['form_id']]];

    return '$' . number_format($amount, 2);
});

add_shortcode('products-expiring-soon', function($attrs, $content='') {
    $html = '';

    $user = wp_get_current_user();
    if ( ! $user->has_cap('can_manage_products'))
    {
        return $html;
    }

    $certifications = get_terms(array(
        'taxonomy' => 'certification'
    ));
    $certs = array();
    if (ipema_user_company_active('surfacing'))
    {
        foreach ($certifications as &$certification)
        {
            if (ipema_certification_product_type($certification->term_id, 'surfacing'))
            {
                $certs[] = $certification->slug;
                unset($certification);
            }
        }
    }
    if (ipema_user_company_active('equipment'))
    {
        foreach ($certifications as &$certification)
        {
            if (ipema_certification_product_type($certification->term_id, 'equipment'))
            {
                $certs[] = $certification->slug;
            }
        }
    }

    if (count($certs) == 0)
    {
        return $html;
    }

    $now = date('Y-m-d');
    $twoMonths = date('Y-m-d', strtotime('+2 months'));

    $ids = array();
    foreach ($certs as $cert)
    {
        $expiring = get_posts(array(
            'post_type' => 'product',
            'nopaging' => true,
            'meta_query' => array(
                array(
                    'key' => '_wpcf_belongs_company_id',
                    'value' => $user->company_id
                ),
                array(
                    'key' => $cert,
                    'value' => array($now, $twoMonths),
                    'type' => 'DATE',
                    'compare' => 'BETWEEN'
                )
            )
        ));

        foreach ($expiring as $product)
        {
            $ids[] = $product->ID;
        }
    }

    $ids = array_unique($ids);

    $expiringCount = 0;
    $expiringIDs = array();
    foreach ($ids as $id)
    {
        $rvs = get_posts(array(
            'post_type' => 'rv',
            'post_status' => 'draft',
            'meta_query' => array(array(
                'key' => 'affected_id',
                'value' => $id
            ))
        ));

        if (count($rvs) == 0)
        {
            $expiringCount++;
            $expiringIDs[] = $id;
        }
    }

    if ($expiringCount > 0)
    {
        $products = 'product' . ($expiringCount > 1 ? 's' : '');
        $msg = "The following $products will expire in the next two months.";
        $html .= '<p class="alert"><a href="/members/product-alert/?i[]=';
        $html .= implode('&i[]=', $expiringIDs) . '&msg=' . urlencode($msg);
        $html .= "\">$expiringCount $products will expire soon.</a></p>";
    }

    $twoWeeksAgo = date('Y-m-d', strtotime('2 weeks ago'));
    $ids = array();
    foreach ($certs as $cert)
    {
        $expired = get_posts(array(
            'post_type' => 'product',
            'post_status' => 'draft',
            'nopaging' => true,
            'meta_query' => array(
                array(
                    'key' => '_wpcf_belongs_company_id',
                    'value' => $user->company_id
                ),
                array(
                    'key' => 'obsolete',
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key' => $cert,
                    'value' => array($twoWeeksAgo, $now),
                    'type' => 'DATE',
                    'compare' => 'BETWEEN'
                )
            ),
            'tax_query' => array(array(
                'taxonomy' => 'certification',
                'terms' => $cert,
                'field' => 'slug'
            ))
        ));

        foreach ($expired as $product)
        {
            $ids[] = $product->ID;
        }
    }

    $ids = array_unique($ids);

    $expiredCount = 0;
    $expiredIDs = array();
    foreach ($ids as $id)
    {
        $rvs = get_posts(array(
            'post_type' => 'rv',
            'post_status' => 'draft',
            'meta_query' => array(array(
                'key' => 'affected_id',
                'value' => $id
            ))
        ));

        if (count($rvs) == 0)
        {
            $expiredCount++;
            $expiredIDs[] = $id;
        }
    }

    if ($expiredCount > 0)
    {
        $products = 'product' . ($expiredCount > 1 ? 's' : '');
        $msg = "The following $products expired in the past two weeks.";
        $html .= '<p class="alert"><a href="/members/product-alert/?i[]=';
        $html .= implode('&i[]=', $expiredIDs) . '&msg=' . urlencode($msg);
        $html .= "\">$expiredCount $products expired recently.</a></p>";
    }

    return $html;
});

add_shortcode('product-alert-msg', function($attrs, $content='') {
    if ($_GET['msg'])
    {
        return '<p><strong>' . htmlentities(
            $_GET['msg'],
            ENT_NOQUOTES | ENT_HTML401
        ) . '</strong></p>';
    }
    return '';
});

add_shortcode('french-date', function($attrs, $content='') {
    $default = $_GET['cert'];
    if ( ! $default)
    {
        $default = get_the_ID();
    }
    $attrs = shortcode_atts(array('post' => $default), $attrs);

    $post = get_post($attrs['post']);

    $oldLocale = setlocale(LC_TIME, 0);

    setlocale(LC_TIME, 'fr_CA.UTF8', 'fr.UTF8');

    $date = strftime('%B %e, %Y', strtotime($post->post_date));

    setlocale(LC_TIME, $oldLocale);

    return $date;
});

add_shortcode('notes', function($attrs, $content='') {
    global $post;

    if ( ! $attrs['source'])
    {
        return '';
    }

    $html = '';

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_POST['new_note'])
    {
        if ($_POST['note_nonce']
            && wp_verify_nonce($_POST['note_nonce'], 'add_note')
        )
        {
            $user = wp_get_current_user();
            wp_insert_comment(array(
                'comment_post_ID' => $post->ID,
                'comment_author' => $user->display_name,
                'comment_author_email' => $user->user_email,
                'user_id' => $user->ID,
                'comment_content' => htmlentities($_POST['new_note']),
                'comment_meta' => array(
                    'source' => $attrs['source']
                )
            ));

            $html .= '<script type="text/javascript">location.href = location.href;</script>';
        }
    }

    $notes = get_comments(array(
        'post_id' => $post->ID,
        'meta_key' => 'source',
        'meta_value' => $attrs['source']
    ));

    if (count($notes) > 0)
    {
        $html .= '<h3>Notes:</h3>';
    }

    foreach ($notes as $note)
    {
        $html .= '<div class="note"><div class="name">';
        $html .= $note->comment_author . '</div><div class="date">';
        $html .= date('M jS, Y', strtotime($note->comment_date)) . '</div>';
        $html .= $note->comment_content . '</div>';
    }

    $html .= '<form method="post">';
    $html .= '<textarea name="new_note"></textarea>';
    $html .= '<input type="submit" value="Add Note">';
    $html .= wp_nonce_field('add_note', 'note_nonce', true, false);
    $html .= '</form>';

    return $html;
});

add_shortcode('user-company', function($attrs, $content='') {
    $user = wp_get_current_user();

    if ($user->company_id)
    {
        $company = get_post($user->company_id);

        if ($company)
        {
            return $company->post_title;
        }
    }

    return $user->display_name;
});

add_shortcode('company-meta', function($attrs, $content='') {
    $post = get_post();
    if ($post->post_type == 'company')
    {
        $id = $post->ID;
    }
    else
    {
        $user = wp_get_current_user();
        $id = $user->company_id;
    }

    $attrs = shortcode_atts(
        array('id' => $id, 'field' => NULL),
        $attrs
    );

    if ( ! $attrs['field'])
    {
        return '';
    }

    return get_post_meta($attrs['id'], $attrs['field'], true);
});

add_shortcode('retest-count', function($attrs, $content='') {
    $attrs = shortcode_atts(
        array('company' => get_the_ID(), 'type' => 'none'),
        $attrs
    );

    $count = ipema_retested_count($attrs['company'], $attrs['type']);

    $user = wp_get_current_user();
    if ($count > 0 && $user->company_id)
    {
        $count = '<a href="models/?retest=' . ipema_retest_year() . '&t=' . $attrs['type']
            . '">' . $count . '</a>';
    }

    return $count;
});

add_shortcode('product-expiration', function($attrs, $content='') {
     $attrs = shortcode_atts(array('product' => get_the_ID()), $attrs);

     $certs = get_the_terms($attrs['product'], 'certification');
     $oneTrueExp = true;
     $lastExp = NULL;
     foreach ($certs as $cert)
     {
        $exp = get_post_meta($attrs['product'], $cert->slug, true);
        if ($lastExp != NULL && $lastExp != $exp)
        {
            $oneTrueExp = false;
            break;
        }

        $lastExp = $exp;
     }

     if ($oneTrueExp)
     {
        $s = 's';
        $lastExp = strtotime($lastExp);
        if ($lastExp < time())
        {
            $s = 'd';
        }
        $lastExp = date('F j, Y', $lastExp);
        return "<strong>Expire$s:</strong> $lastExp";
     }

     $alert = '<strong>Expiration:</strong>';
     foreach ($certs as $cert)
     {
        $exp = get_post_meta($attrs['product'], $cert->slug, true);
        $exp = date('F j, Y', strtotime($exp));
        $alert .= "<br><strong>{$cert->name}:</strong> $exp";
     }

     return $alert;
});

function ipema_single_product_rv($rvID)
{
    $affected = get_post_meta($rvID, 'affected_id');

    return count($affected) == 1;
}

function ipema_get_pending_rv_type($rv)
{
    $rv = get_post($rv);
    if ($rv->post_type != 'rv')
    {
        return '';
    }

    $followUp = '';
    if ($rv->post_parent)
    {
        $followUp = ' (Follow-up)';
        while ($rv->post_parent)
        {
            $rv = get_post($rv->post_parent);
        }
    }

    $type = '';
    $types = get_the_terms($rv->_wpcf_belongs_product_id, 'product-type');
    foreach ($types as $type)
    {
        $type = $type->name;
    }

    $group = '';
    $affected = get_post_meta($rv->ID, 'affected_id');
    if (count($affected) > 1)
    {
        $group = ' Family';
    }

    $request = get_the_terms($rv->ID, 'request')[0]->slug;
    if ($request == 'test')
    {
        $action = 'New ';
        $certs = get_the_terms($rv->ID, 'certification');
        foreach ($affected as $productID)
        {
            foreach ($certs as $cert)
            {
                if (get_post_meta($productID, $cert->slug, true))
                {
                    $action = 'Retest ';
                    break;
                }
            }
        }
    }
    elseif ($request == 'add-model')
    {
        $action = 'Add Model';
        if (count($affected) > 1)
        {
            $action .= 's';
        }
        $action .= ' to ';
        $group = ' Family';
    }
    elseif ($request == 'add-certification')
    {
        $action = 'Add ';
        $group .= ' Certification';
    }
    elseif ($request == 'family')
    {
        $action = 'Move Models to ';
        $group = ' Family';
    }
    elseif ($request == 'leave')
    {
        $action = 'Remove Model from ';
        $group = ' Family';
    }
    elseif ($request == 'shared-features')
    {
        $action = 'Edit Features of ';
        $group = ' Family';
    }
    elseif ($request == 'edit')
    {
        $action = 'Modify ';
    }
    elseif ($request == 'remove')
    {
        $action = 'Remove ';
        $group = ' Certification';
        if (count($affected) > 1)
        {
            $group .= ' from Family';
        }
    }

    return "$action$type$group$followUp";
}

add_shortcode('pending-rv-type', function($attrs, $content) {
    $attrs = shortcode_atts(array('rv' => get_the_ID()), $attrs);

    return ipema_get_pending_rv_type($attrs['rv']);
});

$receiptFields = array(
    1 => array(
        'membership' => 76,
        'equipment' => 77,
        'surfacing' => 78
    ),
    23 => array(
        'membership' => 33,
        'equipment' => 34,
        'surfacing' => 35
    ),
    34 => array(
        'equipment' => 40,
        'surfacing' => 41
    ),
    2 => array(
        'membership' => 6
    ),
    25 => array(
        'membership' => 2
    )
);

add_shortcode('receipt', function($attrs, $content) {
    global $receiptFields;
    if ( ! array_key_exists('entry', $attrs))
    {
        return 'You forgot the Entry ID';
    }
    $entry = GFFormsModel::get_lead($attrs['entry']);

    if ( ! in_array($entry['form_id'], array_keys($receiptFields)))
    {
        return '';
    }

    $fields = $receiptFields[$entry['form_id']];

    $table = '<table><thead><tr><th></th>'
        . '<th style="padding-left: 1em">Price (USD)</th></tr></thead><tbody>';

    if (array_key_exists('membership', $fields))
    {
        $price = trim($entry[$fields['membership']], '$ ');
        if (is_numeric($price) && $price > 0)
        {
            $table .= '<tr><td>Membership</td><td style="text-align:right">$'
                . number_format($price, 2)
                . '</td></tr>';
        }
    }
    if (array_key_exists('equipment', $fields))
    {
        $price = trim($entry[$fields['equipment']], '$ ');
        if (is_numeric($price) && $price > 0)
        {
            $table .= '<tr><td>Equipment Certification Program</td>'
                . '<td style="text-align:right">$'
                . number_format($price, 2)
                . '</td></tr>';
        }
    }
    if (array_key_exists('surfacing', $fields))
    {
        $price = trim($entry[$fields['surfacing']], '$ ');
        if (is_numeric($price) && $price > 0)
        {
            $table .= '<tr><td>Surfacing Certification Program</td>'
                . '<td style="text-align:right">$'
                . number_format($price, 2)
                . '</td></tr>';
        }
    }

    $table .= '</tbody></table>';

    return $table;
});

add_shortcode('user-meta', function($attrs, $content='') {
    $user = wp_get_current_user();

    $attrs = shortcode_atts(
        array('id' => $user->ID, 'field' => NULL),
        $attrs
    );

    if ( ! $attrs['field'])
    {
        return '';
    }

    return get_user_meta($attrs['id'], $attrs['field'], true);
});

add_shortcode('shared-feature-changes', function($attrs, $content='') {
    $attrs = shortcode_atts(array('rv' => get_the_ID()), $attrs);

    $rv = get_post($attrs['rv']);

    if ($rv->post_type != 'rv')
    {
        return '';
    }

    $old = get_term(get_post_meta($rv->ID, 'old-base', true));
    $new = get_the_terms($rv->ID, 'base')[0];

    $html = '<table><thead><tr><th></th>';
    if ($rv->post_status == 'draft')
    {
        $html .= '<th>Current</th><th>Requested</th>';
    }
    else
    {
        $html .= '<th>Previous</th><th>Updated</th>';
    }
    $html .= '</tr></thead><tbody>';

    $oldName = get_term_meta($old->term_id, 'name', true);
    $newName = get_term_meta($new->term_id, 'name', true);
    if ($oldName != $newName)
    {
        $html .= '<tr><td><strong>Name Prefix</strong></td>';
        $html .= "<td>$oldName</td><td>$newName</td></tr>";
    }

    if ($old->name != $new->name)
    {
        $oldName = $old->name;
        if ($oldName == '~Unnamed~')
        {
            $oldName = '';
        }
        $newName = $new->name;
        if ($newName == '~Unnamed~')
        {
            $newName = '';
        }
        $html .= '<tr><td><strong>Model Number Prefix</strong></td>'
            . "<td>{$oldName}</td><td>{$newName}</td></tr>";
    }

    if ($old->description != $new->description)
    {
        $html .= '<tr><td><strong>Description</strong></td>'
            . "<td>{$old->description}</td><td>{$new->description}</td></tr>";
    }

    $oldFrench = get_term_meta($old->term_id, 'french-description', true);
    $newFrench = get_term_meta($new->term_id, 'french-description', true);
    if ($oldFrench != $newFrench)
    {
        $oldFrench = nl2br($oldFrench);
        $newFrench = nl2br($newFrench);
        $html .= '<tr><td><strong>French-Canadian Description</strong></td>'
            . "<td>$oldFrench</td><td>$newFrench</td>";
    }

    $oldLine = get_term_meta($old->term_id, 'product_line', true);
    $newLine = get_term_meta($new->term_id, 'product_line', true);
    if ($oldLine != $newLine)
    {
        $oldLine = get_term($oldLine, 'product-line');
        $newLine = get_term($newLine, 'product-line');
        $html .= '<tr><td><strong>Product Line</strong></td>'
            . "<td>{$oldLine->name}</td><td>{$newLine->name}</td></tr>";
    }

    $oldMaterial = get_term_meta($old->term_id, 'material', true);
    $newMaterial = get_term_meta($new->term_id, 'material', true);
    if ($oldMaterial != $newMaterial)
    {
        $oldMaterial = get_term($oldMaterial, 'material');
        $newMaterial = get_term($newMaterial, 'material');
        $html .= '<tr><td><strong>Material</strong></td>'
            . "<td>{$oldMaterial->name}</td><td>{$newMaterial->name}</td></tr>";
    }

    $oldThkToHt = get_term_meta($old->term_id, 'thickness_to_height', true);
    $newThkToHt = get_term_meta($new->term_id, 'thickness_to_height', true);
    if ($oldThkToHt != $newThkToHt)
    {
        list($oldThk, $oldHt) = explode(':', $oldThkToHt);
        list($newThk, $newHt) = explode(':', $newThkToHt);
        $html .= '<tr><td><strong>Thickness to Height Ratio</strong></td>'
            . "<td>$oldThk\" / $oldHt'</td><td>$newThk\" / $newHt'</td></tr>";
    }

    $html .= '</tbody></table>';
    return $html;
});

add_shortcode('removal-changes', function($attrs, $content='') {
    $attrs = shortcode_atts(array('rv' => get_the_ID()), $attrs);

    $rv = get_post($attrs['rv']);

    if ($rv->post_type != 'rv')
    {
        return '';
    }
    if ($rv->post_status != 'draft')
    {
        return '';
    }

    $productID = get_post_meta($rv->ID, '_wpcf_belongs_product_id', true);
    $product = get_post($productID);
    $base = ipema_get_product_base($productID);

    $html = '<table><thead><tr><th></th>';
    $html .= '<th>Current</th><th>Requested</th>';
    $html .= '</tr></thead><tbody>';

    $prefix = get_term_meta($base->term_id, 'name', true);
    if ($prefix)
    {
        $name = ipema_brand_name(
            array('product' => $productID)
        );
        $html .= '<tr><td><strong>Name</strong></td>';
        $html .= "<td>$name</td><td>{$product->post_title}</td></tr>";
    }

    if ($base->name != '~Unnamed~')
    {
        $model = ipema_model_number(
            array('product' => $productID)
        );
        $newModel = get_post_meta($productID, 'model', true);
        $html .= '<tr><td><strong>Model Number</strong></td>'
            . "<td>{$model}</td><td>{$newModel}</td></tr>";
    }

    $description = nl2br(trim($base->description));
    if ($description != '')
    {
        $newDescription = nl2br(trim($product->post_content));
        if ($newDescription)
        {
            $description .= "<br><br>$newDescription";
        }
        $html .= '<tr><td><strong>Description</strong></td>'
            . "<td>$description</td><td>$newDescription</td></tr>";
    }

    $french = nl2br(trim(
        get_term_meta($base->term_id, 'french-description', true)
    ));
    if ($french != '')
    {
        $newFrench = nl2br(trim(
            get_post_meta($productID, 'french-description', true)
        ));
        if ($newFrench)
        {
            $french .= "<br><br>$newFrench";
        }
        $html .= '<tr><td><strong>French-Canadian Description</strong></td>'
            . "<td>$french</td><td>$newFrench</td>";
    }

    $html .= '</tbody></table>';
    return $html;
});

add_shortcode('expiration-date-changes', function($attrs, $content='') {
    $attrs = shortcode_atts(array('rv' => get_the_ID()), $attrs);

    $rv = get_post($attrs['rv']);

    if ($rv->post_type != 'rv')
    {
        return '';
    }

    $html = '';
    $certs = get_the_terms($rv->ID, 'certification');
    foreach ($certs as $cert)
    {
        $exp = get_post_meta($rv->ID, $cert->slug, true);
        $exp = date('F jS, Y', strtotime($exp));
        $html .= "<p>Changed {$cert->name} expiration date to $exp</p>";
    }

    return $html;
});

add_shortcode('select-product-line', function($attrs, $content='') {
    $user = wp_get_current_user();

    $productLines = get_terms(array(
        'taxonomy' => 'product-line',
        'hide_empty' => false,
        'meta_query' => array(array(
            'key' => 'company_id',
            'value' => $user->company_id
        ))
    ));

    $html = '<select name="wpv-product-line" class="wpcf-form-select '
        . 'form-select select"><option value="">Any</option>';

    foreach ($productLines as $productLine)
    {
        $html .= '<option value="' . $productLine->slug . '"';
        if ($_GET['wpv-product-line'] == $productLine->slug)
        {
            $html .= ' selected="selected"';
        }
        $html .= ">{$productLine->name}</option>";
    }
    $html .= '</select>';

    // Don't lose existing queries
    $hidden = array('t', 'family', 'retest');
    foreach ($hidden as $field)
    {
        if ($_GET[$field])
        {
            $html .= '<input type="hidden" name="' . $field . '" value="'
                . $_GET[$field] .'">';
        }
    }

    return $html;

    $ids = array('');
    $labels = array('Any');
    $comma = urlencode(',');
    foreach ($productLines as $productLine)
    {
        $ids[] = $productLine->slug;
        $labels[] = str_replace(',', $comma, $productLine->name);
    }

    $code = '[wpv-control url_param="wpv-product-line" type="select" '
        . 'hide_empty="false" source="custom" display_values="'
        . implode(',', $labels) . '" values="' . implode(',', $ids) . '"]';

    return do_shortcode($code);
});

add_shortcode('keep-rv-safe', function($attrs, $content='') {
    if ( ! is_page('printable'))
    {
        return '';
    }
    if (get_post_meta($_GET['request'], 'printable', true) < time())
    {
        while (ob_end_clean()) {}
        die('Unknown Request for Validation');
    }
    delete_post_meta($_GET['request'], 'printable');
    return '';
});

add_shortcode('rv-badges', function($attrs, $content='') {
    $certs = get_the_terms($_GET['request'], 'certification');
    if ( ! $certs)
    {
        return '';
    }

    $html = '';
    foreach ($certs as $cert)
    {
        $img = get_term_meta($cert->term_id, 'seal', true);
        $html .= '<img src="' . $img . '">';
    }

    return $html;
});

add_shortcode('new-or-retest', function($attrs, $content='') {
    $attrs = shortcode_atts(array('rv' => get_the_ID()), $attrs);

    $rv = get_post($rv);
    $products = get_post_meta($attrs['rv'], 'affected_id');

    $action = 'New';

    $cutoff = strtotime($rv->post_date);
    $cutoff = strtotime('-1 month', $cutoff);

    $older = get_posts(array(
        'post_type' => 'product',
        'post__in' => $products,
        'date_query' => array(
            'before' => date('Y-m-d H:i:s', $cutoff)
        ),
        'posts_per_page' => 1,
        'fields' => 'ids'
    ));

    if (count($older) > 0)
    {
        $action = 'Retest';
    }

    return "<span class='guess'>$action</span>";
});

add_shortcode('get-param', function($attrs, $content='') {
    if ( ! array_key_exists('param', $attrs))
    {
        return '';
    }

    return $_GET[$attrs['param']];
});

add_shortcode('test', function($attrs, $content='') {
    $user = wp_get_current_user();
    var_dump($user);

    var_dump(current_user_can('can_manage_products'));
});

add_filter('upload_mimes', function($mimes) {
    // New allowed mime types.
    $mimes['xls'] = 'application/msexcel';
    $mimes['xlxs'] = 'application/msexcel';

    return $mimes;
});