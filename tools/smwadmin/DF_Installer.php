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

// Error constants
define('DEPLOY_FRAMEWORK_INSTALL_LOWER_VERSION', 1);
define('DEPLOY_FRAMEWORK_NO_TMP_DIR', 2);
define('DEPLOY_FRAMEWORK_COULD_NOT_FIND_UPDATE', 3);
define('DEPLOY_FRAMEWORK_PACKAGE_NOT_EXISTS', 4);
define('DEPLOY_FRAMEWORK_DEPENDENCY_EXIST',5);
define('DEPLOY_FRAMEWORK_CODE_CHANGED',6);
define('DEPLOY_FRAMEWORK_MISSING_FILE', 7);
define('DEPLOY_FRAMEWORK_ALREADY_INSTALLED', 8);
define('DEPLOY_FRAMEWORK_NOT_INSTALLED', 9);
define('DEPLOY_FRAMEWORK_PRECEDING_CYCLE', 10);

require_once 'DF_PackageRepository.php';
require_once 'DF_Tools.php';
require_once 'DF_Rollback.php';
require_once 'DF_ResourceInstaller.php';

/**
 * Provides the basic installation routines for the smwadmin tool.
 *
 * @author: Kai K�hn / ontoprise / 2009
 *
 */

class Installer {

	static $instance = NULL; // singleton

	public function getInstance($rootDir = NULL, $force = false, $noAsk = false, $noRollback = false) {
		if (!is_null(self::$instance)) return self::$instance;
		self::$instance = new Installer($rootDir, $force, $noAsk, $noRollback);
		return self::$instance;
	}
	/*
	 * Temporary folder for storing downloaded files
	 */
	var $tmpFolder;

	/*
	 * Mediawiki installation directory
	 */
	var $rootDir;

	/*
	 * Installation directory
	 * Normally identical with $rootDir except for testing or dry runs.
	 */
	private $instDir;

	// force installation even on warnings
	private $force;

	// no questions (for testing)
	private $noAsk;

	// Helper obejcts
	private $rollback;
	private $res_installer;

	/**
	 * Creates new Installer.
	 *
	 * @param string $rootDir Explicit root dir. Only necessary for testing
	 */
	private function __construct($rootDir = NULL, $force = false, $noAsk = false, $noRollback = false) {
		// create temp folder
		$this->tmpFolder = Tools::isWindows() ? 'c:\temp\mw_deploy_tool' : '/tmp/mw_deploy_tool';
		if (!file_exists($this->tmpFolder)) Tools::mkpath($this->tmpFolder);
		if (!file_exists($this->tmpFolder)) {
			throw new InstallationError(DEPLOY_FRAMEWORK_NO_TMP_DIR, "Could not create temporary directory. Not Logged in as root?");
		}

		// get root dir
		$this->rootDir = $rootDir === NULL ? realpath(dirname(__FILE__)."/../../../") : $rootDir;
		$this->instDir = $rootDir; // normally rootDir == instDir


		$this->rollback = Rollback::getInstance($this->instDir);
		$this->res_installer = ResourceInstaller::getInstance($this->instDir);

		$this->force = $force;
		$this->noAsk = $noAsk;
		$this->noRollback = $noRollback;
	}

	public function setInstDir($instDir) {
		$this->instDir = $instDir;
	}

	/**
	 * Installs or updates a package.
	 *
	 * @param string $packageID
	 * @param int $version If omitted (or NULL), the latest version is installed.
	 */
	public function installOrUpdate($packageID, $version = NULL) {

		list($new_package, $old_package, $extensions_to_update) = $this->checkDependencies($packageID, $version);

		// Install/update all dependant and super extensions
		print "\nInstall dependant extensions and update super extensions if necessary";
		$extensions_to_update[] = array($new_package, $new_package->getVersion(), $new_package->getVersion());
		$this->installOrUpdatePackages($extensions_to_update);

		if (!$this->noRollback) $this->rollback->saveRollbackLog();
			
	}

