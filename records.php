<?php require_once("include/common.php");
open_database_readonly();

// We want to ensure nobody puts junk into our SQL query,
// but we want to allow multiple integers for a class query.
$ex_classes = explode(',', $_GET['class']);
$classes = array();
foreach ($ex_classes as $class)
	if (intval($class) != 0) $classes[] = intval($class);
unset($ex_classes);
$classes_count = count($classes);
$classes = implode(',', $classes);
// And now we have a safe query string.

if ($classes_count < 1)
	die(error_box("No classes specified."));

// To display records, we must be showing them from the same map and from the same type.
// Only the character can differ.
if ($classes_count > 1) {
	$unique_check = mysql::fetchq("SELECT COUNT(DISTINCT map_id) map_check, COUNT(DISTINCT type) type_check FROM `classes` WHERE class_id IN ({$classes})");
	if ($unique_check['map_check'] != 1 || $unique_check['type_check'] != 1)
		die(error_box("The requested classes are incompatible with each other."));
}

// --- Get all character data ---
$characters_list = mysql::getarraybykey("SELECT c.class_id,ch.character_id,ch.name,ch.face_icon "
	."FROM classes c "
	."LEFT JOIN characters ch ON (c.character_id=ch.character_id) "
	."WHERE class_id IN ({$classes})", 'class_id');
// ------------------------------

// --- Get other important class data ---
$class_map  = mysql::fetchq("SELECT m.map_id,m.pack_id,m.zone_name,m.is_zone,m.act FROM classes c "
	."LEFT JOIN maps m ON (c.map_id=m.map_id) WHERE class_id IN ({$classes}) LIMIT 1");

$class_type = mysql::resultq("SELECT type FROM classes WHERE class_id IN ({$classes}) LIMIT 1");
// Record sort is now always descending
// --------------------------------------

?>
<div align="center">
	<br>
	<!-- Zone display name -->
	<?php echo make_zone_name_table($class_map['zone_name'], $class_map['is_zone'], $class_map['act']); ?>
	<br>
<?php

// --- Table header ---
$real_class_name = get_class_name($class_type);

foreach ($characters_list as $char)
	$char_names[] = $char['name'];
$characters_string = array_to_formal_list($char_names);
$s = (($classes_count > 1) ? 's' : '');
echo "Showing all records for {$real_class_name} for character{$s} {$characters_string}";
// --------------------

$records = mysql::getarraybykey("SELECT r.record_id,r.class_id,r.version,r.record,r.record_time,r.replay_id,r.manual_id, "
	."u.user_id uid, u.name user, CONCAT(u.user_id, '-', r.class_id) array_key, "
	."rp.result reportdata, "
	."DATE_FORMAT(r.submit_time, '%M %D, %Y') submit_time, "
	."DATE_FORMAT(r.submit_time, '%l:%i %p') submit_time_hour, "
	."TIMESTAMPDIFF(MINUTE, r.submit_time, NOW()) recency "
	."FROM records r "
	."LEFT JOIN users u ON (r.user_id=u.user_id) "
	."LEFT JOIN reports rp ON (r.record_id=rp.record_id) "
	."WHERE r.class_id IN ({$classes}) "
	.((should_show_old_records()) ? "" : "AND r.version='" . get_latest_version() . "' " )
	."ORDER BY r.record DESC, r.record_time ASC, r.submit_time ASC",
'array_key', false);
// Key is user ID so that only one record per user is shown
// Overwrite mode off so that the best record per user ID is shown and no other
// This makes some after-selection code very unnecessary

?>
	<br>
	<table class="class-table" width="50%">
		<tr>
			<?php if ($classes_count > 1) echo '<th>CH</th>'; ?>
			<th>Position</th>
			<th>Record</th>
			<th>Held By</th>
			<th>Submitted</th>
			<?php if (should_show_old_records()) echo "<th>Ver</th>"; ?>
			<th>DL</th>
			<th>Rpt</th>
		</tr>
<?php

$position = $position_real = 0;
$record_last = -1;

$show_record_time = false;
$can_show_record_time = in_array($class_type, array('score', 'rings'));

$count_records = count($records);

// --- Count tied records ---
$count_scores = array();
foreach ($records as $rec_data) {
	$rec_score = $rec_data['record'];
	$count_scores[$rec_score] = ((isset($count_scores[$rec_score])) ? $count_scores[$rec_score] + 1 : 1);
}
// --------------------------

