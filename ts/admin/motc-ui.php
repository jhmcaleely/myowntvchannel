<?php

// motc-ui.php
// Copyright John McAleely, 2008


function ui_writeMessageDoc($page, $htmlcode, $message) {
	header("HTTP/1.1 $htmlcode $message");
	
	$op = new XMLWriter();
	$op->openURI("php://output");
	$op->setIndent(TRUE);

	ui_startDocument($op, "$message - My Own TV Channel Channel");
	ui_writeHeader($op);
	
	ui_writeBrandedTitle($op, $message);
	
	$op->text($message);
	
	ui_writeFooter($op, $page);
	ui_endDocument($op);
}


// does not depend on cf_motc (usefull for torrent.php)
function ui_writePlainMessageDoc($htmlcode, $message) {
	header("HTTP/1.1 $htmlcode $message");
	
	$op = new XMLWriter();
	$op->openURI("php://output");
	$op->setIndent(TRUE);

	ui_startDocument($op, "$message - My Own TV Channel Channel");
	$op->writeElement('h1', $message);	
	$op->text($message);
	ui_endDocument($op);
}


function ui_startDocument($xmlout, $title, $closehead = TRUE) {
	
	$xmlout->startDocument();
	$xmlout->writeRaw('<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">'."\n");
	$xmlout->writeRaw('<html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">'."\n");
	
	$xmlout->startElement('head');
	$xmlout->startElement('title');
	$xmlout->text($title);
	$xmlout->endElement();
	if ($closehead) {
		$xmlout->endElement();
		$xmlout->startElement('body');
	}
}


function ui_endDocument($xmlout) {
	$xmlout->endElement();
	$xmlout->writeRaw('</html>');
	$xmlout->endDocument();
	
}


function ui_name_with_ID($name, $id) {
	return $name."_".$id;
}


function ui_writeLink($op, $url, $text) {
	$op->startElement('a');
	$op->writeAttribute('href', $url);
	$op->text($text);
	$op->endElement();
}


function ui_startForm($op, $action, $method = 'post') {
	$op->startElement('form');
	if ($method == 'post') {
		$op->writeAttribute('method', $method);
	}
	$op->writeAttribute('action', $action);	
}


function ui_writeInputSubmit($op, $caption, $name = null) {
	$op->startElement('input');
	$op->writeAttribute('value', $caption);
	$op->writeAttribute('type', 'submit');
	if (isset($name)) {
		$op->writeAttribute('name', $name);
	}
	$op->endElement();
}


function ui_writeInputHidden($op, $name, $value) {
	$op->startElement('input');
	$op->writeAttribute('name', $name);
	$op->writeAttribute('value', $value);
	$op->writeAttribute('type', 'hidden');
	$op->endElement();
	
}


function ui_writeInputText($op, $name, $size, $value) {
	$op->startElement('input');
	$op->writeAttribute('name', $name);
	$op->writeAttribute('size', $size);
	$op->writeAttribute('value', $value);
	$op->endElement();
}


function ui_writeInputPass($op, $name, $size) {
	$op->startElement('input');
	$op->writeAttribute('name', $name);
	$op->writeAttribute('size', $size);
	$op->writeAttribute('type', 'password');
	$op->endElement();
}


function ui_writeInputCheck($op, $name, $state) {
	$op->startElement('input');
	$op->writeAttribute('name', $name);
	$op->writeAttribute('type', 'checkbox');
	if ($state !== FALSE) { 
		$op->writeAttribute('checked', 'checked'); 
	}
	$op->endElement();
}


function ui_writeWizardTitle($op, $step, $channelurl) {
	ui_writeBrandedTitle($op, $step.' - '.htmlspecialchars($channelurl));
}


function ui_writeBrandedTitle($op, $title) {
	$op->startElement('img');
	$op->writeAttribute('src', cf_url_for_script('motc-icon'));
	$op->writeAttribute('height', 90);
	$op->writeAttribute('width', 160);
	$op->writeAttribute('align', 'left');
	$op->writeAttribute('alt', 'My Own TV Channel logo');
	$op->writeAttribute('border', 0);
	$op->endElement();

	$op->writeElement('h1', $title);	
}


function ui_writeImg($op, $src, $alt, $height = null, $width = null, $border = 0) {
	$op->startElement('img');
	$op->writeAttribute('src', $src);
	if (isset($height)) { $op->writeAttribute('height', $height); }
	if (isset($width)) { $op->writeAttribute('width', $width); }
	$op->writeAttribute('alt', $alt);
	$op->writeAttribute('border', $border);
	$op->endElement();
}


function ui_writeFooter($op, $page) {
	$op->writeElement('hr');

	if ($page == 'home') {
		$op->text('Admin');
	}
	else {
		ui_writeLink($op, cf_url_for_script('home'), 'Admin');
	}
	$op->text(' | ');
	ui_writeLink($op, 'http://myowntvchannel.net/codex/', 'Documentation');	
}


function ui_writeHeader($op, $page = null) {
	global $cf_is_authorised, $cf_user, $cf_is_expired, $cf_motc;

	if ($page == 'home') {
		$op->text('Admin');
	}
	else {
		ui_writeLink($op, cf_url_for_script('home'), 'Admin');
	}
	$op->text(' | ');
	ui_writeLink($op, 'http://myowntvchannel.net/codex/', 'Documentation');	
	$op->text(' | ');

	$for = 'for='.rawurlencode($cf_motc['script_url']);
	
	if ($cf_is_authorised) {
		$op->text("Logged in as: $cf_user (");
		ui_writeLink($op, cf_url_for_script('login')."?action=logout&$for", 'logout');
		$op->text(')');
	}
	else if($cf_is_expired) {
		$op->text("Session expired: $cf_user (");
		$query = "?$for&user=".rawurlencode($cf_user);
		ui_writeLink($op, cf_url_for_script('login').$query, 'Login');
		$op->text(')');
	}
	else {
		ui_writeLink($op, cf_url_for_script('login')."?$for", 'Login');
	}
	$op->writeElement('hr');
}


