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

require_once ('DF_DeployDescriptorProcessor.php');

/**
 * @file
 * @ingroup DFDeployDescriptor
 *
 * @defgroup DFDeployDescriptor Deploy Descriptor
 * @ingroup DeployFramework
 *
 * This class works as a parser of the general
 * description of a deployable entity (aka deploy descriptor).
 *
 * @author: Kai K�hn / ontoprise / 2009
 *
 */
class DeployDescriptor {

	// extracted data from deploy descriptor
	var $globalElement; // global metadata, ie. version, id, vendor, description, install dir
	var $codefiles; // code files or directories (which are controlled by hash)
	var $codeHash; // accumalted hash over all codefiles
	var $wikidumps; // wiki XML dump file (Halo format)
	var $mappings; // mappings (LOD)
	var $resources; // resources: images
	var $oc_resources; // resources which get only copied
	var $configs;   // config elements concerning localsettings changes
	var $removeAllConfigs;

	var $successors; // extensions which are successors of this one in localsettings
	var $userReqs;  // variables which need to be defined by the user
	var $dependencies; // depending extensions
	var $install_scripts; // scripts which need to be run during installation
	var $uninstall_scripts; // scripts which need to be run during de-installation
	var $patches; // patches which need to be applied during installation
	var $uninstallpatches; // patches which need to be unapplied during de-installation
	var $patchlevel; // patchlevel of an version

	// xml
	var $dom;
	var $wikidumps_xml;
	var $codefiles_xml;
	var $resources_xml;
	var $resources_onlycopyxml;
	var $mappings_xml;

	// last errors on applying  configurations
	var $lastErrors;


	/**
	 * Creates a DeployDescriptor from an XML representation.
	 *
	 * @param $xml XML representation
	 * @param $fromVersion Use the configuration from this version, otherwise use the 'new' configuration is used
	 * 			ie. if an extension is newly installed.
	 * @param $fromPatchlevel  Use the configuration from this patchlevel, otherwise use the patchlevel 0 is used
	 * 			ie. if an extension is newly installed.
	 *
	 */
	function __construct($xml, $fromVersion = NULL, $fromPatchlevel = NULL) {

		// parse xml results
		$this->dom = simplexml_load_string($xml);

		$this->globalElement = $this->dom->xpath('/deploydescriptor/global');
		$this->codefiles_xml = $this->dom->xpath('/deploydescriptor/codefiles/file');
		$codeElement = $this->dom->xpath('/deploydescriptor/codefiles');
		$this->codeHash = isset($codeElement[0]) ? $codeElement[0]->attributes()->hash : NULL;

		$this->wikidumps_xml = $this->dom->xpath('/deploydescriptor/wikidumps/file');
		$this->resources_xml = $this->dom->xpath('/deploydescriptor/resources/file[not(@dest)]');
		$this->resources_onlycopyxml = $this->dom->xpath('/deploydescriptor/resources/file[@dest]');
		$this->mappings_xml = $this->dom->xpath('/deploydescriptor/mappings/file');
		$this->removeAllConfigs = false;
		$this->createConfigElements($fromVersion, $fromPatchlevel); // assume new config, not update
	}


	/**
	 * Returns extension IDs which must follow this extension.
	 * @return Array of string
	 */
	public function getSuccessors() {
		return $this->successors;
	}


	/**
	 * Returns configuration elements (subclasses of ConfigElement)
	 * @return Array of ConfigElement
	 */
	public function getConfigs() {
		return $this->configs;
	}

	/**
	 * True if the selected configuration requires to remove all configuration items
	 * before they are applied.
	 * @return boolean
	 */
	public function doRemoveAllConfigs() {
		return $this->removeAllConfigs;
	}

	/**
	 * Returns installation scripts with parameters.
	 * @return Array of hash array ('script'=>$script, 'params'=>$params)
	 */
	public function getInstallScripts() {
		return $this->install_scripts;
	}

	/**
	 * Returns uninstallation scripts with parameters.
	 * @return Array of hash array ('script'=>$script, 'params'=>$params)
	 */
	public function getUninstallScripts() {
		return $this->uninstall_scripts;
	}

