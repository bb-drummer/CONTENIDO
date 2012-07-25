<?php
/**
 * Project:
 * CONTENIDO Content Management System
 *
 * Description:
 * Module list
 *
 * Requirements:
 * @con_php_req 5.0
 *
 *
 * @package    CONTENIDO Backend Includes
 * @version    1.3.2
 * @author     unknown
 * @copyright  four for business AG <www.4fb.de>
 * @license    http://www.contenido.org/license/LIZENZ.txt
 * @link       http://www.4fb.de
 * @link       http://www.contenido.org
 * @since      file available since CONTENIDO release <= 4.6
 *
 * {@internal
 *   created 2003-03-21
 *   $Id$:
 * }}
 */

if (!defined('CON_FRAMEWORK')) {
    die('Illegal call');
}

if (!(int) $client > 0) {
    // If there is no client selected, display empty page
    $oPage = new cGuiPage("mod_overview");
    $oPage->render();
    return;
}

############################
# Now build bottom with list
############################
$cApiModuleCollection = new cApiModuleCollection();
$classmodule          = new cApiModule();
$oPage                = new cGuiPage("mod_overview");
$searchOptions = array();

// no value found in request for items per page -> get form db or set default
$oUser = new cApiUser($auth->auth["uid"]);
if (!isset($_REQUEST["elemperpage"]) || !is_numeric($_REQUEST['elemperpage']) || $_REQUEST['elemperpage'] < 0) {
    $_REQUEST["elemperpage"] = $oUser->getProperty("itemsperpage", $area);
}
if (!is_numeric($_REQUEST["elemperpage"])) {
    $_REQUEST["elemperpage"] = 0;
}
if ($_REQUEST["elemperpage"] > 0) {
    // -- All -- will not be stored, as it may be impossible to change this back to something more useful
    $oUser->setProperty("itemsperpage", $area, $_REQUEST["elemperpage"]);
}
unset($oUser);

if (!isset($_REQUEST["page"]) || !is_numeric($_REQUEST['page']) || $_REQUEST['page'] <= 0 || $_REQUEST["elemperpage"] == 0) {
    $_REQUEST["page"] = 1;
}


// Build list for left_bottom considering filter values
$mlist              = new cGuiMenu();
$sOptionModuleCheck = getSystemProperty("system", "modulecheck");
$sOptionForceCheck  = getEffectiveSetting("modules", "force-menu-check", "false");
$iMenu              = 0;

$searchOptions['elementPerPage'] = $_REQUEST['elemperpage'];

$searchOptions['orderBy'] = 'name';
if ($_REQUEST['sortby'] == 'type') {
    $searchOptions['orderBy'] = 'type';
}

$searchOptions['sortOrder'] = 'asc';
if ($_REQUEST['sortorder'] == "desc") {
    $searchOptions['sortOrder'] = 'desc';
}

$searchOptions['moduleType'] = '%%';
if ($_REQUEST['filtertype'] == '--wotype--') {
    $searchOptions['moduleType'] = '';
}

if (!empty($_REQUEST['filtertype']) && $_REQUEST['filtertype'] != '--wotype--' && $_REQUEST['filtertype'] != '--all--') {
    $searchOptions['moduleType'] = cSecurity::escapeDB($_REQUEST['filtertype'], $db);
}

$searchOptions['filter'] = cSecurity::escapeDB($_REQUEST['filter'], $db);

//search in
$searchOptions['searchIn'] = 'all';
if ($_REQUEST['searchin']== 'name' || $_REQUEST['searchin'] == 'description' || $_REQUEST['searchin']== 'type' || $_REQUEST['searchin']== 'input' || $_REQUEST['searchin']== 'output') {
    $searchOptions['searchIn'] = $_REQUEST['searchin'];
}

$searchOptions['selectedPage'] = $_REQUEST['page'];

$contenidoModulSearch = new cModuleSearch($searchOptions);

$allModules = $contenidoModulSearch->getModules();

if ($_REQUEST["elemperpage"] > 0) {
    $iItemCount = $contenidoModulSearch->getModulCount();
} else {
    $iItemCount = 0;
}

