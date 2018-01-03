<?php require_once("include/common.php");
open_database_readonly();

// *** Output functions ***
function get_map_list($pack)
{
	$pack_data = mysql::fetchq("SELECT * FROM packs WHERE pack_id='{$pack}' LIMIT 1");
	$arrange_data = explode("\n",$pack_data['page_arrangement']);

	// Page arrangement is a newline separated list of strings
	// In the format of Row name:map,map,map,map,...
	$map_ids = array();   // stores all map numbers (for query)
	$maps_rows = array(); // stores row placement data
	$max_cols = 0;        // used for table alignment
	foreach($arrange_data as $arrangement) {
		$exp = explode(":", trim($arrangement), 2);
		// everything before the colon: $exp[0]
		// everything after the colon:  $exp[1]
		$name_strings = explode("~", $exp[0]); // separate due to newlines
		$maps_strings = explode(",", $exp[1]); // colon separated list of map numbers
		$maps_nums = array();
		foreach($maps_strings as $str)
			$map_ids[] = $maps_nums[] = intval($str);
		
		$maps_rows[] = array("name" => $name_strings, "maps" => $maps_nums);
		if ($max_cols < count($maps_nums)) $max_cols = count($maps_nums);
	}

	$maps_data = mysql::getarraybykey(
		'SELECT map_id, map_number, zone_name, act '
		.'FROM maps '
		.'WHERE map_id IN ('.implode(',', $map_ids).')'
	, 'map_id');

?>
<div align="center">
	<table class="class-table" width="50%">
	<tr><th class="nosep"><?php echo $pack_data['name']; ?></th></tr>
<?php if ($pack_data['description']) { ?>
	<tr><td class="endline" style="line-height:18px;">
		<?php echo $pack_data['description']; ?>
	</td></tr>
<?php } ?>
	</table>

	<table width="100%">
<?php
	foreach ($maps_rows as $row) {
		echo '<tr height="160px"><td align="right" width="22%">';
		echo make_zone_row_table($row['name']);
		echo '</td><td align="center" width="*"><table width="100%"><tr>';
		foreach($row['maps'] as $mapnum) {
			$map = $maps_data[$mapnum];
			$mapimgname = 'images/maps/'.$pack_data['images_folder'].'/'.get_map_name($map['map_number']).'P.png';
			if (!file_exists($mapimgname))
				$mapimgname = 'images/maps/missing.png';
			if ($pack_data['full_display']) // Non-sequitur map numbers (SUGOI)
				$textbelow = $map['zone_name'] . (($map['act']) ? " {$map['act']}" : '');
			else
				$textbelow = (($map['act']) ? "Act {$map['act']}" : $map['zone_name']);
			echo '<td align="center" width="*">';
			echo "<a href=\"?id={$mapnum}\">";
			echo "<img src=\"/{$mapimgname}\">";
			echo "<br><span class=\"ac-std\">{$textbelow}</span>";
			echo "</a>";
			echo "</td>";
		}
		echo '<td width="22%"></td></tr></table></td></tr>';
	}
?>
	</table>
</div>
<?php
}

