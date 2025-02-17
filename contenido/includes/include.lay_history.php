<?php

/**
 * This file contains the backend page for layout history.
 *
 * @package    Core
 * @subpackage Backend
 * @author     Bilal Arslan
 * @author     Timo Trautmann
 * @copyright  four for business AG <www.4fb.de>
 * @license    https://www.contenido.org/license/LIZENZ.txt
 * @link       https://www.4fb.de
 * @link       https://www.contenido.org
 */

defined('CON_FRAMEWORK') || die('Illegal call: Missing framework initialization - request aborted.');

global $idlay, $bInUse;

$perm = cRegistry::getPerm();
$client = cSecurity::toInteger(cRegistry::getClientId());
$area = cRegistry::getArea();

$oPage = new cGuiPage('lay_history');

if (!$perm->have_perm_area_action($area, 'lay_history_manage')) {
    $oPage->displayError(i18n('Permission denied'));
    $oPage->abortRendering();
    $oPage->render();
    return;
} elseif (!$client > 0) {
    $oPage->abortRendering();
    $oPage->render();
    return;
} elseif (getEffectiveSetting('versioning', 'activated', 'false') == 'false') {
    $oPage->displayWarning(i18n('Versioning is not activated'));
    $oPage->abortRendering();
    $oPage->render();
    return;
}

cInclude('includes', 'functions.lay.php');
cInclude('external', 'codemirror/class.codemirror.php');
cInclude('classes', 'class.layout.synchronizer.php');

$db = cRegistry::getDb();
$cfg = cRegistry::getConfig();
$frame = cRegistry::getFrame();
$cfgClient = cRegistry::getClientConfig();
$belang = cRegistry::getBackendLanguage();

$readOnly = (getEffectiveSetting('client', 'readonly', 'false') === 'true');
if ($readOnly) {
    cRegistry::addWarningMessage(i18n('This area is read only! The administrator disabled edits!'));
}

$bDeleteFile = false;

$requestAction = $_POST['action'] ?? '';
$requestLaySend = cSecurity::toInteger($_POST['lay_send'] ?? '0');
$requestLayCode = $_POST['laycode'] ?? '';
$requestLayName = $_POST['layname'] ?? '';

// Truncate history action
if ((!$readOnly) && $requestAction === 'history_truncate') {
    $oVersion = new cVersionLayout($idlay, $cfg, $cfgClient, $db, $client, $area, $frame);
    $bDeleteFile = $oVersion->deleteFile();
    unset($oVersion);
}

// Save action
if ((!$readOnly) && $requestLaySend && $requestLayName != '' && $requestLayCode != '' && (int) $idlay > 0) {
    $oVersion = new cVersionLayout($idlay, $cfg, $cfgClient, $db, $client, $area, $frame);
    $sLayoutName = $requestLayName;
    $sLayoutCode = $requestLayCode;
    $sLayoutDescription = $_POST['laydesc'] ?? '';

    // Save and make a new revision
    $oPage->reloadLeftBottomFrame(['idlay' => $idlay]);
    layEditLayout($idlay, $sLayoutName, $sLayoutDescription, $sLayoutCode);
    unset($oVersion);
}

// Init construct with CONTENIDO variables, in class.VersionLayout
$oVersion = new cVersionLayout($idlay, $cfg, $cfgClient, $db, $client, $area, $frame);

// Init form variables of select box
$oVersion->setVarForm('action', '');
$oVersion->setVarForm('area', $area);
$oVersion->setVarForm('frame', $frame);
$oVersion->setVarForm('idlay', $idlay);

// Create and output the select box
$sSelectBox = $oVersion->buildSelectBox(
    'lay_history', 'Layout History',
    i18n('Show history entry'), 'idlayhistory', $readOnly
);

// Generate form
$oForm = new cGuiTableForm('lay_display');
$oForm->addTableClass('col_flx_m_50 col_first_100');
$oForm->setHeader(i18n('Edit Layout'));
$oForm->setVar('area', 'lay_history');
$oForm->setVar('frame', $frame);
$oForm->setVar('idlay', $idlay);
$oForm->setVar('lay_send', 1);

// if send form refresh
if (!empty($_POST['idlayhistory'])) {
    $sRevision = $_POST['idlayhistory'];
} else {
    $sRevision = $oVersion->getLastRevision();
}

$sName = '';
$description = '';
$sCode = '';

if ($sRevision != '' && ($requestAction != 'history_truncate' || $readOnly)) {
    // File Path
    $sPath = $oVersion->getFilePath() . $sRevision;

    // Read XML nodes and get an array
    $aNodes = [];
    $aNodes = $oVersion->initXmlReader($sPath);

    // Create textarea and fill it with xml nodes
    if (count($aNodes) > 1) {
        // if choose xml file read value an set it
        $sName = $oVersion->getTextBox('layname', cString::stripSlashes(conHtmlentities(conHtmlSpecialChars($aNodes['name']))), 60, $readOnly);
        $description = $oVersion->getTextarea('laydesc', cString::stripSlashes(conHtmlSpecialChars($aNodes['desc'])), 100, 10, '', $readOnly);
        $sCode = $oVersion->getTextarea('laycode', conHtmlSpecialChars($aNodes['code']), 100, 30, 'IdLaycode');
    }
}

// Add new elements of form
$oForm->add(i18n('Name'), $sName);
$oForm->add(i18n('Description'), $description);
$oForm->add(i18n('Code'), $sCode);
$oForm->setActionButton('apply', 'images/but_ok' . ($readOnly ? '_off' : '') . '.gif', i18n('Copy to current'), 'c'/*, 'mod_history_takeover'*/);
$oForm->unsetActionButton('submit');

// Render and handle history area
$oCodeMirrorOutput = new CodeMirror('IdLaycode', 'php', cString::getPartOfString(cString::toLowerCase($belang), 0, 2), true, $cfg, !$bInUse);
    if($readOnly) {
        $oCodeMirrorOutput->setProperty('readOnly', 'true');
    }
$oPage->addScript($oCodeMirrorOutput->renderScript());

if ($sSelectBox != '') {
    $div = new cHTMLDiv();
    $div->setContent($sSelectBox . '<br>');
    $oPage->setContent([
            $div,
            $oForm
    ]);
} else {
    if ($bDeleteFile) {
        $oPage->displayOk(i18n('Version history was cleared'));
    } else {
        $oPage->displayInfo(i18n('No layout history available'));
    }

    $oPage->abortRendering();
}
$oPage->render();
