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
 * @ingroup DeployFramework
 *
 * @defgroup DeployFramework Deploy Framework
 * @ingroup DeployFramework
 * 
 * Installation tool.
 *
 * @author: Kai K�hn / ontoprise / 2009
 *
 *
 */
define('DEPLOY_FRAMEWORK_VERSION', '@VERSION@ [B@BUILD_NUMBER@]');

global $rootDir;
$rootDir = dirname(__FILE__);
$rootDir = str_replace("\\", "/", $rootDir);
$rootDir = realpath($rootDir."/../../");

require_once('DF_Tools.php');
require_once('DF_Installer.php');

$phpver = str_replace(".","",phpversion());
if ($phpver < 520) {
	print "\nPHP version must be >= 5.2\n";
	die();
}

if (array_key_exists('SERVER_NAME', $_SERVER) && $_SERVER['SERVER_NAME'] != NULL) {
	echo "Invalid access! A maintenance script MUST NOT accessed from remote.";
	die();
}

// check tools
$check = Tools::checkEnvironment();
if ($check !== true) {
	fatalError($check);
}

// check if the user is allowed to create files, directory.
$check = Tools::checkPriviledges();
if ($check !== true) {
	fatalError($check);
}

# Attempt to connect to the database as a privileged user
# This will vomit up an error if there are permissions problems
$dbclass = 'Database' . ucfirst( $wgDBtype ) ;
$wgDatabase = new $dbclass( $wgDBserver, $wgDBadminuser, $wgDBadminpassword, $wgDBname, 1 );

if( !$wgDatabase->isOpen() ) {
	# Appears to have failed
	echo( "A connection to the database could not be established. Check the\n" );
	echo( "values of \$wgDBadminuser and \$wgDBadminpassword.\n" );
	exit();
}

$packageToInstall = array();
$packageToDeinstall = array();
$packageToUpdate = array();

// defaults:
$dfgForce = false;
$dfgGlobalUpdate= false;
$dfgShowDescription=false;
$dfgListPackages=false;
$dfgCheckDep=false;
$dfgRestore=false;
$dfgCheckInst=false;
$dfgInstallPackages=false;

$help = array_key_exists("help", $options);
if ($help || count($argv) == 0) {
	showHelp();
	die();
}

// get command line parameters
$args = $_SERVER['argv'];
array_shift($args); // remove script name
for( $arg = reset( $args ); $arg !== false; $arg = next( $args ) ) {

	//-i => Install
	if ($arg == '-i') {
		$package = next($args);
		if ($package === false) fatalError("No package found");
		$packageToInstall[] = $package;
		continue;
	} else if ($arg == '-d') { // -d => De-install
		$package = next($args);
		if ($package === false) fatalError("No package found");
		$packageToDeinstall[] = $package;
			
		continue;
	} else if ($arg == '-u') { // u => update
		$package = next($args);
		if ($package === false || $package == '--dep') {
			if ($package == '--dep') {
				$dfgCheckDep = true;
			}
			$dfgGlobalUpdate = true;
			continue;
		}
		$packageToUpdate[] = $package;
		continue;
	} else if ($arg == '-l') { // => list packages
		$dfgListPackages = true;
		$pattern = next($args);
		continue;
	} else if ($arg == '-desc') { // => show description for each package
		$dfgShowDescription = true;
		continue;
	} else if ($arg == '--dep') { // => show dependencies
		$dfgCheckDep = true;
		continue;
	} else if ($arg == '--checkdump') { // => analyze installed dump
		$checkDump = true;
		$package = next($args);
		if ($package === false) fatalError("No package found");
		$packageToInstall[] = $package;
		continue;
	} else if ($arg == '--install') { // => analyze installed dump
        $dfgInstallPackages = true;
		continue;
    } else if ($arg == '-f') { // => force
		$dfgForce = true;
		continue;
	} else if ($arg == '-r') { // => rollback last installation
		$dfgRestore = true;
		continue;
	} else {
		print "\nUnknown command: $arg. Try --help\n\n";
		die();
	}
	$params[] = $arg;
}


$mediaWikiLocation = dirname(__FILE__) . '/../../..';
require_once "$mediaWikiLocation/maintenance/commandLine.inc";