	/**
	 * De-Installs extension
	 *
	 * @param string $packageID
	 */
	public function deInstall($packageID) {

		print "\nChecking for package $packageID...";
		$localPackages = PackageRepository::getLocalPackages($this->rootDir.'/extensions');
		$ext = NULL;
		foreach($localPackages as $p) {
			if ($p->getID() == $packageID) {
				$ext = $p;
				break;
			}
		}
		if (is_null($ext)) {
			throw new InstallationError(DEPLOY_FRAMEWORK_PACKAGE_NOT_EXISTS, "Package does not exist", $packageID);
		}

		// check if there are depending extensions
		print "\nChecking for dependant packages of $packageID...";
		$existDependency = false;
		$dependantPackages = array();
		foreach($localPackages as $p) {
			$dependencies = $p->getDependencies();

			foreach($dependencies as $dep) {
				list($id, $from, $to) = $dep;
				if ($id == $packageID) {
					$dependantPackages[] = $p->getID();
					$existDependency = true;
				}
			}
		}

		if ($existDependency) {
			throw new InstallationError(DEPLOY_FRAMEWORK_DEPENDENCY_EXIST, "Can not remove package. Dependency from the following packages exists:", $dependantPackages);
		}

		// remove ontology
		print "\nDe-install ontologies...";
		$this->res_installer->deinstallWikidump($ext);

		print "\nDelete resources...";
		$this->res_installer->deleteResources($ext);

		// undo all config changes
		// - from LocalSettings.php
		// - from database (setup scripts)
		// - patches
		print "\nUnapply configurations of $packageID...";
		$ext->unapplyConfigurations($this->instDir, false);

		// remove extension code
		print "\nRemove code of $packageID...";
		Tools::remove_dir($this->instDir."/".$ext->getInstallationDirectory());

	}

	/**
	 * Updates all packages if possible
	 *
	 * @param boolean $onlyDependencyCheck
	 * @return true, if anything was updated.
	 */
	public function updateAll($onlyDependencyCheck = false) {

		$localPackages = PackageRepository::getLocalPackages($this->rootDir.'/extensions');

		// get top level extensions, ie. those which have no super extensions.
		$topLevelExtensions = array();
		foreach($localPackages as $l1) {
			$hasDependency = false;
			foreach($localPackages as $l2) {
				$hasDependency |= ($l2->getDependency($l1->getID()));
				if ($hasDependency) break;
			}
			if (!$hasDependency) {
				$topLevelExtensions[] = $l1;
			}
		}

		$updatesNeeded = array();
		foreach($topLevelExtensions as $tl_ext) {
			$dd = PackageRepository::getLatestDeployDescriptor($tl_ext->getID());
			if ($dd->getVersion() > $localPackages[$dd->getID()]->getVersion()) {
				$this->checkForDependingExtensions($dd, $updatesNeeded, $localPackages);
				$updatesNeeded[] = array($dd, $dd->getVersion(), $dd->getVersion());
			}
		}

		$this->calculateVersionRange($updatesNeeded, $extensions_to_update);

		if ($onlyDependencyCheck) {
			return array($extensions_to_update, false);
		} else {
			$this->installOrUpdatePackages($extensions_to_update);
			if (!$this->noRollback) $this->rollback->saveRollbackLog();
			return array($extensions_to_update, true);

		}
	}



	public function listDependencies($packageID, $version = NULL) {
		list($new_package, $old_package, $extensions_to_update) = $this->checkDependencies($packageID, $version);

		print "\n\nThe following extensions need to get updated: ";
		foreach($extensions_to_update as $id => $etu) {
			list($desc, $min, $max) = $etu;
			if (!array_key_exists($id, $localPackages)) continue;
			print "\n\t$id-".Tools::addVersionSeparators($min);
		}
		if (!array_key_exists($new_package->getID(), $localPackages)) print "\n".$new_package->getID()."-".Tools::addVersionSeparators($new_package->getVersion());
		print "\n\n";

		print "\n\nThe following extensions would get installed new: ";
		foreach($extensions_to_update as $id => $etu) {
			list($desc, $min, $max) = $etu;
			if (array_key_exists($id, $localPackages)) continue;
			print "\n\t$id-".Tools::addVersionSeparators($min);
		}
		if (array_key_exists($new_package->getID(), $localPackages)) print "\n".$new_package->getID()."-".Tools::addVersionSeparators($new_package->getVersion());
		print "\n\n";
	}


