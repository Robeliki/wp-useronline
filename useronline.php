<?php
/*
Plugin Name: WP-UserOnline
Plugin URI: http://www.lesterchan.net/portfolio/programming.php
Description: Adds A Useronline Feature To WordPress
Version: 2.02
Author: GaMerZ
Author URI: http://www.lesterchan.net
*/


/*  Copyright 2005  Lester Chan  (email : gamerz84@hotmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/


### UserOnline Table Name
$wpdb->useronline = $table_prefix . 'useronline';


### Search Bots Name
$bots = array('Google Bot' => 'googlebot', 'Google Bot' => 'google', 'MSN' => 'msnbot', 'Alex' => 'ia_archiver', 'Lycos' => 'lycos', 'Ask Jeeves' => 'jeeves', 'Altavista' => 'scooter', 'AllTheWeb' => 'fast-webcrawler', 'Inktomi' => 'slurp@inktomi', 'Turnitin.com' => 'turnitinbot', 'Technorati' => 'technorati', 'Yahoo' => 'yahoo', 'Findexa' => 'findexa', 'NextLinks' => 'findlinks', 'Gais' => 'gaisbo', 'WiseNut' => 'zyborg', 'WhoisSource' => 'surveybot', 'Bloglines' => 'bloglines', 'BlogSearch' => 'blogsearch', 'PubSub' => 'ubsub', 'Syndic8' => 'syndic8', 'RadioUserland' => 'userland', 'Gigabot' => 'gigabot', 'Become.com bot' => 'become.com', 'Technorati Blog Bot'=>'technorati');


### Function: Get IP
function get_IP() {
	if (empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
		$ip_address = $_SERVER["REMOTE_ADDR"];
	} else {
		$ip_address = $_SERVER["HTTP_X_FORWARDED_FOR"];
	}
	if(strpos($ip_address, ',') !== false) {
		$ip_address = explode(',', $ip_address);
		$ip_address = $ip_address[0];
	}
	return $ip_address;
}


### Function: Process UserOnline
add_action('wp_head', 'useronline');
function useronline() {
	global $wpdb, $useronline, $bots;

	// Useronline Settings
	$timeoutseconds = 300;
	$timestamp = current_time('timestamp');
	$timeout = ($timestamp-$timeoutseconds);
	$ip = get_IP();
	$url = addslashes(urlencode($_SERVER['REQUEST_URI']));

	// Check For Members
	if(!empty($_COOKIE['comment_author_'.COOKIEHASH]))  {
		$memberonline = addslashes(trim($_COOKIE['comment_author_'.COOKIEHASH]));
		$where = "WHERE username='$memberonline'";
	// Check For Admins
	} elseif(!empty($_COOKIE['wordpressuser_'.COOKIEHASH])) {
		$memberonline = addslashes(trim($_COOKIE['wordpressuser_'.COOKIEHASH]));
		$where = "WHERE username='$memberonline'";
	// Check For Guests
	} else { 
		$memberonline = 'Guest';
		$where = "WHERE ip='$ip'";
	}
	// Check For Bot
	foreach ($bots as $name => $lookfor) { 
		if (stristr($_SERVER['HTTP_USER_AGENT'], $lookfor) !== false) { 
			$memberonline = addslashes($name);
			$where = "WHERE ip='$ip'";
		} 
	}
	// Check For Page Title
	$make_page = wp_title('&raquo;', false);
	if(empty($make_page)) {
		$make_page = get_bloginfo('name');
	} elseif(is_single()) {
		$make_page = get_bloginfo('name').' &raquo; Blog Archive '.$make_page;
	} else {
		$make_page = get_bloginfo('name').$make_page;
	}
	$make_page = addslashes($make_page);
	
	// Check User First
	$check_user = intval($wpdb->get_var("SELECT COUNT(*) FROM $wpdb->useronline $where"));	

	// If User Exists, Update User
	if($check_user > 0) {
		$update_user = $wpdb->query("UPDATE $wpdb->useronline SET timestamp = '$timestamp', ip = '$ip', location = '$make_page', url = '$url' $where");		
	// Else Insert User
	} else {
		$insert_user = $wpdb->query("INSERT INTO $wpdb->useronline VALUES ('$timestamp', '$memberonline', '$ip', '$make_page', '$url')");
	}

	// Delete Users
	$delete_users = $wpdb->query("DELETE FROM $wpdb->useronline WHERE timestamp < $timeout");

	// Count Users Online
	$useronline = intval($wpdb->get_var("SELECT COUNT(*) FROM $wpdb->useronline"));
	
	// Get Most User Online
	$most_useronline = intval(get_settings('useronline_most_users'));

	// Check Whether Current Users Online Is More Than Most Users Online
	if($useronline > $most_useronline) {
		update_option('useronline_most_users', $useronline);
		update_option('useronline_most_timestamp', current_time('timestamp'));
	}
}


### Function: Display UserOnline
function get_useronline($user = 'User', $users = 'Users', $display = true) {
	global $useronline;
	// Display User Online
	if($display) {
		if($useronline > 1) {
			echo "<b>$useronline</b> $users ".__('Online');
		} else {
			echo "<b>$useronline</b> $user ".__('Online');
		}
	} else {
		return $useronline;
	}
}


### Function: Display Max UserOnline
if(!function_exists('get_most_useronline')) {
	function get_most_useronline($display = true) {
		$most_useronline_users = intval(get_settings('useronline_most_users'));
		if($display) {
			echo $most_useronline_users;
		} else {
			return $most_useronline_users;
		}
	}
}


### Function: Display Max UserOnline Date
if(!function_exists('get_most_useronline_date')) {
	function get_most_useronline_date($date_format = 'jS F Y, H:i', $display =true) {
		$most_useronline_timestamp = get_settings('useronline_most_timestamp');
		$most_useronline_date = gmdate($date_format, $most_useronline_timestamp);
		if($display) {
			echo $most_useronline_date;
		} else {
			return$most_useronline_date;
		}
	}
}


### Function: Display Users Browsing The Page
function get_users_browsing_page() {
	global $wpdb, $bots;

	// Get Users Browsing Page
	$page_url = addslashes(urlencode($_SERVER['REQUEST_URI']));
	$users_browse = $wpdb->get_results("SELECT username FROM $wpdb->useronline WHERE url = '$page_url'");

	// Variables
	$members = array();
	$total_users = 0;
	$total_members = 0;
	$total_guests = 0;
	$total_bots = 0;
	$nicetext_members = '';
	$nicetext_guests = '';
	$nicetext_bots = '';

	// If There Is Users Browsing, Then We Execute
	if($users_browse) {
		// Reassign Bots Name
		$bots_name = array();
		foreach($bots as $botname => $botlookfor) {
			$bots_name[] = $botname;
		}
		// Get Users Information
		foreach($users_browse as $user_browse) {
			if($user_browse->username == 'Guest') {
				$total_guests++;
			} elseif(in_array($user_browse->username, $bots_name)) {
				$total_bots++;
			} else {
				$members[] = stripslashes($user_browse->username);
				$total_members++;
			}
		}
		$total_users = ($total_guests+$total_bots+$total_members);

		// Nice Text For Members
		if($total_members > 1) {
			$nicetext_members = $total_members.' '.__('Members');
		} else {
			$nicetext_members = $total_members.' '.__('Member');
		}
		// Nice Text For Guests
		if($total_guests > 1) { 
			$nicetext_guests = $total_guests.' '.__('Guests');
		} else {
			$nicetext_guests = $total_guests.' '.__('Guest'); 
		}
		// Nice Text For Bots
		if($total_bots > 1) {
			$nicetext_bots = $total_bots.' '.__('Bots'); 
		} else {
			$nicetext_bots = $total_bots.' '.__('Bot'); 
		}
		
		// Print User Count
		echo __('Users Browsing This Page: ').'<b>'.$total_users.'</b> ('.$nicetext_members.', '.$nicetext_guests.' '.__('and').' '.$nicetext_bots.')<br />';

		// Print Member Name
		if($members) {
			$temp_member = '';
			foreach($members as $member) {
				$temp_member .= '<a href="'.get_settings('home').'/wp-stats.php?author='.urlencode($member).'">'.$member.'</a>, ';
			}
			echo __('Members').': '.substr($temp_member, 0, -2);
		}
	} else {
		// This Should Not Happen
		_e('No User Is Browsing This Page');
	}
}


### Function: Create UserOnline Table
add_action('activate_useronline.php', 'create_useronline_table');
function create_useronline_table() {
	global $wpdb;
	include(ABSPATH.'/wp-admin/upgrade-functions.php');
	// Drop UserOnline Table
	$wpdb->query("DROP TABLE IF EXISTS $wpdb->useronline");
	// Create UserOnline Table
	$create_table = "CREATE TABLE $wpdb->useronline (".
						  " timestamp int(15) NOT NULL default '0',".
						  " username varchar(50) NOT NULL default '',".
						  " ip varchar(40) NOT NULL default '',".
						  " location varchar(255) NOT NULL default '',".
						  " url varchar(255) NOT NULL default '',".
						  " PRIMARY KEY (timestamp))";
	maybe_create_table($wpdb->useronline, $create_table);
	// Add In Options
	add_option('useronline_most_users', 1, 'Most Users Ever Online Count');
	add_option('useronline_most_timestamp', current_time('timestamp'), 'Most Users Ever Online Date');
}
?>