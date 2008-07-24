<?php

// motc-ui-channel.php
// Copyright John McAleely, 2008

$edit_fields = array('channel_title', 'channel_desc', 'channel_icon', 'channel_rss_file', 'channel_nt_rss_file', 'channel_html_file');

function ui_writeEditChannel($op, $cf_channel) {
	global $cf_motc;
	
	$form = ui_get_edit_vals($cf_channel);
	
	ui_startForm($op, '');

	ui_writeInputSubmit($op, 'Save', 'save-channel');
	ui_writeInputSubmit($op, 'Cancel', 'display');
	
	$op->writeElement('h2', 'Overview');
	
	$op->text('Channel Title:');
	ui_writeInputText($op, 'channel_title', 20, $form->channel_title);

	$op->text("Description:");
	ui_writeInputText($op, 'channel_desc', 40, $form->channel_desc);

	ui_writeLogoIconSelect($op, 'channel_icon', $cf_channel->icons->icon, $form->channel_icon, 'My Own TV Channel', 'motc-channel');
	
	
	$op->startElement('h2');
	$op->text('Feeds (RSS)');
	$op->endElement();
	$op->text('File: '.$cf_channel->base_path.'/');
	ui_writeInputText($op, 'channel_rss_file', 20, $form->channel_rss_file);
	$op->writeElement('br');

	ui_writeInputCheck($op, 'publish_non_torrent', $cf_channel->publish_non_torrent == 1);
	$op->text('Publish non-torrent feed as well');
	$op->writeElement('br');
	$op->text('File: '.$cf_channel->base_path.'/');
	ui_writeInputText($op, 'channel_nt_rss_file', 20, $form->channel_nt_rss_file);


	$op->startElement('h2');
	$op->text('Web page (HTML)');
	$op->endElement();
	ui_writeInputCheck($op, 'publish_html', $cf_channel->publish_html == 1);
	$op->text('Update html');
	$op->writeElement('br');
	$op->text('File: '.$cf_channel->base_path.'/');
	ui_writeInputText($op, 'channel_html_file', 20, $form->channel_html_file);
	$op->writeElement('br');
	$op->text('URL: ');
	ui_writeLink($op, cfcms_url_channel_item('channel_html_file', $cf_channel), $cf_motc['host_url'].cfcms_url_channel_item('channel_html_file', $cf_channel));
	$op->writeElement('br');
	ui_writeInputCheck($op, 'publish_admin_link', $cf_channel->publish_admin_link == 1);
	$op->text('Include a link to Admin in HTML');

	$op->endElement();
}


function ui_writeChannel($op, $cf_channel) {
	global $cf_motc;
	
	$op->writeElement('h2', 'Channel');
	ui_startForm($op, '');

	ui_writeInputSubmit($op, 'Edit...', 'edit-channel');

	$op->writeElement('h3', 'Overview');
	
	$op->text('Title: '.$cf_channel->channel_title);
	$op->writeElement('br');
	$op->text('Description: '.$cf_channel->channel_desc);


	$op->writeElement('h3', 'Feeds (RSS)');
	$op->text('With Torrents:');
	$op->writeElement('br');
	$op->text('File: '.$cf_channel->base_path."/$cf_channel->channel_rss_file");
	$op->writeElement('br');
	$op->text('URL: ');
	ui_writeLink($op, cfcms_url_channel_item('channel_rss_file', $cf_channel), $cf_motc['host_url'].cfcms_url_channel_item('channel_rss_file', $cf_channel));
	$op->writeElement('br');
	if ($cf_channel->publish_non_torrent == 1) {
		$op->text('Without Torrents:');
		$op->writeElement('br');
		$op->text('File: '.$cf_channel->base_path."/$cf_channel->channel_nt_rss_file");
		$op->writeElement('br');
		$op->text('URL: ');
		ui_writeLink($op, cfcms_url_channel_item('channel_nt_rss_file', $cf_channel), $cf_motc['host_url'].cfcms_url_channel_item('channel_nt_rss_file', $cf_channel));
	}
	else {
		$op->text('Not publishing feed without torrents');
	}

	$op->writeElement('h3', 'Web page (HTML)');
	
	if ($cf_channel->publish_html == 1) {
		$op->text('Publish HTML');
		if ($cf_channel->publish_admin_link == 1) {
			$op->text(' (With link to Admin in footer)');
		}
		$op->writeElement('br');
		$op->text('File: '.$cf_channel->base_path."/$cf_channel->channel_html_file");
		$op->writeElement('br');
		$op->text('URL: ');
		ui_writeLink($op, cfcms_url_channel_item('channel_html_file', $cf_channel), $cf_motc['host_url'].cfcms_url_channel_item('channel_html_file', $cf_channel));
	}
	else {
		$op->text('Not publishing a HTML file');
	}
	
	$op->endElement();
}

function ui_get_edit_vals($cf_channel) {
	global $edit_fields;
	
	foreach ($edit_fields as $p) {
		$edit_vals->$p = isset($_POST[$p]) ? $edit_vals->$p = $_POST[$p] : $cf_channel->$p;
	}
	
	return $edit_vals;
}


function ui_updateChannel($cf_channel) {
	global $edit_fields, $cf_channel_files;
	
	$edit_vals = ui_get_edit_vals($cf_channel);
	
	foreach ($edit_fields as $p) {
		$edit_vals->$p = isset($_POST[$p]) ? $edit_vals->$p = $_POST[$p] : $cf_channel->$p;
	}
	
	// to be valid rss both the channel title and description elements must be present.
	if (strlen($edit_vals->channel_title) == 0 || strlen($edit_vals->channel_desc) == 0) {
		return 'A title and description must be present';
	}
	
	foreach($cf_channel_files as $name) {
		if (   $edit_vals->$name != $cf_channel->$name
			&& file_exists($cf_channel->base_path.'/'.$cf_channel->$name)) {
			cms_unlink($cf_channel->base_path.'/'.$cf_channel->$name);
		}
	}

	foreach ($edit_fields as $p) {
		$cf_channel->$p = $edit_vals->$p;
	}

	if (isset($_POST['channel_html_file'])) {
		ui_update_check('publish_html', $cf_channel);
		ui_update_check('publish_admin_link', $cf_channel);
		ui_update_check('publish_non_torrent', $cf_channel);
	}
	
	return TRUE;
}
?>