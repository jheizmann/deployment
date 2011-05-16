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

define('DEPLOY_FRAMEWORK_REPO_PACKAGE_DOES_NOT_EXIST', 1);
define('DEPLOY_FRAMEWORK_REPO_INVALID_DESCRIPTOR', 2);

global $rootDir;
require_once $rootDir.'/io/DF_HttpDownload.php';
require_once $rootDir.'/tools/smwadmin/DF_Tools.php';
require_once $rootDir.'/descriptor/DF_DeployDescriptor.php';


// default repository
// this URL is supposed to be fix forever
define("SMWPLUS_REPOSITORY", "http://dailywikibuilds.ontoprise.com/repository/");


/**
 * @file
 * @ingroup DFInstaller
 *
 * Allows access package repositories.
 *
 * @author: Kai K�hn / ontoprise / 2009
 *
 */
class PackageRepository {

	// repository DOM
	static $repo_dom = array();
	// cache for deploy descriptors
	static $deploy_descs = array();
	// credentials for repositories
	static $repo_credentials = array();

	// cache for local packages
	static $localPackages = NULL;
	// cache for local packages
	static $localPackagesToInitialize = NULL;

	/**
	 * Downloads all package repositories from remote.
	 *
	 * @return PackageRepository
	 */
	private static function getPackageRepository() {
		if (!empty(self::$repo_dom)) return self::$repo_dom;
		$rep_urls = array();
		global $smwgDFIP;
		$repositoriesFile = isset($smwgDFIP) ? "$smwgDFIP/tools/repositories" : "repositories";
		if (file_exists($repositoriesFile)) {
			print "\nReading from repository file...";
			$content = file_get_contents($repositoriesFile);
			$rep_file_lines = array_unique(explode("\n", $content));
			$repo_urls = array();
			foreach($rep_file_lines as $u) {
				$u = trim($u);
				if (trim($u) == "" || substr(trim($u),0,1) == "#") continue;
				@list($rawurl, $user, $pass) = explode(" ", $u); // do not complain about missing credentials
				$url = (substr(trim($rawurl), -1) == "/") ? $rawurl : (trim($rawurl)."/"); //add trailing / if necessary

				$repo_urls[] = $url;
				if ((is_null($user) || empty($user)) && (is_null($pass) || empty($pass))) {
					self::$repo_credentials[$url] = "";
				} else {
					self::$repo_credentials[$url] = "$user:$pass";
				}
			}
			print "done.";
		} else {
			print "\nNo repository file. Using default repository.";
			self::$repo_credentials[SMWPLUS_REPOSITORY] = "" ; // default repo
			$repo_urls[] = SMWPLUS_REPOSITORY;
		}
		$d = new HttpDownload();
		foreach($repo_urls as $url) {
			$url = trim($url);
			if (substr($url, -1) != '/') $url .= '/';
			$partsOfURL = parse_url($url. 'repository.xml');

			$path = $partsOfURL['path'];
			if (!array_key_exists('path', $partsOfURL)) {
				print "\nWarning: Could not parse $url";
			}
			$host = $partsOfURL['host'];
			if (!array_key_exists('host', $partsOfURL)) {
				print "\nWarning: Could not parse $url";
			}
			$port = array_key_exists("port", $partsOfURL) ? $partsOfURL['port'] : 80;
			try {
				$res = $d->downloadAsString($path, $port, $host, array_key_exists($url, self::$repo_credentials) ? self::$repo_credentials[$url] : "", NULL);
				self::$repo_dom[$url] = simplexml_load_string($res);
			} catch(HttpError $e) {
				print "\n".$e->getMsg();
				print "\n";
			} catch(Exception $e) {
				print "\n".$e->getMessage();
				print "\n";
			}

		}
		return self::$repo_dom;
	}


	/**
	 * Returns credentials for the given repository URL.
	 *
	 * @param string $repo_url
	 * @return string user:pass
	 */
	public static function getCredentials($repo_url) {
		return array_key_exists($repo_url, self::$repo_credentials) ? self::$repo_credentials[$repo_url] : "";
	}
	/*
	 * Loads package repository from string (for testing)
	 */
	public static function initializePackageRepositoryFromString($repo_xml, $url) {
		self::$repo_dom[$url] = simplexml_load_string($repo_xml);
	}
	/*
	 * Clears package repository (for testing)
	 */
	public static function clearPackageRepository() {
		self::$repo_dom = array();
	}