// check if AdminSettings.php is available
if (!isset($wgDBadminuser) && !isset($wgDBadminpassword)) {
	fatalError("Please set create AdminSettings.php file. Otherwise rollback mechanism will not work properly.");
}

// create language object
$langClass = "DF_Language_$wgLanguageCode";
if (!file_exists("../languages/$langClass.php")) {
	$langClass = "DF_Language_En";
}
require_once("../languages/$langClass.php");
$dfgLang = new $langClass();



$mwrootDir = dirname(__FILE__);
$mwrootDir = str_replace("\\", "/", $mwrootDir);
$mwrootDir = realpath($mwrootDir."/../../../");

$installer = Installer::getInstance($mwrootDir, $dfgForce);
$rollback = Rollback::getInstance($mwrootDir);
$res_installer = ResourceInstaller::getInstance($mwrootDir);


if ($dfgRestore) {
	handleRollback();
	die();
}

// Global update (ie. updates all packages to the latest possible version)
if ($dfgGlobalUpdate) {
	handleGlobalUpdate($dfgCheckDep);
	die();
}

// List all available packages and show which are installed.
if ($dfgListPackages) {
	$installer->listAvailablePackages($dfgShowDescription, $pattern);
	die();
}

if ($dfgInstallPackages) {
	$installer->initializePackages();
	die();
}

// install
foreach($packageToInstall as $toInstall) {
	$toInstall = str_replace(".", "", $toInstall);
	$parts = explode("-", $toInstall);
	$packageID = $parts[0];
	$version = count($parts) > 1 ? $parts[1] : NULL;
	try {
		handleInstallOrUpdate($packageID, $version);
	} catch(InstallationError $e) {
		fatalError($e);
	} catch(HttpError $e) {
		fatalError($e);
	} catch(RepositoryError $e) {
		fatalError($e);
	} catch(RollbackInstallation $e) {
		fatalError("Installation failed! You can try to rollback: smwadmin -r");
	}
}

//de-install
foreach($packageToDeinstall as $toDeInstall) {
	$toDeInstall = str_replace(".", "", $toDeInstall);
	try {
		$installer->deinstall($toDeInstall);
	} catch(InstallationError $e) {
		fatalError($e);
	} catch(HttpError $e) {
		fatalError($e);
	} catch(RollbackInstallation $e) {
		// currently not supported
	}catch(RepositoryError $e) {
		fatalError($e);
	}
}

// update
foreach($packageToUpdate as $toUpdate) {
	$toUpdate = str_replace(".", "", $toUpdate);
	$parts = explode("-", $toUpdate);
	$packageID = $parts[0];
	$version = count($parts) > 1 ? $parts[1] : NULL;
	try {
		handleInstallOrUpdate($packageID, $version);
	} catch(InstallationError $e) {
		fatalError($e);
	} catch(HttpError $e) {
		fatalError($e);
	} catch(RollbackInstallation $e) {
		fatalError("Installation failed! You can try to rollback: smwadmin -r");
	} catch(RepositoryError $e) {
		fatalError($e);
	}
}

if (count($installer->getErrors()) === 0) {
	print "\n\nOK.\n";
} else {
	print "\n\nErrors occured:\n";
	foreach($installer->getErrors() as $e) {
		print "\n$e";
	}
	print "\n";
}

function showHelp() {
	echo "\nsmwhalo admin utility v".DEPLOY_FRAMEWORK_VERSION.", Ontoprise 2009";
	echo "\n\nUsage: smwadmin [ -i | -d ] <package>[-<version>]";
	echo "\n       smwadmin -u [ <package>[-<version>] ]";
	echo "\n";
	echo "\n\t-i <package>: Install";
	echo "\n\t-d <package> ]: De-Install";
	echo "\n\t-u <package>: Update";
	echo "\n\t--install: Finalizes installation";
	echo "\n\t--checkdump <package>: Check only dumps for changes but do not install.";
	echo "\n\t-l [ pattern ] : List installed packages.";
	echo "\n\t-r : Rollback last installation.";
	echo "\n\t--dep : Check only dependencies but do not install.";
	echo "\n";
	echo "\nExamples:\n\n\tsmwadmin -i smwhalo-1.4.4 -u smw-142: Installs the given packages";
	echo "\n\tsmwadmin -i smwhalo: Installs latest version of smwhalo";
	echo "\n\tsmwadmin -u: Updates complete installation";
	echo "\n\tsmwadmin -u --dep: Shows what would be updated.";
	echo "\n\tsmwadmin -d smw: Removes the package smw.";
	echo "\n\n";

}


