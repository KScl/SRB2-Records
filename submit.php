<?php require_once("include/common.php");

// If not logged in redirect to the login page
do_require_login();

// Defaults
$maxsize = 3145728;
$submitted_info = NULL;

///--
///-- AUTOMATIC SUBMISSIONS
///-- 
function read_uploaded_replay() {
	global $maxsize;

	if ($_FILES['replay_file']['error'] == UPLOAD_ERR_NO_FILE)
		return ''; // No file was uploaded, just stay blank
	if (($_FILES['replay_file']['error'] === UPLOAD_ERR_INI_SIZE)
	 || ($_FILES['replay_file']['error'] === UPLOAD_ERR_FORM_SIZE)
	 || ($_FILES['replay_file']['size'] > $maxsize))
		return "File size exceeded maximum.";
	if (($_FILES['replay_file']['error'] === UPLOAD_ERR_CANT_WRITE)
	 || ($_FILES['replay_file']['error'] === UPLOAD_ERR_NO_TMP_DIR)
	 || (!is_uploaded_file($_FILES['replay_file']['tmp_name'])))
		return "Internal error occurred while uploading file.  Please try again.";
	if ($_FILES['replay_file']['error'] !== UPLOAD_ERR_OK)
		return "File upload failed.  Please try again.";

	// file is good from the client
	// open database read only first to get some info
	open_database_readonly();

	// save checksums for storage
	$replay_data['checksum_md5']  = md5_file($_FILES['replay_file']['tmp_name']);
	$replay_data['checksum_sha1'] = sha1_file($_FILES['replay_file']['tmp_name']);

	// Check checksums for duplicate...
	if (mysql::resultq("SELECT replay_id FROM replay_data "
		."WHERE checksum_md5 = '{$replay_data['checksum_md5']}' AND checksum_sha1 = '{$replay_data['checksum_sha1']}'"))
		return "This replay has already been uploaded.";
	
	// now let's open it up and check the data
	$upload_file = fopen($_FILES['replay_file']['tmp_name'], 'rb');
	if (fread($upload_file, strlen(DEMO_HEADER)) !== DEMO_HEADER) {
		fclose($upload_file);
		return "Uploaded file is not a SRB2 Replay.";
	}
	$unpacked = unpack('C2versions', fread($upload_file, 2));
	$replay_info['version'] = sprintf('%03d.%03d', $unpacked['versions1'], $unpacked['versions2']);
	
	// Most recent version number
/*	if ($replay_info['version'] !== get_latest_version()) {
		fclose($upload_file);
		return "Sorry, we aren't accepting demos from this version. Use the latest release of SRB2.";
	} */

	$unpacked = unpack('vdemversion', fread($upload_file, 2));
	if ($unpacked['demversion'] !== DEMO_VERSION) {
		fclose($upload_file);
		return "Sorry, we aren't accepting demos from this version. Use the latest release of SRB2.";
	}

	$unpacked = unpack('C16', fread($upload_file, 16));
	$replay_checksum = '';
	foreach ($unpacked as $md5byte)
		$replay_checksum .= sprintf('%02x', $md5byte);
	$checksum_tell = ftell($upload_file);

	// Confirm checksum.
	// This is kinda silly but okay... whatever
	$checksum_data = substr(file_get_contents($_FILES['replay_file']['tmp_name']), $checksum_tell);
	$replay_our_checksum = md5($checksum_data);
	unset($checksum_data);
	if ($replay_our_checksum !== $replay_checksum) {
		fclose($upload_file);
		return "Checksum failure -- file has been modified.";
	}
	unset($replay_checksum, $replay_our_checksum);

	// Check for player marker (maybe it's a metal replay?)
	if (fread($upload_file, 4) !== DEMO_PLAYER_MARK) {
		fclose($upload_file);
		return "Uploaded file is not a SRB2 Replay.";
	}

	// ignore map number -- we get map from mapmd5
	fread($upload_file, 2);
	$unpacked = unpack('C16', fread($upload_file, 16));
	$mapmd5 = '';
	foreach ($unpacked as $md5byte)
		$mapmd5 .= sprintf('%02x', $md5byte);

	$replay_map = mysql::fetchq("SELECT * FROM maps WHERE checksum = '{$mapmd5}'", MYSQL_ASSOC);
	if (!$replay_map) {
		fclose($upload_file);
		return "Replay is for a map that this site does not support.  "
			."Are you submitting an unsupported mod's replay, or a replay for an older version, perhaps?";
	}
	if (!$replay_map['auto_submit']) {
		fclose($upload_file);
		return "Auto-submissions are not supported for this map.";
	}
	$replay_info['map_id'] = $replay_map['map_id'];

	$unpacked = unpack('Cdflags', fread($upload_file, 1));
	$modeattack = ($unpacked['dflags'] & 0x06) >> 1;
	switch ($modeattack) {
		case 1: // Record Attack
			$unpacked = unpack('Vt/Vs/vr', fread($upload_file, 10));
			if ($unpacked['t'] < 0 || $unpacked['t'] >= 0xFFFFFFFE) {
				fclose($upload_file);
				return "Replay does not complete the map and is not eligible for auto-submission.";
			}
			$replay_info['time'] = $unpacked['t'];
			$replay_info['score'] = $unpacked['s'];
			$replay_info['rings'] = $unpacked['r'];
			break;
		case 2: // NiGHTS Mode
			$unpacked = unpack('Vnt/Vns', fread($upload_file, 8));
			if ($unpacked['nt'] < 0 || $unpacked['nt'] >= 0xFFFFFFFE) {
				fclose($upload_file);
				return "Replay does not complete the map and is not eligible for auto-submission.";
			}
			$replay_info['ntime'] = $unpacked['nt'];
			$replay_info['nscore'] = $unpacked['ns'];
			break;
		default:
			fclose($upload_file);
			return "Replay was not made in Record Attack or NiGHTS Mode and is not eligible for auto-submission.";
	}

	// Skip the random seed (unimportant info for us)
	fread($upload_file, 4);

	// Get the internal name
	$unpacked = unpack('c16', fread($upload_file, 16));
	$player_name = '';
	foreach ($unpacked as $namebyte)
	{
		if (!$namebyte) break;
		$player_name .= chr($namebyte);
	}
	$replay_info['player_name'] = $player_name;
	
	// make sure character is not modified -- fetch character data
	$charmd5 = md5(fread($upload_file, 46));
	$character_data = mysql::fetchq("SELECT * FROM characters WHERE checksum = '{$charmd5}'", MYSQL_ASSOC);
	if (!$character_data) {
		fclose($upload_file);
		return "The character used in the replay is not supported.";
	}
	$replay_info['character_id'] = $character_data['character_id'];

	// User is whoever is logged in
	global $_logged_in_id;
	$replay_info['user_id'] = $_logged_in_id;
	
	// We've got the file data, and it's good. Close, then get all contents.
	fclose($upload_file);
	$fildata   = file_get_contents($_FILES['replay_file']['tmp_name']);
	$fildatagz = gzcompress($fildata, 6);

	// If compressed is greater size, then add raw. Otherwise, add compressed.
	if ($fildatagz !== FALSE && strlen($fildatagz) < strlen($fildata)) {
		$replay_data['gzipped'] = 1;
		$replay_data['data'] = $fildatagz;
	}
	else {
		$replay_data['gzipped'] = 0;
		$replay_data['data'] = $fildata;
	}
	unset($fildata,$fildatagz);

	// We're going to upload!
	// Time to pull out the readwrite!
	open_database_readwrite();

	if (!mysql::insertarray('replay_info', $replay_info))
		return "Internal error occurred while uploading file.  Please try again.";
	$replay_data['replay_id'] = $replay_info['replay_id'] = mysql_insert_id();
	if (!mysql::insertarray('replay_data', $replay_data))
		return "Internal error occurred while uploading file.  Please try again.";

	// Set submitted info data
	global $submitted_info;
	$submitted_info = $replay_info;
	return "No error";
}

