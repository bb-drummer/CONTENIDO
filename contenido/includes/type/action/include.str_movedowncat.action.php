<?php

/**
 * Backend action file str_movedowncat
 *
 * @package          Core
 * @subpackage       Backend
 * @version          SVN Revision $Rev:$
 *
 * @author           Dominik Ziegler
 * @copyright        four for business AG <www.4fb.de>
 * @license          http://www.contenido.org/license/LIZENZ.txt
 * @link             http://www.4fb.de
 * @link             http://www.contenido.org
 */

defined('CON_FRAMEWORK') || die('Illegal call: Missing framework initialization - request aborted.');

cInclude('includes', 'functions.str.php');

if ($perm->have_perm_area_action("str", "str_movedowncat") || $perm->have_perm_area_action_item("str", "str_movedowncat", $idcat)) {
    strMoveDownCategory($idcat);
    strRemakeTreeTable();
    cApiCecHook::execute("Contenido.Action.str_movedowncat.AfterCall", $idcat);
} else {
    $notification->displayNotification("error", i18n("Permission denied"));
}

?>