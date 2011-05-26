<?php
define( 'DF_VERSION', '{{$VERSION}} [B{{$BUILD_NUMBER}}]' );
define ('DF_WIKICONTEXT', 1);

$wgExtensionFunctions[] = 'dfgSetupExtension';
$smwgDFIP = $IP . '/deployment';

$wgHooks['UserLoginComplete'][] = 'dfgCheckUpdate';
$wgAjaxExportList[] = 'dff_authUser';

function dfgSetupExtension() {
	dffInitializeLanguage();
	global $wgAutoloadClasses, $wgSpecialPages, $wgSpecialPageGroups,$smwgDFIP, $wgExtensionCredits, $dfgOut;
	
	$wgAutoloadClasses['SMWCheckInstallation'] = $smwgDFIP . '/specials/SMWCheckInstallation/SMW_CheckInstallation.php';
	$wgAutoloadClasses['DFBundleTools'] = $smwgDFIP . '/io/DF_BundleTools.php';
	$wgAutoloadClasses['DFPrintoutStream'] = $smwgDFIP . '/io/DF_PrintoutStream.php';
	$wgSpecialPages['CheckInstallation'] = array('SMWCheckInstallation');
	$wgSpecialPageGroups['CheckInstallation'] = 'smwplus_group';
	$dfgOut = DFPrintoutStream::getInstance(DF_OUTPUT_FORMAT_HTML);

	$wgExtensionCredits['other'][] = array(
        'path' => __FILE__,
        'name' => 'Deployment framework',
        'version' => DF_VERSION,
        'author' => "Kai K&uuml;hn. Owned by [http://www.ontoprise.de ontoprise GmbH].",
        'url' => 'http://smwforum.ontoprise.com/smwforum/index.php/Deployment_Framework',
	    'description' => 'Eases the installation and updating of extensions.'
	    );
}

function dffInitializeLanguage() {
	global $wgLanguageCode, $dfgLang, $wgMessageCache, $wgLang, $wgLanguageCode, $smwgDFIP;
	$langClass = "DF_Language_$wgLanguageCode";
	if (!file_exists("$smwgDFIP/languages/$langClass.php")) {
		$langClass = "DF_Language_En";
	}
	require_once("$smwgDFIP/languages/$langClass.php");
	$dfgLang = new $langClass();
	$wgMessageCache->addMessages($dfgLang->getLanguageArray(), $wgLang->getCode());
}

function dfgCheckUpdate(&$wgUser, &$injected_html) {
	if (!$wgUser->isAllowed('delete')) return true; // FIXME: check for other right than delete
	global $IP;
	global $rootDir;
	global $dfgOut;
	$rootDir = "$IP/deployment";
	 
	require_once "$IP/deployment/tools/maintenance/maintenanceTools.inc";
	$cc = new ConsistencyChecker($IP);
	$dfgOut->setVerbose(false);
	$updates = $cc->checksForUpdates();
	$dfgOut->setVerbose(true);
	if (count($updates) > 0) {
		$html = $wgUser->getSkin()->makeKnownLinkObj(Title::newFromText("CheckInstallation", NS_SPECIAL), wfMsg('df_updatesavailable'));
		$injected_html = $html;
	}
	return true;
}

/**
 * Checks the credentials for the user and makes sure that it is
 * member of group 'sysop'.
 * 
 * @param string $username
 * @param string $password
 * 
 * @return string true/false
 */
function dff_authUser($username, $password) {
	$user = User::newFromName($username);
	$correct = $user->checkPassword($password);
	$groups = $user->getGroups();
	return $correct && in_array("sysop", $groups) ? "true" : "false";
}