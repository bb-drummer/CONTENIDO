<?php

/**
 * Backend action file con_tplcfg_edit
 *
 * @package    Core
 * @subpackage Backend
 * @author     Dominik Ziegler
 * @copyright  four for business AG <www.4fb.de>
 * @license    https://www.contenido.org/license/LIZENZ.txt
 * @link       https://www.4fb.de
 * @link       https://www.contenido.org
 */

defined('CON_FRAMEWORK') || die('Illegal call: Missing framework initialization - request aborted.');

// rights are being checked by the include file itself
include($cfg["path"]["includes"] . "include.tplcfg_edit_form.php");
