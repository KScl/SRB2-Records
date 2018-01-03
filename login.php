<?php require_once("include/common.php");

// basic logout shit
if (isset($_GET['logout'])) {
	setcookie(COOKIE_NAME, '', 1);
	do_redirect('/');
}

// If the user is already logged in, just redirect them home
if ($_logged_in_id)
	do_redirect('/');

// Other query strings -- use as redirect
$redirect = ($_SERVER['QUERY_STRING']) ? htmlentities($_SERVER['QUERY_STRING']) : NULL;
if (!$redirect && $_POST['redirect_after'])
	$redirect = $_POST['redirect_after'];

// --- Login attempts ---
function handle_login_attempt($user, $pass) {
	open_database_srb2mb();
	$user = mysql_real_escape_string(mb_convert_encoding(trim($user), 'HTML-ENTITIES', 'UTF-8'));
	// $pass is never actually passed to the db, so we don't need to escape it or anything.

	// Mask any login times a little
	usleep(50000 + mt_rand(0, 50000));

	$srb2db_user = mysql::fetchq("SELECT userid,usergroupid,membergroupids,username,password,salt,avatarrevision,adminoptions FROM user WHERE username = '{$user}'");

	if (!$srb2db_user) // no user found with that username
		return 'The username and password you entered are not valid.';

	$passhash_us = md5(md5($pass).$srb2db_user['salt']);
	$passhash_db = $srb2db_user['password'];

	if ($passhash_us !== $passhash_db)
		return 'The username and password you entered are not valid.';

	$banned_groups = array(
		3,  // (email confirmation)
		42, // (permaban)
		43, // (strike 1)
		44, // (strike 2)
		45, // (strike 3)
	);
	if (in_array($srb2db_user['usergroupid'], $banned_groups))
		return "Sorry, but users banned from the SRB2MB aren't allowed to use the Records site.";

	// user good
	return $srb2db_user;
}

function handle_cookies($srb2db_user) {
	open_database_readwrite();

	$local_user = mysql::resultq("SELECT user_id FROM users WHERE mb_user = '{$srb2db_user['userid']}'");
	if (!$local_user) { // Not set yet
		// Insert mb user -- we'll update everything else in just one second
		mysql::insertarray('users', array('mb_user'=>$srb2db_user['userid']));
		$local_user = mysql_insert_id();
	}

	// No, we really don't care about storing a lot of data about their external SRB2MB account, do we?
	// Add random data so that if another user logs in it will kick everyone else out
	$shahash = sha1($srb2db_user['username'].mt_rand(0, 32767).$srb2db_user['password'].mt_rand(0, 32767).$srb2db_user['salt']);
	$cookie = sprintf("%010d|%s", $local_user, $shahash);
	setcookie(COOKIE_NAME, $cookie, time()+(86400*3)); // three days expiry

	// Nicety: Avatar URL
	if ($srb2db_user['avatarrevision'] > 0)
		$avatar = "http://mb.srb2.org/customavatars/avatar{$srb2db_user['userid']}_{$srb2db_user['avatarrevision']}.gif";
	else $avatar = '';

	// Set admin usergroup stats
	$mb_groups = array_merge(array($srb2db_user['usergroupid']), explode(',',$srb2db_user['membergroupids']));
	$admin_usergroup_exists = in_array(56, $mb_groups);

	mysql::updatefromarray('users', array('user_id'=>$local_user), array(
		'name'        => $srb2db_user['username'],
		'avatar_url'  => $avatar,
		'admin'       => $admin_usergroup_exists,
		'verify_hash' => $shahash
	));

	// Don't set this above because it can be NULL
}
// ----------------------

function login_do_form($redirect = NULL, $nse = false) {
	$redirtx = '';
	if ($redirect) {
		if (!$nse)
			$redirtx = error_box("You must log in to do that!");
		$redirtx .= "<input type=\"HIDDEN\" name=\"redirect_after\" value=\"{$redirect}\"/>";
		$redirtx .= "<br />";
	}
?>
<div align="center">
	<form action="login.php" method="POST">
		<?php echo $redirtx; ?>

		<table class="class-table" width="40%">
		<tr><th class="nosep" colspan="2">Log in with your SRB2MB account</th></tr>
		<tr><td class="endline" colspan="2" style="line-height:18px;">
			To use the Records subsite, you need to have a SRB2 Message Board account. If you don't have one, you can register one by visiting it <a href="http://mb.srb2.org">here</a>.
		</td></tr>
		<tr>
			<th>User name</th>
			<td class="endline"><input type="text"     name="mb___user" maxlength="128" style="width:98%" /></td>
		</tr>
		<tr>
			<th>Password</th>
			<td class="endline"><input type="password" name="mb___pass" maxlength="128" style="width:98%;" /></td>
		</tr>
		<tr>
			<th colspan="2"><input type="submit" value="Log in"/></th>
		</tr>
		</table>
	</form>
</div>
<?php
}

// *** Execution ***
$noshowerror = false;

if ($_POST['mb___user'] && $_POST['mb___pass']) {
	$user = handle_login_attempt($_POST['mb___user'], $_POST['mb___pass']);
	if (is_array($user)) {
		handle_cookies($user); // user login successful
		// send back home
		do_redirect(($_POST['redirect_after']) ? $_POST['redirect_after'] : '/');
	}
	else {
		echo error_box($user); // error occured -- display
		$noshowerror = true; // Don't show the redirect error!
}	}

login_do_form($redirect, $noshowerror);
