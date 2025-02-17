<?php

/**
 * This file contains the backend page for module rights management.
 *
 * @package    Core
 * @subpackage Backend
 * @author     Unknown
 * @copyright  four for business AG <www.4fb.de>
 * @license    https://www.contenido.org/license/LIZENZ.txt
 * @link       https://www.4fb.de
 * @link       https://www.contenido.org
 */

defined('CON_FRAMEWORK') || die('Illegal call: Missing framework initialization - request aborted.');

global $notification, $oTpl, $right_list, $rights_client, $rights_lang, $db, $lngAct, $userid, $area_tree;

$area = cRegistry::getArea();
$perm = cRegistry::getPerm();
$cfg = cRegistry::getConfig();
$action = cRegistry::getAction();

include_once(cRegistry::getBackendPath() . 'includes/include.rights.php');

// set the areas which are in use fore selecting these
$possible_area = "'" . implode("','", $area_tree[$perm->showareas("mod")]) . "'";
$sql = "SELECT A.idarea, A.idaction, A.idcat, B.name, C.name
        FROM " . $cfg["tab"]["rights"] . " AS A, " . $cfg["tab"]["area"] . " AS B, " . $cfg["tab"]["actions"] . " AS C
        WHERE user_id = '" . $db->escape($userid) . "' AND idclient = " . cSecurity::toInteger($rights_client) . "
        AND A.type = 0 AND idlang = " . cSecurity::toInteger($rights_lang) . " AND B.idarea IN ($possible_area)
        AND idcat != 0 AND A.idaction = C.idaction AND A.idarea = C.idarea AND A.idarea = B.idarea";
$db->query($sql);

$rights_list_old = [];
while ($db->nextRecord()) { // set a new rights list for this user
    $rights_list_old[$db->f(3) . "|" . $db->f(4) . "|" . $db->f("idcat")] = "x";
}

$sMessage = '';
if (($perm->have_perm_area_action("user_overview", $action)) && ($action == "user_edit")) {
    $ret = cRights::saveRights();
    if ($ret === true) {
        $sMessage = $notification->returnNotification('ok', i18n('Changes saved'));
    }
} else {
    if (!$perm->have_perm_area_action("user_overview", $action)) {
        $sMessage = $notification->returnNotification("error", i18n("Permission denied"));
    }
}

// declare new template variables
$sJsBefore = '';
$sJsAfter = '';
$sJsExternal = '';
$sTable = '';

$sJsBefore .= "var itemids = [];\n"
            . "var actareaids = [];\n";

// Init Table
$oTable = new cHTMLTable();
$oTable->updateAttributes([
    "class" => "generic",
    "cellspacing" => "0",
    "cellpadding" => "2"
]);
$objHeaderRow = new cHTMLTableRow();
$objHeaderItem = new cHTMLTableHead();
$objFooterRow = new cHTMLTableRow();
$objFooterItem = new cHTMLTableData();
$objRow = new cHTMLTableRow();
$objItem = new cHTMLTableData();

// table header
// 1. zeile
$headerOutput = "";
$items = "";
$objHeaderItem->updateAttributes([
    "class" => "center",
    "valign" => "top",
    "align" => "center"
]);
$objHeaderItem->setContent(i18n("Module name"));
$items .= $objHeaderItem->render();
$objHeaderItem->advanceID();

$aSecondHeaderRow = [];
$possible_areas = [];

// look for possible actions in mainarea []
foreach ($right_list["mod"] as $value2) {
    // if there are some actions
    if (isset($value2["action"]) && is_array($value2["action"])) {
        foreach ($value2["action"] as $key3 => $value3) { // set the areas that
            // are in use
            $possible_areas[$value2["perm"]] = "";

            // set the possible areas and actions for this areas
            $sJsBefore .= "actareaids[\"$value3|" . $value2["perm"] . "\"]=\"x\";\n";

            // checkbox for the whole action
            $objHeaderItem->setContent($lngAct[$value2["perm"]][$value3] ? $lngAct[$value2["perm"]][$value3] : "&nbsp;");
            $items .= $objHeaderItem->render();
            $objHeaderItem->advanceID();
            $aSecondHeaderRow[] = "<input type=\"checkbox\" name=\"checkall_" . $value2["perm"] . "_$value3\" value=\"\" onClick=\"setRightsFor('" . $value2["perm"] . "', '$value3', '')\">";
        }
    }
}

// checkbox for all rights
$objHeaderItem->setContent(i18n("Check all"));
$items .= $objHeaderItem->render();
$objHeaderItem->advanceID();
$aSecondHeaderRow[] = '<input type="checkbox" name="checkall" value="" onclick="setRightsForAll()">';

