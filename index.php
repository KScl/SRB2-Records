<?php require_once("include/common.php");
open_database_readonly();

?>
<div style="width:100%;">
	<br>
	<div style="text-align:center;font-size:18pt;">
		Welcome to the official SRB2 Records tracker!
	</div>
	<br>
	<div style="width:45%;float:left;padding:4px;">
		<b>Records.SRB2.org</b> is a centralized tracker where anyone can submit Record Attack replays and show off their skills.
		Even if you're not the best, you can still submit your replays and see how well you stack up compared to other members of the community!
		<br><br><br><br>
		To do: <br>
		<img src="/images/report-good.png" /> Better support for older versions<br>
		<img src="/images/download-none.png" /> Report system<br>
		<img src="/images/download-none.png" /> Finish the index page<br>
		<img src="/images/report-good.png" /> Finish the map list<br>
	</div>
	<div style="width:52%;float:right;text-align:center;padding:4px;">

<?php

	$latest_records = mysql::getarray("SELECT ri.replay_id,ri.score,ri.time,ri.rings, "
		."u.user_id uid, u.name user, "
		."m.map_id,m.zone_name,m.is_zone,m.act, "
		."ch.name char_name,ch.face_icon char_icon, "
		."DATE_FORMAT(ri.submit_time, '%b %e %Y') submit_time, "
		."TIMESTAMPDIFF(MINUTE, ri.submit_time, NOW()) recency "
		."FROM replay_info ri "
		."LEFT JOIN users u ON (ri.user_id=u.user_id) "
		."LEFT JOIN characters ch ON (ri.character_id=ch.character_id) "
		."LEFT JOIN maps m ON (ri.map_id=m.map_id) "
		."ORDER BY ri.submit_time DESC LIMIT 10"
	);
?>
	Latest 10 replay submissions:
	<table class="class-table" width="94%">
	<tr>
		<th colspan="2" rowspan="2"  >Map</th>
		<th             rowspan="2"  >Submitter</th>
		<th colspan="5" class="nosep">Replay Data</th>
	</tr>
	<tr>
		<!-- element: Map        -->
		<!-- element: CH         -->
		<!-- element: Submitter  -->
		<th>Submitted</th>
		<th>Score</th>
		<th>Time</th>
		<th>Ring</th>
		<th class="nosep">DL</th>
	</tr>
<?php

$cssbottom = "classline";
$i = 0;

	foreach ($latest_records as $rec_data) {
		if (++$i >= 10) $cssbottom = "endline";

		echo '<tr>';

		$zone = $rec_data['zone_name'];
		//if ($rec_data['is_zone']) $zone .= ' Zone';
		if ($rec_data['act'])     $zone .= " {$rec_data['act']}";
		echo "<th width=\"150px\"><a href=\"maps.php?id={$rec_data['map_id']}\">{$zone}</a></th>";

		// --- Link to class ---
		$character_icon = "<img src=\"/images/{$rec_data['char_icon']}\" title=\"{$rec_data['char_name']}\" />";
		echo "<td class=\"{$cssbottom} brightsep\" width=\"24px\">{$character_icon}</td>";

	$record_user  = $rec_data['user'];
	if ($rec_data['uid'])
		$record_user  = "<a href=\"user.php?user={$rec_data['uid']}\">{$record_user}</a>";

	echo "<td class=\"{$cssbottom} rightsep\" width=\"*\">{$record_user}</td>";

	// --- Record submission info and replay ---
	$submit_time = display_recency($rec_data['submit_time'], $rec_data['recency']);
	$dl_icon = get_download_link($rec_data['replay_id'], $rec_data['manual_id']);

	echo "<td class=\"{$cssbottom} rightsep\" width=\"18%\">{$submit_time}</td>";

	// --- Record data ---
	$record = record_to_display('score', $rec_data['score']);
	echo "<td class=\"{$cssbottom} rightsep table-number\" width=\"60px\">";
	echo "<span class=\"ac-num\">{$record}</span>";
	echo "</td>";

	$record = record_to_display('time', -$rec_data['time']);
	echo "<td class=\"{$cssbottom} rightsep table-number\" width=\"68px\">";
	echo "<span class=\"ac-num\">{$record}</span>";
	echo "</td>";

	$record = record_to_display('rings', $rec_data['rings']);
	echo "<td class=\"{$cssbottom} rightsep table-number\" width=\"36px\">";
	echo "<span class=\"ac-num\">{$record}</span>";
	echo "</td>";
	// -------------------

	echo "<td class=\"{$cssbottom} nosep\" width=\"24px\">{$dl_icon}</td>";
	// -----------------------------------------

		echo '</tr>'."\r\n";
	}
?>
	</table>
	</div>
	<div style="clear:both;"></div>
</div>