foreach ($records as $rec_data) {
	echo '<tr>';

	++$position;
	$record_this = $rec_data['record'];
	if ($record_this !== $record_last) {
		$position_real = $position;
		$count_scores_int = $count_scores[$record_this];
	}
	$record_last = $record_this;

	// --- Border CSS ---
	$cssbottom = 'classline';
	if ((--$count_scores_int)) $cssbottom = 'midline';
	if (!(--$count_records))   $cssbottom = 'endline';
	// ------------------

	// --- Position CSS and Images ---
	$ordinal = 'th';
	if ((int)($position_real / 10) !== 1) switch ($position_real % 10) {
		case 1: $ordinal = 'st'; break;
		case 2: $ordinal = 'nd'; break;
		case 3: $ordinal = 'rd'; break;
	}

	$cssordinal = '';
	if ($position_real == 1) $cssordinal = 'position-first';
	else if ($position_real == 2) $cssordinal = 'position-second';
	else if ($position_real == 3) $cssordinal = 'position-third';
	else if ($position_real <= 10) $cssordinal = 'position-topten';

	$tied_text = (($count_scores[$record_this] > 1) ? '<span class="ac-tiny">Tied</span> ' : '');
	if ($can_show_record_time)
		$show_record_time = ($count_scores[$record_this] > 1);

	$position_display = "{$tied_text}<span class=\"ac-num\">{$position_real}</span><span class=\"ac-tiny\">{$ordinal}</span>";
	// -------------------------------

	// *** Output ***

	// --- Link to class ---
	if ($classes_count > 1) {
		$character = $characters_list[$rec_data['class_id']];
		$class_image_link = "<a href=\"/records.php?class={$character['class_id']}\">"
			."<img src=\"/images/{$character['face_icon']}\" title=\"{$character['name']}\" /></a>";

		echo "<td class=\"{$cssbottom} brightsep\" width=\"24px\">{$class_image_link}</td>";
	}
	// ---------------------

	echo "<td class=\"{$cssordinal} {$cssbottom} rightsep table-number\" width=\"60px\">{$position_display}</td>";

	// --- Record data ---
	$record = record_to_display($class_type, $rec_data['record']);
	if ($show_record_time) {
		$record_time = make_time_string($rec_data['record_time']);
		if (strlen($record_time) <= 7) $record_time = '_'.$record_time;
	}
	else $record_time = NULL;

	// Style A (In one cell, all numbers right aligned)
	echo "<td class=\"{$cssbottom} rightsep table-number\" width=\"160px\">";
	echo "<span class=\"ac-num\">{$record}</span>";
	if ($record_time)
		echo "<br><span class=\"ac-tiny\">Time: {$record_time}</span>";
	echo "</td>";
	// -------------------

	// --- User data ---
	$submit_time = "{$rec_data['submit_time']}<br /><span class=\"moreinfo\">{$rec_data['submit_time_hour']}</span>";
	$submit_time = display_recency($submit_time, $rec_data['recency']);
	$file_image_link = get_download_link($rec_data['replay_id'], $rec_data['manual_id']);

	$record_user  = $rec_data['user'];
	if ($rec_data['uid'])
		$record_user  = "<a href=\"user.php?user={$rec_data['uid']}\">{$record_user}</a>";

	echo "<td class=\"{$cssbottom} rightsep\" width=\"*\">{$record_user}</td>";
	echo "<td class=\"{$cssbottom} rightsep\" width=\"25%\">{$submit_time}</td>";
	if (should_show_old_records()) {
		if ($rec_data['version'] === '000.000')
			$real_version = '?';
		else
			$real_version = /*$rec_data['version']{0}.'.'.$rec_data['version']{2}.*/'.'.intval(substr($rec_data['version'],4));
		if ($rec_data['version'] !== get_latest_version())
			echo "<td class=\"{$cssbottom} brightsep\" width=\"24px\"><i>{$real_version}</i></td>";
		else
			echo "<td class=\"{$cssbottom} brightsep\" width=\"24px\"><b>{$real_version}<b></td>";
	}
	echo "<td class=\"{$cssbottom} brightsep\" width=\"24px\">{$file_image_link}</td>";
	// -----------------

	// --- Report button ---
	$report_image_link = get_report_link($rec_data['record_id'], $rec_data['reportdata']);
	echo "<td class=\"{$cssbottom}\" width=\"24px\">{$report_image_link}</td>";
	// ---------------------

	echo '</tr>';
	echo "\r\n";
}

?>
	</table>
</div>
<?php
// Show location
// Really it doesn't matter where we put this, but it's nice to put it at the end
// since everything's done and evaluated already.
$pack_name = mysql::resultq("SELECT name FROM packs WHERE pack_id='{$class_map['pack_id']}' LIMIT 1");
$canonical_levelname = $class_map['zone_name'] . (($class_map['act'] != '0') ? " {$class_map['act']}" : '');
set_location(array("maps.php?pack={$class_map['pack_id']}" => $pack_name, "maps.php?id={$class_map['map_id']}" => $canonical_levelname));