function autosubmit_do_process() {
	// Read the replay data from the form
	$result = read_uploaded_replay();

	// If successful $submitted_info will be set for us, if not then error
	global $submitted_info;
	if (!$submitted_info) {
		if ($result)
			echo error_box('Sorry!  An error occurred while trying to upload your replay.<br><br>'.$result);
		return;
	}

	$class_types_all = array('score','time','rings','nscore','ntime');
	$class_types_records = array_intersect_key($submitted_info, array_flip($class_types_all));

	// this is silly -- rely on the outer quotes in the query and add inner ones ourselves
	$class_types_keys = implode('\',\'', array_keys($class_types_records));
	$classes = mysql::getresultsbykey("SELECT class_id,type FROM classes "
		."WHERE map_id='{$submitted_info['map_id']}' AND character_id='{$submitted_info['character_id']}' "
		."AND type IN ('{$class_types_keys}') ORDER BY class_id ASC", 'class_id', 'type');

	// for time and ntime: flip the times over zero
	if ($class_types_records['time'])	$class_types_records['time']  *= -1;
	if ($class_types_records['ntime'])	$class_types_records['ntime'] *= -1;

	$update_array = array(
		'user_id'		=>	$submitted_info['user_id'],
		'replay_id'		=>	$submitted_info['replay_id'],
		'manual_id'		=>	-1,
		'record_time'	=>	$submitted_info['time'],
		'version'		=>	$submitted_info['version'],

		'class_id'		=>	NULL, // replaced in loop
		'record'		=>	NULL  // replaced in loop
	);

	$added_records = array();
	foreach($classes as $class_id => $class_type) {
		$update_array['class_id'] = $class_id;
		$update_array['record']   = $class_types_records[$class_type];

		$current = mysql::fetchq("SELECT record,record_time FROM records "
			/* ."LEFT JOIN replay_info re ON (r.replay_id=re.replay_id) " */
			."WHERE class_id='{$class_id}' AND user_id='{$submitted_info['user_id']}' AND version='{$submitted_info['version']}'");

		if ($current === FALSE) // No current record; we can freely insert
			mysql::insertarray('records', $update_array);
		else { // Record exists, let's compare it
			if ($current['record'] >= $update_array['record'] // higher score, rings, etc; lower time with delta considered
			|| ($current['record'] == $update_array['record']
			 && $current['record_time'] <= $update_array['record_time'])) // identical record -- lower replay time
				continue; // don't update, record is better

			// record is an improvement -- update
			mysql::updatefromarray('records',
				array('class_id'=>$class_id, 'user_id'=>$submitted_info['user_id'], 'version'=>$submitted_info['version']),
				$update_array);
			mysql::query("UPDATE records SET submit_time=CURRENT_TIMESTAMP() "
				."WHERE class_id='{$class_id}' AND user_id='{$submitted_info['user_id']}' AND version='{$submitted_info['version']}'");
		}

		// Record has been added one way or another,
		// save some info so we can display it to the client
		$added_records[$class_type] = array('record'=>$class_types_records[$class_type], 'class_id'=>$class_id);
	}

	if (count($added_records) <= 0) {
		echo error_box('Sorry!  It seems your uploaded replay does not beat any of your previous bests.');
		return;
	}

	if ($submitted_info['version'] !== get_latest_version()) {
		echo
			'<table class="warning-table" width="75%"><tr><th>Older Version Notice</th></tr><tr><td>'
			. 'It looks like you\'re submitting a replay for an older version.<br><br>'
			. 'Please be aware that while records are saved separately for each version,<br>only the fastest replay you\'ve uploaded in any version will be shown when "Show Older Versions" is enabed.'
			. '</td></tr></table><br>';
	}

?>
	<table class="class-table" width="50%">
	<tr>
		<th colspan="6">Replay submitted successfully!</th>
	</tr>
	<tr>
		<th colspan="6">
<?php
	$mapinfo = mysql::fetchq("SELECT * FROM maps WHERE map_id={$submitted_info['map_id']}");
	echo "<a href=\"maps.php?id={$mapinfo['map_id']}\">{$mapinfo['zone_name']}";
	if ($mapinfo['is_zone']) echo ' Zone';
	if ($mapinfo['act']) echo " {$mapinfo['act']}";
	echo '</a>';
?>
		</th>
	</tr>
	<tr>
		<th colspan="2" rowspan="2">Class</th>
		<th colspan="4">Your Personal Best</th>
	</tr>
	<tr>
		<th>Record</th>
		<th>Held By</th>
		<th>Submitted</th>
		<th>DL</th>
	</tr>
<?php
	$character = $submitted_info['character_id'];
	$character_data = mysql::fetchq("SELECT name,face_icon FROM characters WHERE character_id='{$character}'");
	$file_image_link = "<a href=\"download.php?replay={$submitted_info['replay_id']}\">"
					.'<img src="/images/download.png" alt="DL" title="Download the replay file!" /></a>';
	global $_logged_in_user, $_logged_in_id;

	foreach ($added_records as $rtype => $rdata) {
		$record = record_to_display($rtype, $rdata['record']);
		$clnum = $rdata['class_id'];
		$realcname = get_class_name($rtype);
		$user_link = "<a href=\"user.php?user={$_logged_in_id}\">{$_logged_in_user}</a>";
		$class_image_link = "<a href=\"/records.php?class={$clnum}\">"
			."<img src=\"/images/{$character_data['face_icon']}\" title=\"{$character_data['name']}\" /></a>";

		echo '<tr>';
		echo "<th class=\"endline\" width=\"100px\">{$realcname}</th>";
		echo "<td class=\"endline brightsep\" width=\"24px\">{$class_image_link}</td>";
		echo "<td class=\"endline rightsep ac-num\" style=\"text-align:right;\" width=\"128px\">{$record}</td>";
		echo "<td class=\"endline rightsep\" width=\"*\">{$user_link}</td>";
		echo "<td class=\"endline rightsep\" width=\"25%\"><b>Just now</b></td>";
		echo "<td class=\"endline rightsep\" width=\"24px\">{$file_image_link}</td>";
		echo '</tr>';
	}
	echo '</table>';

}