function ui_writeChannelControl($op, $current_channels, $form_dir) {

	global $cf_motc;

	ui_startForm($op, '');

	ui_startTable($op, array('Title', 'URL', 'Path', '', ''));

	$op->startElement('tr');
	$op->startElement('td');
	$op->writeAttribute('colspan', '5');
	$op->text('Registered channels:');
	$op->endElement();
	$op->endElement();

	foreach ($current_channels as $cx) {

		if ($cx['status'] == 'registered') {

			$ccf = new SimpleXMLElement(cfcms_config_path($cx['localdir']), NULL, TRUE);
			$op->startElement('tr');

			$op->writeElement('td', (string) $ccf->channel_title);

			$op->startElement('td');
			ui_writeLink($op, $cx['localurl'], $cf_motc['host_url'].$cx['localurl']);
			$op->endElement();

			$op->writeElement('td', $cx['localdir']);

			$op->startElement('td');
			ui_writeLink($op, cf_url_for_script('manage').'?channel='.rawurlencode($cx['localurl']), 'Details');
			$op->endElement();
			$op->startElement('td');
			ui_writeInputSubmit($op, 'Unregister', ui_name_with_ID('delete', rawurlencode($cx['localurl'])));
			$op->endElement();

			$op->endElement();
		}
	}


	$op->startElement('tr');
	$op->startElement('td');
	$op->writeAttribute('colspan', '5');
	$op->text('Register a channel from an existing directory:');
	$op->endElement();
	$op->endElement();

	foreach ($current_channels as $cx) {
		if ($cx['status'] == 'candidate') {
			$op->startElement('tr');
			
			$pathid = rawurlencode(realpath($cx['localdir']));

			$op->startElement('td');
			ui_writeInputText($op, ui_name_with_ID('title', $pathid), 20, MOTC_CHANNEL_DEF_TITLE);
			$op->endElement();

			$op->writeElement('td');

			$op->startElement('td');
			$op->text(realpath($cx['localdir']));
			$op->endElement();

			$op->writeElement('td');

			$op->startElement("td");
			ui_writeInputSubmit($op, 'Register...', ui_name_with_ID('create', $pathid));
			$op->endElement();

			$op->endElement();
		}
	}

	$op->startElement('tr');
	$op->startElement('td');
	$op->writeAttribute('colspan', '5');
	$op->text('Create a channel from a new directory:');
	$op->endElement();
	$op->endElement();

	$op->startElement('tr');

	$op->startElement('td');
	ui_writeInputText($op, 'new_title', 20, MOTC_CHANNEL_DEF_TITLE);
	$op->endElement();

	$op->writeElement('td');

	$op->startElement('td');
	ui_writeInputText($op, 'new_channel', 50, $form_dir);
	$op->endElement();

	$op->writeElement('td');

	$op->startElement("td");
	ui_writeInputSubmit($op, 'Create...', 'create_new');
	$op->endElement();

	$op->endElement();
	$op->endElement();
	$op->endElement();
}


function ui_writeChannelStats($op) {
	
	global $cf_motc;

	$op->startElement('table');
	$op->startElement('tr');
	$op->writeElement('td', 'Title');
	$op->writeElement('td', 'URL');
	$op->endElement();

	$registered_channels = cms_channels();
	while ($channel = get_next_record_array($registered_channels)) {

		$ccf = new SimpleXMLElement(cfcms_config_path($channel['localdir']), NULL, TRUE);

		$op->startElement('tr');
		$op->writeElement('td', (string) $ccf->channel_title);
		$op->startElement('td');
		ui_writeLink($op, $channel['localurl'], $cf_motc['host_url'].$channel['localurl']);
		$op->endElement();
		$op->endElement();
	}

	$op->endElement();
}


function ui_selectOption($text, $selected, $value = null) {
	if ($selected) {
		$option->selected = TRUE;
	}
	if (isset($value)) {
		$option->value = $value;
	}
	$option->name = (string) $text;
	return $option;
}


function ui_writeLogoIconSelect($op, $name, $icons, $default, $logotext, $logoval) {
	
	$display[] = ui_selectOption('none', 'none' == $default, 'none');
	$display[] = ui_selectOption($logotext, $logoval == $default, $logoval);
	
	if (count($icons) > 0) {
		foreach($icons as $i) {
			$display[] = ui_selectOption($i->filename, ((string) $i->filename) == $default);
		}
	}
	
	ui_writeInputSelect($op, $name, $display);
}


function ui_writeInputSelect($op, $name, $items) {
	
	$op->startElement('select');
	$op->writeAttribute('name', $name);
	$op->writeAttribute('size', 1);
	foreach ($items as $i) {
		$op->startElement('option');
		if (isset($i->value)) {
			$op->writeAttribute('value', (string) $i->value);
		}
		if (isset($i->selected)) {
			$op->writeAttribute('selected', 'selected');
		}
		$op->text($i->name);
		$op->endElement();
	}
	$op->endElement();
}


// TRUE and FALSE dont play well with simplexml
function ui_update_check($name, $cf_channel) {
	$cf_channel->$name = isset($_POST[$name]) ? 1 : 0;
}

function ui_startTable($op, $headings) {
	$op->startElement('table');
		$op->startElement('tr');
	foreach ($headings as $h) {
		$op->writeElement('td', $h);		
	}
	$op->endElement();

}


?>