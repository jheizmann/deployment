<?php
/*  Copyright 2011, ontoprise GmbH
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
 * @ingroup DFMaintenance
 *
 * Adds a bundle or a set of bundles to a repository.
 *
 * Usage: php addBundle2Repository.php
 *                  -r <repository-dir>         The repository root directory
 *                  -b <bundle file or dir>     The bundle file or a directory containing bundle files
 *                  --url <repository-url>      The download base URL
 *                  [--latest]                  Latest version?
 *                  [--mediawiki]               Include Mediawiki?
 *                  [--contains <substring> ]   File name contains a substring
 *
 * @author: Kai Kühn / ontoprise / 2011
 *
 */

global $rootDir;
$rootDir = dirname(__FILE__);
$rootDir = str_replace("\\", "/", $rootDir);
$rootDir = realpath($rootDir."/../../");

require_once($rootDir."/descriptor/DF_DeployDescriptor.php");
require_once($rootDir."/tools/smwadmin/DF_PackageRepository.php");
require_once($rootDir."/tools/smwadmin/DF_Tools.php");


$latest = false;
$createSymlinks = true;
$fileNamecontains = false;
for( $arg = reset( $argv ); $arg !== false; $arg = next( $argv ) ) {

	//-r => repository directory
	if ($arg == '-r') {
		$repositoryDir = next($argv);
		continue;
	}

	// -b => bundle file or directory containing bundles
	if ($arg == '-b') {
		$bundlePath = next($argv);
		continue;
	}

	// --url => base URL for downloads
	if ($arg == '--url') {
		$repositoryURL = next($argv);
		continue;
	}

	if ($arg == '--latest') {
		$latest = true;
		continue;
	}
	if ($arg == '--mediawiki') {
		$mediawiki = true;
		continue;
	}

	if ($arg == '--contains') {
		$fileNamecontains = next($argv);
		continue;
	}
}

if (!isset($repositoryDir) || !isset($bundlePath) || !isset($repositoryURL)) {
	echo "\nUsage: php addBundle2Repository.php -r <repository-dir> -b <bundle file or dir> --url <repository-url>\n";
	die(1);
}

// create symlinks for Linux and Windows 7
if (Tools::isWindows($os) && $latest) {
	$createSymlinks = ($os == 'Windows 7');
	if (!$createSymlinks) {
		echo "Be careful: Cannot create symbolic links on Windows <= 7!";
	}
}

// create binary path
Tools::mkpath($repositoryDir."/bin");

// read bundles and extract the deploy descriptors
echo "\nExtract deploy descriptors";
$descriptors = extractDeployDescriptors($bundlePath, $fileNamecontains);
echo "..done.";

// load existing repository
echo "\nLoading repository...";
$repoDoc = loadRepository($repositoryDir."/repository.xml");
echo "..done.";

$nodeList = $repoDoc->getElementsByTagName("extensions");
$extensionsNode = $nodeList->item(0);

