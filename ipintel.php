<?php /* 
Plugin Name: IP Intel
Plugin URI: http://www.nullamatix.com/wordpress-ip-intelligence/
Description: Easily obtain information about a commentators IP. 
Version: 0.0.1
Author: Guy Patterson
Author URI: http://www.nullamatix.com/ 					      
*/

/*  Copyright 2009 Guy Patterson (email : 'ipintel at nullamatix')

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
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA  */

/*	IP Location API: http://www.ipinfodb.com/ip_location_api.php
	## -- Donate to IpInfoDB.com, They Rock --  ##                        */

function ip_intel_adminhead() {
	// make sure these files aren't reinserted when viewing ip intelligence
	if (!isset($_REQUEST['page']) && !isset($_REQUEST['ip']) || $_REQUEST['page'] == 'akismet-admin') {
		echo '<link rel="stylesheet" type="text/css" href="' . plugins_url() . '/ipintel/css/lightbox.css" />'."\n";
		echo '<script type="text/javascript" src="' . plugins_url() . '/ipintel/js/prototype.js"></script>'."\n";
		echo '<script type="text/javascript" src="' . plugins_url() . '/ipintel/js/lightbox.js"></script>'."\n";
	}
	// there has to be a better way to do this... help, plz? kthx
        if (isset($_REQUEST['page']) && isset($_REQUEST['ip'])) {
                echo '<style type="text/css" media="screen">'."\n";
                echo 'li#menu-dashboard,div#adminmenu,div#screen-meta,div#user_info,div#wphead,ul#dashmenu,ul#sidemenu {display:none}'."\n";
                echo 'ul#adminmenu,ul#submenu,ul#user_info,div#contextual-help-link-wrap,div#footer {display:none}'."\n";
                echo '</style>'."\n";
        }
}

function ip_intel_addtoadmin() {
	global $submenu;
	if (isset($submenu['edit-comments.php'])) {
		add_submenu_page('edit-comments.php', '', '', 'moderate_comments', 'ipintel', 'run_ip_intelligence' );
	} elseif (function_exists('add_management_page')) {
		add_management_page('', '', 'moderate_comments', 'ipintel', 'run_ip_intelligence');
	}
}

function ip_intel_adminfooter() {
	global $wp_version;
	// make sure the following doesn't run when viewing ip intelligence
	if (!isset($_REQUEST['ip']) && !isset($_REQUEST['page']) || $_REQUEST['page'] == 'akismet-admin') {
		$ipIntel_regex = '(\d+\.\d+\.\d+\.\d+)';
		$ipIntel_ipurl = "(<a[ \\n\\r]*href=\"([^\"]*)\"([^>]*)>$ipIntel_regex<\/a>)"; 
		// this is here due to a permissions issue with 2.8... ?
		if ($wp_version >= "2.8") { 
			$ipIntel_new_url = "tools.php?page=ipintel&ip=$4";
		} else { 
			$ipIntel_new_url = "edit-comments.php?page=ipintel&ip=$4";
		} // digitalramble.com/2007/03/20/101/ - cinnamonthoughts.org/wordpress/commenter-spy/ 
?>

	<script type="text/javascript">
	//<![CDATA[
<?php if (isset($_REQUEST['page']) && $_REQUEST['page'] == 'akismet-admin') { ?>
	var ipih = document.getElementsByTagName('p');
<?php } else { ?>
	var ipih = document.getElementsByTagName('td');
<?php } ?>
	for (var i=0; i<ipih.length; i++) {
		var ipic = ipih[i];
		ipic.innerHTML = ipic.innerHTML.replace(/<?php echo $ipIntel_ipurl; ?>/ig, "$1 | <a  href=\"<?php echo $ipIntel_new_url; ?>\" class=\"lbOn\" $3>IP Intel</a>");
	}
	//]]></script>

<?php	} // end if !isset $_REQUEST....
} // end func ip_intel_adminfooter

if (basename($_SERVER['SCRIPT_FILENAME']) === 'edit-comments.php' || (basename($_SERVER['SCRIPT_FILENAME']) === 'tools.php')) {
	add_action('admin_head', 'ip_intel_adminhead');
	add_action('admin_footer', 'ip_intel_adminfooter');
}

function ip_intel_geolocateip($ip) {
	$d = file_get_contents("http://www.ipinfodb.com/ip_query.php?ip=$ip&output=xml");
 	//Use backup server if cannot make a connection
	if (!$d) {
		$backup = file_get_contents("http://backup.ipinfodb.com/ip_query.php?ip=$ip&output=xml");
		$ipinfo_answer = new SimpleXMLElement($backup);
		if (!$backup) return false; // Failed to open connection
	} else {
		$ipinfo_answer = new SimpleXMLElement($d);
	}
		$country_name = $ipinfo_answer->CountryName;
		$region_name = $ipinfo_answer->RegionName;
		$city = $ipinfo_answer->City;
		$timezone = $ipinfo_answer->Timezone;
		return array('ip' => $ip, 'country_name' => $country_name, 'region_name' => $region_name, 'city' => $city, 'timezone' => $timezone);
}

function ip_intel_sfsipcheck($ip) {
	$sfs_url = file_get_contents('http://www.stopforumspam.com/api?ip='.$ip);
	$sfs_answer = new SimpleXMLElement($sfs_url);
	$sfs_says = $sfs_answer->appears;
	if ($sfs_says == 'yes') {
		$sfs_last_seen = $sfs_answer->lastseen;
		$sfs_frequency = $sfs_answer->frequency;
		return array('a' => $sfs_says, 'l' => $sfs_last_seen, 'f' => $sfs_frequency);
	} else {
		$sfs_says = "no";
		$sfs_last_seen = "never";
		$sfs_frequency = "0";
		return array('a' => $sfs_says, 'l' => $sfs_last_seen, 'f' => $sfs_frequency);
	}
}

