<?php

/*
 * home.php
 *
 * Copyright John McAleely 2008
 */

require('motc-internal.php');
$cf_motc = cf_globals();
db_open();
$cf_is_authorised = user_connection_is_authorised($cf_user, $cf_is_expired);
$tables_present = db_tables_present();

if (!$tables_present) {
	db_reset_tables(); 
	$message = 'Database installed';
	$tables_present = db_tables_present();
}


if ($cf_is_authorised && $_POST['action'] == 'reset') {

	$registered_channels = cms_channels();
	while ($channel = get_next_record_array($registered_channels)) {
		cms_remove_channel($channel['localurl']);
	}

	db_reset_tables(); 
	$message = 'Files deleted; database reset';

	// re-establish if authorised (unlikely after a reset!)
	$cf_is_authorised = user_connection_is_authorised($cf_user, $cf_is_expired);
	$tables_present = db_tables_present();
}


if (user_num_users() == 0 && isset($_POST['username'])) {
	if (   isset($_POST['password'])
	    && $_POST['password'] != $_POST['password_check']) {
		$message = 'retry password entry';
	}
	else if (  isset($_POST['password'])) {
		user_register($_POST['username'], $_POST['password']);
		$cf_user = $_POST['username'];
		$cf_is_authorised = user_start_session($_POST['username'], $_POST['password']);
		$message = 'User '.$_POST['username'].' added';
	}
}


header("Content-Type: text/html; charset=UTF-8");

$op = new XMLWriter();
$op->openURI("php://output");
$op->setIndent(TRUE);

ui_startDocument($op, 'Setup: My Own TV Channel');
ui_writeHeader($op);
ui_writeBrandedTitle($op, 'My Own TV Channel Setup');

$op->writeElement('hr');

if ($message) {
	$op->text($message);
	$op->writeElement('hr');
}

if (user_num_users() == 0) {
	$op->startElement('h1');
	$op->text('User');
	$op->endElement();
	ui_startForm($op, '');

	$op->text('Username:');
	ui_writeInputText($op, 'username', 20, isset($_POST['username']) ? $_POST['username'] : 'admin');

	$op->text('Password:');
	ui_writeInputPass($op, 'password', 20);
	$op->text('Reenter password:');
	ui_writeInputPass($op, 'password_check', 20);

	ui_writeInputSubmit($op, 'Add User');

	$op->endElement();
}

if ($cf_is_authorised) {
	$op->startElement('h1');
	$op->text('Database');
	$op->endElement();
	ui_startForm($op, '');
	ui_writeInputHidden($op, 'action', 'reset');
	ui_writeInputSubmit($op, 'Reset Server');
	$op->endElement();
}

ui_writeFooter($op, 'setup');
ui_endDocument($op);


?>