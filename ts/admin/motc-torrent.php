<?php
/*
 * Copyright John McAleely, 2008
 */



// We don't want PHP's usual error chat in the bencoded output. Hence swallow all warnings, notices, etc.
function torrent_error_handler($no, $string, $file, $line, $context) {

//	useful for debugging - normal chat
//	return FALSE;
	
	return TRUE;
}


function torrent_parse_announce($requesturl) {
	$raw_query = parse_url($requesturl, PHP_URL_QUERY);
	if ($raw_query === FALSE) {
		return FALSE;
	}

	if (strlen($raw_query) == 0) {
		return FALSE;
	}

	$raw_query_array = explode('&', $raw_query);

	foreach ($raw_query_array as $arg) {
		$argset = explode('=', $arg);
		$param = urldecode($argset[0]);
		$value = urldecode($argset[1]);
		if ($param == 'info_hash' && strlen($value) == MOTC_INFOHASH_LEN) {
			$request['info_hash'] = $value;
		}
		if ($param == 'peer_id' && strlen($value) == MOTC_PEERID_LEN) {
			$request['peer_id'] = $value;
		}
	}

	$request['port'] = $_GET['port'];
	$request['compact'] = $_GET['compact'];
	$request['no_peer_id'] = $_GET['no_peer_id'];
	$request['event'] = $_GET['event'];
	$request['ip'] = dq_to_int($_SERVER['REMOTE_ADDR']);

	$request['numwant'] = 50;
	if (isset($_GET['numwant']) && $_GET['numwant'] >= 0 && $_GET['numwant'] < 50) { 
		$request['numwant'] = $_GET['numwant'];
	}

	$request['left'] = $_GET['left'];
	if ($request['left'] == 0) { 
		$request['is_seed'] = TRUE;
	}
	else {
		$request['is_seed'] = FALSE;
	}

	return $request;
}


function torrent_record_announce($client_request) {
	$local_mysql_params = array( 'infohash' => $client_request['info_hash']
		   					   , 'peerid' => $client_request['peer_id']
							   , 'port' => $client_request['port']
							   , 'ip' => $client_request['ip']
							   , 'complete' => (int) $client_request['is_seed']
							   );

	if (!torrent_session_exists($client_request['peer_id'], $client_request['info_hash'])) {

		foreach ($local_mysql_params as $col => $val) {
			$val = mysql_real_escape_string($val);
			$values.='"'.$val.'", ';
			$cols.="$col, ";
		}

		$values.='"1"';
		$cols.='messagecount';

		$query = 'INSERT '.cf_torrent_session_table()." ($cols) VALUES ($values)";
	}
	else {
		$query = "UPDATE ".cf_torrent_session_table()." SET ";

		foreach ($local_mysql_params as $col => $val) {
			$val = mysql_real_escape_string($val);
			$query.=$col.'="'.$val.'", ';
		}

		$query .= "messagecount=messagecount+1 ";
		$query .= "WHERE peerid=\"".$local_mysql_params['peerid']."\" ";
		$query .= "AND infohash=\"".$local_mysql_params['infohash']."\"";
	}
	
	mysql_query($query);
	
	if ($client_request['event'] == 'completed') {
		mysql_query('UPDATE '.cf_torrent_infohash_table().' SET completions=completions+1 WHERE infohash="'.$local_mysql_params['infohash'].'"');
	}
	
}


function torrent_infohash_registered($infohash) {
	
	$queryhash = mysql_real_escape_string($infohash);
	
	return mysql_motc_rec_exists('infohash', cf_torrent_infohash_table(), "infohash=\"$queryhash\"");
}


function torrent_register_infohash($infohash) {
	if (!torrent_infohash_registered($infohash)) {
		$querystr = mysql_real_escape_string($infohash);
		mysql_query('INSERT '.cf_torrent_infohash_table()." () VALUES (\"$querystr\", 0)");		
	}
}


function torrent_unregister_infohash($infohash) {
	$querystr = mysql_real_escape_string($infohash);
	mysql_query('DELETE FROM '.cf_torrent_infohash_table()." WHERE infohash=\"$querystr\"");		
}


function torrent_session_exists($peer_id, $infohash) {
	$queryhash = mysql_real_escape_string($infohash);
	$querypeer_id = mysql_real_escape_string($peer_id);

	return mysql_motc_rec_exists('peerid', cf_torrent_session_table(), "peerid=\"$querypeer_id\" AND infohash=\"$queryhash\"");
}