function handleRollback() {
	global $rollback;
	print "Rollback...";
	$rollback->rollback();

}


function handleGlobalUpdate($dfgCheckDep) {
	global $installer;
	try {
		list($extensions_to_update, $contradictions, $updated) = $installer->updateAll($dfgCheckDep);
		if ($dfgCheckDep) {
			if (count($extensions_to_update) > 0) {

				print "\n\nThe following extensions would get updated:\n";
				foreach($extensions_to_update as $id => $etu) {
					list($desc, $min, $max) = $etu;
					print "\n\t*$id-".Tools::addVersionSeparators(array($min, $desc->getPatchlevel()));
				}


			}
			if (count($contradictions) > 0) {
				print "\nThe following extensions can not be installed/updated due to conflicts:";
				foreach($contradictions as $etu) {
					list($dd, $min, $max) = $etu;
					print "\n- ".$dd->getID();
				}
			}
			print "\n\n";

		}

		if ($updated && count($extensions_to_update) > 0) {
			echo "\n\nYour installation is now up-to-date!\n";
		} else if (count($extensions_to_update) == 0) {
			echo "\n\nYour installation is already up-to-date!\n";
		}
	} catch(InstallationError $e) {
		fatalError($e);
	} catch(HttpError $e) {
		fatalError($e);
	} catch(RollbackInstallation $e) {
		fatalError("Installation failed! You can try to rollback: smwadmin -r");
	} catch(RepositoryError $e) {
		fatalError($e);
	}

}

function handleInstallOrUpdate($packageID, $version) {
	global $checkDump, $dfgCheckDep, $installer, $res_installer;
	if (isset($checkDump) && $checkDump == true) {
		// check status of a currently installed wikidump
		$res_installer->checkWikidump($packageID, $version);
		print "\n\n";

	} else if (isset($dfgCheckDep) && $dfgCheckDep == true) {

		// check dependencies of a package to install or update
		list($new_package, $old_package, $extensions_to_update) = $installer->collectPackagesToInstall($packageID, $version);
		print "\n\nThe following extensions would be installed:\n";
		foreach($extensions_to_update as $etu) {
			list($desc, $min, $max) = $etu;
			$id = $desc->getID();
			print "\n\t*$id-".Tools::addVersionSeparators(array($min, $desc->getPatchlevel()));
		}


		print "\n\n";
	} else {

		// install or update
		$installer->installOrUpdate($packageID, $version);
	}
}
/**
 * Shows a fatal error which aborts installation.
 *
 * @param Exception $e (InstallationError, HttpError, RollbackInstallation)
 */
function fatalError($e) {
	print "\n\n";

	if ($e instanceof InstallationError) {
		switch($e->getErrorCode()) {
			case DEPLOY_FRAMEWORK_DEPENDENCY_EXIST: {
				$packages = $e->getArg1();
				print $e->getMsg()."\n";
				foreach($packages as $p) {
					print "\n\t*$p";
				}
				break;
			}
			case DEPLOY_FRAMEWORK_ALREADY_INSTALLED:
				$package = $e->getArg1();
				print $e->getMsg()."\n";
				print "\n\t*".$package->getID()."-".$package->getVersion();
				break;
			case DEPLOY_FRAMEWORK_INSTALL_LOWER_VERSION:
			case DEPLOY_FRAMEWORK_NO_TMP_DIR:
			case DEPLOY_FRAMEWORK_COULD_NOT_FIND_UPDATE:
			case DEPLOY_FRAMEWORK_PACKAGE_NOT_EXISTS:
			case DEPLOY_FRAMEWORK_CODE_CHANGED:
			case DEPLOY_FRAMEWORK_MISSING_FILE:
			default: echo "Error: ".$e->getMsg(); break;
		}
	} else if ($e instanceof HttpError) {
		print $e->getMsg();
		//print $e->getHeader(); // for debugging
	} else if ($e instanceof RepositoryError) {
		print "\n".$e->getMsg();
	} else if (is_string($e)) {
		print "\n".$e;
	}
	print "\n\n";
	// stop installation
	die();
}
