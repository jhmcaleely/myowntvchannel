<?php
/*
 * Copyright John McAleely, 2008
 */


require('motc-config.php');
require('motc-ui.php');
require('motc-ui-file.php');
require('motc-ui-channel.php');
require('motc-ui-torrent.php');
require('motc-torrent.php');
require('motc-cms.php');


define('MOTC_CHANNEL_CONFIG_NAME', '.channel_config.xml');
define('MOTC_CHANNEL_RSS_NAME', 'subscription.rss');
define('MOTC_DEFAULT_HTML', 'index.html');
define('MOTC_CHANNEL_HTML_NAME', MOTC_DEFAULT_HTML);
define('MOTC_CREATE_HTML', TRUE);
define('MOTC_INCLUDE_ADMIN', TRUE);
define('MOTC_CHANNEL_DEF_DESC', 'My Own TV Channel');
define('MOTC_CHANNEL_DEF_TITLE', 'My Channel');
define('MOTC_ICON_NAME', 'MOTC-Icon-160x90.png');
define('MOTC_ANNOUNCE_INTERVAL', 60*2);
define('MOTC_STALE_INTERVAL', MOTC_ANNOUNCE_INTERVAL*2);
define('MOTC_USERNAME_LEN', 50);
define('MOTC_SID_LEN', 16);
define('MOTC_COOKIE_NAME', 'motc-auth');
define('MOTC_SESSION_STALE_INTERVAL', 60*60*24);	// 1 day
define('MOTC_INFOHASH_LEN', 20);
define('MOTC_PEERID_LEN', 20);
define('MOTC_TORRENT_PIECE_LEN', 262144);
define('MOTC_TORRENT_CREATOR', 'MOTC-Torrent');
define('MOTC_SHA1_HASH_LEN', 20);
define('MOTC_ICON_EXTENSIONS', 'jpeg|jpg|png|gif');
define('MOTC_CHANNEL_ICON', 'MOTC-Item-Icon-160x90.png');
define('MOTC_ITEM_ICON', 'MOTC-Channel-Icon-160x90.png');


function cf_torrent_session_table() { return MOTC_DB_TABLE_PREFIX.'sessions'; }
function cf_torrent_infohash_table() { return MOTC_DB_TABLE_PREFIX.'torrents'; }
function cf_cms_channels_table() { return MOTC_DB_TABLE_PREFIX.'channels'; }
function cf_cms_infohash_name_table() { return MOTC_DB_TABLE_PREFIX.'torrentnames'; }
function cf_user_id_table() { return MOTC_DB_TABLE_PREFIX.'users'; }
function cf_user_sessions_table() { return MOTC_DB_TABLE_PREFIX.'usersessions'; }

function cf_user_auth_cookie_name() { return MOTC_COOKIE_NAME; }


$cf_schema = array( cf_torrent_session_table() => 'infohash BINARY('.MOTC_INFOHASH_LEN.'), peerid BINARY('.MOTC_PEERID_LEN.'), ip INT, port SMALLINT UNSIGNED, complete BOOL, messagecount TINYINT UNSIGNED, lastseen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
				  , cf_torrent_infohash_table() => 'infohash BINARY('.MOTC_INFOHASH_LEN.'), completions INT'
				  , cf_cms_channels_table() => 'localurl VARCHAR(50), localdir VARCHAR(50)'
				  , cf_cms_infohash_name_table() => 'localurl VARCHAR(50), localfile VARCHAR(50), infohash BINARY('.MOTC_INFOHASH_LEN.')'
				  , cf_user_id_table() => 'username VARCHAR('.MOTC_USERNAME_LEN.') NOT NULL UNIQUE, passhash BINARY(16) NOT NULL'
				  , cf_user_sessions_table() => 'id BINARY('.MOTC_SID_LEN.') NOT NULL UNIQUE, username VARCHAR('.MOTC_USERNAME_LEN.') NOT NULL, lastseen TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
				  );


