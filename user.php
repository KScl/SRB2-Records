<?php
require_once("include/common.php");
open_database_readonly();

$user_id = intval($_GET['user']);
$user_data = mysql::fetchq("SELECT * FROM users WHERE user_id='{$user_id}'");
if (!$user_data)
	die("User doesn't exist.");

$pack_select = ((isset($_GET['pack'])) ? intval($_GET['pack']) : NULL);

// Get submission counts and pack info
$pack_user_info = mysql::getarraybykey(
	 "SELECT p.pack_id,p.name,COUNT(r.record_id) pack_total "
	."FROM records r "
	."LEFT JOIN classes c ON (r.class_id=c.class_id) "
	."LEFT JOIN maps m ON (c.map_id=m.map_id) "
	."LEFT JOIN packs p ON (p.pack_id=m.pack_id) "
	."WHERE r.user_id='{$user_id}' "
	."GROUP BY m.pack_id ORDER BY pack_total ASC", 'pack_id');

// --- Get all of this user's records ---
// (This is a huge query!)
$user_records = mysql::getarraybykey("SELECT m.map_id,m.zone_name,m.is_zone,m.act, "
	."c.type, "
	."ch.name char_name,ch.face_icon char_icon, "
	."r.record_id,r.class_id,r.version,r.replay_id,r.manual_id,r.record,r.record_time, "
	."DATE_FORMAT(r.submit_time, '%M %D, %Y') submit_time, "
	."DATE_FORMAT(r.submit_time, '%l:%i %p') submit_time_hour, "
	."TIMESTAMPDIFF(MINUTE, r.submit_time, NOW()) recency "
	."FROM records r "
	."LEFT JOIN classes c ON (r.class_id=c.class_id) "
	."LEFT JOIN characters ch ON (c.character_id=ch.character_id) "
	."LEFT JOIN maps m ON (c.map_id=m.map_id) "
	."WHERE r.user_id='{$user_id}' "
	.(($pack_select === NULL) ? '' : "AND m.pack_id='{$pack_select}' ")
	.((should_show_old_records()) ? '' : "AND r.version='" . get_latest_version() . "' " )
	."ORDER BY class_id ASC, r.record DESC", 'class_id', false);
// Overwrite mode off so that the best record per class ID is shown and no other
// --------------------------------------

// --- Get user's current position for all records ---
$pos_where = array("user_id='{$user_id}'");
foreach ($user_records as $cl => $rec)
	$pos_where[] = "(class_id='{$cl}' AND record > '{$rec['record']}')";
$pos_where = implode(' OR ', $pos_where);
// Note: COUNT(DISTINCT) because version differences may cause duplicate user IDs, and we filter them out
$user_positions = mysql::getresultsbykey("SELECT class_id,COUNT(DISTINCT user_id) pos "
	."FROM records "
	."WHERE ({$pos_where}) "
	.((should_show_old_records()) ? "" : "AND version='" . get_latest_version() . "'" )
	."GROUP BY class_id ORDER BY class_id ASC",'class_id','pos');
// Replace > with = to count ties.
$pos_where = str_replace('>', '=', $pos_where);
$user_ties = mysql::getresultsbykey("SELECT class_id,COUNT(DISTINCT user_id) ties "
	."FROM records "
	."WHERE ({$pos_where}) "
	.((should_show_old_records()) ? "" : "AND version='" . get_latest_version() . "'" )
	."GROUP BY class_id ORDER BY class_id ASC",'class_id','ties');

// ---------------------------------------------------

?>
<div align="center">
	<?php if ($user_data['avatar_url']) { ?>
	<div style="display:inline-block;vertical-align:center;">
		<img src="<?php echo $user_data['avatar_url'];?>" width=64 height=64 />
	</div>
	<?php } ?>
	<div style="display:inline-block;vertical-align:center;">
		<br>
		<div style="font-size:x-large;">User data for <?php echo $user_data['name']; ?></div>
		<a href="http://mb.srb2.org/member.php?u=<?php echo $user_data['mb_user']; ?>">View SRB2MB profile</a><br><br>
	</div>
	<table class="class-table" width="60%">
	<tr>
		<th             rowspan="2">Map</th>
		<th colspan="2" rowspan="2">Class</th>
		<?php
			if (should_show_old_records())
			     echo '<th colspan="5"            >Personal Bests</th>';
			else echo '<th colspan="4"            >Personal Bests</th>';
		?>
	</tr>
	<tr>
		<!-- element: Map   -->
		<!-- element: Class -->
		<th>Position</th>
		<th>Record</th>
		<th>Submitted</th>
		<?php if (should_show_old_records()) echo "<th>Ver</th>"; ?>
		<th>DL</th>
	</tr>