function autosubmit_do_form() {
	global $maxsize;

	// Just in case later
	open_database_readonly();

	ini_set('upload_max_filesize', $maxsize);
	ini_set('max_file_uploads', 1);
?>
	<form enctype="multipart/form-data" action="submit.php" method="POST">
		<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo $maxsize; ?>" />
		<table class="class-table" width="50%">
		<tr><th class="nosep" colspan="2">Automatic Submission</th></tr>
		<tr><td class="classline" colspan="2" style="line-height:18px;">
			Upload a replay file from your replay folder using this form.<br>
			The site will automatically update your personal bests.<br>
			(Maximum file size is 3MB)
		</td></tr>
		<tr><td class="endline rightsep" style="text-align:left">
			<input name="replay_file" type="file" width="100%"/>
		</td><td class="endline" width="60px">
			<input type="submit" value="Submit" />
		</td></tr>
		</table>
	</form>
<?php
}
///--
///-- End submissions
///--

///--
///-- MANUAL SUBMISSIONS
///--
function read_post_data() {
	$class = intval($_POST['manual']);

	$stat = -1;
	switch ($_POST['stat-type']) {
		case 'global-gameclear':
		case 'global-allemeralds':
		case 'global-160emblems':
			// trim whitespace just in case
			$stx_a = trim($_POST['stat-t2-1']);
			$stx_b = trim($_POST['stat-t2-2']);
			$stx_c = trim($_POST['stat-t2-3']);
			$hours = intval($stx_a);
			$minutes = intval($stx_b);
			$seconds = intval($stx_c);

			// check for nonnumeric characters
			if ($minutes > 59 || $seconds > 59 || preg_match('/[^0-9]/', $stx_a) || preg_match('/[^0-9]/', $stx_b) || preg_match('/[^0-9]/', $stx_c))
				$stat = ENTRY_NONEXISTANT;
			else // good
				$stat = (($hours * 60 * 60) + ($minutes * 60) + $seconds) * -1;
			break;
		case 'time':
		case 'ntime':
			// trim whitespace just in case
			$stx_a = trim($_POST['stat-t-1']);
			$stx_b = trim($_POST['stat-t-2']);
			$stx_c = trim($_POST['stat-t-3']);
			$centiseconds = centiseconds_to_tics(intval($stx_c));
			$seconds = intval($stx_b);
			$minutes = intval($stx_a);

			// check for nonnumeric characters
			if ($centiseconds == -1 || $seconds > 59 || preg_match('/[^0-9]/', $stx_a) || preg_match('/[^0-9]/', $stx_b) || preg_match('/[^0-9]/', $stx_c))
				$stat = ENTRY_NONEXISTANT;
			else // good
				$stat = (($minutes * 35 * 60) + ($seconds * 35) + $centiseconds) * -1;
			break;
		case 'rings':
			$stat = intval($_POST['stat-r']);
			if (strlen($_POST['stat-r']) > 4 || preg_match('/[^0-9]/', $_POST['stat-r']))
				$stat = ENTRY_NONEXISTANT;
			break;
		default:
			$stat = intval($_POST['stat-n']);
			if ($stat > 999999990 || strlen($_POST['stat-n']) > 9 || preg_match('/[^0-9]/', $_POST['stat-n']))
				$stat = ENTRY_NONEXISTANT;
			break;
	}

	if ($stat === ENTRY_NONEXISTANT)
		return "Submitted data was invalid.  Please try again.";

	open_database_readonly();
	$class_data = mysql::fetchq("SELECT * FROM classes WHERE class_id='{$class}'");
	$map_data   = mysql::fetchq("SELECT * FROM maps WHERE map_id='{$class_data['map_id']}'");

	if (!$class_data || $map_data['auto_submit'] === '1')
		return NULL;

	// --- Get video url and type ---
	$video_url = $_POST['video'];
	$video_type = 'none';
	// Trim out http or https
	if (!strncasecmp($video_url, 'http://', 7))
		$video_url = substr($video_url, 7);
	else if (!strncasecmp($video_url, 'https://', 8))
		$video_url = substr($video_url, 8);

	// === Youtube ===
	// Youtube Long
	if (!strncasecmp($video_url, 'www.youtube.com/watch?', ($ytlen = 22))
	 || !strncasecmp($video_url, 'youtube.com/watch?', ($ytlen = 18))) {
		$video_type = 'youtube';
		$video_url = substr($video_url, $ytlen);
		$token = strtok($video_url, '&');
		while ($token !== false) {
			$pieces = explode('=', $token);
			if ($pieces[0] == 'v') {
				$video_url = $pieces[1];
				break;
			}
			strtok('&');
		}
		if ($token === false)
			return "Invalid Youtube URL.";
	}
	// Youtube Short
	else if (!strncasecmp($video_url, 'youtu.be/', 9) && strlen($video_url) > 9) {
		$video_type = 'youtube';
		$video_url = substr($video_url, 9);
		$video_url = strtok($video_url, '?&');
	}
	// ===============

	// === Hitbox ===
	else if (!strncasecmp($video_url, 'www.hitbox.tv/video/', ($hblen = 20))
	 || !strncasecmp($video_url, 'hitbox.tv/video/', ($hblen = 16))) {
		$video_type = 'hitbox';
		$video_url = substr($video_url, $hblen);
		$video_url = strtok($video_url, '?&');
		if (strlen($video_url) < 6 || (string)intval($video_url) !== $video_url)
			return "Invalid Hitbox URL.";
	}
	// ==============

	// === Twitch ===
	else if (!strncasecmp($video_url, 'beta.twitch.tv/', ($twlen = 15))
	 || !strncasecmp($video_url, 'www.twitch.tv/', ($twlen = 14))
	 || !strncasecmp($video_url, 'twitch.tv/', ($twlen = 10))) {
		$video_type = 'twitch';
		$video_url = substr($video_url, $twlen);

		// we store twitch like this, but do checks first
		$checks = explode('/', $video_url);
		if (count($checks) != 3)
			return "Invalid Twitch URL.";
		if ($checks[1] === 'b')
			return "Don't submit a raw broadcast!  Make a separate highlight first, and then submit that.";
		if ($checks[1] !== 'v')
			return "Invalid Twitch URL.";
		// checks out
	}
	// ==============
	
	else
		return "Submitted proof doesn't match any supported format.";
	// ------------------------------

	// Check data for duplication
	$final_check = mysql::resultq("SELECT manual_id FROM manual_submissions "
		."WHERE class_id='{$class}' AND type ='{$video_type}' AND url = '".mysql_real_escape_string($video_url)."'");
	if ($final_check !== NULL)
		return 'This video has already been used as proof for another submission in this class.<br>If you made an error when submitting a stat, report it and an admin will fix it.';

	// --- Manual submission info ---
	// User is whoever is logged in
	global $_logged_in_id;
	$manual_info['user_id'] = $_logged_in_id;

	// Other info
	$manual_info['class_id'] = $class;
	$manual_info['type'] = $video_type;
	$manual_info['url'] = $video_url;
	$manual_info['record'] = $stat;
	// ------------------------------

	// We're going to upload!
	// Time to pull out the readwrite!
	open_database_readwrite();

	if (!mysql::insertarray('manual_submissions', $manual_info))
		return "Internal error occurred while sending data.  Please try again.";
	$manual_info['manual_id'] = mysql_insert_id();

	// Set submitted info data
	global $submitted_info;
	$submitted_info = $manual_info;
	return "No error";
}

