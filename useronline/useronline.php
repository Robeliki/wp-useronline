<?php
/*
Plugin Name: WP-UserOnline
Plugin URI: http://www.lesterchan.net/portfolio/programming.php
Description: Adds A Useronline Feature To WordPress
Version: 2.05
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


### Function: WP-UserOnline Menu
add_action('admin_menu', 'useronline_menu');
function useronline_menu() {
	if (function_exists('add_submenu_page')) {
		add_submenu_page('index.php',  __('WP-UserOnline'),  __('WP-UserOnline'), 1, 'useronline/useronline.php', 'display_useronline');
	}
	if (function_exists('add_options_page')) {
		add_options_page(__('Useronline'), __('Useronline'), 'manage_options', 'useronline/useronline-options.php') ;
	}
}


### Function: Displays UserOnline Header
add_action('wp_head', 'useronline_header');
function useronline_header() {
	echo '<script type="text/javascript">'."\n";
	echo '/* Start Of Javascript Generated By WP-UserOnline 2.05 */'."\n";
	echo '/* <![CDATA[ */'."\n";
	echo "\t".'if(site_url != \''.get_settings('siteurl').'\' || ajax_url != \''.$_SERVER['SCRIPT_NAME'].'\') {'."\n";
	echo "\t\t".'var site_url = \''.get_settings('siteurl').'\';'."\n";
	echo "\t\t".'var ajax_url = \''.$_SERVER['SCRIPT_NAME'].'\';'."\n";
	echo "\t".'}'."\n";
	echo "\t".'var useronline_timeout = '.(get_settings('useronline_timeout')*1000).';'."\n";
	echo '/* ]]> */'."\n";
	echo '/* End Of Javascript Generated By WP-UserOnline 2.05 */'."\n";
	echo '</script>'."\n";
	echo '<script src="'.get_settings('siteurl').'/wp-includes/js/tw-sack.js" type="text/javascript"></script>'."\n";
	echo '<script src="'.get_settings('siteurl').'/wp-content/plugins/useronline/useronline-js.js" type="text/javascript"></script>'."\n";
}


### Function: Process AJAX Request
add_action('init', 'useronline_ajax');
function useronline_ajax() {
	global $wpdb, $useronline;
	$mode = trim($_GET['useronline_mode']);
	if(!empty($mode)) {
		switch($mode) {
			case 'useronline_count':
				$useronline = intval($wpdb->get_var("SELECT COUNT(*) FROM $wpdb->useronline"));
				get_useronline();
				break;
			case 'useronline_browsingsite':
				get_users_browsing_site();				
				break;
			case 'useronline_browsingpage':
				get_users_browsing_page();
				break;
		}
		exit();
	}
}