<?php
// Get zone info reaaallly quickly!
// Calculate number of records per map.
$maprec_count = array();
$classdta_count = array();
foreach ($user_records as $rec_data) {
	$map = $rec_data['map_id'];
	$maprec_count[$map] = ((isset($maprec_count[$map])) ? $maprec_count[$map] + 1 : 1);

	$classdta = $rec_data['map_id'] . $rec_data['type'];
	$classdta_count[$classdta] = ((isset($classdta_count[$classdta])) ? $classdta_count[$classdta] + 1 : 1);
}

// Now iterate
foreach ($user_records as $class => $rec_data) {
	echo '<tr>';

	// --- Map change? ---
	$map_this = $rec_data['map_id'];
	if ($map_last !== $map_this) {
		$zone = $rec_data['zone_name'];
		if ($rec_data['is_zone']) $zone .= ' Zone';
		if ($rec_data['act'])     $zone .= " {$rec_data['act']}";
		echo "<th rowspan=\"{$maprec_count[$map_this]}\" width=\"*\"><a href=\"maps.php?id={$map_this}\">{$zone}</a></th>";

		$map_count = $maprec_count[$map_this];
	}
	$map_last = $map_this;
	// -------------------

	// --- Class change? ---
	$class_this = $rec_data['map_id'] . $rec_data['type'];
	if ($class_last !== $class_this) {
		$display_class = get_class_name($rec_data['type']);

		echo "<th rowspan=\"{$classdta_count[$class_this]}\" width=\"100px\">{$display_class}</th>";
		$class_count = $classdta_count[$class_this];
	}
	$class_last = $class_this;
	// ---------------------

	$cssbottom = 'midline';
	if (!(--$class_count)) $cssbottom = 'classline';
	if (!(--$map_count))  $cssbottom = 'endline';

	// --- Position CSS and Images ---
	$position_real = $user_positions[$class];
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

	$tied_text = (($user_ties[$class] > 1) ? '<span class="ac-tiny">Tied</span> ' : '');
	if ($rec_data['type']==='score' || $rec_data['type']==='rings')
		$show_record_time = ($user_ties[$class] > 1);
	else $show_record_time = false;

	$position_display = "{$tied_text}<span class=\"ac-num\">{$position_real}</span><span class=\"ac-tiny\">{$ordinal}</span>";
	// -------------------------------

	// *** Output ***

	// --- Link to class ---
	$class_image_link = "<a href=\"/records.php?class={$rec_data['class_id']}\">"
		."<img src=\"/images/{$rec_data['char_icon']}\" title=\"{$rec_data['char_name']}\" /></a>";
	echo "<td class=\"{$cssbottom} brightsep\" width=\"24px\">{$class_image_link}</td>";
	// ---------------------

	echo "<td class=\"{$cssordinal} {$cssbottom} rightsep table-number\" width=\"60px\">{$position_display}</td>";

	// --- Record data ---
	$record = record_to_display($rec_data['type'], $rec_data['record']);
	$record_time = record_to_display('time', -$rec_data['record_time']);
	if (strlen($record_time) <= 7) $record_time = '_'.$record_time;

	// Style A (In one cell, all numbers right aligned)
	echo "<td class=\"{$cssbottom} rightsep table-number\" width=\"160px\">";
	echo "<span class=\"ac-num\">{$record}</span>";
	if ($show_record_time)
		echo "<br><span class=\"ac-tiny\">Time: {$record_time}</span>";
	echo "</td>";
	// -------------------

	// --- Record submission info and replay ---
	$submit_time = "{$rec_data['submit_time']}<br /><span class=\"moreinfo\">{$rec_data['submit_time_hour']}</span>";
	$submit_time = display_recency($submit_time, $rec_data['recency']);
	$dl_icon = get_download_link($rec_data['replay_id'], $rec_data['manual_id']);

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
	echo "<td class=\"{$cssbottom} brightsep\" width=\"24px\">{$dl_icon}</td>";
	// -----------------------------------------

	echo "</tr>\r\n";
}
?>
</table>
</div>