	/**
	 * Creates/Updates the config element data depending on the given version.
	 * Can be called as often as needed.
	 *
	 * @param int $from Version to update from (if NULL the new config is assumed)
	 * @param int $fromPatchlevel Version to update from (if NULL the patchlevel 0 is assumed)
	 */
	public function createConfigElements($from = NULL, $fromPatchlevel = NULL) {

		// initialize (or reset) config data
		$this->configs = array();

		$this->successors = array();
		$this->install_scripts = array();
		$this->uninstall_scripts = array();
		$this->userReqs = array();
		$this->patches = array();
		$this->uninstallpatches = array();

		// create xpath selecting the config depending on version to update from.
		if ($from == NULL) {
			$path = "/deploydescriptor/configs/new";
		} else {
			if ($fromPatchlevel == NULL || $fromPatchlevel == 0) {
			 $path = "//update[@from='$from']";
			} else {
				$path = "//update[@from='$from' and @patchlevel='$fromPatchlevel']";
				$update = $this->dom->xpath($path);
				if (count($update) === 0) {
					// if not appropriate patchlevel update exists, try without patchlevel constraint
					$path = "//update[@from='$from']";
				}
			}

			$update = $this->dom->xpath($path);
			if (count($update) === 0) {
				// if update config missing, do not use anything
				// should work, otherwise the dd is false.
				return;
			}
				
			if (isset($update[0]->attributes()->removeAll)) {
				$this->removeAllConfigs = true;
			}
		}

		// select config elements

		$successors = $this->dom->xpath('/deploydescriptor/configs/successor');
		$configElements = $this->dom->xpath($path.'/child::node()');
		$install_scripts = $this->dom->xpath($path.'/script');
		$uninstall_scripts = $this->dom->xpath("/deploydescriptor/configs/uninstall/script");
		$uninstall_patches = $this->dom->xpath("/deploydescriptor/configs/uninstall/patch");
		$patches = $this->dom->xpath($path.'/patch');


		// successors, ie. all the extensions which must succeed this one.
		if (count($successors) > 0 && $successors != '') {
			foreach($successors as $p) {
				$this->successors[] = (string) $p->attributes()->ext;
			}
		}

		// the config elements concerning the LocalSettings.php
		if (count($configElements) > 0 && $configElements != '') {

			foreach($configElements[0] as $p) {
				$this->userReqs = array_merge($this->userReqs, $this->extractUserRequirements($p));
				switch($p->getName()) {
					case 'variable': $this->configs[] = new VariableConfigElement($p);break;
					case 'function': $this->configs[] = new FunctionCallConfigElement($p);break;
					case 'require': $this->configs[] = new RequireConfigElement($p);break;
					case 'php': $this->configs[] = new PHPConfigElement($p);break;
					case 'replace': $this->configs[] = new ReplaceConfigElement($p);break;
				}
			}
		}

		// the config elements concerning install scripts.
		if (count($install_scripts) > 0 && $install_scripts != '') {

			foreach($install_scripts as $p) {
				$script = (string) $p->attributes()->file;
				if (is_null($script) && $script == '') throw new IllegalArgument("Setup 'file'-attribute missing");
				$params = (string) $p->attributes()->params;
				if (is_null($params)) $params = "";
				$this->install_scripts[] = array('script'=>$script, 'params'=>$params);
			}
		}

		// the config elements concerning uninstall scripts.
		if (count($uninstall_scripts) > 0 && $uninstall_scripts != '') {

			foreach($uninstall_scripts as $p) {
				$script = (string) $p->attributes()->file;
				if (is_null($script) && $script == '') throw new IllegalArgument("Setup 'file'-attribute missing");
				$params = (string) $p->attributes()->params;
				if (is_null($params)) $params = "";
				$this->uninstall_scripts[] = array('script'=>$script, 'params'=>$params);
			}
		}

		// the config elements concerning patches
		if (count($patches) > 0 && $patches != '') {
			$this->patches = array();

			foreach($patches as $p) {
				$patchFile = trim((string) $p->attributes()->file);
				$ext = trim((string) $p->attributes()->ext);
				$from = trim((string) $p->attributes()->from);
				$to = trim((string) $p->attributes()->to);
				if (is_null($patchFile) || $patchFile == '') throw new IllegalArgument("Patch 'file'-atrribute missing");
				$this->patches[] = array(array($ext, $from, $to), $patchFile);
			}
		}

		// the config elements concerning uninstall patches
		if (count($uninstall_patches) > 0 && $uninstall_patches != '') {
			$this->uninstallpatches = array();

			foreach($uninstall_patches as $p) {
				$patchFile = trim((string) $p->attributes()->file);
				$ext = trim((string) $p->attributes()->ext);
				$from = trim((string) $p->attributes()->from);
				$to = trim((string) $p->attributes()->to);
				if (is_null($patchFile) || $patchFile == '') throw new IllegalArgument("Patch 'file'-atrribute missing");
				$this->uninstallpatches[] = array(array($ext, $from, $to), $patchFile);
			}
		}
	}

