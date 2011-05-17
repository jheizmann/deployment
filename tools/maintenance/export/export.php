<?php
/**
 * Copyright (C) 2005 Brion Vibber <brion@pobox.com>
 * http://www.mediawiki.org/
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup DFMaintenance
 */

$originalDir = getcwd();

$optionsWithArgs = array( 'pagelist', 'start', 'end' );

// get root dir of DF
global $rootDir;
$rootDir = dirname(__FILE__);
$rootDir = str_replace("\\", "/", $rootDir);
$rootDir = realpath($rootDir."/../../../");

require_once( $rootDir.'/../maintenance/commandLine.inc' );
require_once( $rootDir.'/../maintenance/backup.inc' );
require_once( $rootDir.'/io/export/DF_DeployWikiExporter.php');
require_once( $rootDir.'/tools/smwadmin/DF_Tools.php');


$langClass = "DF_Language_".ucfirst($wgLanguageCode);

if (!file_exists("../../../languages/$langClass.php")) {
	$langClass = "DF_Language_En";
}
require_once("../../../languages/$langClass.php");
$dfgLang = new $langClass();

// check if required properties exist
// return false if not
$check = Tools::checkPackageProperties();
if (!$check) {
	print "\n\nCorrect the errors and try again!\n";
	die();
}

$dumper = new DeployBackupDumper( $argv );

if( isset( $options['quiet'] ) ) {
	$dumper->reporting = false;
}

if ( isset( $options['pagelist'] ) ) {
	$olddir = getcwd();
	chdir( $originalDir );
	$pages = file( $options['pagelist'] );
	chdir( $olddir );
	if ( $pages === false ) {
		wfDie( "Unable to open file {$options['pagelist']}\n" );
	}
	$pages = array_map( 'trim', $pages );
	$dumper->pages = array_filter( $pages, create_function( '$x', 'return $x !== "";' ) );
}

if( isset( $options['start'] ) ) {
	$dumper->startId = intval( $options['start'] );
}
if( isset( $options['end'] ) ) {
	$dumper->endId = intval( $options['end'] );
}
$dumper->skipHeader = isset( $options['skip-header'] );
$dumper->skipFooter = isset( $options['skip-footer'] );
$dumper->dumpUploads = isset( $options['uploads'] );

$textMode = isset( $options['stub'] ) ? DeployWikiExporter::STUB : DeployWikiExporter::TEXT;

if( isset( $options['full'] ) ) {
	$dumper->dump( DeployWikiExporter::FULL, $textMode );
} elseif( isset( $options['current'] ) ) {
	$dumper->dump( DeployWikiExporter::CURRENT, $textMode );

} else {
	$dumper->progress( <<<ENDS
This script dumps the wiki page database into an XML interchange wrapper
format for export or backup.

XML output is sent to stdout; progress reports are sent to stderr.

Usage: php export.php <action> [<options>]
Actions:
  --full      Dump complete history of every page.
  --current   Includes only the latest revision of each page.

  --includeInstances=true|false|yes|no|1|0
	  includes all instances of all categories belonging to exported 
	  bundle.  default=false
  --includeTemplates=true|false|yes|no|1|0
	  includes all templates used by at least one page belonging to exported 
	  bundle.  default=true
  
  
Options:
  --quiet     Don't dump status reports to stderr.
  --report=n  Report position and speed after every n pages processed.
              (Default: 100)
  --server=h  Force reading from MySQL server h
  --start=n   Start from page_id n
  --end=n     Stop before page_id n (exclusive)
  --skip-header Don't output the <mediawiki> header
  --skip-footer Don't output the </mediawiki> footer
  --stub      Don't perform old_text lookups; for 2-pass dump
  --uploads   Include upload records (experimental)

Fancy stuff:
  --plugin=<class>[:<file>]   Load a dump plugin class
  --output=<type>:<file>      Begin a filtered output stream;
                              <type>s: file, gzip, bzip2, 7zip
  --filter=<type>[:<options>] Add a filter on an output branch

ENDS
	);

}




