<?php
$execstart = microtime(true);

///--
///-- Templating block
///--
ob_start(); // Start the output buffer before anything else

// --- Base template and header data ---
$_no_template = false;
$_template = file_get_contents("./template.html");

$_header_location = ''; // Hidden by default

$_header_left =
	 '<div style="position:absolute; left:0px;">'
	.'<a class="button" href="/">Home</a>'
	.'<a class="button" href="/maps.php">Map list</a>'
	.'<a class="button" href="/packs.php">Modifications</a>'
	.'</div>';

$_header_userinfo =
	 '<div style="position:absolute; right:4px; top:-16px;">'
	.'You are not logged in.'
	.'</div>';

$_header_right = 
	 '<div style="position:absolute; right:2px;">'
	.'<span class="button-info"><label><input style="margin:0px;" type="checkbox" onchange="toggle_oldview();" id="oldview" checked disabled> Show older versions</label></span>'
	.'<a class="button" href="/login.php">Log in with your SRB2MB account</a>'
	.'</div>';

$_replace_userinfo = 
	 '<div style="position:absolute; right:4px; top:-16px;">'
	.'You are logged in as <a href="/user.php?user={USERID}">{USERNAME}</a>.'
	.'</div>';

$_replace_right = 
	 '<div style="position:absolute; right:2px;">'
	.'<span class="button-info"><label><input style="margin:0px;" type="checkbox" onchange="toggle_oldview();" id="oldview" checked disabled> Show older versions</label></span>'
	.'<a class="button" href="/submit.php">Submit</a>'
	.'<a class="button" href="/login.php?logout">Log out</a>'
	.'</div>';

$_admin_right = 
	 '<div style="position:absolute; right:2px;">'
	.'<span class="button-info"><label><input style="margin:0px;" type="checkbox" onchange="toggle_oldview();" id="oldview" checked disabled> Show older versions</label></span>'
	.'<a class="button" href="/index.php">Admin</a>'
	.'<a class="button" href="/submit.php">Submit</a>'
	.'<a class="button" href="/login.php?logout">Log out</a>'
	.'</div>';

// -------------------------------------

// --- Output handler automatic handling ---
function ob_shutdown() {
	global $_no_template;
	if ($_no_template) {
		ob_end_flush();
		return;
	}

	global $_template;

	$local_template = $_template;
	if (!$local_template)
		$local_template = '<html><body>No template data found: raw data stream commencing<br><br>'
			.'HEADER<br>{HEADER_BEGIN}<br><br>'
			.'BODY<br>{CONTENT_BEGIN}<br></body></html>';

	$t = ob_get_contents();

	// Split header
	global $_header_location, $_header_left, $_header_userinfo, $_header_right;
	$h = $_header_location . $_header_left . $_header_userinfo . $_header_right;

	global $execstart;
	$exectime = microtime(true) - $execstart;
	$qseconds = sprintf("%01.3f", mysql::$time);
	$sseconds = sprintf("%01.3f", $exectime - mysql::$time);
	$tseconds = sprintf("%01.3f", $exectime);

	$m = sprintf('SQL: %d queries; %ss/%ss/%ss', mysql::$queries, $qseconds, $sseconds, $tseconds);

	// end and clean buffer so it doesn't get outputted again (or buffer our output!)
	ob_end_clean();

	echo str_replace(array('{HEADER_BEGIN}', '{CONTENT_BEGIN}', '{MYSQL_BEGIN}'),
	                 array(  $h,               $t,                $m           ), $local_template);
}
register_shutdown_function(ob_shutdown);
// -----------------------------------------

// --- Overrides for other stuff ---
// Handles redirects -- does not return!
function do_redirect($location) {
	global $_no_template;
	$_no_template = true;
	die(header('Location: '.$location));
}

// Redirects to login page if not logged in, link back to our page when done
function do_require_login() {
	global $_logged_in_user;
	if ($_logged_in_user)
		return; // Well, we don't really care then.

	do_redirect('/login.php?'.$_SERVER['REQUEST_URI']);
}

// Sets the location field in the header
function set_location($where) {
	global $_header_location;

	foreach ($where as $url=>$text)
		$locations[] = ((is_string($url)) ? "<a href=\"{$url}\">{$text}</a>" : $text);

	$_header_location = 
	 '<div style="position:absolute; left:2px; top:-16px; ">'
	.'(' . implode(' &#8594; ', $locations) . ' &#8594; This page)'
	.'</div>';
}
// ---------------------------------

///--
///-- End templating
///--

///--
///-- Database block
///--
require_once("./include/mysql.php");
require_once("./include/db_info.php");

///--
///-- End database
///--

///--
///-- Text-to-images functions
///--
function make_zone_name_table($name, $zone = true, $act = 0) {
	$upper = '<span class="ac-zone">'.$name.'</span>';
	$lower = (($zone) ? '<span class="ac-zone">ZONE</span>' : '');
	$right = (($act > 9) ? '<img src="/images/act-'.(string)$act{0}.'.png"><img src="/images/act-'.(string)$act{1}.'.png">' 
		: (($act > 0) ? '<img src="/images/act-'.$act.'.png">' : ''));

	return
	'<table><tr><td align="right">'
		.$upper
	.'</td><td rowspan="2" align="left" valign="bottom">'
		.$right
	.'</td></tr><tr><td align="right">'
		.$lower
	.'</td></tr></table>';
}