function bencode_int($int) {
	return sprintf('i%de', $int);
}


function bencode_string($string) {
	$len = strlen($string);
	return sprintf('%d:%s', $len, $string);
}


function bencode_list($array) {
	$result = 'l';
    foreach ($array as $item) {
	    if (is_int($item)) { $result = $result.bencode_int($item); }
		elseif (is_string($item)) { $result = $result.bencode_string($item); }
    }

	return $result.'e';
}


function bencode_key_pair($key, $value) {
    if (is_int($value)) { return bencode_string($key).bencode_int($value); }
	elseif (is_string($value)) { return bencode_string($key).bencode_string($value); }
}


function bencode_as_dictionary_fragment($array) {
	ksort($array);
	
    foreach ($array as $name => $item) {
		$result .= bencode_key_pair($name, $item);
    }

	return $result;
}


function bencode_dictionary($array) {
	$result = 'd';

	$result .= bencode_as_dictionary_fragment($array);

	return $result.'e';
}


function bencode_failure($message) {
	$message = array('failure reason' => $message);
	return (bencode_dictionary($message));
}


function bencode_peer($ip, $port, $peerid = "") {
	$dict = array('ip' => int_to_dq($ip), 'port' => (int)$port);
	if ($peerid) { $dict['peer id'] = $peerid; }
	return bencode_dictionary($dict);
}


function hton_string($var, $bytes) {
	for ($i = $bytes-1; $i >= 0; $i--) {
		$result .= chr(($var >> $i*8) & 0xff);
	}
	return $result;
}


// output the IP and port in network byte order.
function compact_encode_peer($ip, $port) {
	$result .= hton_string($ip, 4);
	$result .= hton_string($port, 2);

	return $result;
}


function torrent_stat_sessions() {
	return mysql_query('SELECT infohash, peerid, ip, port, complete, messagecount, lastseen FROM '.cf_torrent_session_table().' ORDER BY lastseen DESC');
}


function torrent_stat_torrents() {

	return mysql_query('SELECT infohash, completions FROM '.cf_torrent_infohash_table().' ORDER BY infohash');
}


function torrent_parse_scrape($requesturl) {
	
	$info_hash = array();
	
	$raw_query = parse_url($requesturl, PHP_URL_QUERY);
	if ($raw_query === FALSE) {
		return FALSE;
	}
	
	if (strlen($raw_query) == 0) {
		return $info_hash;
	}
	
	$raw_query_array = explode('&', $raw_query);
	
	foreach ($raw_query_array as $arg) {
		$argset = explode('=', $arg);
		$param = urldecode($argset[0]);
		$value = urldecode($argset[1]);
		if ($param == 'info_hash' && strlen($value) == MOTC_INFOHASH_LEN) {
			$info_hash[] = urldecode($argset[1]);
		}
		else {
			return FALSE;
		}
	}
		
	return $info_hash;
}


// Returns a set of peers for this client.
//
// For seeders, it does not return other seeders.
// It does not return the ame peer id, so the result does not include the client
// It orders the results with the freshest session first.
function torrent_session_peers($client_request) {
	$queryhash = mysql_real_escape_string($client_request['info_hash']);
	$queryid = mysql_real_escape_string($client_request['peer_id']);
	$querynum = mysql_real_escape_string($client_request['numwant']);
	
	$need_peer_ids = !($client_request['compact'] || $client_request['no_peer_id']);
	$cols = 'ip, port';
	if ($need_peer_ids) {
		$cols .= ', peerid';
	}
	
	$where = "infohash=\"$queryhash\" AND peerid!=\"$queryid\"";
	if ($client_request['is_seed']) {
		$where.=" AND !complete";
	}
	
	$query = "SELECT $cols FROM ".cf_torrent_session_table()." WHERE $where ORDER BY lastseen DESC LIMIT $querynum";
	
	return mysql_query($query);
}


function torrent_cull_stale_sessions() {
	
	$idle_secs = MOTC_STALE_INTERVAL;
	$query = 'DELETE FROM '.cf_torrent_session_table()." WHERE lastseen < SUBTIME(NOW(), SEC_TO_TIME($idle_secs))";
	
	mysql_query($query);
}