	/**
	 * List all available packages and show if it is installed and in wich version.
	 *
	 *
	 */
	public function listAvailablePackages($showDescription, $pattern = NULL) {

		$allPackages = PackageRepository::getAllPackages();
		$localPackages = PackageRepository::getLocalPackages($this->rootDir.'/extensions');
		if (count($allPackages) == 0) {
			print "\n\nNo packages available!\n";
			return;
		}
		print "\n Installed       | Package            | Available versions";
		print "\n----------------------------------------------------------\n";
		foreach($allPackages as $p_id => $versions) {
			if (!is_null($pattern) && !empty($pattern)) { // filter packages
				if (substr(trim($pattern),0,1) == '*') {
					$cleanPattern = str_replace("*", "", $pattern);
					if (strpos($p_id, strtolower($cleanPattern)) === false) continue;
				} else {
					$cleanPattern = str_replace("*", "", $pattern);
					if (strpos($p_id, strtolower($cleanPattern)) !== 0) continue;
				}
			}
			if (array_key_exists($p_id, $localPackages)) {
				$instTag = "[installed ".Tools::addVersionSeparators($localPackages[$p_id]->getVersion())."]";

			} else {
				$instTag = str_repeat(" ", 16);
			}

			$id_shown = $p_id;
			$id_shown .= str_repeat(" ", 20-strlen($p_id) >= 0 ? 20-strlen($p_id) : 0);
			$sep_v = array();
			foreach($versions as $v) $sep_v[] = Tools::addVersionSeparators($v);
			print "\n $instTag $id_shown| (".implode(", ", $sep_v).")";
			if ($showDescription && array_key_exists($p_id, $localPackages)) print "\n ".$localPackages[$p_id]->getDescription()."\n\n";
		}
		print "\n";
	}

	/**
	 * Checks dependencies when installing the given package.
	 *
	 * @param string $packageID
	 * @param int $version
	 * @return array($new_package, $old_package, $extensions_to_update)
	 */
	public function checkDependencies($packageID, $version = NULL) {
		// 1. Check if package is installed
		print "\nCheck if package installed...";
		$localPackages = PackageRepository::getLocalPackages($this->rootDir.'/extensions');
		$old_package = NULL;
		foreach($localPackages as $p) {
			if ($p->getID() == $packageID) {
				$old_package = $p;
				print "found!";
				break;
			}
		}

		if (is_null($old_package)) {
			print "not found.";
		}


		// 2. Check code integrity of existing package
		if (!is_null($old_package)) {
			print "\nCheck code integrity...";
			$status = $old_package->validatecode($this->rootDir);
			if ($status !== true) {
				if (!$this->force) {
					throw new InstallationError(DEPLOY_FRAMEWORK_CODE_CHANGED, "Code files were modified. Use -f (force)", $status);
				} else {
					print "\nWarning: Code files contain differences. Patches may get lost.";
				}
			}

			print "done!";
		}

		// 3. Get required package descriptor
		if ($version == NULL) {
			print "\nRead latest deploy descriptor of $packageID...";
			$new_package = PackageRepository::getLatestDeployDescriptor($packageID);
		} else {
			print "\nRead deploy descriptor of $packageID-$version...";
			$new_package = PackageRepository::getDeployDescriptor($packageID, $version);
		}

		// 5. check if update is neccessary
		if (!is_null($old_package) && $old_package->getVersion() > $new_package->getVersion()) {
			throw new InstallationError(DEPLOY_FRAMEWORK_INSTALL_LOWER_VERSION, "Really install lower version? Use -f (force)", $old_package);
		}

		if (!is_null($old_package) && ($old_package->getVersion() == $new_package->getVersion()) && !$this->force) {
			throw new InstallationError(DEPLOY_FRAMEWORK_ALREADY_INSTALLED, "Already installed. Nothing to do.", $old_package);
		}

	 // 6. Check dependencies for install/update
	 // get package to install
		$updatesNeeded = array();
		print "\nCheck for updates...";
		$this->checkForDependingExtensions($new_package, $updatesNeeded, $localPackages);
		$this->checkForSuperExtensions($new_package, $updatesNeeded, $localPackages);


		// 7. calculate version which matches all depdencies of an extension.
		print "\nCalculate matching versions";
		$this->calculateVersionRange($updatesNeeded, $extensions_to_update);

		return array($new_package, $old_package, $extensions_to_update);
	}

	/**
	 * Install extensions.
	 *
	 * @param array(descriptor, minVersion, maxVersion) $extensions_to_update
	 * @param int fromVersion Update from this version
	 */
	private function installOrUpdatePackages($extensions_to_update) {
		$d = new HttpDownload();
		$localPackages = PackageRepository::getLocalPackages($this->rootDir.'/extensions');
		foreach($extensions_to_update as $arr) {
			list($desc, $min, $max) = $arr;
			$id = $desc->getID();
			// log extension for possible rollback

			// apply deploy descriptor and save local settings
			$fromVersion = array_key_exists($desc->getID(), $localPackages) ? $localPackages[$desc->getID()]->getVersion() : NULL;
			if (!is_null($fromVersion)) {
				$desc->createConfigElements($fromVersion);
			}
			if (!$this->noRollback) {
				$this->rollback->saveExtension($desc->getID());
				if (count($desc->getInstallScripts()) > 0) $this->rollback->saveDatabase();
			}


			list($url,$repo_url) = PackageRepository::getVersion($id, $min);
			$credentials = PackageRepository::getCredentials($repo_url);
			$d->downloadAsFileByURL($url, $this->tmpFolder."/$id-$min.zip", $credentials);

			// unzip
			$this->unzip($id, $min);

			if (!$this->noRollback) {
				if (count($desc->getConfigs()) > 0) $this->rollback->saveLocalSettings();
			}
			$desc->applyConfigurations($this->instDir, false, $fromVersion, $this);

			$this->res_installer->installOrUpdateWikidumps($desc, $fromVersion, $this->force ? DEPLOYWIKIREVISION_FORCE : DEPLOYWIKIREVISION_WARN);
			$this->res_installer->installOrUpdateResources($desc);

			print "\n-------\n";
		}
	}



