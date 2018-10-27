<?php

//number of last lines of rsync output to include in error report
define('RSYNC_ERROR_REPORT_LINES', 20);

//ssh connection options
$ssh_opt = array(
//	turn off pseudo-tty. we dont need it
	'-T' => null,
//
//	arcfour cipher is no longer availible since ubuntu 18.04.
	'-c' => 'aes128-ctr',
//
	'-o' => array(
//		compressing media files is futile
		'Compression=no',
//		disable password prompt as we can only login with keys
		'BatchMode=yes',
	),
);

//rsync options
$rsync_opt = array(
	'--verbose' => null,
//	'--dry-run' => null,
	'--human-readable' => null,
//
//	show progress info
	'--progress' => null,
//
//	recurse into directories
	'--recursive' => null,
//
//	copy symlinks as symlinks
	'--links' => null,
//
//	 update destination files in-place
	'--inplace' => null,
//
//	keep partially transferred files.
//	it is important for media (=big) files
	'--partial' => null,
//
//	preserve permissions and modification time.
//	it is important to have both options enabled for incremental backups
//	to work properly (i.e. rsync option --link-dest)
	'--perms' => null,
	'--times' => null,
//
//	turn off compression completely.
//	we dont need it on media files
	'--compress-level' => 0,
//
//	I/O timeout in seconds.
//	default timeout is undefined (=unlimited)
	'--timeout' => 60,
);