	/**
	 * Returns values which must be provided by the user.
	 * @return hash array ($nameConfigElement => array($type, $description))
	 */
	function getUserRequirements() {
		return $this->userReqs;
	}

	// global properties
	// GETTER
	function getVersion() {
		return trim((string) $this->globalElement[0]->version);
	}

	/**
	 * Returns patchlevel
	 * @return int
	 */
	function getPatchlevel() {
		$patchlevel = trim((string) $this->globalElement[0]->patchlevel);
		return empty($patchlevel) ? 0 : intval($patchlevel);
	}

	/**
	 * Returns ID (always lowercase)
	 * @return string
	 */
	function getID() {
		return strtolower(trim((string) $this->globalElement[0]->id));
	}

	/**
	 * Returns vendor
	 * @return string
	 */
	function getVendor() {
		return trim((string) $this->globalElement[0]->vendor);
	}

	/**
	 * Returns maintainer (which is optional)
	 * @return string
	 */
	function getMaintainer() {
		// maintainer is optional
		return isset($this->globalElement[0]->maintainer) ? trim((string) $this->globalElement[0]->maintainer) : '';
	}

	/**
	 * Returns help URL (which is optional)
	 * @return string
	 */
	function getHelpURL() {
		// helpurl is optional
		return isset($this->globalElement[0]->helpurl) ? trim((string) $this->globalElement[0]->helpurl) : '';
	}

	/**
	 * Get notice (which is optional). It is displayed at the end of an installation operation.
	 * @return string
	 */
	function getNotice() {
		// helpurl is optional
		return isset($this->globalElement[0]->notice) ? trim((string) $this->globalElement[0]->notice) : '';
	}

	/**
	 * Returns installation directory.
	 * @return string
	 */
	function getInstallationDirectory() {
		return trim((string) $this->globalElement[0]->instdir);
	}

	/**
	 * Returns the extension's description.
	 * @return string
	 */
	function getDescription() {
		return trim((string) $this->globalElement[0]->description);
	}

	/**
	 * Returns dependant extensions
	 * @return Array of ($ext_id, $minVersion, $maxVersion)
	 */
	function getDependencies() {
		if (!is_null($this->dependencies)) return $this->dependencies;
		$this->dependencies = array();
		$dependencies = $this->dom->xpath('/deploydescriptor/global/dependencies/dependency');

		foreach($dependencies as $dep) {
			$depID = strtolower(trim((string) $dep[0]));
			$depFrom = intval((string) $dep->attributes()->from);
			$depTo = intval((string) $dep->attributes()->to);
			$optional = (string) $dep->attributes()->optional;
			$optional = $optional == "true";
			$message = (string) $dep->attributes()->message;
			if ($depTo == 0) $depTo = 9999; // if to attribute is missing
			$this->dependencies[] = array($depID, $depFrom, $depTo, $optional, $message);
		}
		return $this->dependencies;
	}

	/**
	 * Returns the dependency of the given extension.
	 * @param $ext_id Extension ID
	 * @return Array of ($ext_id, $minVersion, $maxVersion) or NULL if $ext_id does not occur as dependency.
	 */
	function getDependency($ext_id) {
		$ext_id = strtolower($ext_id);
		$dependencies = $this->getDependencies();
		foreach($dependencies as $d) {
			list($id, $from, $to) = $d;
			if ($ext_id === $id) return $d;
		}
		return NULL;
	}

	/**
	 * Checks if $ext_id exists as dependecy
	 * @param $ext_id Extension ID
	 * @return boolean
	 */
	function hasDependency($ext_id) {
		return !is_null($this->getDependency($ext_id));
	}

