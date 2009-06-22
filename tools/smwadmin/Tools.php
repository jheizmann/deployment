<?php
class Tools {

	/**
	 * Checks if script runs on a Windows machine or not.
	 *
	 * @return boolean
	 */
	public static function isWindows() {
		static $thisBoxRunsWindows;

		if (! is_null($thisBoxRunsWindows)) return $thisBoxRunsWindows;

		ob_start();
		phpinfo();
		$info = ob_get_contents();
		ob_end_clean();
		//Get Systemstring
		preg_match('!\nSystem(.*?)\n!is',strip_tags($info),$ma);
		//Check if it consists 'windows' as string
		preg_match('/[Ww]indows/',$ma[1],$os);
		if($os[0]=='' && $os[0]==null ) {
			$thisBoxRunsWindows= false;
		} else {
			$thisBoxRunsWindows = true;
		}
		return $thisBoxRunsWindows;
	}
    
	/**
	 * Creates the given directory.
	 *
	 * @param string $path
	 * @return unknown
	 */
	public static function mkpath($path) {
		if(mkdir($path) || file_exists($path)) return true;
		return (mkpath(dirname($path)) && mkdir($path));
	}
}
?>