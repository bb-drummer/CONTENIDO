<?php
/**
 * Project:
 * CONTENIDO Content Management System
 *
 * Description:
 * Builds the third navigation layer
 *
 * Requirements:
 * @con_php_req 5.0
 *
 *
 * @package    CONTENIDO Backend Includes
 * @version    1.0.2
 * @author     Jan Lengowski
 * @copyright  four for business AG <www.4fb.de>
 * @license    http://www.contenido.org/license/LIZENZ.txt
 * @link       http://www.4fb.de
 * @link       http://www.contenido.org
 * @since      file available since CONTENIDO release <= 4.6
 *
 * {@internal
 *   created 2003-01-25
 *   $Id$:
 * }}
 */

if (!defined('CON_FRAMEWORK')) {
    die('Illegal call');
}

//Get sync options
if (isset($syncoptions)) {
    $syncfrom = $syncoptions;
    $remakeCatTable = true;
}

if (!isset($syncfrom)) {
    $syncfrom = 0;
}
if (!isset($idcat) || $idcat == "") {
    $idcat = 0;
}

if (isset($_GET['display_menu']) && $_GET['display_menu'] == 1) {
    $nav = new Contenido_Navigation();

    $sql = "SELECT
                idarea
            FROM
                ".$cfg["tab"]["area"]." AS a
            WHERE
                a.name = '".cSecurity::escapeDB($area, $db)."' OR
                a.parent_id = '".cSecurity::escapeDB($area, $db)."'
            ORDER BY
                idarea";

    $db->query($sql);

    $in_str = "";

    while ($db->next_record()) {
        $in_str .= $db->f('idarea') . ',';
    }

    $len = strlen($in_str)-1;
    $in_str = substr($in_str, 0, $len);
    $in_str = '('.$in_str.')';

    $sql = "SELECT
                b.location AS location,
                a.name AS name
            FROM
                ".$cfg["tab"]["area"]." AS a,
                ".$cfg["tab"]["nav_sub"]." AS b
            WHERE
                b.idarea IN ".cSecurity::escapeDB($in_str, $db)." AND
                b.idarea = a.idarea AND
                b.level = 1 AND
                b.online = 1
            ORDER BY
                b.idnavs";

    $db->query($sql);

    while ($db->next_record()) {
        // Extract names from the XML document.
        $caption = $nav->getName($db->f("location"));

        $tmp_area = $db->f("name");

        // Set template data
        $tpl->set("d", "ID",      'c_'.$tpl->dyn_cnt);
        $tpl->set("d", "CLASS",   '');
        $tpl->set("d", "OPTIONS", '');
        if ($cfg['help'] == true) {
            $tpl->set("d", "CAPTION", '<a onclick="'.setHelpContext(i18n("Article")."/$caption").'sub.clicked(this);artObj.doAction(\''.$tmp_area.'\')">'.$caption.'</a>');
        } else {
            $tpl->set("d", "CAPTION", '<a onclick="sub.clicked(this);artObj.doAction(\''.$tmp_area.'\')">'.$caption.'</a>');
        }

        $tpl->next();
    }

    $tpl->set('s', 'COLSPAN', ($tpl->dyn_cnt * 2) + 2);
    $tpl->set('s', 'IDCAT', $idcat);
    $tpl->set('s', 'SESSID', $sess->id);
    $tpl->set('s', 'CLIENT', $client);
    $tpl->set('s', 'LANG', $lang);

    // Generate the third navigation layer
    $tpl->generate($cfg["path"]["templates"] . $cfg["templates"]["con_subnav"]);

} else {
    include($cfg["path"]["contenido"].$cfg["path"]["templates"] . $cfg["templates"]["right_top_blank"]);
}

?>