	/**
	 * Unzips the package denoted by $id and $version
	 *
	 *  (requires 7-zip installed on Windows, unzip on Linux)
	 *
	 * @param string $id
	 * @param int $version
	 */
	private function unzip($id, $version) {

		if (Tools::isWindows()) {
			print "\n\nUncompressing:\n7z x -y -o".$this->instDir." ".$this->tmpFolder."\\".$id."-$version.zip";
			exec('7z x -y -o'.$this->instDir." ".$this->tmpFolder."\\".$id."-$version.zip");
		} else {
			print "\n\nUncompressing:\nunzip ".$this->tmpFolder."/".$id."-$version.zip -d ".$this->instDir;
			exec('unzip '.$this->tmpFolder."/".$id."-$version.zip -d ".$this->instDir);
		}
	}

	/**
	 * Calculates for any extension individually the interval of min/max version, so that it is a subset of all.
	 *
	 * @param array($dd, $min, $max) $updatesNeeded
	 * @param array(id=>array($dd, $min, $max)) $extensions_to_update
	 */
	private function calculateVersionRange($updatesNeeded, & $extensions_to_update) {
		$extensions = array();
		$extensions_to_update = array();
		foreach($updatesNeeded as $un) {
			list($un, $from, $to) = $un;
			if (!array_key_exists($un->getID(), $extensions)) {

				$extensions[$un->getID()] = array($un, $from, $to);
			} else {
				list($min, $max) = $extensions[$un->getID()];
				if ($from > $min) $min = $from;
				if ($to < $max) $max = $to;
				$extensions[$un->getID()] = array($un, $min, $max);
			}
		}
		$precedenceOrder = $this->sortPrecedence($extensions);
		foreach($precedenceOrder as $po) {
			$extensions_to_update[$po] = $extensions[$po];
		}

	}

	/**
	 * Provides a topologic sorting based on the precedence graph.
	 *
	 * @param array(ID=>array($dd,$min,$max)) $extensions_to_update
	 * @return array(ID)
	 */
	private function sortPrecedence(& $extensions_to_update) {
		$sortedPackages = array();
		$vertexes = array_keys($extensions_to_update);
		$descriptors = array_values($extensions_to_update);
		while (!empty($vertexes)) {
			$cycle = true;
			foreach($vertexes as $v) {
				$hasPreceding = false;
				foreach($descriptors as $e) {
					list($dd, $from, $to) = $e;
					if (in_array($dd->getID(), $vertexes)) {
						if ($dd->hasPreceding($v)) {
							$hasPreceding = true;
							break;
						}
					}
				}
				if (!$hasPreceding) {
					$cycle = false;
					break;
				}
			}
			if (!$hasPreceding) {
				// remove $v from $vertexes
				$vertexes = array_diff($vertexes, array($v));

			}
			$sortedPackages[] = $v;

			if ($cycle) throw new InstallationError(DEPLOY_FRAMEWORK_PRECEDING_CYCLE, "Cycle in the preceding graph.");
		}
		return array_reverse($sortedPackages);
	}