	/**
	 * Returns patches which are suitable for the given local packages.
	 *
	 * @param $localPackages array of DeployDescriptor
	 * @return array of string (patch file paths)
	 */
	function getPatches($localPackages) {
			
		$patches = array();
		foreach($this->patches as $patch) {
			foreach($localPackages as $id => $lp) {
				list($dep, $pf) = $patch;
				list($ext_id, $from, $to) = $dep;
				if (empty($ext_id) && !in_array($pf, $patches)) { // add patches without extension constraint
					$patches[] = $pf;
					continue;
				}
				if ($lp->getID() == $ext_id && $from <= $lp->getVersion() && $to >= $lp->getVersion()) {
					$patches[] = $pf;
				}
			}
		}
		return $patches;
	}

	/**
	 * Returns patches which are suitable for the given local packages.
	 *
	 * @param array of DeployDescriptor $localPackages
	 * @return array of string (patch file paths)
	 */
	function getUninstallPatches($localPackages) {

		$patches = array();
		foreach($this->uninstallpatches as $patch) {
			foreach($localPackages as $id => $lp) {
				list($dep, $pf) = $patch;
				list($ext_id, $from, $to) = $dep;
				if (empty($ext_id) && !in_array($pf, $patches)) { // add patches without extension constraint
					$patches[] = $pf;
					continue;
				}
				if ($lp->getID() == $ext_id && $from <= $lp->getVersion() && $to >= $lp->getVersion()) {
					$patches[] = $pf;
				}
			}
		}
		return $patches;
	}

	/**
	 * Returns locations of files explicitly marked as codefiles in the deploy descriptor (relative paths).
	 * It can be a directory or a single file.
	 *
	 * @return array of string
	 */
	function getCodefiles() {
		if (!is_null($this->codefiles)) return $this->codefiles;
		$this->codefiles = array();

		foreach($this->codefiles_xml as $file) {

			$this->codefiles[] = (string) $file->attributes()->loc;
		}
		return $this->codefiles;
	}

	/**
	 * Returns the location of wiki dump files (relative paths)
	 * @return array of string
	 */
	function getWikidumps() {
		if (!is_null($this->wikidumps)) return $this->wikidumps;
		$this->wikidumps = array();
		foreach($this->wikidumps_xml as $file) {
			$this->wikidumps[] = (string) $file->attributes()->loc;
		}
		return $this->wikidumps;
	}

	/**
	 * Returns the location of mappings (relative paths)
	 * @return array of string
	 */
	function getMappings() {
		if (!is_null($this->mappings)) return $this->mappings;
		$this->mappings = array();
		foreach($this->mappings_xml as $file) {
			$loc = (string) $file->attributes()->loc;
			$source = (string) $file->attributes()->source;
			$target = (string) $file->attributes()->target;
			if (!isset($this->mappings[$source])) {
				$this->mappings[$source] = array();
			}
			$this->mappings[$source][] = array($loc, $target);
			 
		}
		return $this->mappings;
	}

	/**
	 * Returns the location of resource files (relative paths)
	 * @return array of string
	 */
	function getResources() {

		if (!is_null($this->resources)) return $this->resources;
		$this->resources = array();
		foreach($this->resources_xml as $file) {
			$this->resources[] = (string) $file->attributes()->loc;
		}
		return $this->resources;
	}

	/**
	 * Returns the location of resource files (relative paths) which get only copied but not imported.
	 * @return hash array of ($location => $destination)
	 */
	function getOnlyCopyResources() {

		if (!is_null($this->oc_resources)) return $this->oc_resources;
		$this->oc_resources = array();
		foreach($this->resources_onlycopyxml as $file) {
			$dest = (string) $file->attributes()->dest;
			$loc = (string) $file->attributes()->loc;
			$this->oc_resources[$loc] = $dest;
		}
		return $this->oc_resources;
	}

	/**
	 * Extracts config elements which are marked as user requirements.
	 * @param $child
	 * @return hash array ($nameConfigElement => array($type, $description))
	 */
	private function extractUserRequirements($child) {
		$userReqs = array();
		$this->_extractUserRequirements($child, $userReqs);
		return $userReqs;
	}