function manualsubmit_do_process() {
	// Read the form data
	$result = read_post_data();

	// If successful $submitted_info will be set for us, if not then error
	global $submitted_info;
	if (!$submitted_info) {
		if ($result) {
			echo error_box('Sorry!  An error occurred while trying to submit your stat.<br><br>'.$result);
			return 0;
		}
		echo error_box('You can\'t do a manual submission for this class.<br>Use the automatic submissions process instead.');
		return -1;
	}

	// --- There's only one record to add. ---
	$class_id = $submitted_info['class_id'];
	$class_type = mysql::resultq("SELECT type FROM classes WHERE class_id='{$class_id}'");

	$update_array = array('class_id'=>$class_id, 'user_id'=>$submitted_info['user_id'],
			'replay_id'=>-1, 'manual_id'=>$submitted_info['manual_id'],
			'record'=>$submitted_info['record'], 'record_time'=>-1);

	$current_record = mysql::resultq("SELECT record FROM records "
		."WHERE class_id='{$class_id}' AND user_id='{$submitted_info['user_id']}'");
	if ($current_record === NULL) // No current record; we can freely insert
		mysql::insertarray('records', $update_array);

	// Record exists, let's compare it
	else if ($current_record < $update_array['record']) // new is better
	{
		mysql::updatefromarray('records',
			array('class_id'=>$class_id, 'user_id'=>$submitted_info['user_id']),
			$update_array);
		mysql::query("UPDATE records SET submit_time=CURRENT_TIMESTAMP() "
			."WHERE class_id='{$class_id}' AND user_id='{$submitted_info['user_id']}'");
	}
	else {
		echo error_box('Sorry!  The stat you submitted is worse than a stat you previously uploaded.<br>If you made an error when submitting a stat, report it and an admin will fix it.');
		return 1;
	}
?>
	<table class="class-table" width="50%">
	<tr>
		<th colspan="6">Stat submitted successfully!</th>
	</tr>
	<tr>
		<th colspan="6">
<?php
	$mapinfo = mysql::fetchq("SELECT * FROM maps WHERE map_id='{$class_data['map_id']}'");
	echo "<a href=\"maps.php?id={$mapinfo['map_id']}\">{$mapinfo['zone_name']}";
	if ($mapinfo['is_zone']) echo ' Zone';
	if ($mapinfo['act']) echo " {$mapinfo['act']}";
	echo '</a>';
?>
		</th>
	</tr>
	<tr>
		<th colspan="2" rowspan="2">Class</th>
		<th colspan="4">Your Personal Best</th>
	</tr>
	<tr>
		<th>Record</th>
		<th>Held By</th>
		<th>Submitted</th>
		<th>DL</th>
	</tr>
<?php
	$character_data = mysql::fetchq("SELECT name,face_icon FROM characters WHERE character_id = "
		."(SELECT character_id FROM classes WHERE class_id='{$class_id}')");
	$file_image_link = get_download_link(-1, $submitted_info['manual_id']);

	global $_logged_in_user, $_logged_in_id;

	$record = record_to_display($class_type, $submitted_info['record']);
	$realcname = get_class_name($class_type);
	$user_link = "<a href=\"user.php?user={$_logged_in_id}\">{$_logged_in_user}</a>";
	$class_image_link = "<a href=\"/records.php?class={$class_id}\">"
		."<img src=\"/images/{$character_data['face_icon']}\" title=\"{$character_data['name']}\" /></a>";

	echo '<tr>';
	echo "<th class=\"endline\" width=\"100px\">{$realcname}</th>";
	echo "<td class=\"endline brightsep\" width=\"24px\">{$class_image_link}</td>";
	echo "<td class=\"endline rightsep ac-num\" style=\"text-align:right;\" width=\"128px\">{$record}</td>";
	echo "<td class=\"endline rightsep\" width=\"*\">{$user_link}</td>";
	echo "<td class=\"endline rightsep\" width=\"25%\"><b>Just now</b></td>";
	echo "<td class=\"endline rightsep\" width=\"24px\">{$file_image_link}</td>";
	echo '</tr>';
	echo '</table>';

	return 1;
}