### Function: Process UserOnline
add_action('admin_head', 'useronline');
add_action('wp_head', 'useronline');
function useronline() {
	global $wpdb, $useronline;
	// Useronline Settings
	$timeoutseconds = get_settings('useronline_timeout');
	$timestamp = current_time('timestamp');
	$timeout = ($timestamp-$timeoutseconds);
	$ip = get_ipaddress();
	$url = addslashes(urlencode($_SERVER['REQUEST_URI']));
	$useragent = $_SERVER['HTTP_USER_AGENT'];
	$current_user = wp_get_current_user();

	// Check For Bot
	$bots = get_settings('useronline_bots');
	foreach ($bots as $name => $lookfor) { 
		if (stristr($useragent, $lookfor) !== false) { 
			$user_id = 0;
			$display_name = addslashes($name);
			$user_name = addslashes($lookfor);		
			$type = 'bot';
			$where = "WHERE ip = '$ip'";
			$bot_found = true;
			break;
		} 
	}

	// If No Bot Is Found, Then We Check Members And Guests
	if(!$bot_found) {
		// Check For Member
		if($current_user->ID > 0) {
			$user_id = $current_user->ID;
			$display_name = addslashes($current_user->display_name);
			$user_name = addslashes($current_user->user_login);		
			$type = 'member';
			$where = "WHERE userid = '$user_id'";
		// Check For Comment Author (Guest)
		} elseif(!empty($_COOKIE['comment_author_'.COOKIEHASH])) {
			$user_id = 0;
			$display_name = addslashes(trim($_COOKIE['comment_author_'.COOKIEHASH]));
			$user_name = "guest_$display_name";		
			$type = 'guest';
			$where = "WHERE ip = '$ip'";
		// Check For Guest
		} else {
			$user_id = 0;
			$display_name = 'Guest';
			$user_name = "guest";		
			$type = 'guest';
			$where = "WHERE ip = '$ip'";
		}
	}

	// Get User Agent
	$useragent = addslashes($useragent);

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
	
	// Delete Users
	$delete_users = $wpdb->query("DELETE FROM $wpdb->useronline $where OR (timestamp < $timeout)");
	
	// Insert Users
	$insert_user = $wpdb->query("INSERT INTO $wpdb->useronline VALUES ('$timestamp', '$user_id', '$user_name', '$display_name', '$useragent', '$ip', '$make_page', '$url', '$type')");

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
if(!function_exists('get_useronline')) {
	function get_useronline($user = 'User', $users = 'Users', $display = true) {
		global $useronline;
		$useronline_url = get_settings('useronline_url');
		// Display User Online
		if($display) {
			if($useronline > 1) {
				echo '<a href="'.$useronline_url.'"><b>'.number_format($useronline).'</b> '.$users.' '.__('Online').'</a>'."\n";
			} else {
				echo '<a href="'.$useronline_url.'"><b>'.$useronline.'</b> '.$user.' '.__('Online').'</a>'."\n";
			}
		} else {
			return $useronline;
		}
	}
}

### Function: Display Max UserOnline
if(!function_exists('get_most_useronline')) {
	function get_most_useronline($display = true) {
		$most_useronline_users = intval(get_settings('useronline_most_users'));
		if($display) {
			echo number_format($most_useronline_users);
		} else {
			return $most_useronline_users;
		}
	}
}


### Function: Display Max UserOnline Date
if(!function_exists('get_most_useronline_date')) {
	function get_most_useronline_date($display = true, $date_format = 'jS F Y, H:i') {
		$most_useronline_timestamp = get_settings('useronline_most_timestamp');
		$most_useronline_date = gmdate($date_format, $most_useronline_timestamp);
		if($display) {
			echo $most_useronline_date;
		} else {
			return$most_useronline_date;
		}
	}
}


### Function: Display Users Browsing The Site
function get_users_browsing_site($display = true) {
	global $wpdb;

	// Get Users Browsing Site
	$page_url = addslashes(urlencode($_SERVER['REQUEST_URI']));
	$users_browse = $wpdb->get_results("SELECT displayname, type FROM $wpdb->useronline ORDER BY type");

	// Variables
	$members = array();
	$guests = array();
	$bots = array();
	$total_users = 0;
	$total_members = 0;
	$total_guests = 0;
	$total_bots = 0;
	$nicetext_members = '';
	$nicetext_guests = '';
	$nicetext_bots = '';

	// If There Is Users Browsing, Then We Execute
	if($users_browse) {
		// Get Users Information
		foreach($users_browse as $user_browse) {
			switch($user_browse->type) {
				case 'member':
					$members[] = stripslashes($user_browse->displayname);
					$total_members++;
					break;
				case 'guest':						
					$guests[] = stripslashes($user_browse->displayname);
					$total_guests++;
					break;
				case 'bot':
					$bots[] = stripslashes($user_browse->displayname);
					$total_bots++;
					break;
			}
		}
		$total_users = ($total_guests+$total_bots+$total_members);

		// If We Do Not Display It, Return Respective Users Count
		if(!$display) {
			return array ($total_users, $total_members, $total_guests, $total_bots);
		} 

		// Nice Text For Guests
		if($total_guests == 1) { 
			$nicetext_guests = $total_guests.' '.__('Guest');
		} else {
			$nicetext_guests = number_format($total_guests).' '.__('Guests'); 
		}
		// Nice Text For Bots
		if($total_bots == 1) {
			$nicetext_bots = $total_bots.' '.__('Bot'); 
		} else {
			$nicetext_bots = number_format($total_bots).' '.__('Bots'); 
		}

		// Print Member Name
		if($members) {
			$temp_member = '';
			foreach($members as $member) {
				$temp_member .= '<a href="'.get_settings('home').'/wp-stats.php?author='.urlencode($member).'">'.$member.'</a>, ';
			}
			if(!function_exists('get_totalposts')) {
				$temp_member = strip_tags($temp_member);
			}
		}
		// Print Guests
		if($total_guests > 0) {
			$temp_member .= $nicetext_guests.', ';
		}
		// Print Bots
		if($total_bots > 0) {
			$temp_member .= $nicetext_bots.', ';
		}
		// Print User Count
		$temp_member = substr($temp_member, 0, -2);
		echo __('Users: ').'<b>'.$temp_member.'</b><br />';
	} else {
		// This Should Not Happen
		_e('No User Is Browsing This Site');
	}
}


### Function: Display Users Browsing The Page
function get_users_browsing_page($display = true) {
	global $wpdb;

	// Get Users Browsing Page
	$page_url = addslashes(urlencode($_SERVER['REQUEST_URI']));
	$users_browse = $wpdb->get_results("SELECT displayname, type FROM $wpdb->useronline WHERE url = '$page_url' ORDER BY type");

	// Variables
	$members = array();
	$guests = array();
	$bots = array();
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
		$bots = get_settings('useronline_bots');
		$bots_name = array();
		foreach($bots as $botname => $botlookfor) {
			$bots_name[] = $botname;
		}
		// Get Users Information
		foreach($users_browse as $user_browse) {
			switch($user_browse->type) {
				case 'member':
					$members[] = stripslashes($user_browse->displayname);
					$total_members++;
					break;
				case 'guest':						
					$guests[] = stripslashes($user_browse->displayname);
					$total_guests++;
					break;
				case 'bot':
					$bots[] = stripslashes($user_browse->displayname);
					$total_bots++;
					break;
			}
		}
		$total_users = ($total_guests+$total_bots+$total_members);

		// If We Do Not Display It, Return Respective Users Count
		if(!$display) {
			return array ($total_users, $total_members, $total_guests, $total_bots);
		} 

		// Nice Text For Members
		if($total_members == 1) {
			$nicetext_members = $total_members.' '.__('Member');
		} else {
			$nicetext_members = number_format($total_members).' '.__('Members');
		}
		// Nice Text For Guests
		if($total_guests == 1) { 
			$nicetext_guests = $total_guests.' '.__('Guest');
		} else {
			$nicetext_guests = number_format($total_guests).' '.__('Guests'); 
		}
		// Nice Text For Bots
		if($total_bots == 1) {
			$nicetext_bots = $total_bots.' '.__('Bot'); 
		} else {
			$nicetext_bots = number_format($total_bots).' '.__('Bots'); 
		}
		
		// Print User Count
		echo __('Users Browsing This Page: ').'<b>'.number_format($total_users).'</b> ('.$nicetext_members.', '.$nicetext_guests.' '.__('and').' '.$nicetext_bots.')<br />';

		// Print Member Name
		if($members) {
			$temp_member = '';
			foreach($members as $member) {
				$temp_member .= '<a href="'.get_settings('home').'/wp-stats.php?author='.urlencode($member).'">'.$member.'</a>, ';
			}
			if(!function_exists('get_totalposts')) {
				$temp_member = strip_tags($temp_member);
			}
			echo __('Members').': '.substr($temp_member, 0, -2);
		}
	} else {
		// This Should Not Happen
		_e('No User Is Browsing This Page');
	}
}