foreach ($allModules as $idmod => $module) {
    //$cApiModule = $cApiModuleCollection->next())

    if ($perm->have_perm_item($area, $idmod) || $perm->have_perm_area_action("mod_translate", "mod_translation_save") || $perm->have_perm_area_action_item("mod_translate", "mod_translation_save", $idmod))
    {
        //$idmod = $cApiModule->get("idmod");

        $link = new cHTMLLink;
        $link->setMultiLink("mod", "", "mod_edit", "");
        $link->setCustom("idmod", $idmod);
        $link->updateAttributes(array("alt" => $module['description']));
        $link->updateAttributes(array("title" => $module['description']));
        $link->updateAttributes(array("style" => "margin-left:5px"));

        $sName = $module ['name'];//$cApiModule->get("name");

        if ($sOptionModuleCheck !== "false" && $sOptionForceCheck !== "false") {
            // Check module and force check has been enabled - check module (surprisingly...)
            $inputok = modTestModule($module['input'], $idmod."i", false);
            $outputok = modTestModule($module['output'], $idmod."o", true);

            if ($inputok && $outputok) {        // Everything ok
                $colName = $sName;            // The set default color: none :)
            } else if ($inputok || $outputok) {   // Input or output has a problem
                $colName = '<font color="#B1AC58">'.$sName.'</font>';
            } else {                           // Input >and< output has a problem
                $colName = '<font color="red">'.$sName.'</font>';
            }
        } else {
            // Do not check modules (or don't force it) - so, let's take a look into the database
            $sModuleError = $module['error'];//$cApiModule->get("error");

            if ($sModuleError == "none") {
                $colName = $sName;
            } else if ($sModuleError == "input" || $sModuleError == "output") {
                $colName = '<font color="#B1AC58">'.$sName.'</font>';
            } else {
                $colName = '<font color="red">'.$sName.'</font>';
            }
        }

        $iMenu++;

        $mlist->setTitle($iMenu, $colName);
        if ($perm->have_perm_area_action_item("mod_edit", "mod_edit", $idmod) || $perm->have_perm_area_action_item("mod_translate", "mod_translation_save", $idmod)) {
            $mlist->setLink($iMenu, $link);
        }

        $inUse = $classmodule->moduleInUse($idmod);

        $deletebutton = "";

        if ($inUse) {
            $inUseString = i18n("Click for more information about usage");
            $mlist->setActions($iMenu, 'inuse', '<a href="javascript:;" rel="' . $idmod . '" class="in_used_mod"><img src="'.$cfg['path']['images'].'exclamation.gif" border="0" title="'.$inUseString.'" alt="'.$inUseString.'"></a>');
            $delDescription = i18n("Module in use, cannot delete");
        } else {
            $mlist->setActions($iMenu, 'inuse', '<img src="./images/spacer.gif" border="0" width="16">');
            if ($perm->have_perm_area_action_item("mod", "mod_delete", $idmod)) {
                $delTitle = i18n("Delete module");
                $delDescr = sprintf(i18n("Do you really want to delete the following module:<br><br>%s<br>"), $sName);
                $deletebutton = '<a title="'.$delTitle.'" href="javascript://" onclick="box.confirm(\''.$delTitle.'\', \''.$delDescr.'\', \'deleteModule('.$idmod.')\')"><img src="'.$cfg['path']['images'].'delete.gif" border="0" title="'.$delTitle.'" alt="'.$delTitle.'"></a>';
            } else {
                $delDescription = i18n("No permission");
            }
        }

        if ($deletebutton == "") {
            //$deletebutton = '<img src="images/spacer.gif" width="16" height="16">';
            $deletebutton = '<img src="'.$cfg['path']['images'].'delete_inact.gif" border="0" title="'.$delDescription.'" alt="'.$delDescription.'">';
        }

        $todo = new TODOLink("idmod", $idmod, "Module: $sName", "");

        $mlist->setActions($iMenu, "todo", $todo->render());
        $mlist->setActions($iMenu, "delete", $deletebutton);

        if ($_GET['idmod'] == $idmod) {
            $mlist->setMarked($iMenu);
        }
        //$mlist->setImage($iMenu, "images/but_module.gif");
        //$mlist->setImage($iMenu, 'images/spacer.gif', 5);
    }
}

$oPage->addScript("cfoldingrow.js");
$oPage->addScript("parameterCollector.js");
$oPage->set("s", "FORM", $mlist->render(false));

//generate current content for Object Pager
$oPagerLink = new cHTMLLink;
$pagerl="pagerlink";
$oPagerLink->setTargetFrame('left_bottom');
$oPagerLink->setLink("main.php");
$oPagerLink->setCustom("elemperpage", $elemperpage);
$oPagerLink->setCustom("filter", stripslashes($_REQUEST["filter"]));
$oPagerLink->setCustom("sortby", $_REQUEST["sortby"]);
$oPagerLink->setCustom("sortorder", $_REQUEST["sortorder"]);
$oPagerLink->setCustom("frame", $frame);
$oPagerLink->setCustom("area", $area);
$oPagerLink->enableAutomaticParameterAppend();
$oPagerLink->setCustom("contenido", $sess->id);
$oPager = new cGuiObjectPager("02420d6b-a77e-4a97-9395-7f6be480f497", $iItemCount, $_REQUEST["elemperpage"], $_REQUEST["page"], $oPagerLink, "page", $pagerl);

//add slashes, to insert in javascript
$sPagerContent = $oPager->render(1);
$sPagerContent = str_replace('\\', '\\\\', $sPagerContent);
$sPagerContent = str_replace('\'', '\\\'', $sPagerContent);

//send new object pager to left_top
$sRefreshPager = '
    <script type="text/javascript">
        var sNavigation = \''.$sPagerContent.'\';
        var left_top = parent.left_top;
        if (left_top.document) {
            var oPager = left_top.document.getElementById(\'02420d6b-a77e-4a97-9395-7f6be480f497\');
            if (oPager) {
                oInsert = oPager.firstChild;
                oInsert.innerHTML = sNavigation;
                left_top.toggle_pager(\'02420d6b-a77e-4a97-9395-7f6be480f497\');
            }
        }
    </script>';

$oPage->addScript($sRefreshPager);

$oPage->render();
?>