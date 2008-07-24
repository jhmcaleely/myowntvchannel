<?php

/*
 * index.php
 *
 * Copyright John McAleely 2008
 */

require('motc-internal.php');
$cf_motc = cf_globals();
db_open();
$cf_is_authorised = user_connection_is_authorised($cf_user, $cf_is_expired);

$form_dir = $cf_motc['base_dir'];
if (isset($_POST['new_channel'])) {
	$form_dir = $_POST['new_channel'];
}

header("Content-Type: text/html; charset=UTF-8");


if (   $cf_is_authorised 
	&& isset($_POST['create_new']) 
	&& isset($_POST['new_channel'])
	&& strlen($_POST['new_title']) > 0
	) {
		
	make_channel($form_dir, $_POST['new_title'], $message);
}


if ($cf_is_authorised) {
	$current_channels = cms_channel_dirs();
	foreach ($current_channels as $cx) {
		// $_POST doesn't like variables ending in ., so we use realpath
		$testpath = rawurlencode(realpath($cx['localdir']));

		if (isset($_POST['create_'.$testpath])) {
			make_channel($cx['localdir'], $_POST['title_'.$testpath], $message);
		}
		else if (isset($cx['localurl']) && isset($_POST['delete_'.rawurlencode($cx['localurl'])])) {
			cms_remove_channel($cx['localurl']);
			$message = 'Channel deleted';
		}
	}

	$current_channels = cms_channel_dirs();
}


$op = new XMLWriter();
$op->openURI("php://output");
$op->setIndent(TRUE);

ui_startDocument($op, 'Admin: My Own TV Channel');
ui_writeHeader($op, 'home');
ui_writeBrandedTitle($op, 'My Own TV Channel Admin');
$op->writeElement('hr');

if (isset($message)) {
	$op->text($message);
	$op->text(' | ');
}
ui_writeLink($op, cf_url_for_script('setup'), "Setup");
$op->writeElement('hr');

$op->writeElement('h2', 'Channels');

if ($cf_is_authorised) {
	ui_writeChannelControl($op, $current_channels, $form_dir);	
}
else {
	ui_writeChannelStats($op);
}

$op->writeElement('h2', 'Status');
$op->writeElement('h3', 'Sessions');
$op->startElement('table');
$op->startElement('tr');
$op->writeElement('td', 'ip:port');
$op->writeElement('td', 'Peer ID');
$op->writeElement('td', 'File');
$op->writeElement('td', 'Channel');
$op->writeElement('td', 'Completed');
$op->writeElement('td', 'Messages');
$op->writeElement('td', 'Last Seen');
$op->endElement();

$clients = torrent_stat_sessions();
while ($client = get_next_record_array($clients)) {
	$name = cms_infohash_names($client['infohash']);
	
	$op->startElement('tr');
	$op->writeElement('td', int_to_dq($client['ip']).':'.$client['port']);
	$op->writeElement('td', $client['peerid']);
	$op->writeElement('td', $name['localfile']);
	$op->writeElement('td', $name['localurl']);
	if ($client['complete']) {
		$op->writeElement('td', 'yes');
	}
	else {
		$op->writeElement('td', 'no');
	}
	$op->writeElement('td', $client['messagecount']);
	$op->writeElement('td', $client['lastseen']);
	$op->endElement();
}
$op->endElement();

$op->writeElement('h3', 'Files');

$op->startElement('table');
$op->startElement('tr');
$op->writeElement('td', 'Name');
$op->writeElement('td', 'Torrent ID (\'Infohash\')');
$op->writeElement('td', 'Channel');
$op->writeElement('td', 'Downloads');
$op->writeElement('td', 'Downloaders');
$op->writeElement('td', 'Shared Copies');
$op->endElement();
$clients = torrent_stat_torrents();
while ($client = get_next_record_array($clients)) {

	$leechers = torrent_stat_num_downloaders($client['infohash']);
	$seeders = torrent_stat_num_seeders($client['infohash']);
	$name = cms_infohash_names($client['infohash']);

	$op->startElement('tr');
	$op->writeElement('td', $name['localfile']);
	$op->startElement('td');
	$op->startElement('pre');
	$op->text(binary_as_hex($client['infohash']));
	$op->endElement();
	$op->endElement();
	$op->writeElement('td', $name['localurl']);
	$op->writeElement('td', $client['completions']);
	$op->writeElement('td', $leechers);
	$op->writeElement('td', $seeders);
	$op->endElement();
}
$op->endElement();

ui_writeFooter($op, 'home');
ui_endDocument($op);


function make_channel($dir, $title, &$message) {
	global $cf_motc;
	
	$url = cms_make_channel($dir, $title);
	if ($url !== FALSE) {

		$params = array('channel' => $url);

		$nexturl = $cf_motc['host_url'].cf_url_for_script('manage');
		$nexturl .= '?';
		$nexturl .= http_build_query($params);

		header("Location: $nexturl", NULL, 303);
	}
	else {
		$message = 'Channel cannot be created';
	}
}

?>