foreach($descriptors as $tuple) {
	list($dd, $zipFilepath) = $tuple;

	// 1. create extensions substructure
	$id = $dd->getID();
	$version = $dd->getVersion();
	$id = strtolower($id);
	echo "\nCreate extension entry for $id";
	Tools::mkpath($repositoryDir."/extensions/$id");
	Tools::unzipFile($zipFilepath, "deploy.xml", $repositoryDir."/extensions/$id");
	rename($repositoryDir."/extensions/$id/deploy.xml", $repositoryDir."/extensions/$id/deploy-$version.xml");
	if ($createSymlinks && $latest) {
		// remove symbolic link if existing
		if (file_exists($repositoryDir."/$id/deploy.xml")) {
			unlink($repositoryDir."/$id/deploy.xml");
		}
		// create symbolic link
		if (Tools::isWindows()) {
			$target = str_replace("/", "\\", "$repositoryDir/extensions/$id/deploy-$version.xml");
			$link = str_replace("/", "\\", "$repositoryDir/extensions/$id/deploy.xml");
			exec("mklink \"$link\" \"$target\"", $out, $res);
		} else{
			exec("ln -s $repositoryDir/extensions/$id/deploy-$version.xml $repositoryDir/extensions/$id/deploy.xml", $out, $res);
		}
		if ($res == 0) print "\n\tCreated link: ".$repositoryDir."/extensions/".$id.'/deploy.xml';
	}
	echo "\n..done.";

	// 2. Add to repository.xml
	echo "\nAdd to repository: ".$dd->getID();
	list($newExt, $extAlreadyExists) = createRepositoryEntry($repoDoc, $dd, $repositoryURL);
	if (!$extAlreadyExists) $extensionsNode->appendChild($newExt);
	echo "..done.";

	// 3. copy binary package
	$targetPackageFile = $repositoryDir."/bin/$id-".Tools::addSeparators($dd->getVersion(), $dd->getPatchlevel()).".zip";
	echo "\nCopy package $id to $targetPackageFile";
	copy($zipFilepath, $targetPackageFile);
	echo "..done.";
}

if ($mediawiki) {
	$version = Tools::getMediawikiVersion(realpath($rootDir."/../"));
	$version = str_replace(".", "", $version);
	$xml = <<<ENDS
<?xml version="1.0" encoding="ISO-8859-1"?>
<deploydescriptor>
    <global>
        <version>$version</version>
        <id>mw</id>
        <instdir></instdir>
        <vendor>Ontoprise GmbH</vendor>
        <maintainer>Wikimedia foundation</maintainer>
        <description>MediaWiki is a free software open source wiki package written in PHP, originally for use on Wikipedia.</description>
        <helpurl>http://www.mediawiki.org/wiki/MediaWiki</helpurl>
        <license>GPL-v2</license>

        <dependencies>
    </dependencies>
    </global>
    <wikidumps>
    
    </wikidumps>
    <resources>
    
    </resources>
    <configs>
     <new>

       <script file="maintenance/update.php" />
     </new>
    </configs>
</deploydescriptor>
	

ENDS;
	$id = 'mw';
	Tools::mkpath($repositoryDir."/extensions/$id");
	$handle = fopen($repositoryDir."/extensions/$id/deploy-$version.xml", "w");
	fwrite($handle, $xml);
	fclose($handle);

	// creates links
	if ($createSymlinks && $latest) {
		// remove symbolic link if existing
		if (file_exists($repositoryDir."/extensions/$id/deploy.xml")) {
			unlink($repositoryDir."/extensions/$id/deploy.xml");
		}
		// create symbolic link
		if (Tools::isWindows()) {
			$target = str_replace("/", "\\", "$repositoryDir/extensions/$id/deploy-$version.xml");
			$link = str_replace("/", "\\", "$repositoryDir/extensions/$id/deploy.xml");
			exec("mklink \"$link\" \"$target\"", $out, $res);
		} else{
			exec("ln -s $repositoryDir/extensions/$id/deploy-$version.xml $repositoryDir/extensions/$id/deploy.xml", $out, $res);
		}
		if ($res == 0) print "\n\tCreated link: ".$repositoryDir."/extensions/".$id.'/deploy.xml';
	}

	$dd = new DeployDescriptor($xml);

	echo "\nAdd to repository: ".$id;
	list($newExt, $extAlreadyExists) = createRepositoryEntry($repoDoc, $dd, $repositoryURL);
	if (!$extAlreadyExists) $extensionsNode->appendChild($newExt);
	echo "..done.";

	// assume binary package exists

}

// save repository.xml
echo "\nSave repository";
saveRepository($repositoryDir."/repository.xml", $repoDoc);
echo "..done.";
echo "\ns";




/**
 * Save a repository.
 *
 * @param $filePath
 * @param $doc
 */
