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
 * Status tab
 *
 * @author: Kai Kühn / ontoprise / 2011
 *
 */
if (!defined("DF_WEBADMIN_TOOL")) {
    die();
}

require_once ( $mwrootDir.'/deployment/tools/smwadmin/DF_PackageRepository.php' );
require_once ( $mwrootDir.'/deployment/tools/maintenance/maintenanceTools.inc' );

class DFStatusTab {

	/**
	 * Status tab
	 *
	 */
	public function __construct() {

	}
	
	public function getTabName() {
		global $dfgLang;
		return $dfgLang->getLanguageString('df_webadmin_statustab');
	}

	public function getHTML() {
		global $mwrootDir;
		global $dfgOut, $dfgLang;
		$cc = new ConsistencyChecker($mwrootDir);
		$html = "";
		$localPackages = PackageRepository::getLocalPackages($mwrootDir);
		$dfgOut->setVerbose(false);
		$updates = $cc->checksForUpdates();
		if (count($updates) > 0) {
			$html .= "<div id=\"df_updateavailable\">".$dfgLang->getLanguageString('df_webadmin_updatesavailable');
			$html .= "<input type=\"button\" value=\"Global update\" id=\"df_global_update\"></input>";
			$html .= "</div>";
		}
		
		$dfgOut->setVerbose(true);
		$html .= "<table id=\"df_statustable\">";
		$html .= "<th>";
		$html .= $dfgLang->getLanguageString('df_webadmin_extension');
		$html .= "</th>";
		$html .= "<th>";
        $html .= $dfgLang->getLanguageString('df_webadmin_version');
        $html .= "</th>";
		$html .= "<th>";
		$html .= $dfgLang->getLanguageString('df_webadmin_description');
		$html .= "</th>";
		$html .= "<th>";
		$html .= $dfgLang->getLanguageString('df_webadmin_action');
		$html .= "</th>";
		ksort($localPackages);
		$i=0;
		foreach($localPackages as $id => $p) {
			$j = $i % 2;
			$html .= "<tr class=\"df_row_$j\">";
			$i++;
			$html .= "<td class=\"df_extension_id\">";
			$html .= $id;
			$html .= "</td>";
			$html .= "<td class=\"df_extension_version\">";
            $html .= Tools::addVersionSeparators(array($p->getVersion(), $p->getPatchlevel()));
            $html .= "</td>";
			$html .= "<td class=\"df_description\">";
			$html .= $p->getDescription();
			$html .= "</td>";
			$html .= "<td class=\"df_actions\">";
			$updateText = $dfgLang->getLanguageString('df_webadmin_update');
			$deinstallText = $dfgLang->getLanguageString('df_webadmin_deinstall');
			if (array_key_exists($id, $updates)) {
				$html .= "<input type=\"button\" class=\"df_update_button\" value=\"$updateText\" id=\"df_update__$id\"></input>";
				$html .= "<input type=\"button\" class=\"df_deinstall_button\" value=\"$deinstallText\" id=\"df_deinstall__$id\"></input>";
			} else {
				$html .= "<input type=\"button\" class=\"df_update_button\" value=\"$updateText\" id=\"df_update__$id\" disabled=\"true\"></input>";
				$html .= "<input type=\"button\" class=\"df_deinstall_button\" value=\"$deinstallText\" id=\"df_update__$id\"></input>";
			}
			$html .= "</td>";
			$html .= "</tr>";
		}
		$html .= "</table>";
		return $html;
	}


}