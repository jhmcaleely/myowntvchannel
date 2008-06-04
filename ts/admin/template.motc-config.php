<?php

/*
 * Copyright John McAleely, 2008
 */

/*
 * MyOwnTVChannel Configuration Setup
 */

// the following settings must be edited to the correct values for your installation.

define('MOTC_DB_SERVER', 'mydbserver');		// the dns name or IP address of your MySQL database server
define('MOTC_DB_NAME', 'dbname');		// the name of the database to store MyOwnTVChannel data in
define('MOTC_DB_USER', 'dbuser');				// The MySQL username. Must have create/drop table permission and read-write permission
define('MOTC_DB_PASSWORD', 'dbuserpassword');	// the password for MOTC_DB_USER

// You can stop editing here for a basic setup.

// The following values have sensible defaults, but you can change them if you wish.

// These control the appearance of MyOwnTVChannel URLs
define('MOTC_HIDE_INDEX', FALSE);	// TRUE or FALSE. If TRUE, the index.php will be hidden from the URL (like index.html)
define('MOTC_HIDE_INTERNAL_EXT', FALSE);	// TRUE or FALSE. If TRUE the .php will be removed from URLs.
define('MOTC_REWRITE_TORRENTS', FALSE); // TRUE or FALSE. If TRUE, you can add .torrent to any channel file to get its torrent

// Default permissions for the files and directories MyOwnTVChannel creates.
define('MOTC_MODE_DIR', 0777);
define('MOTC_MODE_FILE', 0666);


define('MOTC_DB_TABLE_PREFIX', 'motc1');	// adjust this prefix to have multiple installs in one database. 
											// numbers and letters only, no spaces or other punctuation.

?>