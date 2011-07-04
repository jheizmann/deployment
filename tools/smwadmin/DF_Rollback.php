<?php

/*  Copyright 2009, ontoprise GmbH
 *
 *   The deployment tool is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 3 of the License, or
 *   (at your option) any later version.
 *
 *   The deployment tool is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * @file
 * @ingroup DFInstaller
 *
 * Restore tool. Creates and restores wiki installations (aka 'restore points).
 * Can handle several restore points.
 *
 * @author: Kai K�hn / ontoprise / 2009
 *
 */
class Rollback {

	// installation directory of Mediawiki
	var $rootDir;

	// temporary directory where rollback data is stored.
	var $tmpDir;


	static $instance;

	public static function getInstance($rootDir) {
		if (is_null(self::$instance)) {
			self::$instance = new Rollback($rootDir);

		}
		return self::$instance;
	}

	private function __construct($rootDir) {

		$this->rootDir = $rootDir;
		if (array_key_exists('df_homedir', DF_Config::$settings)) {
			$homeDir = DF_Config::$settings['df_homedir'];
		} else {
			$homeDir = Tools::getHomeDir();
			if (is_null($homeDir)) throw new DF_SettingError(DEPLOY_FRAMEWORK_NO_HOME_DIR, "No homedir found. Please configure one in settings.php");
		}
		$wikiname = DF_Config::$df_wikiName;
		$this->tmpDir = "$homeDir/$wikiname/df_restore";

	}

	/**
	 * Returns the absolute paths of all restore points.
	 *
	 * @return array of string
	 */
	public function getAllRestorePoints() {
		if (!file_exists($this->tmpDir."/")) return array();
		$dirs = Tools::get_all_dirs($this->tmpDir."/");
		return $dirs;
	}

	/**
	 * Copy complete code base of installation including LocalSettings.php
	 * (but excluding deployment folder)
	 *
	 * @param boolean $name Do not ask the user for confirmation, use this name
	 *
	 * @boolean True if no error occured
	 */
	public function saveInstallation($name = NULL) {
		global $dfgOut;
		// make sure to save only once
		static $savedInstallation = false;
		if ($savedInstallation) return true;

		if (is_null($name)) {
			if (!$this->acquireNewRestorePoint($name)) return true;
		}

		$logger = Logger::getInstance();
		$logger->info("Save installation to ".$this->tmpDir."/$name");
		$dfgOut->outputln("[Save installation...");
		$success = Tools::mkpath($this->tmpDir."/$name");
		$success = $success && Tools::copy_dir($this->rootDir, $this->tmpDir."/$name", array($this->rootDir."/deployment"));
		$dfgOut->output("done.]");
		$savedInstallation = true;
		if (!$success) {
			$logger->error("Could not copy the MW installation.");
		}
		return $success;
	}

	/**
	 * Save the database to the rollback directory
	 *
	 * @param boolean $name Do not ask the user for confirmation, use this name
	 *
	 * @return boolean True if no error occured on creating a database dump
	 */
	public function saveDatabase($name = NULL) {

		global $mwrootDir, $dfgOut;
		if (file_exists("$mwrootDir/AdminSettings.php")) {
			require_once "$mwrootDir/AdminSettings.php";
		} else {
			// possible since MW 1.16
			$wgDBadminuser = $this->getVariableValue("LocalSettings.php", "wgDBadminuser");
			$wgDBadminpassword = $this->getVariableValue("LocalSettings.php", "wgDBadminpassword");
			
			if (empty($wgDBadminuser) || empty($wgDBadminpassword)) {
				$dfgOut->outputln('$wgDBadminuser and $wgDBadminpassword is empty! Please set.', DF_PRINTSTREAM_TYPE_WARN);
			}
		}

		if (is_null($name)) {
			if (!$this->acquireNewRestorePoint($name)) return true;
		}
		// make sure to save only once
		static $savedDataBase = false;
		if ($savedDataBase) return true;

		$logger = Logger::getInstance();
		$logger->info("Save database to ".$this->tmpDir."/$name/dump.sql");

		$wgDBname = $this->getVariableValue("LocalSettings.php", "wgDBname");
		$dfgOut->outputln("[Saving database...");
		
		$mysqlDump = "mysqldump";
		if (array_key_exists('df_mysql_dir', DF_Config::$settings) && !empty(DF_Config::$settings['df_mysql_dir'])) {
			$mysqlDump = DF_Config::$settings['df_mysql_dir']."/bin/mysqldump";
		}
		
		$logger->info("\n\"$mysqlDump\" -u $wgDBadminuser --password=$wgDBadminpassword $wgDBname > ".$this->tmpDir."/$name/dump.sql");
		exec("\"$mysqlDump\" -u $wgDBadminuser --password=$wgDBadminpassword $wgDBname > \"".$this->tmpDir."/$name/dump.sql\"", $out, $ret);
		$dfgOut->output("done.]");
		$savedDataBase = true;

		if ($ret != 0) {
			$dfgOut->outputln("Could not run myqsqldump. Skip that. Please set 'df_mysql_dir'. See log for details.");
			$logger->error("Could not save the database.");
		}
		return $ret == 0;
	}

	/**
	 * Rolls back from the latest rollback point.
	 *
	 * @param string Name of restore point.
	 * @return bool true on success.
	 */
	public function restore($name) {
		if (!file_exists($this->tmpDir."/$name")) return false;
		$this->restoreInstallation($name);
		$this->restoreDatabase($name);
		return true;
	}



