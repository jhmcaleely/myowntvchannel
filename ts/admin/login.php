<?php

/*
 * login.php
 *
 * Copyright John McAleely 2008
 */

require('motc-internal.php');
$cf_motc = cf_globals();
db_open();
$cf_is_authorised = user_connection_is_authorised($cf_user, $cf_is_expired);


header('Content-Type: text/html; charset=UTF-8');

if (   isset($_POST['user'])
    && isset($_POST['password'])) {

	$cf_user = $_POST['user'];
	$cf_is_authorised = user_start_session($cf_user, $_POST['password']);
	$redirect = $cf_is_authorised;
}


if (   isset($_GET['action'])
    && $_GET['action'] == 'logout') {

	user_end_session();
	$cf_is_authorised = FALSE;
	$redirect = TRUE;
}



if ($redirect && isset($_GET['for'])) {
	$url = $cf_motc['host_url'].$_GET['for'];
	header("Location: $url", NULL, 303);
}


$op = new XMLWriter();
$op->openURI("php://output");
$op->setIndent(TRUE);
ui_startDocument($op, 'login');
ui_writeBrandedTitle($op, 'My Own TV Channel Login');

if (!$cf_is_authorised) {
	ui_startForm($op, '');
	$op->text("User:");
	ui_writeInputText($op, 'user', 30, $_GET['user']);
	$op->text("Password:");
	ui_writeInputPass($op, 'password', 30);
	ui_writeInputSubmit($op, 'Login');
	$op->endElement();
}
else {
	$op->text("Welcome ");
	$op->text(htmlspecialchars($cf_user));

	ui_startForm($op, '', 'get');
	ui_writeInputHidden($op, 'action', 'logout');
	ui_writeInputSubmit($op, 'Logout');
	$op->endElement();
	
}

ui_writeFooter($op, 'login');
ui_endDocument($op);

?>