$objHeaderRow->updateAttributes([
    "class" => "textw_medium"
]);
$objHeaderRow->setContent($items);
$items = "";
$headerOutput .= $objHeaderRow->render();
$objHeaderRow->advanceID();

// 2. zeile
$objHeaderItem->updateAttributes([
    "class" => "center",
    "valign" => "",
    "align" => "center",
    "style" => "border-top-width: 0px;"
]);
$objHeaderItem->setContent("&nbsp;");
$items .= $objHeaderItem->render();
$objHeaderItem->advanceID();
foreach ($aSecondHeaderRow as $value) {
    $objHeaderItem->setContent($value);
    $items .= $objHeaderItem->render();
    $objHeaderItem->advanceID();
}

$objHeaderRow->updateAttributes([
    "class" => "textw_medium"
]);
$objHeaderRow->setContent($items);
$items = "";
$headerOutput .= $objHeaderRow->render();
$objHeaderRow->advanceID();

// table content
$output = "";

// Select the itemids
$sql = "SELECT * FROM " . $cfg["tab"]["mod"] . " WHERE idclient='" . cSecurity::toInteger($rights_client) . "' ORDER BY name";
$db->query($sql);

while ($db->nextRecord()) {
    $tplname = conHtmlentities($db->f('name'));
    $description = conHtmlentities($db->f('description') ?? '');

    $objItem->updateAttributes([
        "class" => "td_rights0"
    ]);
    $objItem->setContent($tplname);
    $objItem->setAlt($description);
    $items .= $objItem->render();
    $objItem->advanceID();

    // set javascript array for itemids
    $sJsBefore .= "itemids[\"" . $db->f("idmod") . "\"]=\"x\";\n";

    // look for possible actions in mainarea[]
    foreach ($right_list["mod"] as $value2) {
        // if there area some
        if (isset($value2["action"]) && is_array($value2["action"])) {
            foreach ($value2["action"] as $key3 => $value3) {
                // does the user have the right
                if (in_array($value2["perm"] . "|$value3|" . $db->f("idmod"), array_keys($rights_list_old))) {
                    $checked = "checked=\"checked\"";
                } else {
                    $checked = "";
                }

                // set the checkbox the name consists of areaid+actionid+itemid
                $objItem->updateAttributes([
                    "class" => "td_rights2",
                    "style" => ""
                ]);
                $objItem->setContent("<input type=\"checkbox\" name=\"rights_list[" . $value2["perm"] . "|$value3|" . $db->f("idmod") . "]\" value=\"x\" $checked>");
                $items .= $objItem->render();
                $objItem->advanceID();
            }
        }
    }

    // checkbox for checking all actions fore this itemid
    $objItem->updateAttributes([
        "class" => "td_rights3"
    ]);
    $objItem->setContent("<input type=\"checkbox\" name=\"checkall_" . $value2["perm"] . "_" . $value3 . "_" . $db->f("idmod") . "\" value=\"\" onClick=\"setRightsFor('" . $value2["perm"] . "', '$value3', '" . $db->f("idmod") . "')\">");
    $items .= $objItem->render();
    $objItem->advanceID();

    $objRow->setContent($items);
    $items = "";
    $output .= $objRow->render();
    $objRow->advanceID();
}

// table footer
$objItem->updateAttributes([
    "class" => "",
    "valign" => "top",
    "align" => "right",
    "colspan" => "10"
]);
$objItem->setContent(
    '<div class="con_form_action_control">'
    . "<a class=\"con_img_button\" href=\"javascript:submitrightsform('user_edit', '');\"><img src=\"" . $cfg['path']['images'] . "but_ok.gif\"></a>"
    . "<a class=\"con_img_button\" href=\"javascript:submitrightsform('', 'area');\"><img src=\"" . $cfg['path']['images'] . "but_cancel.gif\"></a>"
    . '</div>'
);
$items = $objItem->render();
$objItem->advanceID();
$objFooterRow->setContent($items);
$items = "";
$footerOutput = $objFooterRow->render();
$objFooterRow->advanceID();

$oTable->setContent($headerOutput . $output . $footerOutput);
$sTable = stripslashes($oTable->render());
// Table end

$oTpl->set('s', 'NOTIFICATION_SAVE_RIGHTS', $sMessage);
$oTpl->set('s', 'JS_SCRIPT_BEFORE', $sJsBefore);
$oTpl->set('s', 'JS_SCRIPT_AFTER', $sJsAfter);
$oTpl->set('s', 'RIGHTS_CONTENT', $sTable);
$oTpl->set('s', 'EXTERNAL_SCRIPTS', $sJsExternal);

$oTpl->generate('templates/standard/' . $cfg['templates']['rights']);
