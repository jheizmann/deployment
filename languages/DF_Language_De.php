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

require_once('DF_Language.php');
/**
 * Language abstraction.
 *
 * @author: Kai Kühn
 *
 */
class DF_Language_De extends DF_Language {
	protected $language_constants = array(
	'df_ontologyversion' => 'Ontologieversion',
	'df_patchlevel' => 'Patchlevel',
	'df_partofbundle' => 'Teil des Pakets',
	
	'df_dependencies'=> 'Abhängigkeit',
    'df_instdir' => 'Installationsverzeichnis',

    'df_rationale' => 'Beschreibung',
	'df_maintainer' => 'Entwickler',
    'df_vendor' => 'Anbieter',
    'df_helpurl' => 'Hilfs-URL',
    'df_license' => 'Lizenz',
	'df_contentbundle' => 'Content bundle',
    'df_ontologyuri' => 'Ontologie URI',
	'df_usesprefix' => 'Bundle benutzt Namensraumpräfix',
	'df_mwextension' => 'Mwextension',
	'df_minversion' => 'Minversion',
    'df_maxversion' => 'Maxversion',

	
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
	'df_webadmin' => 'Wiki Administration Tool',
	'df_username' => 'Benutzername',
	'df_password' => 'Passwort',
	'df_login' => 'Einloggen',
	'df_linktowiki' => 'Gehe zum Wiki',
    'df_logout' => 'Ausloggen',
	'df_webadmin_login_failed' => 'Login fehlgeschlagen',
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
	'df_webadmin_checkdependency' => 'Prüfe auf Abhängigkeiten',
    'df_webadmin_remove' => 'Datei entfernen',
	'df_webadmin_addrepository' => 'Repository hinzufügen',
	'df_webadmin_removerepository' => 'Repository löschen',

	'df_webadmin_statustab' => 'Status',
    'df_webadmin_searchtab' => 'Suche Extensions',
    'df_webadmin_maintenacetab' => 'Systemwiederherstellung',
	'df_webadmin_uploadtab' => 'Hochladen von lokalen Bundles/Ontologien',
	'df_webadmin_settingstab' => 'Registrierte Paketquellen',
	'df_webadmin_localsettingstab' => 'LocalSettings',
	'df_webadmin_serverstab' => 'Server',

	'df_webadmin_restorepoint' => 'Rücksetzpunkt',
	'df_webadmin_creationdate' => 'Datum der Erzeugung',
	'df_webadmin_version' => 'Version',
	'df_webadmin_upload' => 'Hochladen',
	'df_webadmin_restore' => 'Wiederherstellen',
    'df_webadmin_removerestore' => 'Lösche',
	'df_webadmin_nothingfound' => 'Keine passenden Pakete für <b>"$1"</b> gefunden!',
	'df_webadmin_searchinfoifnothingfound' => 'Um das Ontoprise-Repository zu browsen klicken Sie hier: ',
	'df_webadmin_norestorepoints' => 'Keine Wiederherstellungspunkte gefundend.',
	'df_webadmin_nouploadedfiles' => 'Keine Dateien gefunden.',

	'df_webadmin_maintenancetext' => 'Dieser Tab erlaubt das Erzeugen und Wiederherstellen von Wiederherstellungspunkt. Wiederherstellungspunkte speichern den Zustand einer Wiki-Installation inkl. Daten..',
	'df_webadmin_settingstext' =>  'Dieser Tab erlaubt das Hinzufügen und Löschen von Repository-URLs.',
	'df_restore_warning' => 'Ein Rücksetzen bewirkt sowohl die Zurücksetzung der Installation wie auch des Inhalts des Wikis. Wollen Sie fortfahren?',
	'df_remove_restore_warning' => 'Wiederherstellungspunkt wird gelöscht!',
    'df_uninstall_warning' => 'Die folg. Extensions werden de-installiert. Wollen Sie fortfahren?',
    'df_globalupdate_warning' => 'Global Update durchführen?',
	'df_checkextension_heading' => 'Extension-Details',
	'df_select_extension' => 'Wähle Extension',
	'df_webadmin_localsettings_description' => "Editieren die Konfiguration einer Extension in LocalSettings.php. Nicht vergessen die Änderungen zu speichern. Wenn Sie die Datei LocalSettings.php in irgendeiner Form manuell editieren, verändern Sie nicht die Extension-Tags, sonst geht diese Ansicht kaputt.",
	
	'df_webadmin_configureservers' => 'Hier können Sie die Server ihrer Wiki-Installation neu starten oder stoppen. Bitte warten Sie ein paar Sekunden bis WAT den Status neu geladen hat.',
	'df_webadmin_process_runs' => 'läuft',
    'df_webadmin_process_doesnot_run' => 'läuft nicht',
	'df_webadmin_process_unknown' => 'lädt neu...',
	'df_webadmin_server_execute' => 'Ausführen',
    'df_webadmin_server_start' => 'start',
    'df_webadmin_server_end' => 'stop',
	'df_webadmin_refresh' => 'Aktualisieren',
	'df_webadmin_upload_message' => 'Hier können Sie Bundles ($1) und Ontologie-Dateien ($2) hochladen',
	
	'df_webadmin_newreleaseavailable' => 'Neues Release verfügbar! Sehen Sie in der $1 nach.',
    'df_webadmin_repository_link' => 'Repository-Liste',
	
	
	/*Message for ImportOntologyBot*/
    'smw_importontologybot' => 'Importiere eine Ontologie',
    'smw_gard_import_choosefile' => 'Die folgenden Datei-Typen $1 sind erlaubt.',
    'smw_gard_import_addfiles' => 'Füge $2-Dateien hinzu per $1.',
    'smw_gard_import_nofiles' => 'Keine Dateien des Typs $1 verfügbar.',
    'smw_gard_import_docu' => 'Importiert eine Ontologie-Datei.',
    'smw_df_missing' => 'Um die Gardening Bots nutzen zu können müssen Sie das Wiki admin tool installieren! <br/> Folgen Sie dem Link für weitere Informationen: '
    
	);
}