function make_zone_row_table($name_strings) {
	foreach($name_strings as $k=>$n)
		$name_strings[$k] = '<tr><td align="right"><span class="ac-zone">'.$n.'</span></td></tr>';
	return '<table>'.implode('',$name_strings).'</table>';
}

function error_box($text) {
	return '<table class="error-table" width="75%"><tr><th>Error</th></tr>'
		. "<tr><td>{$text}</td></tr></table>";
}
///--
///-- End text-to-image
///--

///--
///-- Replay/record info start
///--
define('DEMO_HEADER',      "\xF0"."SRB2Replay"."\x0F");
define('DEMO_VERSION',     0x0009);
define('DEMO_PLAYER_MARK', 'PLAY');

// So that we don't have to constantly check for class type and do different ordering
// for time records and such, we store time records as negative ints.
// This reverses the ordering conditions to match the other class types.
// This define is used to mark a nonexistant entry.
define('ENTRY_NONEXISTANT', ~PHP_INT_MAX);

function make_time_string($tics) {
	if ($tics < 1) return "--:--.--";
	$cs = floor((100 / 35) * ($tics % 35));
	$s  = floor(($tics % (35*60)) / 35);
	$m  = floor($tics / (35*60));
	return sprintf("%d:%02d.%02d", $m, $s, $cs);
}

function make_time_string_seconds($seconds) {
	if ($seconds < 1) return "--::--:--";
	$s  = intval($seconds) % 60;
	$m  = intval($seconds / 60) % 60;
	$h  = intval($seconds / 3600);
	return sprintf("%d::%02d:%02d", $h, $m, $s);
}

function record_to_display($n, $record) {
	switch ($n) {
		case 'score':
		case 'nscore':
			if ($record === ENTRY_NONEXISTANT) return '---------';
			return (string)$record;
		case 'rings':
			if ($record === ENTRY_NONEXISTANT) return '----';
			return (string)$record;
		case 'time':
		case 'ntime':
			if ($record === ENTRY_NONEXISTANT) return "--:--.--";
			return make_time_string($record * -1);
		case 'global-gameclear':
		case 'global-allemeralds':
			if ($record === ENTRY_NONEXISTANT) return "--::--:--";
			return make_time_string_seconds($record * -1);
		default: break;
	}

	// Variable numeric classes (can't be put in switch statements)
	if (preg_match('/^global-(\d+)emblems$/', $n)) {
		if ($record === ENTRY_NONEXISTANT) return "--::--:--";
		return make_time_string_seconds($record * -1);
	}

	// Unknown class name?
	return $record;
}

function get_class_name($n) {
	switch ($n) {
		case 'score': return 'Score';
		case 'time':  return 'Time';
		case 'rings': return 'Rings';
		case 'nscore': return 'NiGHTS Score';
		case 'ntime':  return 'NiGHTS Time';
		case 'global-gameclear':   return 'Game Clear';
		case 'global-allemeralds': return 'Clear with All Emeralds';
		default: break;
	}

	// Variable numeric classes (can't be put in switch statements)
	$match = array();
	if (preg_match('/^global-(\d+)emblems$/', $n, $match))
		return 'Collect All '.$match[1].' Emblems';

	// Unknown class name?
	return 'Unknown ("'.$n.'")';
}

function get_map_name($num) {
	if ($num < 100)
		return sprintf("MAP%02d", $num);
	$num -= 100;
	$ret = "MAP";
	$ret .= chr(ord('A') + (int)($num / 36));
	$num %= 36;
	$ret .= (($num < 10) ? (string)$num : chr(ord('A') + $num-10));
	return $ret;
}

function centiseconds_to_tics($cs) {
	// this is stupid but w/e
	switch ($cs) {
		case  0: return 0;	case  2: return 1;	case  5: return 2;	case  8: return 3;	case 11: return 4;	case 14: return 5;	case 17: return 6;
		case 20: return 7;	case 22: return 8;	case 25: return 9;	case 28: return 10;	case 31: return 11;	case 34: return 12;	case 37: return 13;
		case 40: return 14;	case 42: return 15;	case 45: return 16;	case 48: return 17;	case 51: return 18;	case 54: return 19;	case 57: return 20;
		case 60: return 21;	case 62: return 22;	case 65: return 23;	case 68: return 24;	case 71: return 25;	case 74: return 26;	case 77: return 27;
		case 80: return 28;	case 82: return 29;	case 85: return 30;	case 88: return 31;	case 91: return 32;	case 94: return 33;	case 97: return 34;
		default: return -1;
	}
}

