<?php
/*
   Plugin Name: Harp Column Short Codes
   Plugin URI: http://www.harpcolumn.com
   Description: Short codes to display custom membership data for Harp Column
   Version: 1.0.3
   Requires: 4.5.3
   Author: Hugh Brock <hbrock@harpcolumn.com>
   Author URI: http://www.hewbrocca.com
   GitHub Plugin URI: hbrock25/hc-short-codes
   License: GPL2
   License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

function pmpro_expiration_date_shortcode( $atts ) {

    //make sure PMPro is active
    if(!function_exists('pmpro_getMembershipLevelForUser'))
	return;
    
    //get attributes
    $a = shortcode_atts( array(
	'user' => '',
    ), $atts );
    
    //find user
    if(!empty($a['user']) && is_numeric($a['user'])) {
	$user_id = $a['user'];
    } elseif(!empty($a['user']) && strpos($a['user'], '@') !== false) {
	$user = get_user_by('email', $a['user']);
	$user_id = $user->ID;
    } elseif(!empty($a['user'])) {
	$user = get_user_by('login', $a['user']);
	$user_id = $user->ID;
    } else {
	$user_id = false;
    }

    //use globals if no values supplied
    if(!$user_id)
	$user_id = get_current_user_id();

    //no user ID? bail
    if(!$user_id)
	return '<a href="/my-account">Login</a> | <a href="/my-account">Register</a>';

    //get the user's level
    $level = pmpro_getMembershipLevelForUser($user_id);

    if(!empty($level) && !empty($level->enddate) && $level->id > 1)
	$content = 'Your subscription expires on ' . date(get_option('date_format'), $level->enddate) . '. <a href="/renew">Renew</a> | <a href="/my-account">My Account</a> | <a href="' . wc_logout_url()  . '">Logout</a>';
    else
	$content = '<a href="/subscribe">Subscribe</a> | <a href="/my-account">My Account</a> | <a href="' . wc_logout_url()  . '">Logout</a>';

    return $content;
}

add_shortcode('pmpro_expiration_date', 'pmpro_expiration_date_shortcode');


/*
   Shortcode to show membership account information
 */

function pmpro_hc_shortcode_account($atts, $content=null, $code="") {
    global $wpdb, $pmpro_msg, $pmpro_msgt, $pmpro_levels, $current_user, $levels;
    
    // $atts    ::= array of attributes
    // $content ::= text within enclosing form of shortcode element
    // $code    ::= the shortcode found, when == callback name
    // examples: [pmpro_account]

    ob_start();
    
    //if a member is logged in, show them some info here (1. past invoices. 2. billing information with button to update.)
    if(pmpro_hasMembershipLevel())
    {
	$ssorder = new MemberOrder();
	$ssorder->getLastMemberOrder();
	$mylevels = pmpro_getMembershipLevelsForUser();
	$pmpro_levels = pmpro_getAllLevels(false, true); // just to be sure - include only the ones that allow signups
	$invoices = $wpdb->get_results("SELECT *, UNIX_TIMESTAMP(timestamp) as timestamp FROM $wpdb->pmpro_membership_orders WHERE user_id = '$current_user->ID' AND status NOT IN('refunded', 'review', 'token', 'error') ORDER BY timestamp DESC LIMIT 6");
?>	
    <div id="pmpro_account">		

	<div id="pmpro_account-membership" class="pmpro_box">
	    
	    <h2><?php _e("My Subscription", "pmpro");?></h2>
	    <table width="100%" cellpadding="0" cellspacing="0" border="0">
		<thead>
		    <tr>
			<th><?php _e("Subscription", "pmpro");?></th>
			<th><?php _e("Price", "pmpro"); ?></th>
			<th><?php _e("Expiration", "pmpro"); ?></th>
		    </tr>
		</thead>
		<tbody>
		    <?php
		    foreach($mylevels as $level) {
		    ?>
			<tr>
			    <td class="pmpro_account-membership-levelname">
				<?php echo $level->name?>
				<div class="pmpro_actionlinks">
				    <?php do_action("pmpro_member_action_links_before"); ?>
				    
				    <?php if( array_key_exists($level->id, $pmpro_levels) && pmpro_isLevelExpiringSoon( $level ) ) { ?>
					<a href="/subscribe"><?php _e("Renew", "pmpro");?></a>
				    <?php } ?>

				    <a href="<?php echo pmpro_url("cancel", "?levelstocancel=" . $level->id)?>"><?php _e("Cancel", "pmpro");?></a>
				    <?php do_action("pmpro_member_action_links_after"); ?>
				</div> <!-- end pmpro_actionlinks -->
			    </td>
			    <td class="pmpro_account-membership-levelfee">
				<p><?php echo pmpro_getLevelCost($level, true, true);?></p>
			    </td>
			    <td class="pmpro_account-membership-expiration">
				<?php 
				if($level->enddate) 
				    echo date_i18n(get_option('date_format'), $level->enddate);
				else
				    echo "---";
				?>
			    </td>
			</tr>
		    <?php } ?>
		</tbody>
	    </table>
	</div> <!-- end pmpro_account-membership -->
	
    </div> <!-- end pmpro_account -->		
<?php
}

$content = ob_get_contents();
ob_end_clean();

return $content;
}
add_shortcode('pmpro_hc_account', 'pmpro_hc_shortcode_account');