function cf_globals() {
	
	$ts_files = array('announce.php', 'scrape.php', 'torrent.php');

	$scriptpathinfo = pathinfo($_SERVER['SCRIPT_FILENAME']);

	if (array_search($scriptpathinfo['basename'], $ts_files) !== FALSE) {
		$path_suffix = '/ts';
	}
	else {	// must be an admin file
		$path_suffix = '/ts/admin';
	}

	$result['base_dir'] = strip_path_suffix($scriptpathinfo['dirname'], $path_suffix);
	
	$urlinfo = parse_url($_SERVER['REQUEST_URI']);
	
	// if this is a directory-only url, pathinfo doesn't quite work right
	if (substr($urlinfo['path'], -1, 1) == '/') {
		$urlpathinfo['dirname'] = substr($urlinfo['path'], 0, -1);
		$urlpathinfo['extension'] = '';
	}
	else {
		$urlpathinfo = pathinfo($urlinfo['path']);		
	}
	
	$result['base_url'] = strip_path_suffix($urlpathinfo['dirname'], $path_suffix);
	$result['base_url'] .= '/';
	
	$result['script_url'] = $urlinfo['path'];
	if (isset($_GET['channel'])) {
		$result['script_url'] .= '?channel='.urlencode($_GET['channel']);
	}

	$result['host_url'] = cf_host_url();

	return $result;
}


function cf_host_url() {
	$result = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
	$result .= $_SERVER['SERVER_NAME'];
	$default_port = isset($_SERVER['HTTPS']) ? 443 : 80;
	$result .= $_SERVER['SERVER_PORT'] != $default_port ? ':'.$_SERVER['SERVER_PORT'] : '';
	
	return $result;
}


function cf_url_for_script($script) {
	
	global $cf_motc;

	if ($script == 'publish_helper.js') {
		return $cf_motc['base_url'].'ts/admin/'.$script;
	}

	if ($script == 'motc-icon') {
		return $cf_motc['base_url'].'ts/admin/'.MOTC_ICON_NAME;
	}
	
	if ($script == 'motc-channel') {
		return $cf_motc['base_url'].'ts/admin/'.MOTC_CHANNEL_ICON;
	}
	
	if ($script == 'motc-item') {
		return $cf_motc['base_url'].'ts/admin/'.MOTC_ITEM_ICON;
	}

	// now deal with all the php scripts.

	// first the admin/home page
	if ($script == 'home') {
		$result = $cf_motc['base_url'].'ts/admin/';
		$result .= !MOTC_HIDE_INDEX ? 'index.php': '';
		return $result;
	}

	// now the rest
	if (   $script == 'announce'
	    || $script == 'torrent'
	    || $script == 'scrape') {
		$prefix = 'ts/';
	}
	else if (   $script == 'setup'
        	 || $script == 'login'
	         || $script == 'manage') {
		$prefix = 'ts/admin/';
	}
	
	$result = $cf_motc['base_url'].$prefix.$script;
	$result .= !MOTC_HIDE_INTERNAL_EXT ? '.php' : '';
	return $result;
}


function strip_path_suffix($dirpath, $suffix) {

	return substr($dirpath, 0, 0-strlen($suffix));
	
}


function db_open() {
	$link = mysql_connect(MOTC_DB_SERVER, MOTC_DB_USER, MOTC_DB_PASSWORD);
	if ($link !== FALSE) {
		return mysql_select_db(MOTC_DB_NAME);
	}
	return $link;
}


function db_reset_tables() {

	global $cf_schema;

	foreach ($cf_schema as $table => $config) {
		mysql_query("DROP TABLE $table");
	}

	foreach ($cf_schema as $table => $config) {
		$result = mysql_query("CREATE TABLE $table ($config)");
		if ($result === FALSE) { return FALSE; }
	}

	return TRUE;
}


function db_tables_present() {

	global $cf_schema;

	$db_results = mysql_query('SHOW TABLES LIKE \''.MOTC_DB_TABLE_PREFIX.'%\'');
	while ($table = mysql_fetch_array($db_results, MYSQL_NUM)) {
		$db_tables[] = $table[0];
	}
	if (count($db_tables) > 0) {
		$diff = array_diff(array_keys($cf_schema), $db_tables);
		return count($diff) == 0;
	}
	else {
		return FALSE;
	}
}


function binary_as_hex($bin) {
	
	for($i = 0; $i < strlen($bin); $i++) {
		$result .= sprintf("%02x", ord($bin[$i]));
	}
	
	return $result;
}


function hex_as_binary($hex) {

	for($i = 0; $i < strlen($hex); $i += 2) {
		$result .= chr(hexdec(substr($hex, $i, 2)));
	}
	
	return $result;
}


function mysql_motc_rec_exists($col, $table, $condition) {
	$querycol = mysql_real_escape_string($col);
	$querytable = mysql_real_escape_string($table);
	
	$result = mysql_query("SELECT $querycol FROM $querytable WHERE ($condition)");
	return (bool) mysql_num_rows($result) > 0;	
}


