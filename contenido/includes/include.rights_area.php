<?php

/**
 * This file contains the backend page for area rights management.
 *
 * @package Core
 * @subpackage Backend
 * @author Unknown
 * @copyright four for business AG <www.4fb.de>
 * @license https://www.contenido.org/license/LIZENZ.txt
 * @link https://www.4fb.de
 * @link https://www.contenido.org
 */

defined('CON_FRAMEWORK') || die('Illegal call: Missing framework initialization - request aborted.');

global $notification, $oTpl, $right_list, $rights_client, $rights_lang, $db, $lngAct, $userid;

$sess = cRegistry::getSession();
$area = cRegistry::getArea();
$perm = cRegistry::getPerm();
$cfg = cRegistry::getConfig();
$action = cRegistry::getAction();

include_once(cRegistry::getBackendPath() . 'includes/include.rights.php');

$debug = (cDebug::getDefaultDebuggerName() != cDebug::DEBUGGER_DEVNULL);

// set the areas which are in use for selecting these
$sql = "SELECT A.idarea, A.idaction, A.idcat, B.name, C.name
        FROM `:tab_rights` AS A, `:tab_area` AS B, `:tab_actions` AS C
        WHERE user_id = ':user_id' AND idclient = :idclient
        AND idlang = :idlang AND idcat = 0 AND A.idaction = C.idaction AND A.idarea = B.idarea";
$db->query($sql, [
    'tab_rights' => $cfg['tab']['rights'],
    'tab_area' => $cfg['tab']['area'],
    'tab_actions' => $cfg['tab']['actions'],
    'user_id' => $userid,
    'idclient' => $rights_client,
    'idlang' => $rights_lang,
]);

$rights_list_old = [];
while ($db->nextRecord()) { // set a new rights list for this user
    $rights_list_old[$db->f(3) . "|" . $db->f(4) . "|" . $db->f("idcat")] = "x";
}
$rights_list_old_keys = array_keys($rights_list_old);

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

$sJsBefore .= "var areatree = [];\n";

if (!isset($rights_perms) || $action == "" || !isset($action)) {
    // search for the permissions of this user
    $sql = "SELECT `perms` FROM `%s` WHERE `user_id` = '%s'";
    $db->query($sql, $cfg['tab']['user'], $userid);
    $db->nextRecord();
    $rights_perms = $db->f("perms");
}

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
$headerOutput = "";
$aTh = [
    [
        "&nbsp;",
        "&nbsp;",
        i18n("Check all")
    ],
    [
        "&nbsp;",
        "&nbsp;",
        '<input type="checkbox" name="checkall" value="" onclick="setRightsForAllAreas()">'
    ]
];

foreach ($aTh as $i => $tr) {
    $items = "";
    foreach ($tr as $td) {
        if ($i == 1) {
            $objHeaderItem->updateAttributes([
                "class" => "center",
                "align" => "center",
                "valign" => "",
                "style" => "border-top-width: 0px;"
            ]);
        } else {
            $objHeaderItem->updateAttributes([
                "class" => "center",
                "align" => "center",
                "valign" => "top"
            ]);
        }
        $objHeaderItem->setContent($td);
        $items .= $objHeaderItem->render();
        $objHeaderItem->advanceID();
    }
    $objHeaderRow->updateAttributes([
        "class" => "textw_medium"
    ]);
    $objHeaderRow->setContent($items);
    $headerOutput .= $objHeaderRow->render();
    $objHeaderRow->advanceID();
}

