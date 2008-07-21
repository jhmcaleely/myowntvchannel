<?php

/*
 * Copyright John McAleely, 2008
 */


function cfcms_url_channel_item($script, $cf_channel, $cf_item = null) {
	
	if ($script == 'rss') {
		if (MOTC_HIDE_INTERNAL_EXT) {
			return $cf_channel->base_url.pathinfo($cf_channel->channel_rss_file, PATHINFO_FILENAME);
		}
		return $cf_channel->base_url.$cf_channel->channel_rss_file;
	}
	else if ($script == 'html') {
		if (cfcms_is_default_filename($cf_channel->channel_html_file)) {
			return $cf_channel->base_url;
		}
		else if (MOTC_HIDE_INTERNAL_EXT) {
			return $cf_channel->base_url.pathinfo($cf_channel->channel_html_file, PATHINFO_FILENAME);
		}
		else {
			return $cf_channel->base_url.$cf_channel->channel_html_file;
		}
	}
	else if ($script == 'file') {
		return $cf_channel->base_url.rawurlencode($cf_item->filename);
	}
	else if ($script == 'torrent' && MOTC_REWRITE_TORRENTS) {
		return $cf_channel->base_url.rawurlencode($cf_item->torrent_file);
	}
	else if ($script == 'torrent') {
		$url = cf_url_for_script('torrent');
		$query = 'channel='.rawurlencode($cf_channel->base_url);
		$query .= '&filename='.rawurlencode($cf_item->filename);
		
		return $url.'?'.$query;
	}
	else if ($script == 'icon' && $cf_item->icon == 'motc-item') {
		return cf_url_for_script('motc-item');
	}
	else if ($script == 'icon') {
		return $cf_channel->base_url.$cf_item->icon;
	}
	else if ($script == 'channel-icon' && $cf_channel->channel_icon == 'motc-channel') {
		return cf_url_for_script('motc-channel');
	}
	else if ($script == 'channel-icon') {
		return $cf_channel->base_url.$cf_channel->channel_icon;
	}
}


function cfcms_is_default_filename($filename) {
	return $filename == MOTC_DEFAULT_HTML;
}


function cfcms_config_path($channeldir) { 
	return $channeldir.'/'.MOTC_CHANNEL_CONFIG_NAME; 
}


function cms_channel_config($channelurl) {
	
	$queryurl = mysql_real_escape_string($channelurl);
	$sqlresult = mysql_query('SELECT localdir FROM '.cf_cms_channels_table()." WHERE (localurl=\"$queryurl\")");

	if (!(bool) mysql_num_rows($sqlresult) > 0) {
		return FALSE;
	}
	else {
		$result_arr = mysql_fetch_array($sqlresult, MYSQL_ASSOC);
		$config_path = cfcms_config_path($result_arr['localdir']);
		if (file_exists($config_path)) {
			return new SimpleXMLElement($config_path, NULL, TRUE);
		}
		else {
			return FALSE;
		}
	}
}


function cms_write_channel_config($cf_channel) {
	$cf_channel->asXML(cfcms_config_path($cf_channel->base_path));
}


function cms_write_channel_rss($cf_channel) {
	
	global $cf_motc;
	
	$rsspath = $cf_channel->base_path.'/'.$cf_channel->channel_rss_file;
	$channelxml = new XMLWriter();
	$channelxml->openURI($rsspath);
	$channelxml->setIndent(TRUE);
	chmod($rsspath, MOTC_MODE_FILE);

	cms_startChannel($channelxml, $cf_channel);
	
	if (isset($cf_channel->items->item)) {
		foreach ($cf_channel->items->item as $i) {

			if ( $i->status == 'included') {
				cms_writeItem($channelxml, $i, $cf_channel, TRUE, $date);
			}
			$channel_date = max($date, $channel_date);
		}
	}
	$channelxml->writeElement('lastBuildDate', date('r'));
	$channelxml->writeElement('pubDate', date('r', (int) $channel_date));
	
	cms_endChannel($channelxml);
}