function manualsubmit_do_form($class) {
	$class = intval($class);

	open_database_readonly();
	$class_data = mysql::fetchq("SELECT * FROM classes WHERE class_id='{$class}'");
	$map_data   = mysql::fetchq("SELECT * FROM maps WHERE map_id='{$class_data['map_id']}'");
	
	if (!$class_data || $map_data['auto_submit'] === '1') {
		echo error_box('You can\'t do a manual submission for this class.<br>Use the automatic submissions process instead.');
		echo '<br>';
		autosubmit_do_form();
		return;
	}

	// Map name
	$mtx = $map_data['zone_name'];
	if ($map_data['is_zone']) $mtx .= ' Zone';
	if ($map_data['act'])     $mtx .= " {$map_data['act']}";

	// Character name
	$chtx = mysql::resultq("SELECT name FROM characters WHERE character_id='{$class_data['character_id']}'");

	// Class info
	$ctx = get_class_name($class_data['type']);
	$ctx .= " - ";
	$ctx .= $chtx;

	$exrtext = '';
	if ($class_data['extra-rules'])
		$exrtext = '<tr><th class="nosep" colspan="2">Additional Rules for this Category</th></tr>'
			.'<tr><td colspan="2" class="endline" style="line-height:18px;text-align:left;"><ul>'
			.str_replace(array("\n", '*'), array('<br>', '<li>'), $class_data['extra-rules'])
			.'</ul></td></tr>';

	// Submit Form Data
	$submit_form = '<input type="text" name="stat-n" style="width:120px" maxlength="9" required />';
	switch ($class_data['type']) {
		case 'global-gameclear':
		case 'global-allemeralds':
		case 'global-160emblems':
			$submit_form = '<input type="text" name="stat-t2-1" style="width:24px" maxlength="2" required placeholder="HH" /> hours, ';
			$submit_form .= '<input type="text" name="stat-t2-2" style="width:24px" maxlength="2" required placeholder="MM" /> minutes, ';
			$submit_form .= '<input type="text" name="stat-t2-3" style="width:24px" maxlength="2" required placeholder="SS" /> seconds';
			break;
		case 'time':
		case 'ntime':
			$submit_form = '<input type="text" name="stat-t-1" style="width:24px" maxlength="2" required placeholder="MM" /> minutes, ';
			$submit_form .= '<input type="text" name="stat-t-2" style="width:24px" maxlength="2" required placeholder="SS" /> seconds, ';
			$submit_form .= '<input type="text" name="stat-t-3" style="width:24px" maxlength="2" required placeholder="CC" /> centiseconds';
			break;
		case 'rings':
			$submit_form = '<input type="text" name="stat-r" style="width:60px;" maxlength="4" required />';
			break;
	}
	// --- Form output begin ---
?>
	<form action="submit.php" method="POST">
		<input type="hidden" name="manual" value="<?php echo $class ?>" />
		<input type="hidden" name="stat-type" value="<?php echo $class_data['type'] ?>" />
		<table class="class-table" width="50%">
		<tr><th class="nosep" colspan="2">Manual Submission</th></tr>
		<tr><th width="40%"><?php echo $mtx; ?></th><th class="nosep"><?php echo $ctx ?></th></tr>
		<tr><th class="nosep" colspan="2">Basic Rules</th></tr>
		<tr><td colspan="2" class="endline" style="line-height:18px;text-align:left;">
		<ul>
		<li>We'd prefer you to use the latest version of the game.<br>
		Submissions are accepted for earlier versions, however won't be displayed if "Show older versions" is toggled off.
		<li>A video with proof of your achievement is required; <b>no exceptions</b>.<br>
		Screenshots alone are not accepted.
		<li>Accepted sources include:
			<ul>
			<li>Youtube Video
			<li>Hitbox.tv Video
			<li>Twitch.tv Highlights
			</ul>
		<li>Post the entire video URL into the proof form.<br>
		<li>For categories that require external timers:
			<ul>
			<li>If you don't already have an external timer, you can use these:
				<ul>
				<li><a href="http://livesplit.org/">LiveSplit</a>
				<li><a href="http://jenmaarai.com/llanfair/en/">Llanfair</a>
				<li><a href="http://www.speedrunslive.com/tools/">WSplit, etc.</a>
				</ul>
			<li>You must not pause the timer in any way.
			<li>You should preferably display the timer in your video/stream.
			<li>The start and stop times will be displayed in the "Additional Rules".
			</ul>
		</ul>
		</td></tr>
		<?php echo $exrtext; ?>
		<tr><th colspan="2">Submission Form</th></tr>
		<tr>
			<th>Your Submission</th>
			<td style="text-align:left;"><?php echo $submit_form; ?></td>
		</tr>
		<tr>
			<th>Video Proof</th>
			<td style="text-align:left;"><input type="text" name="video" style="width:98%;" required /></td>
		</tr>
		<tr>
			<th>Game Version</th>
			<td style="text-align:left;">2.1.<input type="text" name="version" style="width:24px" maxlength="2" required /></td>
		</tr>
		<tr><th colspan="2"><input type="submit" value="Submit" /></th></tr>
		</table>
	</form>
<?php
	// -------------------------
}

