<?php

/**
 * This file contains the menu frame (overview) backend page for module management.
 *
 * @package    Core
 * @subpackage Backend
 * @author     Olaf Niemann
 * @copyright  four for business AG <www.4fb.de>
 * @license    https://www.contenido.org/license/LIZENZ.txt
 * @link       https://www.4fb.de
 * @link       https://www.contenido.org
 */

defined('CON_FRAMEWORK') || die('Illegal call: Missing framework initialization - request aborted.');

global $db;

// Display critical error if client or language does not exist
$client = cSecurity::toInteger(cRegistry::getClientId());
$lang = cSecurity::toInteger(cRegistry::getLanguageId());
if (($client < 1 || !cRegistry::getClient()->isLoaded()) || ($lang < 1 || !cRegistry::getLanguage()->isLoaded())) {
    $message = $client && !cRegistry::getClient()->isLoaded() ? i18n('No Client selected') : i18n('No language selected');
    $oPage = new cGuiPage("mod_overview");
    $oPage->displayCriticalError($message);
    $oPage->render();
    return;
}

$auth = cRegistry::getAuth();
$perm = cRegistry::getPerm();
$sess = cRegistry::getSession();
$frame = cRegistry::getFrame();
$area = cRegistry::getArea();
$cfg = cRegistry::getConfig();

$oPage = new cGuiPage('mod_overview');

$requestIdMod = (isset($_REQUEST['idmod'])) ? cSecurity::toInteger($_REQUEST['idmod']) : 0;
$elemPerPage = cSecurity::toInteger($_REQUEST['elemperpage'] ?? '0');
$page = cSecurity::toInteger($_REQUEST['page'] ?? '1');
$sortby = cSecurity::toString($_REQUEST['sortby'] ?? '');
$sortorder = cSecurity::toString($_REQUEST['sortorder'] ?? '');
$filter = cSecurity::toString($_REQUEST['filter'] ?? '');
$filterType = cSecurity::toString($_REQUEST['filtertype'] ?? '');
$searchIn = cSecurity::toString($_REQUEST['searchin'] ?? '');

// Now build bottom with list
$cApiModuleCollection = new cApiModuleCollection();
$cApiModule = new cApiModule();
$searchOptions = [];

// no value found in request for items per page -> get form db or set default
$oUser = new cApiUser($auth->auth['uid']);
if ($elemPerPage < 0) {
    $elemPerPage = $oUser->getProperty('itemsperpage', $area);
}
if ($elemPerPage > 0) {
    // -- All -- will not be stored, as it may be impossible to change this back to something more useful
    $oUser->setProperty('itemsperpage', $area, $elemPerPage);
}
unset($oUser);

if ($page <= 0 || $elemPerPage == 0) {
    $page = 1;
}


// Build list for left_bottom considering filter values
$cGuiMenu = new cGuiMenu();
$sOptionModuleCheck = getSystemProperty("system", "modulecheck");
$sOptionForceCheck = getEffectiveSetting("modules", "force-menu-check", "false");
$iMenu = 0;

$searchOptions['elementPerPage'] = $elemPerPage;

$searchOptions['orderBy'] = ($sortby === 'type') ? 'type' : 'name';

$searchOptions['sortOrder'] = ($sortorder == 'desc') ? 'desc' : 'asc';

$searchOptions['moduleType'] = ($filterType == '--wotype--') ? '' : '%%';
if (!empty($filterType) && $filterType !== '--wotype--' && $filterType !== '--all--') {
    $searchOptions['moduleType'] = $db->escape($filterType);
}

$searchOptions['filter'] = $db->escape($filter);

//search in
$searchOptions['searchIn'] = 'all';
if (in_array($searchIn, ['name', 'description', 'type', 'input', 'output'])) {
    $searchOptions['searchIn'] = $searchIn;
}

$searchOptions['selectedPage'] = $page;

$cModuleSearch = new cModuleSearch($searchOptions);

$allModules = $cModuleSearch->getModules();

if ($elemPerPage > 0) {
    $iItemCount = $cModuleSearch->getModulCount();
} else {
    $iItemCount = 0;
}