function cms_write_channel_html($cf_channel) {

	global $cf_motc;
	
	$path = $cf_channel->base_path.'/'.$cf_channel->channel_html_file;
	$xml = new XMLWriter();
	$xml->openURI($path);
	$xml->setIndent(TRUE);
	
	chmod($path, MOTC_MODE_FILE);
	
	ui_startDocument($xml, $cf_channel->channel_title);
	
	
	if (isset($cf_channel->channel_icon) && $cf_channel->channel_icon != 'none') {
		if ($cf_channel->channel_icon == 'motc-channel') {
			ui_writeImg($xml, cfcms_url_channel_item('channel-icon', $cf_channel), 'Icon', 90, 160);
		}
		else {
			ui_writeImg($xml, cfcms_url_channel_item('channel-icon', $cf_channel), 'Icon');
		}
	}
	$xml->writeElement('h1', $cf_channel->channel_title);
	
	ui_writeLink($xml, cfcms_url_channel_item('rss', $cf_channel), 'RSS Feed');
	$xml->text(' ');
	$xml->startElement('a');
	$xml->writeAttribute('href', 'http://subscribe.getMiro.com/?url1='.urlencode($cf_motc['host_url'].cfcms_url_channel_item('rss', $cf_channel)));
	$xml->writeAttribute('title', 'Miro: Internet TV');
	ui_writeImg($xml, 'http://subscribe.getmiro.com/img/buttons/gabriel1.png', 'Miro Video Player');
	$xml->endElement();
	
	$xml->writeElement('p', $cf_channel->channel_desc);

	if (isset($cf_channel->items->item)) {
		foreach ($cf_channel->items->item as $i) {
			if ( $i->status == 'included') {
				$xml->startElement('div');
				
				if (isset($i->icon) && $i->icon != 'none') {
					if ($i->icon == 'motc-item') {
						ui_writeImg($xml, cfcms_url_channel_item('icon', $cf_channel, $i), 'Icon', 90, 160);
					}
					else {
						ui_writeImg($xml, cfcms_url_channel_item('icon', $cf_channel, $i), 'Icon');
					}
				}
				$xml->writeElement('h2', $i->title);
				if (strlen($i->description) > 0) {
					$xml->writeElement('p', $i->description);
				}
				$xml->text('(');
				ui_writeLink($xml, cfcms_url_channel_item('torrent', $cf_channel, $i), 'torrent');
				$xml->text('|');
				ui_writeLink($xml, cfcms_url_channel_item('file', $cf_channel, $i), 'download');
				$xml->text(')');

				$xml->endElement();
			}
		}
	}

	if ($cf_channel->publish_admin_link == 1) {
		$xml->writeElement('hr');
		ui_writeLink($xml, cf_url_for_script('home'), 'Admin');
	}
	
	ui_endDocument($xml);
}


function cms_startChannel($xml, $cf_channel) {
	
	global $cf_motc;
	
	$htmlurl = $cf_motc['host_url'].cfcms_url_channel_item('html', $cf_channel);
	
	$xml->startDocument(NULL, 'utf-8');
	$xml->startElement('rss');
	$xml->writeAttribute('version', '2.0');
	$xml->startElement('channel');
	$xml->writeElement('description', $cf_channel->channel_desc);
	$xml->writeElement('link', $htmlurl);
	$xml->writeElement('title', $cf_channel->channel_title);
	$xml->writeElement('generator', 'MOTC-CMS');
	
	if (isset($cf_channel->channel_icon) && $cf_channel->channel_icon != 'none') {
		$xml->startElement('image');
		$xml->writeElement('link', $htmlurl);
		$xml->writeElement('title', $cf_channel->channel_title);
		$xml->writeElement('url', $cf_motc['host_url'].cfcms_url_channel_item('channel-icon', $cf_channel));
		$xml->writeElement('description', $cf_channel->channel_desc);
		if ($cf_channel->channel_icon == 'motc-channel') {
			$xml->writeElement('height', 90);
			$xml->writeElement('width', 160);
		}
		$xml->endElement();
	}

}


function cms_endChannel($xml) {
	
	$xml->endElement();
	$xml->endElement();
}


