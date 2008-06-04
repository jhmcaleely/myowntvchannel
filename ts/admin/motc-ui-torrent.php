<?php

// motc-ui.php
// Copyright John McAleely, 2008


function ui_writeTorrentUpdateScript($op, $cf_config) {
	
	// for some reason the 'short form' '/>' doesn't work for script here
	$op->startElement('script');
	$op->writeAttribute('type', 'text/javascript');
	$op->writeAttribute('src', 'publish_helper.js');
	$op->fullEndElement();

	$op->startElement('script');
	$op->writeAttribute('type', 'text/javascript');
	$op->writeRaw("<!-- \n");
	$op->writeRaw("function create_worklist() {\n");

	$make_torrenturl = cf_url_for_script('torrent');
	$make_torrenturl .= '?channel='.urlencode($_GET['channel']);

	$op->writeRaw("make_torrent_url=\"$make_torrenturl\";\n");

	if (isset($_POST['rebuild'])) {
		$op->writeRaw("make_torrent_post_options=\"cache=ignore\";\n");	
	}
	else {
		$op->writeRaw("make_torrent_post_options=null;\n");	
	}

	$size = count($cf_config->items->item);

	for ($x = $size - 1; $x >= 0; $x--) {
		$i = $cf_config->items->item[$x];

		if ($i->status == 'included') {
			$media_file = urlencode($i->filename);
			$status_message_id = "status$x";

			$op->writeRaw("add_workitem(\"$media_file\", \"$status_message_id\");\n");
		}
	}

	$op->writeRaw("}\n");
	$op->writeRaw('// -->');

	$op->endElement();
	
}


?>