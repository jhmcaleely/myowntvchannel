<?php
/*
 * scrape.php
 *
 * Provide a machine readable version of our stats
 *
 * Copyright John McAleely 2008
 */

require('admin/motc-internal.php');

set_error_handler('torrent_error_handler');

$filter_info_hashes = torrent_parse_scrape($_SERVER['REQUEST_URI']);
if ($filter_info_hashes === FALSE) {
	exit(bencode_failure('Syntax error in query'));
}
$filtering = (bool) (count($filter_info_hashes) > 0);

if (db_open() === FALSE) { 
	exit(bencode_failure('Internal database unavailable')); 
}

$infohashes = torrent_stat_torrents();

$result .= 'd';
$result .= bencode_string('files');

while ($line = get_next_record_array($infohashes)) {
	
	if (   !$filtering
		|| ($filtering && (array_search($line['infohash'], $filter_info_hashes) !== FALSE))
		) {
		$message_data = array( 'downloaded' => (int) $line['completions']
							 , 'complete' => (int) torrent_stat_num_seeders($line['infohash'])
							 , 'incomplete' => (int) torrent_stat_num_downloaders($line['infohash'])
							 );

		$result .= 'd';
		$result .= bencode_string($line['infohash']);
		$result .= bencode_dictionary($message_data);
		$result .= 'e';			
	}		
}

$result .= 'e';

echo $result;
?>