function manualsubmit_do_list() {
?>
	<table class="class-table" width="50%">
	<tr><th class="nosep" colspan="2"> Manual Submission</th></tr>
	<tr><td class="classline" colspan="2" style="line-height:18px;">
		Unfortunately, there are some classes we can't rely on automatic submissions for.<br>
		Things like NiGHTS maps, where there is no replay system implemented yet,<br>
		or for the global records like "fastest completion".<br>
		<br>
		You can submit stats for these classes manually.  Choose from the list below to start the process.
	</td></tr>
<?php
	open_database_readonly();
	$maps = mysql::getarraybykey('SELECT * FROM maps WHERE auto_submit != 1 ORDER BY map_id ASC', 'map_id');

	$map_list = array();
	foreach ($maps as $map)
		$map_list[] = $map['map_id'];
	$map_list = implode(',', $map_list);

	$classes = mysql::getarraybykey("SELECT * FROM classes WHERE map_id IN ({$map_list}) ORDER BY map_id ASC, class_id ASC", 'class_id');
	$characters = mysql::getresultsbykey('SELECT name,character_id FROM characters', 'character_id', 'name');

	foreach ($classes as $class) {
		$mapinfo = $maps[$class['map_id']];

		$mtx = $mapinfo['zone_name'];
		if ($mapinfo['is_zone']) $mtx .= ' Zone';
		if ($mapinfo['act'])     $mtx .= " {$mapinfo['act']}";

		$ctx = "<a href=\"submit.php?manual={$class['class_id']}\">";
		$ctx .= get_class_name($class['type']);
		$ctx .= " - ";
		$ctx .= $characters[$class['character_id']];
		$ctx .= "</a>";

		echo '<tr><th width="40%">'.$mtx.'</th>';
		echo '<td>'.$ctx.'</td></tr>';
	}
?>
	</table>
<?php
}
///--
///-- End manual submissions
///--

?>
<div align="center">
<?php

// Manual submissions process
if (isset($_POST['manual'])) {
	$res = manualsubmit_do_process();
	echo "<br>";
	if ($res === 1)
		manualsubmit_do_list();
	else if ($res === 0)
		manualsubmit_do_form($_POST['manual']);
	else
		autosubmit_do_form();
}
else if (isset($_GET['manual'])) {
	manualsubmit_do_form($_GET['manual']);
}
// Automatic submissions process
else if ($_FILES['replay_file']) {
	autosubmit_do_process();
	echo "<br>";
	autosubmit_do_form();
}
// Show all
else {
	autosubmit_do_form();
	echo "<br>";
	manualsubmit_do_list();
}

?>
</div>
