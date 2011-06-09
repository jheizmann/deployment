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
 * @ingroup WebAdmin
 *
 * Command interface
 *
 * @author: Kai Kühn / ontoprise / 2011
 *
 */
if (!defined("DF_WEBADMIN_TOOL")) {
	die();
}

require_once ( $mwrootDir.'/deployment/tools/smwadmin/DF_PackageRepository.php' );
require_once($mwrootDir.'/deployment/tools/smwadmin/DF_Tools.php');
require_once($mwrootDir.'/deployment/tools/smwadmin/DF_Installer.php');

require_once($mwrootDir.'/deployment/tools/smwadmin/DF_UserInput.php');

class DFCommandInterface {
	
	var $phpExe;
	
	/**
	 *
	 *
	 */
	public function __construct() {
		$this->phpExe = 'php';
		if (array_key_exists('df_php_path', DF_Config::$settings)) {
			$this->phpExe = DF_Config::$settings['df_php_path'];
		}
	}

	public function dispatch($command, $args) {
		try {
			return call_user_func_array(array($this, $command), $args);
		} catch(Exception $e) {
			header( "Status: " . $e->getCode(), true, (int)$e->getCode() );
			print $e->getMessage();
		}
	}

	public function readLog($filename) {
		global $mwrootDir, $dfgOut;

		$absoluteFilePath = Tools::getTempDir()."/$filename";
		if (!file_exists($absoluteFilePath)) {
			return '$$NOTEXISTS$$';
		}
		$log = file_get_contents($absoluteFilePath);
		return $log;
	}

	public function getLocalDeployDescriptor($extid) {
		global $mwrootDir, $dfgOut;

		$localPackages = PackageRepository::getLocalPackages($mwrootDir);
		if (!array_key_exists($extid, $localPackages)) {
			return NULL;
		}
		$dd = $localPackages[$extid];
		$result=array();
		$result['id'] = $dd->getID();
		$result['version'] = $dd->getVersion();
		$result['patchlevel'] = $dd->getPatchlevel();
		$result['dependencies'] = $dd->getDependencies();
		$result['maintainer'] = $dd->getMaintainer();
		$result['vendor'] = $dd->getVendor();
		$result['license'] = $dd->getLicense();
		$result['helpurl'] = $dd->getHelpURL();
			
		$result['resources'] = $dd->getResources();
		$result['onlycopyresources'] = $dd->getOnlyCopyResources();


		$runCommand = "php $mwrootDir/deployment/tools/smwadmin/smwadmin.php --listpages $extid --outputformat json --nocheck --noask";
		exec($runCommand, $out, $ret);

		$outText = implode("",$out);
		if (strpos($outText, '$$ERROR$$') !== false) {
			$result['error'] = $outText;
		} else {
			$wikidumps = json_decode(trim($outText));
			$result['wikidumps'] = $wikidumps->wikidumps;
			$result['ontologies'] = $wikidumps->ontologies;
		}
		return json_encode($result);
	}

	public function getDeployDescriptor($extid) {
		global $mwrootDir, $dfgOut;

		$dd = PackageRepository::getLatestDeployDescriptor($extid);
		if (is_null($dd)) {
			return NULL;
		}

		$result=array();
		$result['id'] = $dd->getID();
		$result['version'] = $dd->getVersion();
		$result['patchlevel'] = $dd->getPatchlevel();
		$result['dependencies'] = $dd->getDependencies();
		$result['maintainer'] = $dd->getMaintainer();
		$result['vendor'] = $dd->getVendor();
		$result['license'] = $dd->getLicense();
		$result['helpurl'] = $dd->getHelpURL();

		$result['resources'] = $dd->getResources();
		$result['onlycopyresources'] = $dd->getOnlyCopyResources();

		$result['wikidumps'] = array();
		foreach($dd->getWikidumps() as $loc) {
			$result['wikidumps'][$loc] = array();
		}

		$result['ontologies'] = array();
		foreach($dd->getOntologies() as $loc) {
			$result['ontologies'][$loc] = array();
		}
		return json_encode($result);
	}

