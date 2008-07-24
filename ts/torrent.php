<?php
/*
 * torrent.php
 *
 * Copyright John McAleely 2008
 */

// This file can be called after an (internal) server URL rewrite. So it will often not be named in the user-agent requested URL.
// Hence it does not load cf_globals()

require('admin/motc-internal.php');
db_open();

$cf_hosturl = cf_host_url();

if (isset($_GET['url'])) {
	$channel = parse_url($_GET['url'], PHP_URL_PATH);
	$info = pathinfo($channel);

	$channel = $info['dirname'].'/';
	$file = $info['basename'];
}
else {
	$channel = $_GET['channel'];
	$file = $_GET['filename'];
}

$config = cms_channel_config($channel);
if ($config === FALSE) {
	ui_writePlainMessageDoc(404, "Not Found");
	exit(0);
}

if (isset($config->items->item)) {
	foreach ($config->items->item as $i) {
		if ($i->filename == $file) {
			$publish_item = $i;
		}
	}
}
if (!isset($publish_item)) {
	ui_writePlainMessageDoc(404, "File Not Found");
	exit(0);
}

$media_stats = stat($config->base_path.'/'.$publish_item->filename);
if ($media_stats === FALSE) {	// the file is in the channel config, but couldn't be found on disk. Will heal next time the channel is rebuilt.
	ui_writePlainMessageDoc(404, "File Not Found");
	exit(0);
}



if (   !file_exists($config->base_path.'/'.$publish_item->torrent_file)
    || $publish_item->lastmod != $media_stats['mtime']
    || (isset($_POST['cache']) && $_POST['cache'] == 'ignore')) {

	// crude check that the script completes, and produces a valid torrent.
	$publish_item->lastmod = 0;

	$media = fopen($config->base_path.'/'.$publish_item->filename, "rb");	
	$torrent = fopen($config->base_path.'/'.$publish_item->torrent_file, "wb");
	chmod($config->base_path.'/'.$publish_item->torrent_file, MOTC_MODE_FILE);
	
	if ($media === FALSE || $torrent === FALSE) { // something changed on disk since this torrent was registered. 
												  // Give up, and hope it fixes itself when the channel is rebuilt.
												  // if we proceed, the apache logs will fill with garbage errors while
												  // we use the bad handles.
		ui_writePlainMessageDoc(404, "File Not Found");
		exit(0);
	}
	
	$payload['trackerurl'] = (string) $config->announce_url;
	$payload['filename'] = (string) $publish_item->filename;
	$payload['filelength'] = $media_stats['size'];
	$payload['fileurl'] = $cf_hosturl.$config->base_url.rawurlencode($publish_item->filename);
	$payload['piecelen'] = MOTC_TORRENT_PIECE_LEN;

	$io['in'] = $media;
	$io['out'] = $torrent;

	torrent_make($payload, $io);

	torrent_register_infohash($io['infohash']);
	cms_register_infohash($_GET['channel'], (string) $publish_item->filename, $io['infohash']);

	$publish_item->lastmod = $media_stats['mtime'];
	
	cms_write_channel_config($config);
}

$url = $cf_hosturl.$config->base_url.$publish_item->torrent_file;
// redirect temporarily, so that the orginal URL stands.
header("Location: $url", NULL, 302);

?>