	/**
	 * Acquires a new restore point. The user has to enter a name and to confirm to
	 * overwrite an exisiting restore point. Can be called several
	 * times but will always return the result of the first call (holds
	 * also for all out parameters. All subsequent calls will have no
	 * further effects.
	 *
	 * Note: REQUIRES user interaction
	 *
	 * @param string (out) name of the restore point
	 * @return boolean True if a restore point should be created/overwritten.
	 */
	protected function acquireNewRestorePoint(& $name) {
		global $dfgNoAsk;
		if (isset($dfgNoAsk) && $dfgNoAsk == true) {
			return false;
		}

		global $dfgOut;
		static $calledOnce = false;
		static $answer;
		static $namedStored;
		$name = $namedStored;

		if ($calledOnce) return $answer;
		$calledOnce = true;

		$dfgOut->outputln("Create new restore point (y/n)? ");
		$line = trim(fgets(STDIN));
		if (strtolower($line) == 'n') {
			$dfgOut->outputln("\nDo not create a restore point.\n\n");
			$answer = false;
			return $answer;
		}

		$namedStored = $this->getRestorePointName();
		$name = $namedStored;

		// clear if it already exists
		if (file_exists($this->tmpDir."/".$name)) {
			Tools::remove_dir($this->tmpDir."/".$name);
		}
		Tools::mkpath($this->tmpDir."/".$name);
		$answer = true;
		return $answer;

	}

	/**
	 * Asks for the name of a restore point.
	 * If it exists it asks for permission to overwrite.
	 *
	 * Note: REQUIRES user interaction
	 *
	 * @return string Name of restore point directory.
	 */
	protected function getRestorePointName() {
		global $dfgOut;
		$done = false;
		do {
			$dfgOut->outputln("Please enter a name for the restore point: ");
			$name = trim(fgets(STDIN));
			$name = str_replace(" ","_", $name);

			if (preg_match('/\w+/', $name, $matches) === false) continue;
			if ($name !== $matches[0]) {
				$dfgOut->outputln("Forbidden characters. Please use only alphanumeric chars and spaces");
				continue;
			}

			// clear if it already exists
			if (file_exists($this->tmpDir.$name)) {
				$dfgOut->outputln("A restore point with this name already exists. Overwrite? (y/n) ");
				$line = trim(fgets(STDIN));
				if (strtolower($line) == 'n') {
					continue;
				}
			}
			$done = true;
		} while(!$done);
		return $name;
	}



	/**
	 * Restores complete code base of installation including LocalSettings.php
	 *
	 * @param string Name of restore point.
	 */
	private function restoreInstallation($name) {
		global $dfgOut;
		$logger = Logger::getInstance();

		$logger->info("Remove current installation");
		$dfgOut->outputln("[Remove current installation...");
		Tools::remove_dir($this->rootDir, array(Tools::normalizePath($this->rootDir."/deployment")));
		$dfgOut->output("done.]");

		$logger->info("Restore old installation");
		$dfgOut->outputln("[Restore old installation...");
		$success = Tools::copy_dir($this->tmpDir."/$name", $this->rootDir);
		if (!$success) {
			$logger->error("Restore old installation faild. Could not copy from ".$this->tmpDir."/$name");
		}
		$dfgOut->output("done.]");
	}

	/**
	 * Restore the database dump from the rollback directory.
	 *
	 * @param string Name of restore point.
	 * @return boolean
	 */
	private function restoreDatabase($name) {
		global $mwrootDir, $dfgOut;
		if (file_exists("$mwrootDir/AdminSettings.php")) {
			require_once "$mwrootDir/AdminSettings.php";
		} else {
			// possible since MW 1.16
			$wgDBadminuser = $this->getVariableValue("LocalSettings.php", "wgDBadminuser");
			$wgDBadminpassword = $this->getVariableValue("LocalSettings.php", "wgDBadminpassword");
		}
		$wgDBname = $this->getVariableValue("LocalSettings.php", "wgDBname");
		if (!file_exists($this->tmpDir."/$name/dump.sql")) return false; // nothing to restore

		global $dfgNoAsk;
		if (isset($dfgNoAsk) && $dfgNoAsk == true) {
			// default answer is yes, restore.
		} else {
			if (!DFUserInput::consoleConfirm("Restore database? (y/n) ")) return false;
		}
		$dfgOut->outputln("[Restore database...");
		$logger = Logger::getInstance();
		$logger->info("Restore database");
	    $mysqlExec = "mysql";
        if (array_key_exists('df_mysql_dir', DF_Config::$settings) && !empty(DF_Config::$settings['df_mysql_dir'])) {
            $mysqlExec = DF_Config::$settings['df_mysql_dir']."/bin/mysql";
        }
        $logger->info("\"$mysqlExec\" -u $wgDBadminuser --password=$wgDBadminpassword --database=$wgDBname < \"".$this->tmpDir."/$name/dump.sql\"");
		exec("\"$mysqlExec\" -u $wgDBadminuser --password=$wgDBadminpassword --database=$wgDBname < \"".$this->tmpDir."/$name/dump.sql\"", $out, $ret);
		if ($ret != 0){
			$logger->error("Could not restore database.");
			$dfgOut->outputln("Could not restore database. See log for details.", DF_PRINTSTREAM_TYPE_ERROR);
		}  else $dfgOut->output("done.]");
		return ($ret == 0);
	}

	/**
	 *
	 * Reads variables value.
	 *
	 * @param $file File path (relative to MW directory)
	 * @param $varname Variable name
	 */
	private function getVariableValue($file,$varname) {
		global $mwrootDir;
		$ls_content = file_get_contents("$mwrootDir/$file");
		preg_match('/\$'.$varname.'\s*=\s*["\']([^"\']+)["\']/', $ls_content, $matches);
		return array_key_exists(1, $matches) ? $matches[1] : '';
	}




}
