<?php
// !!! NOTE !!!
// Remove this when switching away from the now-deprecated mysql class.
// (AKA, when the vBulletin dependency is dropped.)
$error_level = error_reporting();
error_reporting($error_level & ~E_DEPRECATED);
// !!! NOTE !!!

class mysql {
	// well isn't this fun
	// a static 'class' now, yay.
	static $queries   = 0;
	static $rowsf     = 0;
	static $rowst     = 0;
	static $time      = 0;

	// Query debugging functions for admins
	static $debug_on   = 0;
	static $debug_list = array();

	// **
	// CONNECT
	static function connect($host,$user,$pass) {
		$start=microtime(true);
		$r = mysql_connect($host,$user,$pass);
		$t = microtime(true)-$start;

		if ($r === FALSE) {
			trigger_error("MySQL connect error: ". mysql_error(), E_USER_WARNING);
		}
		else if (self::$debug_on) {
			$b = self::getbacktrace();
			self::$debug_list[] = array($b['pfunc'], "$b[file]:$b[line]", "<i>Connection established to mySQL server ($host, $user, using password: ".(($pass!=="") ? "YES" : "NO").")</i>", sprintf("%01.6fs",$t));
		}

		self::$time += $t;
		return $r;
	}

	static function selectdb($dbname)	{
		$start=microtime(true);
		$r = mysql_select_db($dbname);
		if ($r === FALSE) {
			trigger_error("MySQL database select error: ". mysql_error(), E_USER_WARNING);
		}
		self::$time += microtime(true)-$start;
		return $r;
	}

	// **
	// BASIC QUERY
	static function query($query) {
		$start=microtime(true);
		if($res = mysql_query($query)) {
			self::$queries++;
			if (!is_bool($res))
				self::$rowst += @mysql_num_rows($res);
		}
		else {
			// the huge SQL warning text sucks
			$err = str_replace("You have an error in your SQL syntax; check the manual that corresponds to your MySQL server version for the right syntax to use", "SQL syntax error", mysql_error());
			trigger_error("MySQL error: $err", E_USER_WARNING);
		}

		$t = microtime(true)-$start;
		self::$time += $t;

		if (self::$debug_on) {
			$b = self::getbacktrace();
			$tx = (($res) ? $query : "<span style=\"color:#FF0000;border-bottom:1px dotted red;\" title=\"".mysql_error()."\">$query</span>");
			self::$debug_list[] = array($b['pfunc'], "$b[file]:$b[line]", $tx, sprintf("%01.6fs",$t));
		}
		return $res;
	}

	// **
	// SELECT
	static function fetch($result, $flag = 0){
		$start=microtime(true);
		if($result && $res=mysql_fetch_array($result, $flag))
			self::$rowsf++;

		self::$time += microtime(true)-$start;
		return $res;
	}

	static function result($result,$row=0,$col=0){
		$start=microtime(true);

		if($result) { 
			if (mysql_num_rows($result) < $row+1)
				$res = NULL;
			elseif ($res=@mysql_result($result,$row,$col))
				self::$rowsf++;
		}

		self::$time += microtime(true)-$start;
		return $res;
	}

	static function fetchq($query, $flag = 0){
		$res=self::query($query);
		$res=self::fetch($res, $flag);
		return $res;
	}

	static function resultq($query,$row=0,$col=0){
		$res=self::query($query);
		$res=self::result($res,$row,$col);
		return $res;
	}

	static function getmultiresults($query, $key, $wanted) {
		$q = self::query($query);
		$ret = array();
		$tmp = array();

		while ($res = @self::fetch($q, MYSQL_ASSOC))
			$tmp[$res[$key]][] = $res[$wanted];
		foreach ($tmp as $keys => $values)
			$ret[$keys] = implode(",", $values);
		return $ret;
	}