function bencode_peer_set($result, $peers, $compact, $no_peer_id) {
	$result = bencode_string('peers');
		
	if ($compact) {
		while ($line = mysql_fetch_array($peers, MYSQL_ASSOC)) {
			$peers_compact .= compact_encode_peer($line['ip'], $line['port']);
		}
		
		$result .= bencode_string($peers_compact);
	}
	else {
		$result .= "l";
		while ($line = mysql_fetch_array($peers, MYSQL_ASSOC)) {
			if ($no_peer_id) {
				$result .= bencode_peer($line["ip"], $line["port"]);
			}
			else {
				$result .= bencode_peer($line["ip"], $line["port"], $line['peerid']);
			}
		}

		$result .= 'e';
	}
	
	return $result;
}


function torrent_stat_num_seeders($infohash) {
	$queryhash = mysql_real_escape_string($infohash);
	$result = mysql_query("SELECT COUNT(peerid) FROM ".cf_torrent_session_table()." WHERE complete=TRUE AND infohash=\"$queryhash\"");
	$seeders = mysql_fetch_array($result, MYSQL_NUM);
	return $seeders[0];
}


function torrent_stat_num_downloaders($infohash) {
	$queryhash = mysql_real_escape_string($infohash);
	$result = mysql_query("SELECT COUNT(peerid) FROM ".cf_torrent_session_table()." WHERE complete=FALSE AND infohash=\"$queryhash\"");
	$dls = mysql_fetch_array($result, MYSQL_NUM);
	return $dls[0];
}


function torrent_length($payload) {
	$io['torrentlength'] = 0;

	torrent_make($payload, $io);
	
	return $io['torrentlength'];
}


function torrent_pieces($payload, &$io) {

	torrent_piece_string_len($payload, $io);

	while (!feof($io['in'])) {
		$piece_data = fread($io['in'], $payload['piecelen']);
		$sha1 = hash('sha1', $piece_data, TRUE);

		torrent_output($io, $sha1);
	}
}


function torrent_piece_string_len($payload, &$io) {
	$peice_string_length = ceil($payload['filelength'] / $payload['piecelen']) * MOTC_SHA1_HASH_LEN;

	$data = "$peice_string_length:";
	torrent_output($io, $data);
	
	return $peice_string_length;
}


function torrent_pieces_length_only($payload, &$io) {

	$io['torrentlength'] += torrent_piece_string_len($payload, $io);
}


function torrent_write_dictionary($dictionary, $payload, &$io) {
	ksort($dictionary);

	$data = 'd';
	torrent_output($io, $data);

    foreach ($dictionary as $name => $item) {
		
		if (is_callable($item)) {
			$data = bencode_string($name);
			torrent_output($io, $data);

			$item($payload, $io);
		}
		else if (is_array($item) && $name == 'info') {
			$data = bencode_string($name);
			torrent_output($io, $data);

			$io['infohashctx'] = hash_init('sha1');
			torrent_write_dictionary($item, $payload, $io);
			$io['infohash'] = hash_final($io['infohashctx'], TRUE); 
			unset($io['infohashctx']);
		}
		else if (is_array($item)) {
			$data = bencode_string($name);
			torrent_output($io, $data);

			torrent_write_dictionary($item, $payload, $io);
		}
		else {
			$data = bencode_key_pair($name, $item);
			torrent_output($io, $data);
		}
	}

	$data = 'e';
	torrent_output($io, $data);
}


function torrent_output(&$io, $data) {
	if (isset($io['out'])) { fwrite($io['out'], $data); }
	if (isset($io['infohashctx'])) { hash_update($io['infohashctx'], $data); }
	if (isset($io['torrentlength'])) { $io['torrentlength'] += strlen($data); }
}


function torrent_make($payload, &$io) {
	
	$piecefn = isset($io['out']) ? 'torrent_pieces' : 'torrent_pieces_length_only';
	
	$keys = array( 'announce' => $payload['trackerurl']
				 , 'info' => array( 'length' => $payload['filelength']
							      , 'pieces' => $piecefn
							      , 'piece length' => $payload['piecelen']
			                      , 'name' => $payload['filename']
							      )
				 , 'url-list' => $payload['fileurl']
				 , 'created by' => MOTC_TORRENT_CREATOR
				 );

	torrent_write_dictionary($keys, $payload, $io);
}

?>