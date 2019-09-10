<?php

/**
 * This file contains various function for the plugin content allocation.
 *
 * @package    Plugin
 * @subpackage ContentAllocation
 * @author     Unknown
 * @copyright  four for business AG <www.4fb.de>
 * @license    http://www.contenido.org/license/LIZENZ.txt
 * @link       http://www.4fb.de
 * @link       http://www.contenido.org
 */

defined('CON_FRAMEWORK') || die('Illegal call: Missing framework initialization - request aborted.');

/**
 * @return array
 */
function pica_RegisterCustomTab()
{
    return ["con_contentallocation"];
}

/**
 * @param $sIntName
 *
 * @return array
 */
function pica_GetCustomTabProperties($sIntName)
{
    if ($sIntName == "con_contentallocation") {
        return ["con_contentallocation", "con_edit", ""];
    }
}

/**
 * @param $aActions
 *
 * @return mixed
 */
function pica_ArticleListActions($aActions)
{
    $aTmpActions["con_contentallocation"] = "con_contentallocation";

    return $aTmpActions + $aActions;
}

/**
 * @param $idcat
 * @param $idart
 * @param $idartlang
 * @param $actionkey
 *
 * @return string
 * @throws cException
 */
function pica_RenderArticleAction($idcat, $idart, $idartlang, $actionkey)
{
    global $sess;

    $anchor = '';
    if ($actionkey == 'con_contentallocation') {
        $label  = i18n('Tagging', 'content_allocation');
        $url    = $sess->url(
            'main.php?area=con_contentallocation&action=con_edit&idart=' . $idart . '&idartlang=' . $idartlang
            . '&idcat=' . $idcat . '&frame=4'
        );
        $image  = '<img src="plugins/content_allocation/images/call_contentallocation.gif" alt="' . $label . '">';
        $anchor = '<a title="' . $label . '" alt="' . $label . '" href="' . $url . '">' . $image . '</a>';
    }

    return $anchor;
}

?>