	static function getresultsbykey($query, $key, $wanted, $overwrite = false) {
		$q = self::query($query);
		$ret = array();
		while ($res = @self::fetch($q, MYSQL_ASSOC))
			if ($overwrite || !array_key_exists($res[$key],$ret)) $ret[$res[$key]] = $res[$wanted];
		return $ret;
	}

	static function getresults($query, $wanted) {
		$q = self::query($query);
		$ret = array();
		while ($res = @self::fetch($q, MYSQL_ASSOC))
			$ret[] = $res[$wanted];
		return $ret;
	}

	static function getarraybykey($query, $key, $overwrite = true) {
		$q = self::query($query);
		$ret = array();
		while ($res = @self::fetch($q, MYSQL_ASSOC))
			if ($overwrite || !array_key_exists($res[$key],$ret)) $ret[$res[$key]] = $res;
		return $ret;
	}

	static function getarray($query) {
		$q = self::query($query);
		$ret = array();
		while ($res = @self::fetch($q, MYSQL_ASSOC))
			$ret[] = $res;
		return $ret;
	}

	// **
	// INSERT
	static function insertarray($table, $array) {
		foreach ($array as $k=>$v)
			$escaped['`'.mysql_real_escape_string($k).'`'] = '\''.mysql_real_escape_string($v).'\'';
		unset($array);
		$table = '`'.mysql_real_escape_string($table).'`';

		$keys   = implode(',',array_keys($escaped));
		$values = implode(',',$escaped);
		$ret = self::query("INSERT INTO {$table} ({$keys}) VALUES ({$values})");
		return $ret;
	}

	// **
	// UPDATE
	static function updatefromarray($table, $keys, $array) {
		foreach ($array as $k=>$v)
			$escaped['`'.mysql_real_escape_string($k).'`'] = '\''.mysql_real_escape_string($v).'\'';
		unset($array);
		foreach ($keys as $k=>$v)
			$escapedkeys['`'.mysql_real_escape_string($k).'`'] = '\''.mysql_real_escape_string($v).'\'';
		unset($keys);
		$table = '`'.mysql_real_escape_string($table).'`';

		$setstr = array();
		foreach ($escaped as $k=>$v)
			$setstr[] = "{$k}={$v}";
		$setstr = implode(',',$setstr);

		$wherestr = array();
		foreach ($escapedkeys as $k=>$v)
			$wherestr[] = "{$k}={$v}";
		$wherestr = implode(' AND ',$wherestr);

		$ret = self::query("UPDATE {$table} SET {$setstr} WHERE {$wherestr}");
		return $ret;
	}

	private function __construct() {}

	// Debugging shit for admins
	public static function debugprinter() {
		global $tccellh, $tccell1, $tccell2, $tblstart, $smallfont;
		if (!self::$debug_on) return;
		print "<br>$tblstart<tr>$tccellh colspan=5><b>SQL Debug</b></td><tr>
			$tccellh width=20>&nbsp</td>
			$tccellh width=300>Function</td>
			$tccellh width=*>Query</td>
			$tccellh width=90>Time</td></tr>";
		foreach(self::$debug_list as $i => $d) {
			$altcell = "tccell" . (($i & 1)+1);
			$cell = $$altcell;
			print "<tr>
				$cell>$i</td>
				$cell>$d[0]$smallfont<br>$d[1]</font></td>
				$cell>$d[2]</td>
				$cell>$d[3]</td></tr>";
		}
		print "$tblend";
	}

	private static function getbacktrace() {
		$backtrace = debug_backtrace();
		for ($i = 1; isset($backtrace[$i]); ++$i) {
			if (substr($backtrace[$i]['file'], -9) !== "mysql.php") {
				if (!($backtrace[$i]['pfunc'] = $backtrace[$i+1]['function']))
					$backtrace[$i]['pfunc'] = "<i>(main)</i>";
				$backtrace[$i]['file'] = str_replace($_SERVER['DOCUMENT_ROOT'], "", $backtrace[$i]['file']);
				return $backtrace[$i];
			}
		}
		return $backtrace[$i-1];
	}
}