function cms_writeItem($xml, $item, $cf_channel, $enclose_torrent, &$date) {
	
	global $cf_motc;

	$xml->startElement('item');
	$xml->writeElement('title', $item->title);
	$xml->writeElement('description', $item->description);
	$xml->writeElement('link', $cf_motc['host_url'].cfcms_url_channel_item('html', $cf_channel));
	$xml->writeElement('guid', $cf_motc['host_url'].$cf_channel->base_url.$item->filename);

	$info = apache_lookup_uri(cfcms_url_channel_item('file', $cf_channel, $item));
	$file_info = stat($cf_channel->base_path.'/'.$item->filename);
	
	if ($enclose_torrent) {
		$xml->startElement('enclosure');
		$xml->writeAttribute('length', $item->torrent_length);
		if ($info->content_type) {
			$xml->writeAttribute('type', 'application/x-bittorrent;enclosed='.$info->content_type);
		}
		else {
			$xml->writeAttribute('type', 'application/x-bittorrent');
		}
		$xml->writeAttribute('url', $cf_motc['host_url'].cfcms_url_channel_item('torrent', $cf_channel, $item));
		$xml->endElement();
	}
	else {
		$xml->startElement('enclosure');
		$xml->writeAttribute('length', $file_info['size']);
		if ($info->content_type) {
			$xml->writeAttribute('type', $info->content_type);
		}
		else {
			$xml->writeAttribute('type', 'application/octet-stream');// default to an unknown binary file.
		}
		$xml->writeAttribute('url', $cf_motc['host_url'].cfcms_url_channel_item('file', $cf_channel, $item));
		$xml->endElement();
	}
	
	if (isset($item->icon) && $item->icon != 'none') {
		$xml->startElementNS('media', 'thumbnail', 'http://search.yahoo.com/mrss/');
		$xml->writeAttribute('url', $cf_motc['host_url'].cfcms_url_channel_item('icon', $cf_channel, $item));
		if ($item->icon == 'motc-item') {
			$xml->writeAttribute('height', 90);
			$xml->writeAttribute('width', 160);
		}
		$xml->endElement();
	}

	if (!isset($item->pub_date)) {
		$item->pub_date = time();
	}
	$date = $item->pub_date;
	$xml->writeElement('pubDate', date('r', (int) $item->pub_date));
	$xml->endElement();	
}


function cms_infohash_names($infohash) {
	$queryhash = mysql_real_escape_string($infohash);
	$result = mysql_query("SELECT localurl, localfile FROM ".cf_cms_infohash_name_table()." WHERE infohash=\"$queryhash\"");
	
	$data = mysql_fetch_array($result, MYSQL_ASSOC);
	return $data;
}


function cms_is_present($filename, $config) {
	
	if ($config->channel_rss_file == $filename) { return TRUE; }
	if ($config->channel_html_file == $filename) { return TRUE; }

	if (isset($config->icons->icon)) { 
		foreach ($config->icons->icon as $i) {
			if (((string) $i->filename) == $filename) { return TRUE; }
		}
	}
	
	if (isset($config->items->item)) { 
		foreach ($config->items->item as $i) {
		
			if (((string) $i->torrent_file) == $filename) { return TRUE; }
			if (((string) $i->filename) == $filename) { return TRUE; }
		}
	}
	return FALSE;
}


// duplicate infohashes (files) are permitted in the channel/file namespace, so we only
// unregister an infohash from the torrent server if it no longer appears in the name table
// after the particular combination we want to remove is gone.
function cms_unregister_channel_names($channelurl, $filename=null) {
	$queryurl = mysql_real_escape_string($channelurl);
	$where = "localurl=\"$queryurl\"";
	if (isset($filename)) {
		$queryfile = mysql_real_escape_string($filename);
		$where .= "AND localfile=\"$queryfile\"";
	}
	
	$result = mysql_query('SELECT infohash FROM '.cf_cms_infohash_name_table()." WHERE $where");
	
	while ($record = get_next_record_array($result)) {
		$infohashes[] = $record['infohash'];
	}

	mysql_query('DELETE FROM '.cf_cms_infohash_name_table()." WHERE $where");
	
	if (isset($infohashes)) {
		foreach ($infohashes as $i) {
			$queryi = mysql_real_escape_string($i);
			if (!mysql_motc_rec_exists('infohash', cf_cms_infohash_name_table(), 'infohash="'.$queryi.'"')) {
				torrent_unregister_infohash($i);
			}
		}
	}
}


// call repeatedly until it returns FALSE
function cms_remove_one_stale_file($cf_channel) {
	$x = 0;
	if (isset($cf_channel->items->item)) {
		foreach($cf_channel->items->item as $i) {
			if (!file_exists($cf_channel->base_path.'/'.$i->filename)) {
				// an external party deleted the file.
				cms_unlink($cf_channel->base_path.'/'.$i->torrent_file);
				cms_unregister_channel_names($cf_channel->base_url, $i->filename);
				unset($cf_channel->items->item[$x]);
				return TRUE;
			}
			$x++;
		}
	}
	return FALSE;
}


function cms_add_new_item($cf_channel, $filename) {
	
	global $cf_motc;
	$path = $cf_channel->base_path.'/'.$filename;
	
	if (!isset($cf_channel->items)) { $cf_channel->addChild('items'); }

	$new_item = $cf_channel->items->addChild('item');
	$new_item->id = $cf_channel->next_id;
	$cf_channel->next_id = (int) $cf_channel->next_id + 1;
	
	$new_item->filename = $filename;
	$new_item->title = $filename;
	$new_item->torrent_file = $filename.'.torrent';
	$new_item->status = 'included';
	$default_icon = cms_default_icon_for_path($path);
	if (isset($default_icon)) {
		$new_item->icon = $default_icon;
	}
	

	$media_stats = stat($path);

	$payload['trackerurl'] = $cf_motc['host_url'].cf_url_for_script('announce');
	$payload['filename'] = (string) $new_item->filename;
	$payload['filelength'] = $media_stats['size'];
	$payload['fileurl'] = $cf_motc['host_url'].$cf_channel->base_url.$new_item->filename;
	$payload['piecelen'] = MOTC_TORRENT_PIECE_LEN;

	$new_item->torrent_length = torrent_length($payload);
}