function get_download_link($replay_id = -1, $manual_id = -1) {
	// Generic no download link
	$file_image_link = '<img src="/images/download-none.png" alt="DL" title="No replay means no download, sorry!" />';

	$replay_id = intval($replay_id);
	$manual_id = intval($manual_id);

	if ($replay_id > 0) {
		// This gets handled all for us! Yay!
		$file_image_link = "<a href=\"download.php?replay={$replay_id}\">"
			.'<img src="/images/download.png" alt="DL" title="Download the replay file!" /></a>';
	}
	else if ($manual_id > 0) {
		// Unfortunately we have to poll the database for this.
		// Because we might need to repeat this a lot, we fetch the entire manual submissions table
		// (which is hopefully small), keep a static copy of it, and work with that whenever we need it.
		static $manual_data = NULL;
		if (!$manual_data)
			$manual_data = mysql::getresultsbykey('SELECT manual_id,type FROM manual_submissions', 'manual_id', 'type');

		$type = $manual_data[$manual_id];
		$ttimage = $tttext = '';
		switch ($type) {
			case 'twitch':	$ttimage = 'download-twitch.png';	$tttext = 'Watch the Twitch highlight!';	break;
			case 'youtube':	$ttimage = 'download-yt.png';		$tttext = 'Watch the YouTube video!';		break;
			case 'hitbox':	$ttimage = 'download-hitbox.png';	$tttext = 'Watch the Hitbox video!';		break;
		}
		if ($ttimage) {
			$file_image_link = "<a href=\"download.php?manual={$manual_id}\">"
				."<img src=\"/images/{$ttimage}\" alt=\"DL\" title=\"{$tttext}\" /></a>";
		}
	}

	return $file_image_link;
}

function get_report_link($id, $result) {
	if ($result === NULL || $result === '-1') {
		// No report
		$report_link = "report.php?id={$id}";
		$report_title = 'Report this submission';
		$report_image = '/images/report.png'; 
	}
	else if ($result === '1') {
		// Confirmed good
		$report_title = 'An administrator has confirmed that this submission is good.';
		$report_image = '/images/report-good.png'; 
	}
	else {
		// In progress
		$report_title = 'This submission has been reported and is pending review.';
		$report_image = '/images/report-done.png'; 
	}

	$report = "<img src=\"{$report_image}\" title=\"{$report_title}\"/>";
	if ($report_link)
		$report = "<a href=\"{$report_link}\">{$report}</a>";
	return $report;
}

function display_recency($string, $recency) {
	if ($recency <= 0)
		return '<b>Just now</b>';
	if ($recency <= 1)
		return '<b>1 minute ago</b>';
	if ($recency < 60)
		return "<b>{$recency} minutes ago</b>";
	if ($recency < 120)
		return '<b>1 hour ago</b>';
	if ($recency < 60*24)
		return '<b>'.intval($recency / 60).' hours ago</b>';
	return $string;
}

function array_to_formal_list($array) {
	$keys = array_keys($array);

	if (count($array) <= 0)
		return "";
	if (count($array) == 1)
		return $array[$keys[0]];
	if (count($array) == 2)
		return $array[$keys[0]] . " and " . $array[$keys[1]];

	$finalElem = array_pop($keys);
	$array[$finalElem] = "and " . $array[$finalElem];
	return implode(', ', $array);
}
///--
///-- End replay/record info
///--

///--
///-- Login handling start
///--
define('COOKIE_NAME', 'srb2rec-user-data');
$_logged_in_user = NULL;
$_logged_in_id = 0;
$_logged_in_admin = 0;

function handle_cookie($c) {
	// Gotta open db to check
	open_database_readonly();

	// cookie format: 0000000001|hash----hash----hash----hash----hash----
	$userid = intval(substr($c, 0, 10));
	$user_data = mysql::fetchq("SELECT name,admin,verify_hash FROM users WHERE user_id='{$userid}'");

	$hash_us = substr($c, 11);
	$hash_db = $user_data['verify_hash'];

	if ($hash_us !== $hash_db)
		return false;

	// hash good
	global $_logged_in_user, $_logged_in_id, $_logged_in_admin;
	$_logged_in_user = $user_data['name'];
	$_logged_in_id = $userid;
	$_logged_in_admin = $user_data['admin'];
	return true;
}

// --- Always run ---
if ($_COOKIE[COOKIE_NAME]) {
	if (!handle_cookie($_COOKIE[COOKIE_NAME])) {
		// Unset all cookie data
		setcookie(COOKIE_NAME, '', 1);
		unset($_COOKIE[COOKIE_NAME]);
	}
	else {
		// Data good, change headers
		$_header_userinfo = str_replace(array('{USERNAME}', '{USERID}'), array($_logged_in_user, $_logged_in_id), $_replace_userinfo);
		$_header_right = (($_logged_in_admin) ? $_admin_right : $_replace_right);
	}
}
// ------------------

///--
///-- End login handling
///--

///--
///-- Show/hide old replays
///--
define('SHOW_OLD_COOKIE_NAME', 'srb2rec-show-old');

function get_latest_version() {
	static $v = NULL;
	if (!$v) $v = mysql::resultq("SELECT value FROM site_data WHERE data_key='current_version'");
	return $v;
}

function should_show_old_records() {
	return (!$_COOKIE[SHOW_OLD_COOKIE_NAME] || $_COOKIE[SHOW_OLD_COOKIE_NAME] != "false");
}