	public function getDependencies($extid) {
		global $mwrootDir, $dfgOut;

		try {
			$dfgOut->setVerbose(false);
			$installer = Installer::getInstance($mwrootDir);
			$dependencies = $installer->getExtensionsToInstall($extid);

			$dfgOut->setVerbose(true);
			return json_encode($dependencies);
		} catch(InstallationError $e) {
			$error = array();
			$error['exception'] = array($e->getMsg(), $e->getErrorCode(), $e->getArg1(), $e->getArg2());
			$dfgOut->setVerbose(true);
			return json_encode($error);
		} catch(RepositoryError $e) {
			$error = array();
			$error['exception'] = array($e->getMsg(), $e->getErrorCode(), $e->getArg1(), $e->getArg2());
			$dfgOut->setVerbose(true);
			return json_encode($error);
		}
	}

	public function install($extid) {
		global $mwrootDir, $dfgOut;

		$filename = uniqid().".log";
		touch(Tools::getTempDir()."/$filename");
		chdir($mwrootDir.'/deployment/tools');
		$php = $this->phpExe;
		if (Tools::isWindows()) {
			$wshShell = new COM("WScript.Shell");
			$runCommand = "cmd /K START $php $mwrootDir/deployment/tools/smwadmin/smwadmin.php --logtofile $filename --outputformat html --nocheck --noask -i $extid";
			$oExec = $wshShell->Run("$runCommand", 7, false);

		} else {
			$runCommand = "$php $mwrootDir/deployment/tools/smwadmin/smwadmin.php --logtofile $filename --outputformat html --nocheck --noask -i $extid";
			$nullResult = `$runCommand &`;
		}
		return $filename;
	}

	public function deinstall($extid) {
		global $mwrootDir, $dfgOut;

		$filename = uniqid().".log";
		touch(Tools::getTempDir()."/$filename");
		chdir($mwrootDir.'/deployment/tools');
		$php = $this->phpExe;
		if (Tools::isWindows()) {
			$wshShell = new COM("WScript.Shell");
			$runCommand = "cmd /K START $php $mwrootDir/deployment/tools/smwadmin/smwadmin.php --logtofile $filename --outputformat html --nocheck --noask -d $extid";
			$oExec = $wshShell->Run("$runCommand", 7, false);

		} else {
			$runCommand = "$php $mwrootDir/deployment/tools/smwadmin/smwadmin.php --logtofile $filename --outputformat html --nocheck --noask -d $extid";
			$nullResult = `$runCommand &`;
		}
		return $filename;
	}

	public function update($extid) {
		global $mwrootDir, $dfgOut;

		$filename = uniqid().".log";
		touch(Tools::getTempDir()."/$filename");
		chdir($mwrootDir.'/deployment/tools');
		$php = $this->phpExe;
		if (Tools::isWindows()) {
			$wshShell = new COM("WScript.Shell");
			$runCommand = "cmd /K START $php $mwrootDir/deployment/tools/smwadmin/smwadmin.php --logtofile $filename --outputformat html --nocheck --noask -u $extid";
			$oExec = $wshShell->Run("$runCommand", 7, false);

		} else {
			$runCommand = "$php $mwrootDir/deployment/tools/smwadmin/smwadmin.php --logtofile $filename --outputformat html --nocheck --noask -u $extid";
			$nullResult = `$runCommand &`;
		}
		return $filename;
	}

	public function finalize($extid) {
		global $mwrootDir, $dfgOut;

		$filename = uniqid().".log";
		touch(Tools::getTempDir()."/$filename");
		chdir($mwrootDir.'/deployment/tools');
		$php = $this->phpExe;
		if (Tools::isWindows()) {
			$wshShell = new COM("WScript.Shell");
			$runCommand = "cmd /K START $php $mwrootDir/deployment/tools/smwadmin/smwadmin.php --logtofile $filename --outputformat html --nocheck --noask --finalize";
			$oExec = $wshShell->Run("$runCommand", 7, false);

		} else {
			$runCommand = "$php $mwrootDir/deployment/tools/smwadmin/smwadmin.php --logtofile $filename --outputformat html --nocheck --noask --finalize";
			$nullResult = `$runCommand &`;
		}
		return $filename;
	}

	public function checkforGlobalUpdate() {
		global $mwrootDir, $dfgOut;

		$dfgOut->setVerbose(false);
		try {
			$installer = Installer::getInstance($mwrootDir);
			$dependencies = $installer->checkforGlobalUpdate();
			$dfgOut->setVerbose(true);
			return json_encode($dependencies);
		} catch(InstallationError $e) {
			$error = array();
			$error['exception'] = array($e->getMsg(), $e->getErrorCode(), $e->getArg1(), $e->getArg2());
			return json_encode($error);
		}  catch(RepositoryError $e) {
			$error = array();
			$error['exception'] = array($e->getMsg(), $e->getErrorCode(), $e->getArg1(), $e->getArg2());
			$dfgOut->setVerbose(true);
			return json_encode($error);
		}
	}