### Function: Get IP Address
if(!function_exists('get_ipaddress')) {
	function get_ipaddress() {
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
}


### Function: Check IP
function check_ip($ip) {
	$current_user = wp_get_current_user();
	$user_level = intval($current_user->wp_user_level);
	$ip2long = ip2long($ip);
	if($user_level == 10 && ($ip != 'unknown') && $ip == long2ip($ip2long) && $ip2long !== false) {
		return "(<a href=\"http://ws.arin.net/cgi-bin/whois.pl?queryinput=$ip\" target=\"_blank\" title=\"".gethostbyaddr($ip)."\">$ip</a>)";
	}
}


### Function Check If User Is Online
function is_online($user_login) { 
	global $wpdb;
	$is_online = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->useronline WHERE username = '$user_login' LIMIT 1");
	return intval($is_online);
}


### Function: Output User's Country Flag/Name
function ip2nation_country($ip, $display_countryname = 0) {
	if(function_exists('wp_ozh_ip2nation')) {
		$country_code = wp_ozh_getCountryCode(0, $ip);
		$country_name = wp_ozh_getCountryName(0, $ip);
		$country_mirror = '';
		$mirrors = array("http://frenchfragfactory.net/images", "http://www.lesterchan.net/wordpress/images/flags");
		if($country_name != 'Private') {
			foreach($mirrors as $mirror) {
				if(file($mirror.'/flag_sg.gif')) {
					$country_mirror = $mirror;
					break;
				}
			}
			$temp = '<img src="'.$mirror.'/flag_'.$country_code.'.gif" alt="'.$country_name.'" />';
			if($display_countryname) {
				$temp .= $country_name;
			}
			return $temp.' ';
		} else {
			return;
		}
	}
	return;
}


### Function: Display UserOnline For Admin
function display_useronline() {
	global $wpdb;
	// Get The Users Online
	$usersonline = $wpdb->get_results("SELECT * FROM $wpdb->useronline ORDER BY type");

	// Variables Variables Variables
	$members = array();
	$guests = array();
	$bots = array();
	$total_users = 0;
	$total_members = 0;
	$total_guests = 0;
	$total_bots = 0;
	$nicetext_users = '';
	$nicetext_members = '';
	$nicetext_guests = '';
	$nicetext_bots = '';

	// Process Those User Who Is Online
	if($usersonline) {
		foreach($usersonline as $useronline) {
			switch($useronline->type) {
				case 'member':
					$members[] = array('timestamp' => $useronline->timestamp, 'user_id' => $useronline->userid, 'user_name' => stripslashes($useronline->username), 'display_name' => stripslashes($useronline->displayname), 'user_agent' => stripslashes($useronline->useragent), 'ip' => $useronline->ip, 'location' => stripslashes($useronline->location), 'url' => stripslashes($useronline->url));
					$total_members++;
					break;
				case 'guest':						
					$guests[] = array('timestamp' => $useronline->timestamp, 'user_id' => $useronline->userid, 'user_name' => stripslashes($useronline->username), 'display_name' => stripslashes($useronline->displayname), 'user_agent' => stripslashes($useronline->useragent), 'ip' => $useronline->ip, 'location' => stripslashes($useronline->location), 'url' => stripslashes($useronline->url));
					$total_guests++;
					break;
				case 'bot':
					$bots[] = array('timestamp' => $useronline->timestamp, 'user_id' => $useronline->userid, 'user_name' => stripslashes($useronline->username), 'display_name' => stripslashes($useronline->displayname), 'user_agent' => stripslashes($useronline->useragent), 'ip' => $useronline->ip, 'location' => stripslashes($useronline->location), 'url' => stripslashes($useronline->url));
					$total_bots++;
					break;
			}
		}
		$total_users = ($total_guests+$total_bots+$total_members);
	}

	//  Nice Text For Users
	if($total_users == 1) {
		$nicetext_users = $total_users.' '.__('User');
	} else {
		$nicetext_users = number_format($total_users).' '.__('Users');
	}

	//  Nice Text For Members
	if($total_members == 1) {
		$nicetext_members = $total_members.' '.__('Member');
	} else {
		$nicetext_members = number_format($total_members).' '.__('Members');
	}


	//  Nice Text For Guests
	if($total_guests == 1) { 
		$nicetext_guests = $total_guests.' '.__('Guest');
	} else {
		$nicetext_guests = number_format($total_guests).' '.__('Guests'); 
	}

	//  Nice Text For Bots
	if($total_bots == 1) {
		$nicetext_bots = $total_bots.' '.__('Bot'); 
	} else {
		$nicetext_bots = number_format($total_bots).' '.__('Bots'); 
	}

	// Check Whether WP-Stats Is Activated
	$wp_stats = false;
	if(function_exists('get_totalposts')) {
		$wp_stats = true;
	}
?>
	<div class="wrap">
		<h2>UserOnline Stats</h2>
		<p><?php if ($total_users == 1) { _e('There is '); } else { _e('There are a total of '); } ?><b><?php echo $nicetext_users; ?></b> online now: <b><?php echo $nicetext_members; ?></b>, <b><?php echo $nicetext_guests; ?></b> and <b><?php echo $nicetext_bots; ?></b>.</p>
		<p>Most users ever online were <b><?php get_most_useronline(); ?></b>, on <b><?php get_most_useronline_date(); ?></b></p>
	</div>
		<?php
			// Print Out Members
			if($total_members > 0) {
				echo 	'<div class="wrap"><h2>'.$nicetext_members.' '.__('Online Now').'</h2>'."\n";
			}
			$no=1;
			if($members) {
				foreach($members as $member) {
					if($wp_stats) {
						echo '<p><b>#'.$no.' - <a href="'.get_settings('siteurl').'/wp-content/plugins/stats/wp-stats.php?author='.$member['display_name'].'">'.$member['display_name'].'</a></b> '.ip2nation_country($member['ip']).check_ip($member['ip']).' on '.gmdate('d.m.Y @ H:i', $member['timestamp']).'<br />'.$member['location'].' [<a href="'.$member['url'].'">url</a>]</p>'."\n";
					} else {
						echo '<p><b>#'.$no.' - '.$member['username'].'</b> '.check_ip($member['ip']).' on '.gmdate('d.m.Y @ H:i', $member['timestamp']).'<br />'.$member['location'].' [<a href="'.$member['url'].'">url</a>]</p>'."\n";
					}
					$no++;
				}
			}
			if($total_members > 0) {
				echo '</div>';
			}

			// Print Out Guest
			if($total_guests > 0) {
				echo 	'<div class="wrap"><h2>'.$nicetext_guests.' '.__('Online Now').'</h2>'."\n";
			}
			$no=1;
			if($guests) {
				foreach($guests as $guest) {
					if($wp_stats) {
						echo '<p><b>#'.$no.' - <a href="'.get_settings('siteurl').'/wp-content/plugins/stats/wp-stats.php?author='.$guest['display_name'].'">'.$guest['display_name'].'</a></b> '.ip2nation_country($guest['ip']).check_ip($guest['ip']).' on '.gmdate('d.m.Y @ H:i', $guest['timestamp']).'<br />'.$guest['location'].' [<a href="'.$guest['url'].'">url</a>]</p>'."\n";
					} else {
						echo '<p><b>#'.$no.' - '.$guest['username'].'</b> '.check_ip($guest['ip']).' on '.gmdate('d.m.Y @ H:i', $guest['timestamp']).'<br />'.$guest['location'].' [<a href="'.$guest['url'].'">url</a>]</p>'."\n";
					}
					$no++;
				}
			}
			if($total_guests > 0) {
				echo '</div>';
			}

			// Print Out Bots
			if($total_bots > 0) {
				echo 	'<div class="wrap"><h2>'.$nicetext_bots.' '.__('Online Now').'</h2>'."\n";
			}
			$no=1;
			if($bots) {
				foreach($bots as $bot) {
					echo '<p><b>#'.$no.' - '.$bot['display_name'].'</b> '.check_ip($bot['ip']).' on '.gmdate('d.m.Y @ H:i', $bot['timestamp']).'<br />'.$bot['location'].' [<a href="'.$bot['url'].'">url</a>]</p>'."\n";
					$no++;
				}
			}
			if($total_bots > 0) {
				echo '</div>';
			}

			// Print Out No One Is Online Now
			if($total_users == 0) {
				echo 	'<div class="wrap"><h2>'.__('No One Is Online Now').'</h2></div>'."\n";
			}
}


### Function: Place Polls Archive In Content
add_filter('the_content', 'place_useronlinepage', '12');
function place_useronlinepage($content){
     $content = preg_replace( "/\<page_useronline\>/ise", "useronline_page()", $content); 
    return $content;
}


### Function: UserOnline Page
function useronline_page() {
	global $wpdb;
	// Get The Users Online
	$usersonline = $wpdb->get_results("SELECT * FROM $wpdb->useronline ORDER BY type");

	// Variables Variables Variables
	$useronline_output = '';
	$members = array();
	$guests = array();
	$bots = array();
	$total_users = 0;
	$total_members = 0;
	$total_guests = 0;
	$total_bots = 0;
	$nicetext_users = '';
	$nicetext_members = '';
	$nicetext_guests = '';
	$nicetext_bots = '';

	// Process Those User Who Is Online
	if($usersonline) {
		foreach($usersonline as $useronline) {
			switch($useronline->type) {
				case 'member':
					$members[] = array('timestamp' => $useronline->timestamp, 'user_id' => $useronline->userid, 'user_name' => stripslashes($useronline->username), 'display_name' => stripslashes($useronline->displayname), 'user_agent' => stripslashes($useronline->useragent), 'ip' => $useronline->ip, 'location' => stripslashes($useronline->location), 'url' => stripslashes($useronline->url));
					$total_members++;
					break;
				case 'guest':						
					$guests[] = array('timestamp' => $useronline->timestamp, 'user_id' => $useronline->userid, 'user_name' => stripslashes($useronline->username), 'display_name' => stripslashes($useronline->displayname), 'user_agent' => stripslashes($useronline->useragent), 'ip' => $useronline->ip, 'location' => stripslashes($useronline->location), 'url' => stripslashes($useronline->url));
					$total_guests++;
					break;
				case 'bot':
					$bots[] = array('timestamp' => $useronline->timestamp, 'user_id' => $useronline->userid, 'user_name' => stripslashes($useronline->username), 'display_name' => stripslashes($useronline->displayname), 'user_agent' => stripslashes($useronline->useragent), 'ip' => $useronline->ip, 'location' => stripslashes($useronline->location), 'url' => stripslashes($useronline->url));
					$total_bots++;
					break;
			}
		}
		$total_users = ($total_guests+$total_bots+$total_members);
	}

	// Nice Text For Users
	if($total_users == 1) {
		$nicetext_users = $total_users.' '.__('User');
	} else {
		$nicetext_users = number_format($total_users).' '.__('Users');
	}

	//  Nice Text For Members
	if($total_members == 1) {
		$nicetext_members = $total_members.' '.__('Member');
	} else {
		$nicetext_members = number_format($total_members).' '.__('Members');
	}


	// Nice Text For Guests
	if($total_guests == 1) { 
		$nicetext_guests = $total_guests.' '.__('Guest');
	} else {
		$nicetext_guests = number_format($total_guests).' '.__('Guests'); 
	}

	//  Nice Text For Bots
	if($total_bots == 1) {
		$nicetext_bots = $total_bots.' '.__('Bot'); 
	} else {
		$nicetext_bots = number_format($total_bots).' '.__('Bots'); 
	}

	// Check Whether WP-Stats Is Activated
	$wp_stats = false;
	if(function_exists('get_totalposts')) {
		$wp_stats = true;
	}
	$useronline_output .= '<p>';
	if ($total_users == 1) { 
		$useronline_output .= __('There is '); 
	} else { 
		$useronline_output .= __('There are a total of ');
	}
	$useronline_output .= "<b>$nicetext_users</b> online now: <b>$nicetext_members</b>, <b>$nicetext_guests</b> and <b>$nicetext_bots</b>.</p>\n";
	$useronline_output .= "<p>Most users ever online were <b>".get_most_useronline(false)."</b>, on <b>".get_most_useronline_date(false)."</b></p>\n";
	// Print Out Members
	if($total_members > 0) {
		$useronline_output .= 	'<h2 class="pagetitle">'.$nicetext_members.' '.__('Online Now').'</h2>'."\n";
	}
	$no=1;
	if($members) {
		foreach($members as $member) {
			if($wp_stats) {
				$useronline_output .= '<p><b>#'.$no.' - <a href="'.get_settings('siteurl').'/wp-content/plugins/stats/wp-stats.php?author='.$member['display_name'].'">'.$member['display_name'].'</a></b> '.ip2nation_country($member['ip']).check_ip($member['ip']).' on '.gmdate('d.m.Y @ H:i', $member['timestamp']).'<br />'.$member['location'].' [<a href="'.$member['url'].'">url</a>]</p>'."\n";
			} else {
				$useronline_output .= '<p><b>#'.$no.' - '.$member['username'].'</b> '.check_ip($member['ip']).' on '.gmdate('d.m.Y @ H:i', $member['timestamp']).'<br />'.$member['location'].' [<a href="'.$member['url'].'">url</a>]</p>'."\n";
			}
			$no++;
		}
	}

	// Print Out Guest
	if($total_guests > 0) {
		$useronline_output .= '<h2 class="pagetitle">'.$nicetext_guests.' '.__('Online Now').'</h2>'."\n";
	}
	$no=1;
	if($guests) {
		foreach($guests as $guest) {
			if($wp_stats) {
				$useronline_output .= '<p><b>#'.$no.' - <a href="'.get_settings('siteurl').'/wp-content/plugins/stats/wp-stats.php?author='.$guest['display_name'].'">'.$guest['display_name'].'</a></b> '.ip2nation_country($guest['ip']).check_ip($guest['ip']).' on '.gmdate('d.m.Y @ H:i', $guest['timestamp']).'<br />'.$guest['location'].' [<a href="'.$guest['url'].'">url</a>]</p>'."\n";
			} else {
				$useronline_output .= '<p><b>#'.$no.' - '.$guest['username'].'</b> '.check_ip($guest['ip']).' on '.gmdate('d.m.Y @ H:i', $guest['timestamp']).'<br />'.$guest['location'].' [<a href="'.$guest['url'].'">url</a>]</p>'."\n";
			}
		}
	}

	// Print Out Bots
	if($total_bots > 0) {
		$useronline_output .= '<h2 class="pagetitle">'.$nicetext_bots.' '.__('Online Now').'</h2>'."\n";
	}
	$no=1;
	if($bots) {
		foreach($bots as $bot) {
			$useronline_output .= '<p><b>#'.$no.' - '.$bot['username'].'</b> '.check_ip($bot['ip']).' on '.gmdate('d.m.Y @ H:i', $bot['timestamp']).'<br />'.$bot['location'].' [<a href="'.$bot['url'].'">url</a>]</p>'."\n";
			$no++;
		}
	}

	// Print Out No One Is Online Now
	if($total_users == 0) {
		$useronline_output .= '<h2 class="pagetitle">'.__('No One Is Online Now').'</h2>'."\n";
	}

	// Output UserOnline Page
	return $useronline_output;
}


### Function: Create UserOnline Table
add_action('activate_useronline/useronline.php', 'create_useronline_table');
function create_useronline_table() {
	global $wpdb;
	$bots = array('Google Bot' => 'googlebot', 'Google Bot' => 'google', 'MSN' => 'msnbot', 'Alex' => 'ia_archiver', 'Lycos' => 'lycos', 'Ask Jeeves' => 'jeeves', 'Altavista' => 'scooter', 'AllTheWeb' => 'fast-webcrawler', 'Inktomi' => 'slurp@inktomi', 'Turnitin.com' => 'turnitinbot', 'Technorati' => 'technorati', 'Yahoo' => 'yahoo', 'Findexa' => 'findexa', 'NextLinks' => 'findlinks', 'Gais' => 'gaisbo', 'WiseNut' => 'zyborg', 'WhoisSource' => 'surveybot', 'Bloglines' => 'bloglines', 'BlogSearch' => 'blogsearch', 'PubSub' => 'pubsub', 'Syndic8' => 'syndic8', 'RadioUserland' => 'userland', 'Gigabot' => 'gigabot', 'Become.com' => 'become.com');
	include_once(ABSPATH.'/wp-admin/upgrade-functions.php');
	// Drop UserOnline Table
	$wpdb->query("DROP TABLE IF EXISTS $wpdb->useronline");
	// Create UserOnline Table
	$create_table = "CREATE TABLE $wpdb->useronline (".
							" timestamp int(15) NOT NULL default '0',".
							" userid int(10) NOT NULL default '0',".
							" username varchar(255) NOT NULL default '',".
							" displayname varchar(255) NOT NULL default '',".
							" useragent varchar(255) NOT NULL default '',".
							" ip varchar(40) NOT NULL default '',".						 
							" location varchar(255) NOT NULL default '',".
							" url varchar(255) NOT NULL default '',".
							" type enum('member','guest','bot') NOT NULL default 'guest',".
							" UNIQUE KEY useronline_id (timestamp,username,ip,useragent))";
	maybe_create_table($wpdb->useronline, $create_table);
	// Add In Options
	add_option('useronline_most_users', 1, 'Most Users Ever Online Count');
	add_option('useronline_most_timestamp', current_time('timestamp'), 'Most Users Ever Online Date');
	add_option('useronline_timeout', 300, 'Timeout In Seconds');
	add_option('useronline_bots', $bots, 'Bots Name/Useragent');
	// Database Upgrade For WP-UserOnline 2.05
	add_option('useronline_url', get_settings('siteurl').'/useronline/', 'UserOnline Page URL');
}
?>