function int_to_dq($uint) {
	
	for ($i = 0; $i < 4; $i++) {
		$dq .= ($uint >> (3-$i)*8) & 0xff;
		$dq .= $i < 3 ? '.' : '';
	}
	
	return $dq;
}


// store a dotted-quad IP adddress in an int, MSB first.
function dq_to_int($dq_ip) {

	$quads = explode('.', $dq_ip);

	for ($i = 0; $i < 4; $i++) {
		$uint += ($quads[3-$i] & 0xff) << ($i * 8);
	}
	
	return $uint;
}


function get_next_record_array($result) {
	return mysql_fetch_array($result, MYSQL_ASSOC);
}


function user_hash_pw($cleartextpw) {
	return md5($username.'MOTC'.$cleartextpw, TRUE);
}


function user_register($username, $cleartextpw) {
	$queryuser = mysql_real_escape_string($username);
	$queryhash = mysql_real_escape_string(user_hash_pw($cleartextpw));
	
	mysql_query('INSERT '.cf_user_id_table()." (username, passhash) VALUES (\"$queryuser\", \"$queryhash\")");
}


function user_connection_is_authorised(&$user, &$is_expired) {
	
	$is_expired = FALSE;
	
	if (!isset($_COOKIE[cf_user_auth_cookie_name()])) {
		return FALSE;
	}

	$token = hex_as_binary($_COOKIE[cf_user_auth_cookie_name()]);
	$querytoken = mysql_real_escape_string($token);

	$stale_where = 'lastseen < SUBTIME(NOW(), SEC_TO_TIME('.MOTC_SESSION_STALE_INTERVAL.'))';
	
	$expired_users = mysql_query('SELECT username FROM '.cf_user_sessions_table()." WHERE $stale_where AND id=\"$querytoken\"");
	if (mysql_num_rows($expired_users) == 1) {
		$resarray = get_next_record_array($expired_users);
		$user = $resarray['username'];
		$is_expired = TRUE;
	}

	mysql_query('DELETE FROM '.cf_user_sessions_table()." WHERE $stale_where");
	
	$result = mysql_query('UPDATE '.cf_user_sessions_table()." SET lastseen=NOW() WHERE id=\"$querytoken\"");
	if ($result === FALSE) {
		return FALSE;
	}
	
	$result = mysql_query('SELECT username FROM '.cf_user_sessions_table()." WHERE id=\"$querytoken\"");
	if (mysql_num_rows($result) != 1) {
		return FALSE;
	}

	$resarray = get_next_record_array($result);
	$user = $resarray['username'];
	return TRUE;
}


function user_end_session() {
	
	$token = hex_as_binary($_COOKIE[cf_user_auth_cookie_name()]);
	$querytoken = mysql_real_escape_string($token);
	
	mysql_query('DELETE FROM '.cf_user_sessions_table()." WHERE id=\"$querytoken\"");
	setcookie(cf_user_auth_cookie_name(), '', 0);
}


function user_start_session($username, $cleartextpw) {
	$queryuser = mysql_real_escape_string($username);
	$queryhash = mysql_real_escape_string(user_hash_pw($cleartextpw));
	
	if (!mysql_motc_rec_exists('username', cf_user_id_table(), "username=\"$queryuser\" AND passhash=\"$queryhash\"")) {
		return FALSE;
	}
		
	for ($x = 0; $x < MOTC_SID_LEN; $x++) {
		$token .= chr(mt_rand(-128, 127));
	}
	
	$querytoken = mysql_real_escape_string($token);
	$result = mysql_query('INSERT '.cf_user_sessions_table()." (id, username) VALUES (\"$querytoken\", \"$queryuser\")");
	if ($result === FALSE) {
		return FALSE;
	}

	$cookietoken = binary_as_hex($token);
	
	// if browsers break the spec and ignore the path part of a cookie, this will 
	// need to be prefixed with an install-unique string. md5(db-server-db-name)?
	setcookie(cf_user_auth_cookie_name(), $cookietoken, 0);
	return TRUE;
}


function user_num_users() {
	$result = mysql_query('SELECT COUNT(username) FROM '.cf_user_id_table());
	if ($result === FALSE) {
		return 0;
	}
	$users = mysql_fetch_array($result, MYSQL_NUM);
	return $users[0];
}
?>