function get_map_info($map)
{
	// Get all face icons (for the All Characters icon)
	$faceicons = mysql::getresultsbykey('SELECT character_id,face_icon FROM characters', 'character_id', 'face_icon');
	$charnames = mysql::getresultsbykey('SELECT character_id,name FROM characters', 'character_id', 'name');

	$classes = mysql::getarraybykey("SELECT class_id clid,character_id chid,type "
		."FROM classes WHERE map_id = {$map['map_id']} ORDER BY class_id ASC", 'clid');
	$classes_fulllist = array();
	foreach ($classes as $cl)
		$classes_fulllist[$cl['type']][$cl['clid']] = $cl['chid'];

	// Sort the array for display by character number
	// Also add "All" if it needs to be there
	foreach ($classes_fulllist as $k=>$irrlevant) {
		// Only one class doesn't need anything
		if (count($classes_fulllist[$k]) <= 1) continue;

		ksort($classes_fulllist[$k]);

		// Add all afterwards if it doesn't exist already
		if (in_array("0", $classes_fulllist[$k], true)) continue;
		$classes_fulllist[$k][implode(',', array_keys($classes_fulllist[$k]))] = 0;
	}

	// Get record info for each class
	$record_info = array();

	foreach ($classes_fulllist as $type=>$classes) {
		// Formerly order was hardcoded and could vary by class type
		// Now however time related records are stored in reverse
		foreach ($classes as $k=>$irrelevant) {
			$record_info[$k] = mysql::fetchq(
				 "SELECT class_id, r.record, r.record_time, r.replay_id, r.manual_id, u.user_id uid, u.name user, "
				."DATE_FORMAT(r.submit_time, '%M %D, %Y') submit_time, "
				."DATE_FORMAT(r.submit_time, '%l:%i %p') submit_time_hour, "
				."TIMESTAMPDIFF(MINUTE, r.submit_time, NOW()) recency, "
				."("
					."SELECT COUNT(DISTINCT user_id) - 1 ties FROM records "
					."WHERE record = r.record "
					."AND class_id IN ({$k}) "
					.((should_show_old_records()) ? "" : "AND version='" . get_latest_version() . "' " )
				.") ties "
				."FROM records r LEFT JOIN users u ON (r.user_id = u.user_id) "
				."WHERE class_id IN ({$k}) "
				.((should_show_old_records()) ? "" : "AND r.version='" . get_latest_version() . "' " )
				."ORDER BY r.record DESC, r.record_time ASC, submit_time ASC LIMIT 1");

			if (!$record_info[$k])
				$record_info[$k] = array(
					'record' => ENTRY_NONEXISTANT,
					'replay_id' => -1,
					'manual_id' => -1,
					'submit_time' => '<i>Never</i>',
					'recency' => 16777216,
					'user' => '<i>Nobody</i>'
				);
		}
	}
	
?>
<div align="center">
	<br>
	<!-- Zone display name -->
	<?php echo make_zone_name_table($map['zone_name'], $map['is_zone'], $map['act']); ?>
	<br>
	Select a category...
	<br>
	<table class="class-table" width="50%">
	<tr>
		<th               colspan="2" rowspan="2">Class</th>
		<th class="nosep" colspan="5"            >Current Record</th>
	</tr>
	<tr>
		<!-- element: Class -->
		<th              >Record</th>
		<th              >Held By</th>
		<th              >Submitted</th>
		<th class="nosep">DL</th>
	</tr>
<?php

	foreach ($classes_fulllist as $cname => $classes) {
		echo '<tr>';

		$count = count($classes);
		$realcname = get_class_name($cname);
		echo "<th class=\"endline\" width=\"100px\" rowspan=\"{$count}\">{$realcname}</th>";

		$first = true;
		foreach ($classes as $clnum => $character) {
			if (!$first) echo '<tr>';
			$first = false;

			if (!(--$count)) $cssclass = 'endline';
			else             $cssclass = 'midline';

			// Shortcut
			$rec_data = $record_info[$clnum];

			// --- Link to class ---
			$class_image_link = "<a href=\"/records.php?class={$clnum}\">"
				."<img src=\"/images/{$faceicons[$character]}\" title=\"{$charnames[$character]}\" /></a>";
			echo "<td class=\"{$cssclass} brightsep\" width=\"24px\">{$class_image_link}</td>";
			// ---------------------

			// --- Record data ---
			$record = record_to_display($cname, $rec_data['record']);

			// Display record time only if necessary.
			// August 2016: Never
/*			if (in_array($cname, array('score', 'rings'))) {
				$record_time = make_time_string($rec_data['record_time']);
				if (strlen($record_time) <= 7) $record_time = '_'.$record_time;
			}
			else $record_time = NULL; */

			// Style A (In one cell, all numbers right aligned)
			echo "<td class=\"{$cssclass} rightsep table-number\" width=\"160px\">";
			echo "<span class=\"ac-num\">{$record}</span>";
/*			if ($record_time)
				echo " <span class=\"ac-tiny\"> in {$record_time}</span>"; */
			echo "</td>";
			// -------------------

			// --- User data ---
			$submit_time = "{$rec_data['submit_time']}<br /><span class=\"moreinfo\">{$rec_data['submit_time_hour']}</span>";
			$submit_time = display_recency($submit_time, $rec_data['recency']);
			$file_image_link = get_download_link($rec_data['replay_id'], $rec_data['manual_id']);
			$record_user  = $rec_data['user'];
			if ($rec_data['uid'])
				$record_user  = "<a href=\"user.php?user={$rec_data['uid']}\">{$record_user}</a>";
			if ($rec_data['ties'] > 0)
				$record_user .= "<br><span class=\"moreinfo\">and {$rec_data['ties']} other"
					.(($rec_data['ties'] > 1) ? 's' : '')
					."</span>";

			echo "<td class=\"{$cssclass} rightsep\" width=\"*\">{$record_user}</td>";
			echo "<td class=\"{$cssclass} rightsep\" width=\"25%\">{$submit_time}</td>";
			echo "<td class=\"{$cssclass}\" width=\"24px\">{$file_image_link}</td>";
			// -----------------

			echo '</tr>';
		}
		echo "\r\n";
	}

?>
	</table>
	</div>
<?php
	// Show location
	$pack_name = mysql::resultq("SELECT name FROM packs WHERE pack_id='{$map['pack_id']}' LIMIT 1");
	set_location(array("?pack={$map['pack_id']}" => $pack_name));
}

// *** Helper Functions ***
function get_map_struct_id($id) {
	$map = mysql::fetchq("SELECT * FROM maps WHERE map_id='{$id}'");
	return ($map) ? $map : FALSE;
}

// *** Execution ***
$mapid      = ((isset($_GET['id'] ))  ? intval($_GET['id']  ) : -1);
$packnumber = ((isset($_GET['pack'])) ? intval($_GET['pack']) : -1);

$map_data = NULL;
if ($mapid >= 0)
	$map_data = get_map_struct_id($mapid);

// Wasn't left blank, but got an error out of it
if ($map_data === FALSE)
	die(error_box("Couldn't find a map matching those criteria."));

if ($map_data)
	get_map_info($map_data);
elseif ($packnumber >= 0)
	get_map_list($packnumber);
else
	get_map_list(1);
