<?php

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log');
error_reporting(E_ALL);

function __autoload( $className){
	require("./inc/class/{$className}.php");
}

function out( $s){
	echo $s."\n";
	flush();
}
function bye( $s='', $c=1, $silent=false){
	if (!$silent) out($s);
	if (defined('BYE_MAILTO')) @mail(BYE_MAILTO,__WATASHI__.' error',$s);
	exit($c);
}

function arEl( &$a, $k, $v=null){
	if (array_key_exists($k,$a)){
		$r = $a[$k];
		if (!$r && !is_null($v)) $r = $v;
	} else $r = $v;

	return $r;
}
function rtrimDir( $s){
	return rtrim($s,'/');
}
function isAbsolutePath( $s){
	return substr($s,0,1) == '/';
}
function cmdArgs( $a){
	$args = array();
	foreach ( $a as $v){
		if (!preg_match('~^--([a-z]+(?:-[a-z]+)*)(?:=(.*))?~',$v,$r)) continue;//skip junk
		$args[$r[1]] = arEl($r,2);
	}

	return $args;
}
function mergeArgs( $o){
	$a = array();
	foreach ($o as $k=>$v){
//		unify the input
		if (!is_array($v)) $v = array($v);

		foreach ($v as $w){
			$t = $k;

			if (!is_null($w)){
				$t .= (substr($k,0,2) == '--' ? '=' : ' ').escapeshellarg($w);
			}

			$a[] = $t;
		}
	}
	return implode(' ',$a);
}
function usage(){
	return sprintf(
'usage: php %s options
options:
    %-16s    %s
    %-16s    %s
    %-16s    %s
    %-16s    %s
    %-16s    %s',
		__WATASHI__,
		'--conf=json-file','task config',
		'--mail','report errors to the mail provided in the config',
		'--restore=N','reverse the sync process restoring sources from backup version N (0 is the most recent one)',
		'--dry-run','this passes the --dry-run option to rsync',
		'--bwlimit=X','limit bandwith in mbps. 0 = no limit (default)'
	);
}
function mytask( $pidfile, $extra=''){
	if ($extra) $extra = "\n{$extra}";
	$pid = getmypid();

	$c = 0;
	while (1){
		if (++$c>8) return 'cant lock pid file';

		if (!file_exists($pidfile)){
//			$extra is supposed to give a hint about what is this pid file about
			if (file_put_contents($pidfile,$pid.$extra) === false) return 'cant write to pid file';
			sleep(1);
			continue;
		}

		if (($pid2=file_get_contents($pidfile)) === false) return 'cant read from pid file';

		$pid2 = (int)$pid2;

		if ($pid2 != $pid){
//			particual pid might be given to another process on kernel hitting the pid limit
//			so we filter out the output using the tool's filename
			exec("ps -p{$pid2} -o command= | grep -q ".__WATASHI__,$o,$ret);

			if ($ret){
//				it looks like the previous worker on this task has died
				if (unlink($pidfile) === false) return 'cant unlink pid file';
				continue;
			}

			return <<<EOL
this task is already taken (pid {$pid2})
use 'ps -p{$pid2} -o command=' to see the process's command line
use 'kill {$pid2}' to kill the worker
use 'kill -9 {$pid2}' if it doesnt help
EOL;
		}

		break;
	}

	return '';
}