// table content
$output = "";
$nav = new cGuiNavigation();
foreach ($right_list as $key => $value) {
    // look for possible actions in mainarea
    foreach ($value as $key2 => $value2) {
        $items = "";
        if ($key == $key2) {
            // does the user have the right
            if (in_array($value2["perm"] . "|fake_permission_action|0", $rights_list_old_keys)) {
                $checked = 'checked="checked"';
            } else {
                $checked = "";
            }

            // Extract names from the XML document.
            $main = $nav->getName(str_replace('/overview', '/main', $value2['location']));

            if ($debug) {
                $locationString = $value2["location"] . " " . $value2["perm"] . "-->" . $main;
            } else {
                $locationString = $main;
            }

            $objItem->updateAttributes([
                "class" => "td_rights1"
            ]);
            $objItem->setContent($locationString);
            $items .= $objItem->render();
            $objItem->advanceID();

            $objItem->updateAttributes([
                "class" => "td_rights2"
            ]);
            $objItem->setContent("<input type=\"checkbox\" name=\"rights_list[" . $value2["perm"] . "|fake_permission_action|0]\" value=\"x\" $checked>");
            $items .= $objItem->render();
            $objItem->advanceID();

            $objItem->updateAttributes([
                "class" => "td_rights2"
            ]);
            $objItem->setContent("<input type=\"checkbox\" name=\"checkall_$key\" value=\"\" onclick=\"setRightsForArea('$key')\">");
            $items .= $objItem->render();
            $objItem->advanceID();

            $objRow->setContent($items);
            $items = "";
            $output .= $objRow->render();
            $objRow->advanceID();
            // set javascript array for areatree
            $sJsBefore .= "areatree[\"$key\"] = [];\n"
                        . "areatree[\"$key\"][\"" . $value2["perm"] . "0\"] = \"rights_list[" . $value2["perm"] . "|fake_permission_action|0]\";\n";
        }

        // if there are some
        if (isset($value2["action"]) && is_array($value2["action"])) {
            foreach ($value2["action"] as $key3 => $value3) {
                $idaction = $value3;
                // does the user have the right
                if (in_array($value2["perm"] . "|$idaction|0", $rights_list_old_keys)) {
                    $checked = 'checked="checked"';
                } else {
                    $checked = "";
                }

                // set the checkbox the name consists of areaid+actionid+itemid
                $sCellContent = '';
                if ($debug) {
                    $label = $lngAct[$value2["perm"]][$value3] ?? i18n('not available');
                    $sCellContent = "&nbsp;&nbsp;&nbsp;&nbsp; " . $value2["perm"] . " | " . $value3 . "-->" . $label . "&nbsp;&nbsp;&nbsp;&nbsp;";
                } else {
                    if (empty($lngAct[$value2["perm"]][$value3])) {
                        $sCellContent = "&nbsp;&nbsp;&nbsp;&nbsp; " . $value2["perm"] . "|" . $value3 . "&nbsp;&nbsp;&nbsp;&nbsp;";
                    } else {
                        $sCellContent = "&nbsp;&nbsp;&nbsp;&nbsp; " . $lngAct[$value2["perm"]][$value3] . "&nbsp;&nbsp;&nbsp;&nbsp;";
                    }
                }

                $objItem->updateAttributes([
                    "class" => "td_rights1"
                ]);
                $objItem->setContent($sCellContent);
                $items .= $objItem->render();
                $objItem->advanceID();

                $objItem->updateAttributes([
                    "class" => "td_rights2"
                ]);
                $objItem->setContent("<input type=\"checkbox\" id=\"rights_list[" . $value2["perm"] . "|$value3|0]\" name=\"rights_list[" . $value2["perm"] . "|$value3|0]\" value=\"x\" $checked>");
                $items .= $objItem->render();
                $objItem->advanceID();

                $objItem->updateAttributes([
                    "class" => "td_rights2"
                ]);
                $objItem->setContent("&nbsp;");
                $items .= $objItem->render();
                $objItem->advanceID();

                $objRow->setContent($items);
                $items = "";
                $output .= $objRow->render();
                $objRow->advanceID();
                // set javascript array for areatree
                $sJsBefore .= "areatree[\"$key\"][\"" . $value2["perm"] . "$value3\"]=\"rights_list[" . $value2["perm"] . "|$value3|0]\";\n";
            }
        }
    }
}

// table footer
$objItem->updateAttributes([
    "class" => "",
    "valign" => "top",
    "align" => "right",
    "colspan" => "3"
]);
$objItem->setContent("<a href=\"javascript:submitrightsform('', 'area');\"><img src=\"" . $cfg['path']['images'] . "but_cancel.gif\"></a><img src=\"images/spacer.gif\" width=\"20\"><a href=\"javascript:submitrightsform('user_edit', '');\"><img src=\"" . $cfg['path']['images'] . "but_ok.gif\"></a>");
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