	/**
	 * Returns deploy descriptor of package $ext_id in the latest version.
	 *
	 * @param string $ext_id
	 * @return DeployDescriptor
	 */
	public static function getLatestDeployDescriptor($ext_id) {
		if (is_null($ext_id)) throw new IllegalArgument("ext must not null");
		if (array_key_exists($ext_id, self::$deploy_descs)) return self::$deploy_descs[$ext_id];

		// get latest version in the available repositories
		$results = array();

		foreach(self::getPackageRepository() as $url => $repo) {
			$versions = $repo->xpath("/root/extensions/extension[@id='$ext_id']/version");
			if (is_null($versions) || $versions == false || count($versions) == 0) continue;
			foreach($versions as $v) {
				$results[$url] = (string) $v->attributes()->ver;
			}
		}
		asort($results, SORT_NUMERIC);
		$results = array_reverse($results, true);
		$rpURLs = array_keys($results);
		$url = reset($rpURLs);


		if ($url === false) throw new RepositoryError(DEPLOY_FRAMEWORK_REPO_PACKAGE_DOES_NOT_EXIST, "Can not find package: $ext_id. Missing repository?");

		// download descriptor
		$d = new HttpDownload();
		$credentials = array_key_exists($url, self::$repo_credentials) ? self::$repo_credentials[$url] : "";
		$partsOfURL = parse_url($url. "extensions/$ext_id/deploy.xml");

		$path = $partsOfURL['path'];
		$host = $partsOfURL['host'];
		$port = array_key_exists("port", $partsOfURL) ? $partsOfURL['port'] : 80;
		$res = $d->downloadAsString($path, $port, $host, $credentials, NULL);

		$dd =  new DeployDescriptor($res);
		self::$deploy_descs[] = $dd;
		return $dd;
	}

	public static function getDeployDescriptorFromRange($ext_id, $minversion, $maxversion) {
		if ($minversion > $maxversion)  throw new RepositoryError(DEPLOY_FRAMEWORK_REPO_INVALID_DESCRIPTOR, "Invalid range of versions: $minversion-$maxversion");
		for($i = $minversion; $i <= $maxversion; $i++) {
			try {
				$dd = self::getDeployDescriptor($ext_id, $i);
				return $dd;
			} catch(RepositoryError $e) {
				// try next version
			}
		}
		throw new RepositoryError(DEPLOY_FRAMEWORK_REPO_PACKAGE_DOES_NOT_EXIST, "Can not find package: $ext_id in version range $minversion-$maxversion");
	}

	/**
	 * Returns deploy descriptor of package $ext_id in version $version
	 *
	 * @param string $ext_id
	 * @param int $version
	 * @return DeployDescriptor
	 */
	public static function getDeployDescriptor($ext_id, $version) {
		if (strlen((string)$version) == 2) $version = "0$version";
		if (is_null($ext_id) || is_null($version)) throw new IllegalArgument("version or ext must not null");
		if (array_key_exists($ext_id.$version, self::$deploy_descs)) return self::$deploy_descs[$ext_id.$version];

		// get latest version in the available repositories
		$results = array();
		foreach(self::getPackageRepository() as $url => $repo) {
			$versions = $repo->xpath("/root/extensions/extension[@id='$ext_id']/version[@ver='$version']");
			if (is_null($versions) || $versions == false || count($versions) == 0) continue;
			$v = reset($versions);
			$repourl = $url;
			break;
		}
		if (!isset($repourl)) throw new RepositoryError(DEPLOY_FRAMEWORK_REPO_PACKAGE_DOES_NOT_EXIST, "Can not find package: $ext_id-$version");

		// download descriptor
		$d = new HttpDownload();
		$credentials = array_key_exists($repourl, self::$repo_credentials) ? self::$repo_credentials[$repourl] : '';
		$partsOfURL = parse_url($url. "extensions/$ext_id/deploy-$version.xml");

		$path = $partsOfURL['path'];
		$host = $partsOfURL['host'];
		$port = array_key_exists("port", $partsOfURL) ? $partsOfURL['port'] : 80;
		$res = $d->downloadAsString($path, $port, $host, $credentials, NULL);

		$dd =  new DeployDescriptor($res);

		self::$deploy_descs[] = $dd;
		return $dd;
	}