function saveRepository($filePath, $doc) {
	$xml = $doc->saveXML();
	$handle = fopen($filePath, "w");
	fwrite($handle, $xml);
	fclose($handle);
}

/**
 * Load a repository
 *
 * @param $filePath
 */
function loadRepository($filePath) {
	$xml = file_get_contents($filePath);
	return DOMDocument::loadXML($xml);
}

/**
 * Extracts the deploy descriptor(s) from a bundle or a set of bundles.
 *
 * @param string $bundlePath (file or directory)
 * @param string $fileNamecontains Filter for files
 * @return array of (DeployDescriptor, Bundle file path)
 */
function extractDeployDescriptors($bundlePath, $fileNamecontains = false) {
	$tmpFolder = Tools::isWindows() ? 'c:\temp\mw_deploy_tool' : '/tmp/mw_deploy_tool';
	if (is_dir($bundlePath)) {
		$result = array();
		$dirHandle=opendir($bundlePath);
		while(false !== ($file=readdir($dirHandle))) {
			if($file!="." && $file!="..") {
				$fileExtension = Tools::getFileExtension($file);
				if (strtolower($fileExtension) != 'zip') continue;
				if ($fileNamecontains !== false) {
					if (strpos($file, $fileNamecontains) === false) continue;
				}
				$__file=$bundlePath."/".$file;
				$dd = Tools::unzipDeployDescriptor($__file, $tmpFolder);
				if (is_null($dd)) {
					print "\nWARNING: $__file does not contain a deploy descriptor. It is skipped.";
					continue;
				}
				$result[] = array($dd, $__file);
			}
		}
		return $result;
	} else {
		$dd = Tools::unzipDeployDescriptor($bundlePath, $tmpFolder);
		return array(array($dd, $bundlePath));
	}
}

function createRepositoryEntry($repoDoc, $dd, $repositoryURL) {
	
	// find existing extension
	$nodeList = $repoDoc->getElementsByTagName("extension");
	$i=0;
	$newExt = NULL;
	
	while($i < $nodeList->length) {
		$ext = $nodeList->item($i);
		$id = $ext->getAttribute("id");
		if ($id == $dd->getID()) {
			$newExt = $ext;
		}
		$i++;
	}
	
    $extAlreadyExists = true;
	if (is_null($newExt)) {
		// create new extension node
		$extAlreadyExists = false;
		$newExt = $repoDoc->createElement("extension");
		$idAttr = $repoDoc->createAttribute("id");
		$idAttr->value = $dd->getID();
		$newExt->appendChild($idAttr);
	}

	$newVer = $repoDoc->createElement("version");
	$newExt->appendChild($newVer);

	$urlAttr = $repoDoc->createAttribute("url");
	$urlAttr->value = $repositoryURL."/bin/".$dd->getID()."-".Tools::addSeparators($dd->getVersion(), $dd->getPatchlevel()).".zip";
	$newVer->appendChild($urlAttr);

	$versionAttr = $repoDoc->createAttribute("ver");
	$versionAttr->value = $dd->getVersion();
	$newVer->appendChild($versionAttr);


	$patchlevelAttr = $repoDoc->createAttribute("patchlevel");
	$patchlevelAttr->value = $dd->getPatchlevel();
	$newVer->appendChild($patchlevelAttr);

	$maintainerAttr = $repoDoc->createAttribute("maintainer");
	$maintainerAttr->value = $dd->getMaintainer();
	$newVer->appendChild($maintainerAttr);

	$descriptionAttr = $repoDoc->createAttribute("description");
	$descriptionAttr->value = $dd->getDescription();
	$newVer->appendChild($descriptionAttr);

	$helpurlAttr = $repoDoc->createAttribute("helpurl");
	$helpurlAttr->value = $dd->getHelpURL();
	$newVer->appendChild($helpurlAttr);

	return array($newExt, $extAlreadyExists);
}



