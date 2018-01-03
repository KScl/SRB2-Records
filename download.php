<?php
require_once("include/common.php");
open_database_readonly();

if (isset($_GET['replay'])) {
	$replayid = intval($_GET['replay']);
	$replay_data = mysql::fetchq("SELECT gzipped, data FROM replay_data WHERE replay_id = '{$replayid}'");
	if (!$replay_data)
		die(error_box("The replay you requested is not available."));

	// Also get replay info for map number
	$replay_mapnum = mysql::resultq("SELECT map_number FROM maps WHERE map_id = (SELECT map_id FROM replay_info WHERE replay_id = '{$replayid}')");
	if ($replay_mapnum === NULL)
		die(error_box("The replay you requested is not available."));
	$replay_filename = get_map_name($replay_mapnum).'-guest.lmp';
	
	$replay_isgzip = ($replay_data['gzipped']);
	$replay_data = $replay_data['data'];

	// Uncompress if compressed in the database
	if ($replay_isgzip) {
		$ungz = gzuncompress($replay_data);
		if ($ungz === FALSE)
			die(error_box("The replay you requested is not available."));
		$replay_data = $ungz;
		unset($ungz);
	}

	// templates mess with us. turn them off.
	$_no_template = true;

	// ok, now let's send the headers to tell the client this is a download
	header('Content-type: application/octet-stream');
	header('Content-Disposition: attachment; filename="'.$replay_filename.'"');
	die($replay_data);
}
else if (isset($_GET['manual'])) {
	$manualid = intval($_GET['manual']);
	$manual_data = mysql::fetchq("SELECT * FROM manual_submissions WHERE manual_id = '{$manualid}'");

	if (!$manual_data);
	else switch($manual_data['type']) {
		case 'hitbox':
			do_redirect('http://hitbox.tv/video/'.$manual_data['url']);
		case 'youtube':
			do_redirect('http://youtube.com/watch?v='.$manual_data['url']);
		case 'twitch':
			do_redirect('http://twitch.tv/'.$manual_data['url']);
	}
	die(error_box("The manual submission you requested is not available."));
}
do_redirect('/');