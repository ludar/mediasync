<?php

class map{
	private static $data;
	public static $error;

//	function get
	private static function e( $s){
		self::$error = $s;
		return false;
	}
	public static function init( $conf){
		self::e('');
		self::$data = $conf;

		return (($e=self::expand())) ? self::e($e) : true;
	}

	public static function get(){
		return arEl(self::$data,'map',array());
	}

//	returns error message
//	it only checks if sources are dirs. let rsync check access itself
	private static function expand(){
		$map = arEl(self::$data,'map',array());
		if (!is_array($map)) return 'map must be an array';
		if (!sizeof($map)) return 'map is not set or empty in conf';

//		check source_base
		$sBase = self::$data['source_base'] = rtrimDir(arEl(self::$data,'source_base',''));
		if ($sBase){
			if (!is_dir($sBase)) return 'source_base if set must be a directory';
			if (!isAbsolutePath($sBase)) return 'source_base if set must be an absolute path';
		}

//		check destination_base
		$dBase = self::$data['destination_base'] = rtrimDir(arEl(self::$data,'destination_base',''));
		if ($dBase && !isAbsolutePath($dBase)) return 'destination_base if set must be an absolute path';

		$map2 = array();
		foreach ($map as $s=>$d){
			$s = rtrimDir($s);
//			skip empty sources
			if (!$s) continue;

			$d = rtrimDir($d);
//			clone source to destination under the same path if destination is not set
			if (!$d){
				if (!$dBase || !$sBase || isAbsolutePath($s))
						return 'empty destinations are only allowed when source_base and destination_base are both set and '.
						'the corresponding source is a relative path';
				$d = $s;
			}

//			check source
			if (!isAbsolutePath($s)){//source_base relative path
				if (!$sBase) return 'either set source_base or use absolute source pathes';
				$s = $sBase.'/'.$s;
			}

			if (!is_dir($s)) return "'{$s}' is not a directory";

//			check destination
			if (!isAbsolutePath($d)){//destination_base relative path
				if (!$dBase) return 'either set destination_base or use absolute destination pathes';
				$d = $dBase.'/'.$d;
			}

			if ($dBase && $d == $dBase) return "destination for '{$s}' is the same as destination_base";

//			this pair has passed all the tests
			$map2[$s] = $d;
		}

		if (!sizeof($map2)) return 'the map is empty';

//		check sources' inodes for dups
		$dups = array();
		foreach ($map2 as $s=>$d){
			if (($inode=fileinode($s))===false) return "cant get inode for '{$s}'";

			@$dups[$inode][] = $s;

			if (sizeof($dups[$inode])>1)
					return "at least one source if listed more than once:\n".
							implode("\n",$dups[$inode]);
		}

//		check destinations for dups
		$dups = array_flip(array_count_values($map2));
		unset($dups[1]);

		if (sizeof($dups)) return "at least one destination is referred more than once: ".reset($dups);

		self::$data['map'] = $map2;

		return '';
	}
}