function ip_intel_getwhois($ip) {
	$qURL = "http://tools.whois.net/index.php?host=$ip&fuseaction=whois.whoisbyipresults";
	$ipwho = file_get_contents($qURL);
	if (!$ipwho) { 
		echo "No Whois Data....."; 
		return false;
	} else {
		$ipwho_regex = '#<pre><blockquote>(.+?)</blockquote></pre>#ims';
		preg_match($ipwho_regex,$ipwho,$ipwho_match);
		return $ipwho_match[1];
	}
}

function ip_intel_getscnetstat($ip) {
	$qURL = "http://www.spamcop.net/w3m?action=checkblock&ip=$ip";
	$spamcop = file_get_contents($qURL);
	if (!$spamcop) {
		echo "Unable to obtain Spamcop.net status.";
	} else {
		$spamcop_regex = "#<p>(.+?) bl.spamcop.net<br>#i";
		preg_match($spamcop_regex,$spamcop,$spamcop_match);
		return $spamcop_match[1];
	}
}

function run_ip_intelligence() {
global $wp_version;
$ip_intel_ipfromurl = $_REQUEST['ip'];
// http://www.blog.highub.com/regular-expression/php-regex-regular-expression/php-regex-validate-ip-address/
	if (preg_match("/^(([1-9]?[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5]).){3}([1-9]?[0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])$/", $ip_intel_ipfromurl)) {
		if ($wp_version >= "2.8") { $ip_intel_cleardiv = '</div></div><div class="clear"></div>'; } 
		else { $ip_intel_cleardiv = '<div style="clear:both"></div>'; }
		$ip_intel_iptohostname = gethostbyaddr($ip_intel_ipfromurl);
		// ip_intel_nsquery = dns_get_record($ip_intel_ipfromurl, DNS_ALL);
		$ip_intel_ip4tolong = ip2long($ip_intel_ipfromurl); 
		$ip_intel_ipwhois = ip_intel_getwhois($ip_intel_ipfromurl);
		$ipinfodb_data = ip_intel_geolocateip($ip_intel_ipfromurl);
		$spamcop_status = ip_intel_getscnetstat($ip_intel_ipfromurl);
		$sfs_data = ip_intel_sfsipcheck($ip_intel_ipfromurl); ?>
<?php echo $ip_intel_cleardiv; ?><a name="goto_top_ipintel"></a>[<a href="#" class="lbAction" rel="deactivate">Close</a>]
<div style="margin:0 0 0 3px;float:left;width:55%">
<pre><?php echo $ip_intel_ipwhois; ?></pre>
[<a href="#" class="lbAction" rel="deactivate">Close</a>] - [<a href="#goto_top_ipintel">Top</a>]
</div>
<div style="margin:0px;float:right;width:40%">
IP: <?php echo $ip_intel_ipfromurl; ?> <br /><br />
rDNS: <?php echo $ip_intel_iptohostname; ?> <br /><br />
Long: <?php echo $ip_intel_ip4tolong; ?> <br /><br />
<a href="http://www.ipinfodb.com" target="_blank">IPInfoDB.com</a> Data:
<ul>
	<li>Country: <?php echo $ipinfodb_data['country_name']; ?></li>
	<?php /* eehh... <a href="http://www.hostip.info"><img width="24" height="12" src="http://api.hostip.info/flag.php?ip=<?php echo $ip_intel_ipfromurl; ?>" /></a> */ ?>
	<li>Region: <?php echo $ipinfodb_data['region_name']; ?></li>
	<li>City: <?php echo $ipinfodb_data['city']; ?></li>
	<li>Timezone: <?php echo $ipinfodb_data['timezone']; ?></li>
</ul>
<a href="http://www.spamcop.net" target="_blank">SpamCop.net</a> Status:
<ul>
	<li><?php echo $spamcop_status; ?> bl.spamcop.net</li>
</ul>
<a href="http://www.stopforumspam.com" target="_blank">StopForumSpam.com</a> Data:
<ul>
	<li>Appears: <?php echo $sfs_data['a']; ?></li>
	<li>Frequency: <?php echo $sfs_data['f']; ?></li>
	<li>Last Seen: <?php echo $sfs_data['l']; ?></li>
</ul>
Lookup:
<ul>
	<li><a href="http://www.google.com/search?num=2&q=<?php echo $ip_intel_ipfromurl; ?>" target="_blank">Google Search</a></li>
	<li><a href="http://groups.google.com/groups?scoring=d&q=<?php echo $ip_intel_ipfromurl; ?>+group:*abuse*" target="_blank">Google Groups</a></li>
	<li><a href="http://www.projecthoneypot.org/ip_<?php echo $ip_intel_ipfromurl; ?>" target="_blank">Project HoneyPot</a></li>
	<li><a href="http://www.spamhaus.org/query/bl?ip=<?php echo $ip_intel_ipfromurl; ?>" target="_blank">SpamHaus</a></li>
	<li><a href="http://openrbl.org/?i=<?php echo $ip_intel_ipfromurl; ?>" target="_blank">OpenRBL</a></li>
	<li><a href="http://www.robtex.com/ip/<?php echo $ip_intel_ipfromurl; ?>.html" target="_blank">Robtext (Lucky)</a></li>
</ul>
[<a href="#" class="lbAction" rel="deactivate">Close</a>]
</div>
<?php 		} else { print "Bad IP..."; }
	}
add_action('admin_menu', 'ip_intel_addtoadmin');
?>
