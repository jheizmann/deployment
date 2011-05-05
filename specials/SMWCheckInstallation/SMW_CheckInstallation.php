<?php
/**
 * @file
 * @ingroup SMWCheckInstallation
 *
 * @defgroup SMWCheckInstallation
 * @ingroup SMWHaloSpecials
 *
 * @author Kai Kühn
 */
if (!defined('MEDIAWIKI')) die();

global $IP;
require_once( $IP . "/includes/SpecialPage.php" );

/*
 * Standard class that is resopnsible for the creation of the Special Page
 */
class SMWCheckInstallation extends SpecialPage {
	public function __construct() {
		parent::__construct('CheckInstallation');
	}
	/*
	 * Overloaded function that is responsible for the creation of the Special Page
	 */
	public function execute($par) {
		global $wgOut,$wgScriptPath, $wgRequest;
		$wgOut->addLink(array('rel'   => 'stylesheet','type'  => 'text/css',
                        'media' => 'screen, projection','href'  => $wgScriptPath . '/deployment/skins/df.css'));
			
		$wgOut->setPageTitle(wfMsg('checkinstallation'));
		$wgOut->addHTML("<h2>".wfMsg('checkinstallation')."</h2>");


		global $IP;
		global $rootDir;
		$rootDir = "$IP/deployment";
		if (!file_exists("$IP/deployment/tools/maintenance/maintenanceTools.inc")) {
			$wgOut->addHTML("No deployment framework installed! Please install to use this feature.");
		}
		require_once "$IP/deployment/tools/maintenance/maintenanceTools.inc";
		$cc = new ConsistencyChecker($IP);
		
		// (1) "check for updates" button
		$html = "";
		if (!is_null($wgRequest->getVal('checkforupdates'))) {
			$html .= "<div id=\"df_updatesection\">";
			$updates = $cc->checksForUpdates();
			if (count($updates) == 0) {
				$html .= wfMsg('df_noupdatesfound');
			} else{
				$html .= wfMsg('df_updateforextensions');
				$html .= "<table>";
				foreach($updates as $tuple) {
                    list($id, $version, $patchlevel) = $tuple;
					$html .= "<tr><td>$id</td><td>".Tools::addSeparators($version, $patchlevel)."</td></tr>";
				}
				$html .= "</table>";
			}
			$html .= "</div><br/>";
		} else {
			$html .= "<form><input name=\"checkforupdates\" type=\"submit\" value=\"".wfMsg('df_checkforupdates')."\"/></form>";
			$html .= "<br/>";
		}
		$wgOut->addHTML($html);

        // (2) consistency checks
        
		$errorsFound = false;
		//$errorsFound |= $cc->checkDependencies(false, DF_OUTPUT_FORMAT_HTML);
		$errorsFound |=$cc->checkInstallation(DF_OUTPUT_FORMAT_HTML);
		$out = $cc->getStatusLog();
		if ($errorsFound) {
			$wgOut->addHTML('<div class="df_checkinst_error">ERRORS FOUND. check below.</div>');
		} else {
			$wgOut->addHTML('<div class="df_checkinst_ok">Installation is OK</div>');
		}
		foreach($out as $o) {
			$wgOut->addHTML($o);
		}
	}
}