/*
   Probably won't use this but maybe it is useful
 */

function my_login_redirect( $redirect_to, $request, $user ) {
    //validating user login and roles
    if (isset($user->roles) && is_array($user->roles)) {
	//is this a gold plan subscriber?
	if (in_array('gold_member', $user->roles)) {
	    // redirect them to their special plan page
	    $redirect_to = "https://mysite.com/gold-member";
	} else {
	    //all other members
	    $redirect_to = "https://mysite.com/members";;
	}
    }
    return $redirect_to;
}

// add_filter( 'login_redirect', 'my_login_redirect', 10, 3 );

/**
 * Redirect users to custom URL based on their role after login
 *
 * @param string $redirect
 * @param object $user
 * @return string
 */
function wc_custom_user_redirect( $redirect, $user ) {
	// Get the first of all the roles assigned to the user
	$role = $user->roles[0];

	$dashboard = admin_url();
	$myaccount = get_permalink( wc_get_page_id( 'myaccount' ) );

	if( $role == 'administrator' ) {
		//Redirect administrators to the dashboard
		$redirect = $dashboard;
	} elseif ( $role == 'shop-manager' ) {
		//Redirect shop managers to the dashboard
		$redirect = $dashboard;
	} elseif ( $role == 'editor' ) {
		//Redirect editors to the dashboard
		$redirect = $dashboard;
	} elseif ( $role == 'author' ) {
		//Redirect authors to the dashboard
		$redirect = $dashboard;
	} elseif ( $role == 'customer' || $role == 'subscriber' ) {
		//Redirect customers and subscribers to the "My Account" page
		$redirect = $myaccount;
	} else {
		//Redirect any other role to the previous visited page or, if not available, to the home
		$redirect = wp_get_referer() ? wp_get_referer() : home_url();
	}

	return $redirect;
}
// add_filter( 'woocommerce_login_redirect', 'wc_custom_user_redirect', 10, 2 );

/**
 * Redirect after registration.
 *
 * @param $redirect
 *
 * @return string
 */


function iconic_register_redirect( $redirect ) {
    return wc_get_page_permalink( 'shop' );
}

// add_filter( 'woocommerce_registration_redirect', 'iconic_register_redirect' );

/** 
 *  Stop non-members from purchasing products if they do not have an active Paid Memberships Pro Level.
 */

function stop_non_pmpro_members_from_buying_woo( $is_purchasable, $product ) {

    // is_purchasable might already be false, so don't set it to true --
    // just change it to false for the cases we definitely want to deny.

    // Product requires an all access membership (1) or a founding membership (17)
    if( has_term( 'membership-required', 'product_cat', $product->get_id() ) ) {
        if ( ! pmpro_hasMembershipLevel(1) && ! pmpro_hasMembershipLevel(17)) {
	    $is_purchasable = false;
	}
    }

    // Product requires a founding membership (17)
    if( has_term( 'founding-member', 'product_cat', $product->get_id() ) ) {
        if ( ! pmpro_hasMembershipLevel(17)) {
	    $is_purchasable = false;
	}
    }

    return $is_purchasable;

}

add_filter( 'woocommerce_is_purchasable', 'stop_non_pmpro_members_from_buying_woo', 10, 2 );

function pmpro_level_name_shortcode( $atts ) {
    //make sure PMPro is active                                                                     
    if(!function_exists('pmpro_getMembershipLevelForUser'))
        return;

    //get attributes                                                                                
    $a = shortcode_atts( array(
        'user' => '',
    ), $atts );

    //find user                                                                                     
    if(!empty($a['user']) && is_numeric($a['user'])) {
        $user_id = $a['user'];
    } elseif(!empty($a['user']) && strpos($a['user'], '@') !== false) {
        $user = get_user_by('email', $a['user']);
        $user_id = $user->ID;
    } elseif(!empty($a['user'])) {
        $user = get_user_by('login', $a['user']);
        $user_id = $user->ID;
    } else {
        $user_id = false;
    }

    //no user ID? bail                                                                              
    if(!isset($user_id))
        return;

    //get the user's level                                                                          
    $level = pmpro_getMembershipLevelForUser($user_id);

    if(!empty($level) && !empty($level->enddate))
        $content = $level->name;
    else
        $content = "None";

    return $content;
}

