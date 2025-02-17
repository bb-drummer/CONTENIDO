<?php

/**
 * Central CONTENIDO file to initialize the application. Performs following steps:
 * - Initial PHP setting
 * - Does basic security check
 * - Includes configurations
 * - Runs validation of request variables
 * - Loads available login languages
 * - Initializes CEC
 * - Includes user-defined configuration
 * - Sets/Checks DB connection
 * - Initializes UriBuilder
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

global $cfg, $cfgClient, $errsite_idcat, $errsite_idart;

/* Initial PHP error handling settings.
 * NOTE: They will be overwritten below...
 */
// Don't display errors
@ini_set('display_errors', false);

// Log errors to a file
@ini_set('log_errors', true);

// Report all errors except warnings
error_reporting(E_ALL ^E_NOTICE);

/* Initial PHP session settings.
 * NOTE: When you change these values by custom configuration, the length of the session ID may differ from 32 characters.
 * As this length was a criteria for session ID validity in previous versions of CONTENIDO, changes may affect your scripts.
 */

// Set session hash function to SHA-1
@ini_set('session.hash_function', 1);

// Set 5 bits per character
@ini_set('session.hash_bits_per_character', 5);

include_once(__DIR__ . '/defines.php');

// Temporary backend path, will be re-set again later...
$backendPath = str_replace('\\', '/', realpath(__DIR__ . '/..'));

// Include the environment definer file
include_once($backendPath . '/environment.php');

require_once($backendPath . '/classes/class.filehandler.php');

// (string) Path to folder containing all contenido configuration files. Use environment setting!
$cfg['path']['contenido_config'] = str_replace('\\', '/', realpath(__DIR__ . '/../..')) . '/data/config/' . CON_ENVIRONMENT . '/';

// check if config folder & files exist
if (false === cFileHandler::exists($cfg['path']['contenido_config'])) {
    $msg = "<h1>Fatal Error</h1><br>"
        . "The configured <b>environment</b> is not valid.<br><br>"
        . "Please make sure that your CON_ENVIRONMENT is valid and has an existing directory in contenido/data/config.";
    die($msg);
}

if (false === cFileHandler::exists($cfg['path']['contenido_config'] . 'config.php')
|| false === cFileHandler::exists($cfg['path']['contenido_config'] . 'config.clients.php')) {
    $msg = "<h1>Fatal Error</h1><br>"
        . "Could not open a configuration file <b>config.php</b> or <b>config.clients.php</b>.<br><br>"
        . "Please make sure that you saved the file in the setup program and that your CON_ENVIRONMENT is valid. "
        . "If you had to place the file manually on your webserver, make sure that it is placed in your contenido/data/config/{environment}/ directory.";
    die($msg);
}

include_once($backendPath . '/includes/functions.php54.php');
include_once($backendPath . '/includes/functions.php_polyfill.php');

// Security check: Include security class and invoke basic request checks
require_once($backendPath . '/classes/class.registry.php');
require_once($backendPath . '/classes/class.security.php');

// Include cStringMultiByteWrapper and cString
require_once($backendPath . '/classes/class.string.multi.byte.wrapper.php');
require_once($backendPath . '/classes/class.string.php');

require_once($backendPath . '/classes/class.requestvalidator.php');
try {
    $requestValidator = cRequestValidator::getInstance();
    $requestValidator->checkParams();
} catch (cFileNotFoundException $e) {
    die($e->getMessage());
}

// "Workaround" for register_globals=off settings.
require_once($backendPath . '/includes/globals_off.inc.php');

// Include some basic configuration files
require_once($cfg['path']['contenido_config'] . 'config.php');
require_once($cfg['path']['contenido_config'] . 'config.path.php');
require_once($cfg['path']['contenido_config'] . 'config.misc.php');
require_once($cfg['path']['contenido_config'] . 'config.templates.php');
require_once($cfg['path']['contenido_config'] . 'cfg_sql.inc.php');

if (cFileHandler::exists($cfg['path']['contenido_config'] . 'config.clients.php')) {
    require_once($cfg['path']['contenido_config'] . 'config.clients.php');
    if (is_array($errsite_idcat)) {
        $errsite_idcat = [];
    }
    if (is_array($errsite_idart)) {
        $errsite_idart = [];
    }
    foreach ($cfgClient as $id => $aClientCfg) {
        if (is_array($aClientCfg)) {
            $errsite_idcat[$id] = $aClientCfg['errsite']['idcat'];
            $errsite_idart[$id] = $aClientCfg['errsite']['idart'];
        }
    }
}

// Include user-defined configuration (if available), where you are able to
// extend/overwrite core settings from included configuration files above
if (cFileHandler::exists($cfg['path']['contenido_config'] . 'config.local.php')) {
    require_once($cfg['path']['contenido_config'] . 'config.local.php');
}

// Takeover configured PHP settings
if ($cfg['php_settings'] && is_array($cfg['php_settings'])) {
    foreach ($cfg['php_settings'] as $settingName => $value) {
        // date.timezone is handled separately
        if ($settingName !== 'date.timezone') {
            @ini_set($settingName, $value);
        }
    }
}
error_reporting($cfg['php_error_reporting']);

// force date.timezone setting
$timezoneCfg = $cfg['php_settings']['date.timezone'];
if (!empty($timezoneCfg) && ini_get('date.timezone') !== $timezoneCfg) {
    // if the timezone setting from the cfg differs from the php.ini setting, set timezone from CFG
    date_default_timezone_set($timezoneCfg);
} elseif (empty($timezoneCfg) && (ini_get('date.timezone') === '' || ini_get('date.timezone') === false)) {
    // if there are no timezone settings, set UTC timezone
    date_default_timezone_set('UTC');
}

$backendPath = cRegistry::getBackendPath();

// Various base API functions
require_once($backendPath . $cfg['path']['includes'] . 'api/functions.api.general.php');

// Initialization of autoloader
require_once($backendPath . $cfg['path']['classes'] . 'class.autoload.php');
cAutoload::initialize($cfg);

// Generate arrays for available login languages
// Author: Martin Horwath
$localePath = $cfg['path']['contenido_locale'];
if (is_dir($localePath)) {
    if (false !== ($handle = cDirHandler::read($localePath, false, true))) {
        foreach ($handle as $locale) {
            if (cFileHandler::fileNameIsDot($locale) === false
                && is_dir($localePath . $locale)) {
                if (cFileHandler::exists($localePath . $locale . '/LC_MESSAGES/contenido.po') &&
                cFileHandler::exists($localePath . $locale . '/LC_MESSAGES/contenido.mo')) {
                    $cfg['login_languages'][] = $locale;
                    $cfg['lang'][$locale] = 'lang_' . $locale . '.xml';
                }
            }
        }
    }
}

// Some general includes
cInclude('includes', 'functions.general.php');
cInclude('includes', 'functions.i18n.php');
cInclude('includes', 'functions.lang.php');

// Initialization of CEC
$_cecRegistry = cApiCecRegistry::getInstance();
$_cecRegistry->flushAddedChains();

// load all system chain inclusions if there are any
if (cFileHandler::exists($cfg['path']['contenido_config'] . 'config.chains.load.php')) {
    include_once($cfg['path']['contenido_config'] . 'config.chains.load.php');
}

// Set default database connection parameter
cDb::setDefaultConfiguration($cfg['db']);

// Initialize UriBuilder, configuration is set in data/config/{environment}/config.misc.php
cUriBuilderConfig::setConfig($cfg['url_builder']);

unset($backendPath, $localePath, $timezoneCfg, $handle);