foreach ($allModules as $idmod => $module) {

    if ($perm->have_perm_item($area, $idmod) ||
        $perm->have_perm_area_action("mod_translate", "mod_translation_save") ||
        $perm->have_perm_area_action_item("mod_translate", "mod_translation_save", $idmod)
    ) {

        $link = new cHTMLLink();
        $link->setClass('show_item')
            ->setLink('javascript:void(0)')
            ->setAttribute('data-action', 'show_module');

        $moduleName = (cString::getStringLength(trim($module['name'])) > 0) ? $module['name'] : i18n("- Unnamed module -");
        $sName = mb_convert_encoding(cString::stripSlashes(conHtmlSpecialChars($moduleName)), cRegistry::getLanguage()->get('encoding')); //$cApiModule->get("name");
        $descr = mb_convert_encoding(cString::stripSlashes(str_replace("'", "&#39;", conHtmlSpecialChars(nl2br($module ['description'])))), cRegistry::getLanguage()->get('encoding'));

        // Do not check modules (or don't force it) - so, let's take a look into the database
        $sModuleError = $module['error']; //$cApiModule->get("error");

        if ($sModuleError == "none") {
            $colName = $sName;
        } elseif ($sModuleError == "input" || $sModuleError == "output") {
            $colName = '<span class="moduleError">' . $sName . '</span>';
        } else {
            $colName = '<span class="moduleCriticalError">' . $sName . '</span>';
        }

        $iMenu++;

        $cGuiMenu->setTitle($iMenu, $colName);
        $cGuiMenu->setId($iMenu, $idmod);
        $cGuiMenu->setTooltip($iMenu, ($descr == "") ? '' : $descr);
        if ($perm->have_perm_area_action_item("mod_edit", "mod_edit", $idmod) ||
            $perm->have_perm_area_action_item("mod_translate", "mod_translation_save", $idmod)) {
            $cGuiMenu->setLink($iMenu, $link);
        }

        $inUse = $cApiModule->moduleInUse($idmod);

        $deleteLink = '';
        $delDescription = '';

        if ($inUse) {
            $inUseString = i18n("For more information about usage click on this button");
            $inUseLink = '<a class="con_img_button" href="javascript:void(0)" data-action="inused_module">'
                       . cHTMLImage::img($cfg['path']['images'] . 'exclamation.gif', $inUseString)
                       . '</a>';
            $delDescription = i18n("Module can not be deleted, because it is already in use!");
        } else {
            $inUseLink = cHTMLImage::img($cfg['path']['images'] . 'spacer.gif', '', ['class' => 'con_img_button_off']);
            if ($perm->have_perm_area_action_item('mod', 'mod_delete', $idmod)) {
                if (getEffectiveSetting('client', 'readonly', 'false') == 'true') {
                    $delTitle = i18n('This area is read only! The administrator disabled edits!');
                    $deleteLink = cHTMLImage::img($cfg['path']['images'] . 'delete_inact.gif', $delTitle, ['class' => 'con_img_button_off']);
                } else {
                    $delTitle = i18n("Delete module");
                    $deleteLink = '<a class="con_img_button" href="javascript:void(0)" data-action="delete_module" title="' . $delTitle . '">'
                                . cHTMLImage::img($cfg['path']['images'] . 'delete.gif', $delTitle)
                                . '</a>';
                }
            } else {
                $delDescription = i18n("No permissions");
            }
        }

        if ($deleteLink == "") {
            $deleteLink = cHTMLImage::img($cfg['path']['images'] . 'delete_inact.gif', $delDescription, ['class' => 'con_img_button_off']);
        }

        $todo = new TODOLink("idmod", $idmod, "Module: $sName", "");

        $cGuiMenu->setActions($iMenu, 'inuse', $inUseLink);
        $cGuiMenu->setActions($iMenu, 'todo', $todo->render());
        $cGuiMenu->setActions($iMenu, 'delete', $deleteLink);

        if ($requestIdMod == $idmod) {
            $cGuiMenu->setMarked($iMenu);
        }
    }
}

$oPage->addScript("cfoldingrow.js");
$oPage->addScript("parameterCollector.js");
$oPage->set("s", "FORM", $cGuiMenu->render(false));
$oPage->set("s", "DELETE_MESSAGE", i18n("Do you really want to delete the following module:<br /><br />%s<br />"));

//generate current content for Object Pager
$oPagerLink = new cHTMLLink();
$pagerl = "pagerlink";
$oPagerLink->setTargetFrame('left_bottom');
$oPagerLink->setLink("main.php");
$oPagerLink->setCustom('elemperpage', $elemPerPage);
$oPagerLink->setCustom("filter", stripslashes($filter));
$oPagerLink->setCustom("sortby", $sortby);
$oPagerLink->setCustom("sortorder", $sortorder);
$oPagerLink->setCustom("frame", $frame);
$oPagerLink->setCustom("area", $area);
$oPagerLink->enableAutomaticParameterAppend();
$oPagerLink->setCustom("contenido", $sess->id);
$oPager = new cGuiObjectPager("02420d6b-a77e-4a97-9395-7f6be480f497", $iItemCount, $elemPerPage, $page, $oPagerLink, 'page', $pagerl);

//add slashes, to insert in javascript
$sPagerContent = $oPager->render(true);
$sPagerContent = str_replace('\\', '\\\\', $sPagerContent);
$sPagerContent = str_replace('\'', '\\\'', $sPagerContent);
$oPage->set('s', 'PAGER_CONTENT', $sPagerContent);

$oPage->render();
