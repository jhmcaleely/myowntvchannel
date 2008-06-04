<?php

/*
 * announce.php
 *
 * Record client stats from bittorrent downloaders, and provide them with peers to download from.
 *
 * Copyright John McAleely 2008
 */

require('admin/motc-internal.php');

set_error_handler('torrent_error_handler');

$client_request = torrent_parse_announce($_SERVER['REQUEST_URI']);
if ($client_request === FALSE) {
	exit(bencode_failure('Syntax error in query'));
}

if (db_open() === FALSE) { 
	exit(bencode_failure('Internal database unavailable')); 
}

if (!torrent_infohash_registered($client_request['info_hash'])) {
	exit(bencode_failure('Infohash not found'));
}


$result = "";

torrent_record_announce($client_request);
torrent_cull_stale_sessions();

$peers = torrent_session_peers($client_request);

$message_header = array( 'complete' => torrent_stat_num_seeders($client_request['info_hash'])
                , 'incomplete' => torrent_stat_num_downloaders($client_request['info_hash'])
				, 'interval' => MOTC_ANNOUNCE_INTERVAL
				);

$result .= "d";
$result .= bencode_as_dictionary_fragment($message_header);
$result .= bencode_peer_set($result, $peers, $client_request['compact'], $client_request['no_peer_id']);
$result .= 'e';

echo $result;

?>