add_shortcode('pmpro_level_name', 'pmpro_level_name_shortcode');

function pmpro_expire_text_shortcode( $atts ) {
    //make sure PMPro is active                                                                     
    if(!function_exists('pmpro_getMembershipLevelsForUser'))
        return;

    //get attributes                                                                                
    $a = shortcode_atts( array(
        'user' => '',
    ), $atts );

    //find user                                                                                     
    if(!empty($a['user']) && is_numeric($a['user'])) {
        $user_id = $a['user'];
    } elseif(!empty($a['user']) && strpos($a['user'], '@') !== false) {
        $user = get_user_by('email', $a['user']);
        $user_id = $user->ID;
    } elseif(!empty($a['user'])) {
        $user = get_user_by('login', $a['user']);
        $user_id = $user->ID;
    } else {
        $user_id = false;
    }

    //no user ID? bail                                                                              
    if(!isset($user_id))
        return;

    // Only show this to users with level 17, otherwise use the Woo shortcode.
    $content = "";
    if (pmpro_hasMembershipLevel()) {
	$mylevels = pmpro_getMembershipLevelsForUser();
	foreach ($mylevels as $level) {
	    if( $level->id == 17 && !empty($level->enddate)) {
		$content = "<p><strong>Your membership level:</strong> " . $level->name . "<br /><strong>Expires: </strong> " . date(get_option('date_format'), $level->enddate) . "</p>";
	    }
	}
    }
    return $content;
}

add_shortcode('pmpro_expire_text', 'pmpro_expire_text_shortcode');

/* Avada code that adds the secondary header actions -- to override */

/* get status info for the header */
function pmpro_status_widget() {

    //make sure PMPro is active
    if(!function_exists('pmpro_getMembershipLevelsForUser'))
	return;

    //no user ID? bail
    if(!is_user_logged_in()) {
	return '<a href="/academy/my-account">Login</a>';
    }

    $user = wp_get_current_user();

    // logged in but no membership
    if(!pmpro_hasMembershipLevel())
	return 'Hello ' . $user->first_name . '! | <a href="/academy/get-started">Get Started With HCA</a>';

    //get the user's levels, only do something if they're level 17
    $mylevels = pmpro_getMembershipLevelsForUser();
    $content = "";
    foreach ($mylevels as $level) {
	if( $level->id == 17 && !empty($level->enddate)) {
	    $content = 'Your HCA Founding Membership expires on ' . date(get_option('date_format'), $level->enddate) . '. | <a href="/academy/current-members">Renew</a> | <a href="/academy/my-account">My Account</a> | <a href="' . wc_logout_url() . '">Logout</a>';
	} else {
		$content = '<a href="/academy/my-account">My Account</a> | <a href="' . wc_logout_url() . '">Logout</a>';
	}
    }
    return $content;
}

add_shortcode('hc_pmpro_status_widget', 'pmpro_status_widget');

/*
   Shortcode to return: 
 ** text "notloggedin" if user is not logged in
 ** text "loggedin" if user is logged in but not a current member
 ** text "member" if user is a current member
 */

function hc_login_conditional_text_shortcode ($atts) {
    
    $a = shortcode_atts( array('notloggedin' => '', 'loggedin' => '', 'member' => '',), $atts, 'hc_login_conditional_text');
    
    $user_id = get_current_user_id();

    //no user ID? bail
    if(!$user_id)
	return $a['notloggedin'];

    //make sure PMPro is active, if not return "logged in" string
    if(!function_exists('pmpro_getMembershipLevelForUser'))
	return $a['loggedin'];

    //get the user's level
    $level = pmpro_getMembershipLevelForUser($user_id);

    if(!empty($level) && ($level->id == 1 || $level->id == 17))
	return $a['member'];
    else
	return $a['loggedin'];

}
add_shortcode('hc_login_conditional_text', 'hc_login_conditional_text_shortcode');

/*
   Shortcode to return: 
 ** "Sign in" link if user is not logged in
 ** "My Account" link if user is logged in
 */

function hc_sign_in_my_account_shortcode ($atts) {
    
    $user_id = get_current_user_id();

    if(!$user_id) {
	return "Sign in";
    } else {
	return "My Account";
    }
}

add_shortcode('hc_sign_in_my_account', 'hc_sign_in_my_account_shortcode');
