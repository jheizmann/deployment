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
require_once('DF_Language.php');
/**
 * Language abstraction.
 * 
 * @author: Kai Kühn / ontoprise / 2009
 *
 */
class DF_Language_De extends DF_Language {
	protected $language_constants = array(
	'df_ontologyversion' => 'Ontologieversion',
	'df_partofbundle' => 'Teil des Pakets',
	'df_contenthash' => 'Inhaltshash',
	'df_dependencies'=> 'Abhängigkeit',
    'df_instdir' => 'Installationsverzeichnis',
    
    'df_rationale' => 'Beschreibung',
	'df_maintainer' => 'Entwickler',
    'df_vendor' => 'Anbieter',
    'df_helpurl' => 'Hilfs-URL',
    'df_license' => 'Lizenz',
	'df_contentbundle' => 'Content bundle',
    'df_ontologyuri' => 'Ontologie URI',
	
	'checkinstallation' => 'Prüfe Installation',
	'category' => 'Kategorie',
	'is_inverse_of' => 'Ist invers zu',
    'has_domain_and_range' => 'Hat Domain und Range',
	'imported_from'=>'Importiert aus',
	
	'df_namespace_mappings_page' => 'NamespaceMappings',
	
	// user
    'checkinstallation' => 'Prüfe Installation',
    'df_checkforupdates' => 'Prüfe auf Updates',
	'df_updatesavailable' => 'Updates verfügbar!',
    'df_updateforextensions' => 'Es gibt Updates für folgende Extensions:',
    'df_noupdatesfound' => 'Keine Updates gefunden!',
    'df_installationpath_heading' => "Installationsverzeichnis des Deployment-Frameworks",
       
    'df_warn' => 'WARNUNG',
    'df_error' => 'FEHLER',
    'df_fatal' => 'FATALER FEHLER',
    'df_failed' => 'FEHLGESCHLAGEN',
    'df_ok' => 'OK',
	
	// webadmin
	'df_linktowiki' => 'Gehe zum Wiki',
    'df_logout' => 'Ausloggen',
	'df_webadmin_findall' => 'Suche all',
	'df_webadmin_about' => 'Über',
	'df_webadmin_status_text' => 'Diese Tabelle zeigt all Pakete und Ontologien, die derzeit in ihrem Wiki installiert sind.',
	'df_webadmin_updatesavailable' => 'Updates verfügbar! Machen Sie ein ',
	'df_webadmin_globalupdate' => 'Globales Update',
	'df_webadmin_extension' => 'Extension',
    'df_webadmin_description' => 'Beschreibung',
    'df_webadmin_action' => 'Aktion',
	'df_webadmin_install' => 'Installieren',
    'df_webadmin_deinstall' => 'De-Installieren',
	'df_webadmin_update' => 'Aktualisieren',
    'df_webadmin_remove' => 'Datei entfernen',
	
	'df_webadmin_statustab' => 'Status',
    'df_webadmin_searchtab' => 'Suche Extensions',
    'df_webadmin_maintenacetab' => 'Systemwiederherstellung',
	'df_webadmin_uploadtab' => 'Hochladen von lokalen Bundles/Ontologien',
	'df_webadmin_settingstab' => 'Paketquellen',
	
	'df_webadmin_restorepoint' => 'Rücksetzpunkt',
	'df_webadmin_creationdate' => 'Datum der Erzeugung',
	'df_webadmin_version' => 'Version',
	'df_webadmin_upload' => 'Hochladen',
	'df_webadmin_nothingfound' => 'Keine passenden Pakete für <b>"{{search-value}}"</b> gefunden!',
	'df_webadmin_searchinfoifnothingfound' => 'Um das Ontoprise-Repository zu browsen klicken Sie hier: ',
	'df_webadmin_norestorepoints' => 'Keine Wiederherstellungspunkte gefundend.',
	'df_webadmin_nouploadedfiles' => 'Keine Dateien gefunden.',
	
	'df_restore_warning' => 'Ein Rücksetzen bewirkt sowohl die Zurücksetzung der Installation wie auch des Inhalts des Wikis. Wollen Sie fortfahren?',
    'df_uninstall_warning' => 'Die folg. Extensions werden de-installiert. Wollen Sie fortfahren?',
    'df_globalupdate_warning' => 'Global Update durchführen?',
	'df_checkextension_heading' => 'Extension-Details'
	);
}