	private function _extractUserRequirements($child, & $userReqs) {

		$children = $child->children();

		foreach($children as $ch) {
			switch($ch->getName()) {
				case "string": {
					$name = (string) $ch->attributes()->name;
					$userValueRequired = (string) $ch->attributes()->userValueRequired;
					if ($userValueRequired == true) {
						$userReqs[$name] = array("string", (string) $ch->attributes()->description);
					}
				}break;
				case "number": {
					$name = (string) $ch->attributes()->name;
					$userValueRequired = (string) $ch->attributes()->userValueRequired;
					if ($userValueRequired == true) {
						$userReqs[$name] = array("number", (string) $ch->attributes()->description);
					}
				}break;
				case "boolean": {
					$name = (string) $ch->attributes()->name;
					$userValueRequired = (string) $ch->attributes()->userValueRequired;
					if ($userValueRequired == true) {
						$userReqs[$name] = array("boolean", (string) $ch->attributes()->description);
					}
				}break;
				case "array": {
					$p = $this->_extractUserRequirements($ch, $mappings);

				}
			}
		}

	}
	/**
	 * Validates the code files. Calculates MD5 hashes over all codefiles and compares
	 * to a MD5 checksum. (attribute 'hash' of codefiles node)
	 *
	 * @return boolean. True if all files are valid, otherwise false.
	 */
	function validatecode($rootDir) {

		$codeFiles = $this->getCodefiles();
		if (count($codeFiles) == 0) return true;
		if (is_null($this->codeHash)) return true;
		$actual_hash = "";
		foreach($codeFiles as $file) {
			$this->_validateCode($rootDir."/".$file, $actual_hash);
		}
		return md5($actual_hash) == $this->codeHash;
	}

	private function _validateCode($SourceDirectory, & $actual_hash) {
		// add trailing slashes
		if (substr($SourceDirectory,-1)!='/'){
			$SourceDirectory .= '/';
		}

		$handle = @opendir($SourceDirectory);
		if (!$handle) {
			return;
		}
		while ( ($entry = readdir($handle)) !== false ){

			if ($entry[0] == '.'){
				continue;
			}


			if (is_dir($SourceDirectory.$entry)) {
				// Unterverzeichnis
				$success = $this->_validateCode($SourceDirectory.$entry, $actual_hash);

			} else{

				$content = file_get_contents($SourceDirectory.$entry);
				$actual_hash .= md5($contents);
			}

		}
	}

	/**
	 * Applies all necessary configurations.
	 *
	 * @param string $rootDir . Location of Mediawiki
	 * @param boolean $dryRun .  If true, nothing gets actually changed or asked.
	 * @param int $fromVersion .  Update from version or NULL if no update.
	 * @param function $userCallback. callback function for user input (see Installer.php)
	 * @return string updated LocalSettings.php
	 */
	function applyConfigurations($rootDir, $dryRun = false, $fromVersion = NULL, $userCallback = NULL) {
		if ($this->configs === false) {
			return;
		}

		$dp = new DeployDescriptionProcessor($rootDir.'/LocalSettings.php', $this);

		print "\n[Configure LocalSettings.php...";
		$content = $dp->applyLocalSettingsChanges($userCallback, $this->getUserRequirements(), $dryRun);
		print "done.]";

		if (!$dryRun) {
				
			$dp->applyPatches($userCallback);
				
		}

		$this->lastErrors = $dp->getErrorMessages();
		return $content; // return for testing purposes.
	}

	/**
	 * Applies the setup script(s)
	 *
	 * @param $dryRun If true, nothing gets actually changed or asked.
	 */
	function applySetups($rootDir, $dryRun = false) {
		$dp = new DeployDescriptionProcessor($rootDir.'/LocalSettings.php', $this);
		if (!$dryRun) $dp->applySetups();
	}

	/**
	 * Reverses configuration changes
	 *
	 * @param string $rootDir Location of Mediawiki
	 * @param boolean $dryRun
	 * @return
	 */
	function unapplyConfigurations($rootDir) {
		$dp = new DeployDescriptionProcessor($rootDir.'/LocalSettings.php', $this);
		$dp->unapplyPatches();
		$content = $dp->unapplyLocalSettingsChanges();
		$this->lastErrors = $dp->getErrorMessages();
		return $content; // return for testing purposes.
	}

	/**
	 * Unapplies the setup scripts
	 * @param $rootDir
	 */
	function unapplySetups($rootDir) {
		$dp = new DeployDescriptionProcessor($rootDir.'/LocalSettings.php', $this);
		$dp->unapplySetups();
	}

	/**
	 * Returns last errors occured on applyConfigurations or unapplyConfigurations
	 *
	 * @return array of string
	 */
	function getLastErrors() {
		return $this->lastErrors;
	}

}

