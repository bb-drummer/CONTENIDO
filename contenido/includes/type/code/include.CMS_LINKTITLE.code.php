<?php
/**
 * Project:
 * CONTENIDO Content Management System
 *
 * Description:
 * CMS_LINKTITLE code
 *
 * NOTE: This file will be included by the code generator while processing CMS tags in layout.
 * It runs in a context of a function and requires some predefined variables!
 *
 * Requirements:
 * @con_php_req 5.0
 *
 * @package    CONTENIDO Backend Includes
 * @version    0.0.1
 * @author     Murat Purc <murat@purc.de>
 * @copyright  four for business AG <www.4fb.de>
 * @license    http://www.contenido.org/license/LIZENZ.txt
 * @link       http://www.4fb.de
 * @link       http://www.contenido.org
 * @since      file available since CONTENIDO release 4.9.0
 *
 * {@internal
 *   created  2012-02-14
 *
 *   $Id$:
 * }}
 *
 */


if (!defined('CON_FRAMEWORK')) {
    die('Illegal call');
}


$linkDescr = new cContentTypeLinkDescr($a_content['CMS_LINKDESCR'][$val], $val, $a_content);

if ($edit) {
    cDeprecated('Do not use CMS_LINKTITLE any more - use CMS_LINKDESCR instead!');
    $cNotification = new Contenido_Notification();
    $notification = $cNotification->messageBox(Contenido_Notification::LEVEL_WARNING, 'Sie benutzen einen veralteten Content-Typen (CMS_LINKTITLE). Dieser Content-Typ wird in einer späteren Version von CONTENIDO nicht mehr unterstützt. Bitte wechseln Sie auf den neuen Content-Typen CMS_LINKDESCR.');
    $notification = addslashes($notification);
    $notification = str_replace("\\'", "'", $notification);
    $notification = str_replace('\$', '\\$', $notification);
    $tmp = $notification;
    $tmp .= $linkDescr->generateEditCode();
} else {
    $tmp = $linkDescr->generateViewCode();
}

?>