	/**
	 * Checks for updates on depending extensions if the package described by $dd would be installed.
	 * Goes recursively down the dependency tree.
	 *
	 * @param DeployDescriptor $dd
	 * @param array $packagesToUpdate
	 * @param array of DeployDescriptor $localPackages
	 */
	private function checkForDependingExtensions($dd, & $packagesToUpdate, $localPackages) {
		$dependencies = $dd->getDependencies();
		$updatesNeeded = array();

		// find packages which need to get updated
		// or installed.

		foreach($dependencies as $dep) {
			list($id, $from, $to) = $dep;
			$packageFound = false;
			foreach($localPackages as $p) {
				if ($id === $p->getID()) {
					$packageFound = true;
					if ($p->getVersion() < $from) {
						print "\nNeed update: ".$p->getID()." ($from-$to)";
						$updatesNeeded[] = array($p->getID(), $from, $to);
					}
					if ($p->getVersion() > $to) {
						print "\nNeed downgrade: ".$p->getID()." ($from-$to)";
						$updatesNeeded[] = array($p->getID(), $from, $to);
					}
				}
			}
			if (!$packageFound) {
				// package was not installed at all.
				print "\nNeed installation: ".$id." ($from-$to)";
				$updatesNeeded[] = array($id, $from, $to);
			}
		}

		// get minimally required versions of packages to upgrade or install
		// and check if other extensions depending on them
		// need to get updated too.
		foreach($updatesNeeded as $up) {
			list($id, $minVersion, $maxVersion) = $up;
			$dd = PackageRepository::getDeployDescriptor($id, $minVersion);

			$packagesToUpdate[] = array($dd, $minVersion, $maxVersion);
			$this->checkForDependingExtensions($dd, $packagesToUpdate, $localPackages);
		}

	}

	/**
	 * Checks for updates on super extensions if the package described by $dd would be installed.
	 * Goes recursively up the dependency tree.
	 *
	 * @param DeployDescriptor $dd
	 * @param array $packagesToUpdate
	 * @param array of DeployDescriptor $localPackages
	 */
	private function checkForSuperExtensions($dd, & $packagesToUpdate, $localPackages) {
		$updatesNeeded = array();
		foreach($localPackages as $p) {

			// check if a local extension has $dd as a dependency
			$dep = $p->getDependency($dd->getID());
			if ($dep == NULL) continue;
			list($id, $from, $to) = $dep;

			// if $dd's version exceeds the limit of the installed,
			// try to find an update
			if ($dd->getVersion() > $to) {
				$versions = PackageRepository::getAllVersions($p->getID());
				// iterate through the available versions
				$updateFound = false;
				foreach($version as $v) {
					$ptoUpdate = PackageRepository::getDeployDescriptor($p->getID(), $v);
					list($id_ptu, $from_ptu, $to_ptu) = $ptoUpdate->getDependency($p->getID());
					if ($from_ptu <= $dd->getVersion() && $to_ptu >= $dd->getVersion()) {
						print "\nNeed update ".$p->getID()." ($from_ptu-$to_ptu)";
						$updatesNeeded[] = array($p, $from_ptu, $to_ptu);
						$updateFound = true;
						break;
					}
				}
				if (!$updateFound) throw new InstallationError(DEPLOY_FRAMEWORK_COULD_NOT_FIND_UPDATE, "Could not find update for: ".$p->getID());
				$packagesToUpdate = array_merge($packagesToUpdate, $updatesNeeded);
				$this->checkForSuperExtensions($p, $packagesToUpdate, $localPackages);
			}
		}

	}

	/**
	 * Callback method. Reads user for required parameters.
	 *
	 * @param array($name=>(array($type, $description)) $userParams
	 * @param array($name=>$value) $mapping
	 *
	 */
	public function getUserReqParams($userParams, & $mapping) {
		if ($this->noAsk || count($userParams) == 0) return;
		print "\n\nRequired parameters:";
		foreach($userParams as $name => $up) {
			list($type, $desc) = $up;
			print "\n$desc\n";
			print "$name ($type): ";
			$line = trim(fgets(STDIN));
			$line = str_replace("\\", "/", $line); // do not allow backslashes
			$mapping[$name] = $line;
		}

	}

	/**
	 * Callback method. Requests a confirmation by the user.
	 *
	 * @param string $message
	 * @param boolean $result
	 * @return unknown
	 */
	public function getUserConfirmation($message, & $result) {
		if ($this->noAsk) return 'y';
		print "\n\n$message [ (y)es/(n)o ]";
		$line = trim(fgets(STDIN));
		$result = strtolower($line);
	}

	public function downloadProgres($percentage) {
		// do nothing
	}
	public function downloadFinished($filename) {
		// do nothing
	}

}

class InstallationError extends Exception {

	var $msg;
	var $arg1;
	var $arg2;

	public function __construct($errCode, $msg = '', $arg1 = NULL, $arg2 = NULL) {
		$this->errCode = $errCode;
		$this->msg = $msg;
		$this->arg1 = $arg1;
		$this->arg2 = $arg2;
	}

	public function getMsg() {
		return $this->msg;
	}

	public function getErrorCode() {
		return $this->errCode;
	}

	public function getArg1() {
		return $this->arg1;
	}

	public function getArg2() {
		return $this->arg2;
	}
}