	/**
	 * Returns all available versions in descendant order.
	 *
	 * @param string $packageID
	 * @return array of versions (descendant)
	 */
	public static function getAllVersions($packageID) {

		$results = array();

		foreach(self::getPackageRepository() as $repo) {
			$versions = $repo->xpath("/root/extensions/extension[@id='$packageID']/version");

			if ($versions !== false) {
				foreach($versions as $v) {
					$results[] = (string) $v->attributes()->ver;
				}
			}
		}

		sort($results, SORT_NUMERIC);

		return array_reverse(array_unique($results));
	}

	/**
	 * Returns all available packages and their versions
	 *
	 * @return array of (package ids => array of (version, patchlevel, repo URL) sorted in descending order
	 */
	public static function getAllPackages() {
		$results = array();
		foreach(self::getPackageRepository() as $repo_url => $repo) {
			$packages = $repo->xpath("/root/extensions/extension");
			foreach($packages as $p) {
				$id = (string) $p->attributes()->id;
				if (!array_key_exists($id, $results)) $results[$id] = array();
				$versions = $p->xpath("version");
				foreach($versions as $v) {
					$version = (string) $v->attributes()->ver;
					$patchlevel = (string) $v->attributes()->patchlevel;
					$patchlevel = empty($patchlevel) ? 0 : $patchlevel;
					$results[$id][] = array($version, $patchlevel, $repo_url);
				}

			}
		}
		$sortedResults = array();
		foreach($results as $id => $versions) {
			Tools::sortVersions($versions);
			$sortedResults[$id] = $versions;
		}

		return $sortedResults;
	}
	/**
	 * Returns latest available version of a package
	 *
	 * @param string $packageID The package ID
	 * @return array (URL (as string), version, repo_url)
	 */
	public static function getLatestVersion($packageID) {
		$results = array();
		foreach(self::getPackageRepository() as $url => $repo) {

			$package = $repo->xpath("/root/extensions/extension[@id='$packageID']/version[position()=last()]");
			if (count($package) == 0) continue;
			$download_url = trim((string) $package[0]->attributes()->url);
			$version = (string) $package[0]->attributes()->ver;
			$results[$version] = array($download_url, $url);

		}
		if (count($results) == 0) return NULL;
		ksort($results, SORT_NUMERIC); // sort for versions
		$results = array_reverse($results, true); // highest version on top
		$keys = array_keys($results);
		$version = reset($keys); // get highest version
		$values = array_values($results);
		list($download_url, $repo_url) = reset($values); // get its download url and repo
		return array($download_url, $version, $repo_url);
	}

	/**
	 * Returns the URL of the requested version of the package if available or NULL if not.
	 *
	 * @param string $packageID
	 * @param number $version
	 * @return array (url, repo_url)
	 */
	public static function getVersion($packageID, $version) {
		$results = array();
		foreach(self::getPackageRepository() as $url => $repo) {
			$package = $repo->xpath("/root/extensions/extension[@id='$packageID']/version[@ver='$version']");

			if (is_null($package) || $package === false || count($package) == 0) continue;
			$repo_url = $url;
			$download_url = trim((string) $package[0]->attributes()->url);
			break;
		}
		if (!isset($download_url)) throw new RepositoryError(DEPLOY_FRAMEWORK_REPO_PACKAGE_DOES_NOT_EXIST, "Can not find package: $packageID-$version. Missing repository?");

		return array($download_url, $repo_url);
	}

	/**
	 * Checks if the package with the given version exists or not.
	 *
	 * @param string $packageID
	 * @param number $version Optional
	 * @return boolean
	 */
	public static function existsPackage($packageID, $version = 0) {
		$results = array();
		foreach(self::getPackageRepository() as $repo) {
			if ($version > 0) {
				$package = $repo->xpath("/root/extensions/extension[@id='$packageID']/version[@ver='$version']");
			} else {
				$package = $repo->xpath("/root/extensions/extension[@id='$packageID']");
			}

			if (count($package) > 0) return true;
		}
		return false;
	}