function cms_default_icon_for_path($path) {

	if (is_dir($path)) {
		$default = 'motc-channel';
		$scanpath = $path;
	}
	else {
		$default = 'motc-item';
		$scanpath = pathinfo($path, PATHINFO_DIRNAME);
	}

	$seed = pathinfo($path, PATHINFO_FILENAME);
	$candidates = scandir($scanpath);
	
	foreach ($candidates as $index => $file) {
		// need to escape $seed
		if (preg_match('/'.$seed.'[.]('.MOTC_ICON_EXTENSIONS.')$/i', $file) != 0) {
			return $file;
		}
	}
	
	return $default;
}


function cms_is_icon($filename) {
	return (preg_match('/[.]('.MOTC_ICON_EXTENSIONS.')$/i', $filename) != 0);
}


function cms_add_icon($cf_channel, $filename) {
	if (!isset($cf_channel->icons)) { $cf_channel->addChild('icons'); }

	$new_icon = $cf_channel->icons->addChild('icon');
	$new_icon->filename = $filename;
	error_log("Icon: $filename", 0);
}


function cms_sync_dir_to_channel($cf_channel) {
	
	while (cms_remove_one_stale_file($cf_channel)) {
		;
	}
	
	$channel_candidates = scandir($cf_channel->base_path);
	
	foreach ($channel_candidates as $index => $filename) {
		$path = $cf_channel->base_path.'/'.$filename;

		if (   !cms_is_ignored_file($filename)
		    && !is_dir($path) 
		    && !cms_is_present($filename, $cf_channel)) {
			
			if (cms_is_icon($filename)) {
				cms_add_icon($cf_channel, $filename);
			}
			else {
				cms_add_new_item($cf_channel, $filename);
			}
		}
	}
}


// on Unix, files are not presented to the user if they start with '.'.
function cms_is_ignored_file($filename) {

	return (isset($filename[0]) && $filename[0] == '.');
}


function cms_is_registered_path($localdir) {
	$querydir = mysql_real_escape_string(realpath($localdir));
		
	return mysql_motc_rec_exists('localurl', cf_cms_channels_table(), "localdir=\"$querydir\"");
}


function cms_is_valid_channel($localdir) {
	if (   cms_is_registered_path($localdir)
	    && cms_is_channel_dir($localdir)
	    && file_exists(cfcms_config_path($localdir))) {
		return TRUE;
	}
	return FALSE;
}


function cms_register_channel($localurl, $localdir) {
	$queryurl = mysql_real_escape_string($localurl);
	$querydir = mysql_real_escape_string(realpath($localdir));
	
	if (!cms_is_registered_path($localdir)) {
		mysql_query("INSERT ".cf_cms_channels_table()." () VALUES (\"$queryurl\", \"$querydir\")");		
	}

}


function cms_register_infohash($channelurl, $localfile, $infohash) {

	$queryhash = mysql_real_escape_string($infohash);
	$queryurl = mysql_real_escape_string($channelurl);
	$queryfile = mysql_real_escape_string($localfile);

	if (!mysql_motc_rec_exists('infohash', cf_cms_infohash_name_table(), "localurl=\"$queryurl\" AND \"localfile=$queryfile\"")) {

		mysql_query("INSERT ".cf_cms_infohash_name_table()." () VALUES (\"$queryurl\", \"$queryfile\", \"$queryhash\")");
	}
}


function cms_is_channel_dir($path) {
	return (   file_exists($path)
	        && is_dir($path)
			&& is_executable($path) 
			&& is_writable($path));
}


