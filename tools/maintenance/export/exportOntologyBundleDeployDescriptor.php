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
 * @ingroup DFMaintenance
 * 
 * Exports the deploy descriptor for an ontology bundle.
 *
 * @author: Kai K�hn
 */

// get root dir of DF
global $rootDir;
$rootDir = dirname(__FILE__);
$rootDir = str_replace("\\", "/", $rootDir);
$rootDir = realpath($rootDir."/../../../");
require_once($rootDir. '/../maintenance/commandLine.inc' );
require_once($rootDir.'/tools/smwadmin/DF_Tools.php');
require_once($rootDir.'/io/export/DF_DeployUploadExporter.php');

$langClass = "DF_Language_$wgLanguageCode";
if (!file_exists("../../../languages/$langClass.php")) {
	$langClass = "DF_Language_En";
}
require_once("../../../languages/$langClass.php");
$dfgLang = new $langClass();

for( $arg = reset( $argv ); $arg !== false; $arg = next( $argv ) ) {

	//-b => Bundle to export
	if ($arg == '-b') {
		$bundleToExport = next($argv);
		if ($bundleToExport === false) fatalError("No bundle given.");
		$bundleToExport = strtoupper(substr($bundleToExport, 0,1)).substr($bundleToExport,1);
		continue;
	} else if ($arg == '-o') {
		$output = next($argv);
		if ($output === false) fatalError("No output file given");
		 
		continue;
	} else if ($arg == '-d') {
		$dumpFile = next($argv);
		if ($dumpFile === false) fatalError("No dump file given");
		 
		continue;
	} else if ($arg == '--nocat') {
		$noCat = true;
		continue;
	}
}

// check bundle page
$bundlePage = Title::newFromText($bundleToExport, NS_MAIN); 
if (!$bundlePage->exists()) {
	print "\n\n".$bundlePage->getText()." does not exist. Please create first.";
	die();
}

// check if relevant package properties exist
if (Tools::checkPackageProperties() === false) {
	print "\n\nCorrect the errors and try again!\n";
    die();
}

dumpDescriptor($bundleToExport, $output, $dumpFile);

function dumpDescriptor($bundeID, $output = "deploy.xml", $dumpFile = "dump.xml") {
	global $dfgLang, $noCat;
	$dependencies_p = SMWPropertyValue::makeUserProperty($dfgLang->getLanguageString('df_dependencies'));

	$instdir_p = SMWPropertyValue::makeUserProperty($dfgLang->getLanguageString('df_instdir'));
	$ontologyversion_p = SMWPropertyValue::makeUserProperty($dfgLang->getLanguageString('df_ontologyversion'));
	$ontologyvendor_p = SMWPropertyValue::makeUserProperty($dfgLang->getLanguageString('df_ontologyvendor'));
	$description_p = SMWPropertyValue::makeUserProperty($dfgLang->getLanguageString('df_description'));

	$bundlePage = Title::newFromText($bundeID);
	$dependencies = smwfGetStore()->getPropertyValues($bundlePage, $dependencies_p);
	$version = smwfGetStore()->getPropertyValues($bundlePage, $ontologyversion_p);
	$instdir = smwfGetStore()->getPropertyValues($bundlePage, $instdir_p);
	$vendor = smwfGetStore()->getPropertyValues($bundlePage, $ontologyvendor_p);
	$description = smwfGetStore()->getPropertyValues($bundlePage, $description_p);

	if ( count($version) == 0) {
		fwrite( STDERR , "No version annotation on $bundeID" . "\n" );
	}
	if ( count($vendor) == 0) {
		fwrite( STDERR , "No vendor annotation on $bundeID" . "\n" );
	}
	if ( count($instdir) == 0) {
		fwrite( STDERR , "No instdir annotation on $bundeID" . "\n" );
	}
	if ( count($description) == 0) {
		fwrite( STDERR , "No description annotation on $bundeID" . "\n" );
	}

	$versionText = count($version) > 0 ? Tools::getXSDValue(reset($version)) : "100";
	$vendorText = count($vendor) > 0 ? Tools::getXSDValue(reset($vendor)) : "no vendor";
	$instdirText = count($instdir) > 0 ? Tools::getXSDValue(reset($instdir)) : "extensions/$bundeID";
	$descriptionText = count($description) > 0 ? Tools::getXSDValue(reset($description)) : "no description";

	$handle = fopen("$output", "w");
	$src = dirname(__FILE__)."/../../../";
	$dest = dirname($output);
	$options['used'] = true;
	$options['shared'] = true;
	$options['noCat'] = $noCat;
	$uploadExporter = new DeployUploadExporter( $options, $bundeID, $handle, $src, $dest );

	$xml = '<?xml version="1.0" encoding="ISO-8859-1"?>'."\n";
	$xml .= '<deploydescriptor>'."\n";
	$xml .= "\t".'<global>'."\n";
	$xml .= "\t\t".'<version>'.$versionText.'</version>'."\n";
	$xml .= "\t\t".'<id>'.strtolower($bundeID).'</id>'."\n";
	$xml .= "\t\t".'<instdir>'.strtolower($instdirText).'</instdir>'."\n";
	$xml .= "\t\t".'<vendor>'.$vendorText.'</vendor>'."\n";
	$xml .= "\t\t".'<description>'.$descriptionText.'</description>'."\n";
	$xml .= "\t\t".'<dependencies>'."\n";
	foreach($dependencies as $dep) {
		$dvs = $dep->getDVs();
		if (count($dvs) != 3) {
		  print "\nWarning: Wrong dependency annotation. Ignore it.";
		  continue;	
		}
		$id = Tools::getXSDValue(reset($dvs));
		$minVersion = Tools::getXSDValue(next($dvs));
		$maxVersion = Tools::getXSDValue(next($dvs));
		$xml .= "\t\t\t".'<dependency from="'.$minVersion.'" to="'.$maxVersion.'">'.$id.'</dependency>'."\n";
	}
	$xml .= "\t".'</dependencies>'."\n";

	$xml .= "\t".'</global>'."\n";
	$xml .= "\t".'<wikidumps>'."\n";
	$xml .= "\t\t".'<file loc="'.$dumpFile.'"/>'."\n";
	$xml .= "\t".'</wikidumps>'."\n";
	$xml .= "\t".'<resources>'."\n";
	fwrite($handle, $xml);
	$uploadExporter->run();
	$xml = "\t".'</resources>'."\n";
	$xml .= '</deploydescriptor>'."\n";
	fwrite($handle, $xml);
	fclose($handle);
}

function fatalError($text) {
	print "\n\n".$text;
	die();
}