	/**
	 * Returns the local package deploy descriptors.
	 *
	 * @param string $ext_dir Extension directory
	 * @return array of (id=>DeployDescriptor)
	 */
	public static function getLocalPackages($ext_dir, $forceReload = false) {
		if (!is_null(self::$localPackages) && !$forceReload) return self::$localPackages;
		self::$localPackages = array();
		// add trailing slashes
		if (substr($ext_dir,-1)!='/'){
			$ext_dir .= '/';
		}

		// read root dir and extensions dir for deploy descriptors
		self::readDirectoryForDD($ext_dir);
		self::readDirectoryForDD($ext_dir."extensions");
		self::readDirectoryForDD(Tools::getProgramDir()."/Ontoprise");

		$OPSoftware = Tools::getOntopriseSoftware();
		if (!is_null($OPSoftware) && count($OPSoftware) > 0) {
			foreach($OPSoftware as $prgname => $path) {
				$path = trim($path);
				if (file_exists($path.'/deploy.xml')) {
					$dd = new DeployDescriptor(file_get_contents($path.'/deploy.xml'));
					if (!array_key_exists($dd->getID(), self::$localPackages)) {
						self::$localPackages[$dd->getID()] = $dd;
					}
				}
			}
		}

		// create special deploy descriptor for Mediawiki itself
		self::$localPackages['mw'] = self::createMWDeployDescriptor(realpath($ext_dir));

		return self::$localPackages;
	}

	private static function readDirectoryForDD($ext_dir) {
		if (substr($ext_dir,-1)!='/'){
			$ext_dir .= '/';
		}
		$handle = @opendir($ext_dir);
		if (!$handle) {
			//print "\nWARNING: $ext_dir does not exist. Skipped.";
			return;
		}

		while ($entry = readdir($handle) ){
			if ($entry[0] == '.'){
				continue;
			}

			if (is_dir($ext_dir.$entry)) {
				// check if there is a deploy.xml
				if (file_exists($ext_dir.$entry.'/deploy.xml')) {
					$dd = new DeployDescriptor(file_get_contents($ext_dir.$entry.'/deploy.xml'));
					self::$localPackages[$dd->getID()] = $dd;

				}
			}

		}
		@closedir($handle);
	}

	/**
	 * Returns the deploy descriptors of packages which have not been initialized.
	 *
	 * @param string $ext_dir Extension directory
	 * @return array of (id=>DeployDescriptor)
	 */
	public static function getLocalPackagesToInitialize($ext_dir, $forceReload = false) {
		if (!is_null(self::$localPackagesToInitialize) && !$forceReload) return self::$localPackagesToInitialize;
		self::$localPackagesToInitialize = array();
		// add trailing slashes
		if (substr($ext_dir,-1)!='/'){
			$ext_dir .= '/';
		}

		self::readDirectoryForDDToInitialize($ext_dir);
		self::readDirectoryForDDToInitialize($ext_dir."extensions");
		self::readDirectoryForDDToInitialize(Tools::getProgramDir()."/Ontoprise");

		// special handling for MW itself
		if (file_exists($ext_dir.'/init$.ext')) {
			$init_ext_file = trim(file_get_contents($ext_dir.'/init$.ext'));
			list($id, $fromVersion) = explode(",", $init_ext_file);
			$dd = self::createMWDeployDescriptor(realpath($ext_dir));
			self::$localPackagesToInitialize[$id] = array($dd, $fromVersion);
		}

		return self::$localPackagesToInitialize;
	}

	private static function readDirectoryForDDToInitialize($ext_dir) {
		if (substr($ext_dir,-1)!='/'){
			$ext_dir .= '/';
		}
		$handle = @opendir($ext_dir);
		if (!$handle) {
			//print "\nWARNING: $ext_dir does not exist. Skipped.";
			return;
		}

		while ($entry = readdir($handle) ){
			if ($entry[0] == '.'){
				continue;
			}

			if (is_dir($ext_dir.$entry)) {
				// check if there is a init$.ext
				if (file_exists($ext_dir.$entry.'/init$.ext')) {
					$init_ext_file = trim(file_get_contents($ext_dir.$entry.'/init$.ext'));
					list($id, $fromVersion) = explode(",", $init_ext_file);
					$dd = new DeployDescriptor(file_get_contents($ext_dir.$entry.'/deploy.xml'));
					self::$localPackagesToInitialize[$id] = array($dd, $fromVersion);

				}
			}

		}
		@closedir($handle);
	}

	private static function createMWDeployDescriptor($rootDir) {
		$version = Tools::getMediawikiVersion($rootDir);
		$version = intval(str_replace(".","", $version));
		$xml = '<?xml version="1.0" encoding="UTF-8"?>
				<deploydescriptor>
				    <global>
				        <version>'.$version.'</version>
				        <id>mw</id>
				        <vendor>Ontoprise GmbH</vendor>
				        <instdir/>
				        <description>Mediawiki software</description>
				       
    			    </global>
				    <codefiles/>
				    <wikidumps/>
				    <resources/>
				    <configs/>
				    </deploydescriptor>';

		return new DeployDescriptor($xml);
	}
}

class RepositoryError extends Exception {

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