function cms_make_channel($path, $title) {
	
	global $cf_motc;
	
	if (!file_exists($path)) {
		//mkdir emits a lot of warnings if we try to use it when we shouldn't.
		//therefore check the parent dir has appropriate rights (conveniently those we need
		//for the channel itself)
		$parentinfo = pathinfo($path, PATHINFO_DIRNAME);
		if (!cms_is_channel_dir($parentinfo)) {
			return FALSE;
		}

		mkdir($path, MOTC_MODE_DIR);	// mode is mangled by umask, so chmod it afterward.
		chmod($path, MOTC_MODE_DIR);
	}

	// now the path certainly exists, cannonicalise it.
	$path = realpath($path);

	// it may have already existed, but with the wrong permissions.
	if (!cms_is_channel_dir($path)) {
		return FALSE;
	}

	// decide on a url for this path. paths don't have trainling slashes. URL's do.
	$urlfragment = substr($path, strlen($cf_motc['base_dir']));	
	if (strlen($urlfragment) > 0) {
		// this is a subdir eg '/test', so we need to strip the prefix /, and add a trailing one.
		$urlfragment = substr($urlfragment, 1);
		$urlfragment .= '/'; 
	}
	$url = $cf_motc['base_url'].$urlfragment;

	// create a default, well formed, channel config.
	$op = new XMLWriter();
	if (!$op->openURI(cfcms_config_path($path))) {
		return FALSE;
	}
	$op->setIndent(TRUE);
	
	chmod(cfcms_config_path($path), MOTC_MODE_FILE);	
	
	$op->startDocument();
	$op->startElement('motc_channel');
	$op->writeElement('channel_title', $title);
	$op->writeElement('channel_desc', MOTC_CHANNEL_DEF_DESC);
	$op->writeElement('base_url', $url);
	$op->writeElement('base_path', $path);
	$op->writeElement('channel_rss_file', MOTC_CHANNEL_RSS_NAME);
	$op->writeElement('channel_html_file', MOTC_CHANNEL_HTML_NAME);
	$op->writeElement('publish_html', MOTC_CREATE_HTML);
	$op->writeElement('publish_admin_link', MOTC_INCLUDE_ADMIN);
	$op->writeElement('next_id', 0);
	$op->writeElement('announce_url', $cf_motc['host_url'].cf_url_for_script('announce'));
	
	
	$default_icon = cms_default_icon_for_path($path);
	if (isset($default_icon)) {
		$op->writeElement('channel_icon', $default_icon);
	}
	
	$op->endElement();
	$op->endDocument();

	cms_register_channel($url, $path);
	
	return $url;
}


function cms_channels() {
	return mysql_query('SELECT * FROM '.cf_cms_channels_table());
}


function cms_unlink($file) { if (is_writable($file)) { unlink($file); } }


function cms_rmdir($dir) {
	$contents = scandir($dir);
	if (count($contents) == 2) {
		rmdir($dir);
	}
}


function cms_remove_channel($channelurl) {
	
	$cf_channel = cms_channel_config($channelurl);
	if ($cf_channel) {
		cms_unlink($cf_channel->base_path.'/'.$cf_channel->channel_rss_file);
		cms_unlink($cf_channel->base_path.'/'.$cf_channel->channel_html_file);
		
		if (isset($cf_channel->items->item)) {
			foreach ($cf_channel->items->item as $i) {
				if (isset($i->torrent_file)) {
					cms_unlink($cf_channel->base_path.'/'.$i->torrent_file);
				}
			}
		}

		cms_unlink(cfcms_config_path($cf_channel->base_path));
	}

	$queryurl = mysql_real_escape_string($channelurl);
	$sqlresult = mysql_query('SELECT localdir FROM '.cf_cms_channels_table()." WHERE localurl=\"$queryurl\"");

	if (mysql_num_rows($sqlresult) > 0) {
		$result_arr = mysql_fetch_array($sqlresult, MYSQL_ASSOC);
		cms_rmdir($result_arr['localdir']);
	}

	cms_unregister_channel_names($channelurl);
	mysql_query('DELETE FROM '.cf_cms_channels_table()." WHERE localurl=\"$queryurl\"");
}


function cms_channel_dirs() {
	
	global $cf_motc;

	$registered_channels = cms_channels();
	while ($channel = get_next_record_array($registered_channels)) {

		if (!cms_is_valid_channel($channel['localdir'])) {
			cms_remove_channel($channel['localurl']);
		}
		else {
			$current_channels[] = array('localurl' => $channel['localurl'], 'localdir' => $channel['localdir'], 'status' => 'registered');		
		}
	}

	$channel_candidates = scandir($cf_motc['base_dir']);
	foreach ($channel_candidates as $index => $filename) {

		$path = $cf_motc['base_dir'].'/'.$filename;
		// resolve . and .. to their actual paths.
		$realpath = realpath($cf_motc['base_dir'].'/'.$filename);

		if (   cms_is_channel_dir($path) 
			&& !cms_is_registered_path($realpath)) {

			$current_channels[] = array('localdir' => $path, 'status' => 'candidate');
		}
	}
	
	return $current_channels;	
}

?>