	public function doGlobalUpdate() {
		global $mwrootDir, $dfgOut;

		$filename = uniqid().".log";
		touch(Tools::getTempDir()."/$filename");
		chdir($mwrootDir.'/deployment/tools');
		$php = $this->phpExe;
		if (Tools::isWindows()) {
			$wshShell = new COM("WScript.Shell");
			$runCommand = "cmd /K START $php $mwrootDir/deployment/tools/smwadmin/smwadmin.php --logtofile $filename --outputformat html --nocheck --noask -u";
			$oExec = $wshShell->Run("$runCommand", 7, false);

		} else {
			$runCommand = "$php $mwrootDir/deployment/tools/smwadmin/smwadmin.php --logtofile $filename --outputformat html --nocheck --noask -u";
			$nullResult = `$runCommand &`;
		}
		return $filename;
	}

	public function restore($restorepoint) {
		global $mwrootDir, $dfgOut;

		$filename = uniqid().".log";
		touch(Tools::getTempDir()."/$filename");
		chdir($mwrootDir.'/deployment/tools');
		$php = $this->phpExe;
		if (Tools::isWindows()) {
			$wshShell = new COM("WScript.Shell");
			$runCommand = "cmd /K START $php $mwrootDir/deployment/tools/smwadmin/smwadmin.php --logtofile $filename --outputformat html --nocheck --noask -r $restorepoint";
			$oExec = $wshShell->Run("$runCommand", 7, false);

		} else {
			$runCommand = "$php $mwrootDir/deployment/tools/smwadmin/smwadmin.php --logtofile $filename --outputformat html --nocheck --noask -r $restorepoint";
			$nullResult = `$runCommand &`;
		}
		return $filename;
	}

	public function createRestorePoint($restorepoint) {
		global $mwrootDir, $dfgOut;

		$filename = uniqid().".log";
		touch(Tools::getTempDir()."/$filename");
		chdir($mwrootDir.'/deployment/tools');
		$php = $this->phpExe;
		if (Tools::isWindows()) {
			$wshShell = new COM("WScript.Shell");
			$runCommand = "cmd /K START $php $mwrootDir/deployment/tools/smwadmin/smwadmin.php --logtofile $filename --outputformat html --nocheck --noask --rcreate $restorepoint";
			$oExec = $wshShell->Run("$runCommand", 7, false);

		} else {
			$runCommand = "$php $mwrootDir/deployment/tools/smwadmin/smwadmin.php --logtofile $filename --outputformat html --nocheck --noask --rcreate $restorepoint";
			$nullResult = `$runCommand &`;
		}
		return $filename;
	}

	public function search($searchValue) {
		global $mwrootDir, $dfgOut, $dfgSearchTab;
		$results = array();
		$packages = PackageRepository::searchAllPackages($searchValue);
		$localPackages = PackageRepository::getLocalPackages($mwrootDir);
		$dfgOut->outputln($dfgSearchTab->searializeSearchResults($packages, $localPackages, $searchValue));
		return true;
	}

	public function removeFile($filepath) {
		global $mwrootDir, $dfgOut;
		unlink($filepath);
	}

	public function removeFromRepository($url) {
		global $rootDir;
		if (!file_exists("$rootDir/tools/repositories")) {
			throw new Exception("Could not find repositories file", 500);
		}
		if (!is_writable("$rootDir/tools/repositories")) {
			throw new Exception("$rootDir/tools/repositories is not writeable!", 500);
		}
		$contents = file_get_contents("$rootDir/tools/repositories");

		//FIXME: consider credentials
		$contents = str_replace($url, "", $contents);
		$handle = fopen("$rootDir/tools/repositories", "w");
		fwrite($handle, $contents);
		fclose($handle);
		return;


	}

	public function addToRepository($url) {
		global $rootDir;
		if (!is_writable("$rootDir/tools/repositories")) {
			throw new Exception("$rootDir/tools/repositories is not writeable!", 500);
		}
		$contents = file_get_contents("$rootDir/tools/repositories");
		$contents .= "\n$url";
		$handle = fopen("$rootDir/tools/repositories", "w");
		fwrite($handle, $contents);
		fclose($handle);
		return;

	}
}