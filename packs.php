<?php require_once("include/common.php");
open_database_readonly();

echo "I spent all my time working on the map list code, so unfortunately this is but a simple lazy list. Sorry.<br>";
$packs = mysql::getresultsbykey("SELECT pack_id, name FROM packs WHERE hidden=0 ORDER BY pack_id ASC", pack_id, name);
foreach ($packs as $id=>$name){
	echo "<br><a href=\"/maps.php?pack={$id}\">{$name}</a>";
}

