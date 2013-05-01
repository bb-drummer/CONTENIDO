<?php
/**
 * Project:
 * Contenido Content Management System
 *
 * Description:
 * Generates the configuration file and saves it into contenido folder or
 * outputs the for download (depending on selected option during setup)
 *
 * Requirements:
 * @con_php_req 5
 *
 * @package    Contenido setup
 * @version    0.2.1
 * @author     unknown
 * @copyright  four for business AG <www.4fb.de>
 * @license    http://www.contenido.org/license/LIZENZ.txt
 * @link       http://www.4fb.de
 * @link       http://www.contenido.org
 * @since      file available since contenido release <Contenido Version>
 *
 * {@internal
 *   created  unknown
 *   $Id: makeconfig.php 622 2008-07-21 13:19:04Z dominik.ziegler $:
 * }}
 *
 */

if (!defined('CON_FRAMEWORK')) {
    define('CON_FRAMEWORK', true);
}
define('C_CONTENIDO_PATH', '../contenido/');

define('CON_SETUP_PATH', str_replace('\\', '/', realpath(dirname(__FILE__))));

define('CON_FRONTEND_PATH', str_replace('\\', '/', realpath(dirname(__FILE__) . '/../')));

include_once('lib/startup.php');


list($root_path, $root_http_path) = getSystemDirectories();

$tpl = new Template();
$tpl->set('s', 'CONTENIDO_ROOT', $root_path);
$tpl->set('s', 'CONTENIDO_WEB', $root_http_path);
$tpl->set('s', 'MYSQL_HOST', $_SESSION['dbhost']);
$tpl->set('s', 'MYSQL_DB', $_SESSION['dbname']);
$tpl->set('s', 'MYSQL_USER', $_SESSION['dbuser']);
$tpl->set('s', 'MYSQL_PASS', $_SESSION['dbpass']);
$tpl->set('s', 'MYSQL_PREFIX', $_SESSION['dbprefix']);
$tpl->set('s', 'MYSQL_CHARSET', $_SESSION['dbcharset']);
$tpl->set('s', 'DB_EXTENSION', (string) getMySQLDatabaseExtension());

if ($_SESSION['start_compatible'] == true) {
    $tpl->set('s', 'START_COMPATIBLE', 'true');
} else {
    $tpl->set('s', 'START_COMPATIBLE', 'false');
}

$tpl->set('s', 'NOLOCK', $_SESSION['nolock']);

if ($_SESSION['configmode'] == 'save') {
    @unlink($root_path . '/contenido/includes/config.php');

    $handle = fopen($root_path . '/contenido/includes/config.php', 'wb');
    fwrite($handle, $tpl->generate(CON_SETUP_PATH . '/templates/config.php.tpl', true, false));
    fclose($handle);

    if (!file_exists($root_path . '/contenido/includes/config.php')) {
        $_SESSION['configsavefailed'] = true;
    } else {
        unset($_SESSION['configsavefailed']);
    }
} else {
    header('Content-Type: application/octet-stream');
    header('Etag: ' . md5(mt_rand()));
    header('Content-Disposition: attachment;filename=config.php');
    $tpl->generate('templates/config.php.tpl', false, false);
}

?>