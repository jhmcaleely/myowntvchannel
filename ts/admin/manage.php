<?php

/*
 * manage.php
 *
 * Copyright John McAleely 2008
 */

require('motc-internal.php');
$cf_motc = cf_globals();
db_open();
$cf_is_authorised = user_connection_is_authorised($cf_user, $cf_is_expired);

header("Content-Type: text/html; charset=UTF-8");

if (!$cf_is_authorised) {
	ui_writeMessageDoc('manage', 401, "Unauthorised");
	exit(0);	
}

$cf_channel = cms_channel_config($_GET['channel']);
if ($cf_channel === FALSE) {
	ui_writeMessageDoc('manage', 404, "Not Found");
	exit(0);
}

$current_page_mode = 'display';

cms_sync_dir_to_channel($cf_channel);

if (isset($_POST['edit-items'])) {
	$current_page_mode = 'edit-items';
}
else if (isset($_POST['order-items'])) {
	$current_page_mode = 'order-items';
}
else if (isset($_POST['edit-channel'])) {
	$current_page_mode = 'edit-channel';
}
else if ($_POST['page'] == 'order-items' && !isset($_POST['display'])) {
	ui_update_channel_order($cf_channel, $message);
	$current_page_mode = 'order-items';
}
else if (isset($_POST['save-items'])) {
	if (($result = ui_update_channel_items($cf_channel)) !== TRUE) {
		$message = $result;
		$current_page_mode = 'edit-items';		
	}
}
else if (isset($_POST['save-channel'])) {	
	if (($result = ui_updateChannel($cf_channel)) !== TRUE) {
		$message = $result;
		$current_page_mode = 'edit-channel';		
	}
}


cms_write_channel_rss($cf_channel);
if ($cf_channel->publish_html == 1) {
	cms_write_channel_html($cf_channel);
}

cms_write_channel_config($cf_channel);

$op = new XMLWriter();
$op->openURI("php://output");
$op->setIndent(TRUE);

ui_startDocument($op, htmlspecialchars($_GET['channel'])." - Edit Channel", FALSE);
if ($current_page_mode == 'display') {
	ui_writeTorrentUpdateScript($op, $cf_channel);
	$op->endElement();
	$op->startElement("body");
	$op->writeAttribute('onload', 'helper_onLoad()');
}
else {
	$op->endElement();
	$op->startElement("body");	
}

ui_writeHeader($op);
ui_writeWizardTitle($op, 'Edit', $_GET['channel']);

$op->writeElement('hr');
$op->startElement('span');
$op->writeAttribute('id', 'message');
if ($message) {
	$op->text($message);
}
else {
	$op->text('Update the details of the channel');
}
$op->endElement();
$op->writeElement('hr');


if ($current_page_mode == 'edit-items') {
	ui_writeEditFileDescription($op, $cf_channel);
}
else if ($current_page_mode == 'edit-channel') {
	ui_writeEditChannel($op, $cf_channel);
}
else if ($current_page_mode == 'order-items') {
	ui_writeEditFileOrder($op, $cf_channel);	
}
else {
	ui_writeFiles($op, $cf_channel);		
	ui_writeChannel($op, $cf_channel);
}

ui_writeFooter($op, 'manage');
ui_endDocument($op);

?>