<?php
//vvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvv
//DONT RENAME THIS FILE UNDER ANY CIRCUMSTANCES!!
//the file's name is used as a process filter
//^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

define('__WATASHI__',basename(__FILE__));

chdir(dirname(__FILE__));
require('inc/lib.php');
require('inc/ini.php');

if (!posix_getuid())
		bye('this script is not supposed to be run under root privileges');

//parse cli args
$args = cmdArgs(array_slice($argv,1));
if (!($conf=arEl($args,'conf')))
		bye(usage(),0);

//which mode is this: mirror or restore
$restore = 0;
if (array_key_exists('restore',$args)){
	$restore = (int)$args['restore'];
	if ((string)$restore !== $args['restore'] || $restore<0)
			bye('--restore value must be a number >= 0');
	$restore++;

//	unset dangerous rsync options so nothing is gonna be spoiled on sync
	unset($rsync_opt['--delete']);
	unset($rsync_opt['--force']);
}

define('DRY_RUN',array_key_exists('dry-run',$args));
//apply --dry-run switch
if (DRY_RUN) $rsync_opt['--dry-run'] = null;

//limit bandwidth in Mbps
if (($bwlimit=(int)arEl($args,'bwlimit'))){
	if ($bwlimit<0)
			bye('"bwlimit" if set must be >= 0');

	$rsync_opt['--bwlimit'] = $bwlimit*1000;//mbps to kbps
}

//decode conf
if (!is_file($conf) || !is_readable($conf))
		bye('cant read conf file');

//strip comments for json to be valid
$json = preg_replace('~^\s*//.+~m','',file_get_contents($conf));

$setup = json_decode($json,true);
if (!is_array($setup))
		bye('bad/not json data. config must be an array');

//check if mailto and --mail is set so all subsequent errors will echo to mail
if (array_key_exists('mail',$args)){
	if (!($mailto=arEl($setup,'mailto')))
			bye('--mail is given but "mailto" is not set in conf');

//	being junk for mailto is not fatal. just pass it as is
	define('BYE_MAILTO',$mailto);
}

//basic conf checks
if (!($host=arEl($setup,'host')))
		bye('"host" is not set or empty');

$host = strtolower($host);
//only do weak checks over the host. let rsync do the rest
if (!preg_match('~^(([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}|(\d+\.){3}\d+)$~',$host))
		bye('"host" doesnt look like a valid hostname');

if (!($user=arEl($setup,'user')))
		bye('"user" is not set or empty');

if ($user == 'root')
	bye('this script is not supposed to be run under root privileges on remote host');

if (($sshkey=arEl($setup,'sshkey')) && (!is_file($sshkey) || !is_readable($sshkey)))
		bye('"sshkey" if set must be a readable file');

$rotate = (int)arEl($setup,'backup_rotate');
if ($rotate<0)
		bye('"backup_rotate" if set must be >= 0');

//process mapping in conf
if (!map::init($setup)) bye(map::$error);
//print_r(conf::map());

//lock the task to the current process. host = task key.
if (($e=mytask('/tmp/mediasync-'.md5($host).'pid',$host))) bye($e);

//set ssh credentials
$ssh_opt['-l'] = $user;
if ($sshkey) $ssh_opt['-i'] = $sshkey;
$rsync_opt['--rsh'] = $ssh_line = 'ssh '.mergeArgs($ssh_opt);

//sync each destination in the map.
//backups rotation and sync is done is a single rsync call (backup rotation
//utilizes --rsync_path option)
$tmp = tempnam('/tmp','');
foreach (map::get() as $s=>$d){
	if ($restore){
//		exchange source and destination
		$t = $s;
		$s = $d;
		$d = $t;

//		selected versioned backup
		$s .= '/'.($restore-1).'/';

//		full source
		$s = "{$host}:{$s}";
	}else{
//		ensure the target directory exists as we gonna use a subdir
		$init_line = 'mkdir -p '.escapeshellarg($d);

//		rotate backups on the host
		if ($rotate && !DRY_RUN){
//			line break on the first line is required
			$init_line .= <<<EOL

&& {
	cd '{$d}' && {
		rm -rf {$rotate};
		for ((i={$rotate};i>0;i--)); do
			[[ -d \$((i-1)) ]] && mv \$((i-1)) \$i;
		done
	}
}
EOL;
		}

//		here path to rsync is a remote one
		$init_line .= ' ; /usr/bin/rsync';

//		make it s single line command
		$init_line = preg_replace('~^\s+~m','',$init_line);
		$init_line = preg_replace('~[\r\n]+~',' ',$init_line);

		$rsync_opt['--rsync-path'] = $init_line;

//		"0" is the most recent backup
		$d .= '/0';

//		the most recent backup gonna be an incremental one based on "1".
//		relative pathes are based on the destination
		if ($rotate && !DRY_RUN) $rsync_opt['--link-dest'] = "../1";

//		full destination
		$d = "{$host}:{$d}";
	}

	out('source directory: '.$s);
	out('target directory: '.$d);
	out('--------- rsync output below -------------');

//	here path to rsync is a local one
	$line = sprintf('/usr/bin/rsync %s %s/ %s 2>&1 | tee %s',
			mergeArgs($rsync_opt),escapeshellarg($s),escapeshellarg($d),$tmp);

//	the call is wrapped into bash to be sure we can use $PIPESTATUS
	passthru('/bin/bash -c '.escapeshellarg($line.';'.'exit ${PIPESTATUS[0]}'),$r);

	if ($r){
		out('==============================');
		out("rsync exit code: {$r}");

//		this only goes to mail
		bye(
			"rsync exit code: {$r}\n".
			"last ".RSYNC_ERROR_REPORT_LINES." lines of output:\n\n".
			implode('',array_slice(file($tmp),-RSYNC_ERROR_REPORT_LINES)),
		1,true);
	}
}
