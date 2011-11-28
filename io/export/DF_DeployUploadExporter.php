<?php
/*
 * Copyright (C) Vulcan Inc.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program.If not, see <http://www.gnu.org/licenses/>.
 *
 */

/**
 * @file
 * @ingroup DFIO
 *
 * @defgroup DFIO Input/Output
 * @ingroup DeployFramework
 *
 * Exports uploaded files contained in one bundle.
 *
 * derived from
 *   Copyright (C) 2003, 2005, 2006 Brion Vibber <brion@pobox.com>
 *   http://www.mediawiki.org/
 */
class DeployUploadExporter {

	/**
	 * Create DeployUploadExporter
	 *
	 * @param array $args
	 * @param stream $filehandle
	 * @param directory $src
	 * @param directory $dest
	 */
	function __construct( $args, $bundleID, $filehandle = NULL, $src = NULL, $dest = NULL ) {
		global $IP, $wgUseSharedUploads;
		$this->mAction = 'fetchLocal';
		$this->mBasePath = $IP;
		$this->mShared = false;
		$this->mSharedSupplement = false;
		$this->bundleID = $bundleID;
		$this->filehandle = $filehandle;
		$this->src = $src;
		$this->dest = $dest;

		if( isset( $args['help'] ) ) {
			$this->mAction = 'help';
		}

		if( isset( $args['base'] ) ) {
			$this->mBasePath = $args['base'];
		}

		if( isset( $args['local'] ) ) {
			$this->mAction = 'fetchLocal';
		}

		if( isset( $args['used'] ) ) {
			$this->mAction = 'fetchUsed';
		}

		if( isset( $args['shared'] ) ) {
			if( isset( $args['used'] ) ) {
				// Include shared-repo files in the used check
				$this->mShared = true;
			} else {
				// Grab all local *plus* used shared
				$this->mSharedSupplement = true;
			}
		}

		// includeInstances means: consider member of categories beloning to a bundle
		if( isset( $args['includeInstances'] ) ) {
			$this->includeInstances = $args['includeInstances'];
		} else {
			// default setting is: false
			$this->includeInstances = false;
		}

		// includeImages means: consider images linked with pages of bundles
		if( isset( $args['includeImages'] ) ) {
			$this->includeImages = $args['includeImages'];
		} else {
			// default setting is: false
			$this->includeImages = false;
		}
	}

	function run() {
		$this->{$this->mAction}( $this->mShared );
		if( $this->mSharedSupplement ) {
			$this->fetchUsed( true );
		}
	}



	/**
	 * Fetch a list of all or used images from a particular image source.
	 * @param string $table
	 * @param string $directory Base directory where files are located
	 * @param bool $shared true to pass shared-dir settings to hash func
	 */
	function fetchUsed( $shared ) {
		global $dfgLang;
		$dbr = wfGetDB( DB_SLAVE );

		$smwids     = $dbr->tableName( 'smw_ids' );
		$smwrels     = $dbr->tableName( 'smw_rels2' );
		$page = $dbr->tableName( 'page' );
		$categorylinks = $dbr->tableName( 'categorylinks' );
		$image = $dbr->tableName( 'image' );
		$imagelinks = $dbr->tableName( 'imagelinks' );

		$partOfBundlePropertyID = smwfGetStore()->getSMWPropertyID(SMWDIProperty::newFromUserLabel($dfgLang->getLanguageString("df_partofbundle")));
		$partOfBundleID = smwfGetStore()->getSMWPageID($this->bundleID, NS_MAIN, "", "");

		// get all image pages beloning to pages of bundle
		if ($this->includeImages) {
       
			$sql = "SELECT DISTINCT il_to AS image FROM $page JOIN $smwids ON smw_title = page_title AND smw_namespace = page_namespace JOIN $smwrels ON smw_id = s_id JOIN $imagelinks ON page_id = il_from WHERE  p_id = $partOfBundlePropertyID AND o_id = $partOfBundleID";

			if ($this->includeInstances) {
				// get all images pages belonging to instances of categories of bundle
				$sql2 = "SELECT DISTINCT il_to AS image FROM $page JOIN $categorylinks ON page_id = cl_from JOIN $smwids ON smw_title = cl_to AND smw_namespace = ".NS_CATEGORY." JOIN $smwrels ON smw_id = s_id JOIN $imagelinks ON page_id = il_from WHERE p_id = $partOfBundlePropertyID AND o_id = $partOfBundleID";

				$res = $dbr->query( "($sql) UNION DISTINCT ($sql2)" );
			} else {
				$res = $dbr->query( $sql );
			}
		} else {
		
			$sql = "SELECT DISTINCT smw_title AS image FROM $smwids JOIN $smwrels ON smw_id = s_id WHERE p_id = $partOfBundlePropertyID AND o_id = $partOfBundleID AND smw_namespace = ".NS_FILE;
			$res = $dbr->query( $sql );	
		}

		if($dbr->numRows( $res ) > 0) {
			while($row = $dbr->fetchObject($res)) {

				$this->outputItem( $row->image, true );
			}
		}

		$dbr->freeResult( $res );
	}



	function fetchLocal( $shared ) {
		$dbr = wfGetDB( DB_SLAVE );
		$result = $dbr->select( 'image',
		array( 'img_name' ),
            '',
		__METHOD__ );

		foreach( $result as $row ) {
			$this->outputItem( $row->img_name, $shared );
		}
		$dbr->freeResult( $result );
	}

	function outputItem( $name, $shared ) {
		$file = wfFindFile( $name );
			
		if( $file && $this->filterItem( $file, $shared ) ) {
			$filename = $file->getFullPath();
			$rel = wfRelativePath( $filename, $this->mBasePath );
			if (!is_null($this->filehandle)) {
				$rel = Tools::makeUnixPath($rel);
				fwrite($this->filehandle, "\t\t<file loc=\"$rel\"/>\n");
				if (!is_null($this->src) && !is_null($this->dest) ) {

					$path = dirname($rel);
					Tools::mkpath($this->dest."/$path");
					copy($this->src."../$rel", $this->dest."/$rel");
				}
			} else {
				echo "$rel\n";
			}
		} else {
				
			wfDebug( __METHOD__ . ": base file? $name\n" );
		}
	}

	function filterItem( $file, $shared ) {
		return $shared || $file->isLocal();
	}
}

