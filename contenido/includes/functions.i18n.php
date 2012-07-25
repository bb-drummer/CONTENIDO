<?php
/**
 * Project:
 * CONTENIDO Content Management System
 *
 * Description:
 * CONTENIDO i18n Functions
 *
 * Requirements:
 * @con_php_req 5.0
 *
 *
 * @package    CONTENIDO Backend Includes
 * @version    1.3.0
 * @author     Timo A. Hummel
 * @copyright  four for business AG <www.4fb.de>
 * @license    http://www.contenido.org/license/LIZENZ.txt
 * @link       http://www.4fb.de
 * @link       http://www.contenido.org
 * @since      file available since CONTENIDO release <= 4.6
 *
 * {@internal
 *   created 2003-07-03
 *   $Id$:
 * }}
 */

if (!defined('CON_FRAMEWORK')) {
    die('Illegal call');
}

/** @deprecated [2012-03-16] use global $_conI18n['language'] */
global $i18nLanguage;

/** @deprecated [2012-03-16] use global $_conI18n['domains'] */
global $i18nDomains;

/** @deprecated [2012-03-16] use global $_conI18n['files'] */
global $transFile;

/** @deprecated [2012-03-16] use global $_conI18n['cache'] */
global $_i18nTranslationCache;


// Global variable containing i18n related data (since 2012-03-16, v4.9)
global $_conI18n;

$_conI18n = array(
    'language' => null,
    'domains' => array(),
    'files' => array(),
    'cache' => array()
);

/**
 * gettext wrapper (for future extensions). Usage:
 * trans('Your text which has to be translated');
 *
 * @param $string string The string to translate
 * @return string  Returns the translation
 */
function trans($string) {
    return i18n($string);
}

/**
 * gettext wrapper (for future extensions). Usage:
 * i18n('Your text which has to be translated');
 *
 * @param  string  $string  The string to translate
 * @param  string  $domain  The domain to look up
 * @return string  Returns the translation
 */
function i18n($string, $domain = 'contenido') {
    global $cfg, $_conI18n, $belang, $contenido, $lang;

    // Auto initialization
    if (!$_conI18n['language']) {
        if (!isset($belang)) {
            if ($contenido) {
                // This is backend, we should trigger an error message here
                $stack = @debug_backtrace();
                $file = $stack[0]['file'];
                $line = $stack[0]['line'];
                cWarning($file, $line, 'i18nInit $belang is not set');
            }

            $belang = false; // Needed - otherwise this won't work
        }

        i18nInit($cfg['path']['contenido_locale'], $belang);
    }

    // Is emulator to use?
    if (!$cfg['native_i18n']) {
        $ret = i18nEmulateGettext($string, $domain);
        $ret = mb_convert_encoding($ret, "HTML-ENTITIES", "utf-8");
        return $ret;
    }

    // Try to use native gettext implementation
    if (extension_loaded('gettext')) {
        if (function_exists('dgettext')) {
            if ($domain != 'contenido') {
                $translation = dgettext($domain, $string);
                return $translation;
            } else {
                return gettext($string);
            }
        }
    }

    // Emulator as fallback
    $ret = i18nEmulateGettext($string, $domain);
    if (is_utf8($ret)) {
        $ret = utf8_decode($ret);
    }
    return $ret;
}

/**
 * Emulates GNU gettext
 *
 * @param  string  $string  The string to translate
 * @param  string  $domain  The domain to look up
 * @return string  Returns the translation
 */
function i18nEmulateGettext($string, $domain = 'contenido') {
    global $_conI18n;

    if ($string == "") {
        return "";
    }

    if (!isset($_conI18n['cache'][$domain])) {
        $_conI18n['cache'][$domain] = array();
    }
    if (isset($_conI18n['cache'][$domain][$string])) {
        return $_conI18n['cache'][$domain][$string];
    }

    $translationFile = $_conI18n['domains'][$domain] . $_conI18n['language'] . '/LC_MESSAGES/' . $domain . '.po';

    if (!cFileHandler::exists($translationFile)) {
        return $string;
    }

    if (!isset($_conI18n['files'][$domain])) {
        $_conI18n['files'][$domain] = cFileHandler::read($translationFile);

        // Normalize eol chars
        $_conI18n['files'][$domain] = str_replace("\n\r", "\n", $_conI18n['files'][$domain]);
        $_conI18n['files'][$domain] = str_replace("\r\n", "\n", $_conI18n['files'][$domain]);

        // Remove comment lines
        $_conI18n['files'][$domain] = preg_replace('/^#.+\n/m', '', $_conI18n['files'][$domain]);

        // Prepare for special po edit format
        /* Something like:
          #, php-format
          msgid ""
          "Hello %s,\n"
          "\n"
          "you've got a new reminder for the client '%s' at\n"
          "%s:\n"
          "\n"
          "%s"
          msgstr ""
          "Hallo %s,\n"
          "\n"
          "du hast eine Wiedervorlage erhalten f�r den Mandanten '%s' at\n"
          "%s:\n"
          "\n"
          "%s"

          has to be converted to:
          msgid "Hello %s,\n\nyou've got a new reminder for the client '%s' at\n%s:\n\n%s"
          msgstr "Hallo %s,\n\ndu hast eine Wiedervorlage erhalten f�r den Mandanten '%s' at\n%s:\n\n%s"
         */
        $_conI18n['files'][$domain] = preg_replace('/\\\n"\\s+"/m', '\\\\n', $_conI18n['files'][$domain]);
        $_conI18n['files'][$domain] = preg_replace('/(""\\s+")/m', '"', $_conI18n['files'][$domain]);
    }

    $stringStart = strpos($_conI18n['files'][$domain], '"' . str_replace(array("\n", "\r", "\t"), array('\n', '\r', '\t'), $string) . '"');
    if ($stringStart === false) {
        return $string;
    }

    $matches = array();
    $quotedString = preg_quote(str_replace(array("\n", "\r", "\t"), array('\n', '\r', '\t'), $string), '/');
    $result = preg_match("/msgid.*\"(" . $quotedString . ")\"(?:\s*)?\nmsgstr(?:\s*)\"(.*)\"/", $_conI18n['files'][$domain], $matches);
    # Old: preg_match("/msgid.*\"".preg_quote($string,"/")."\".*\nmsgstr(\s*)\"(.*)\"/", $_conI18n['files'][$domain], $matches);

    if ($result && !empty($matches[2])) {
        // Translation found, cache it
        $_conI18n['cache'][$domain][$string] = stripslashes(str_replace(array('\n', '\r', '\t'), array("\n", "\r", "\t"), $matches[2]));
    } else {
        // Translation not found, cache original string
        $_conI18n['cache'][$domain][$string] = $string;
    }

    return $_conI18n['cache'][$domain][$string];
}

/**
 * Initializes the i18n stuff.
 *
 * @param  string  $localePath  Path to the locales
 * @param  string  $langCode  Language code to set
 */
function i18nInit($localePath, $langCode) {
    global $_conI18n;

    if (function_exists('bindtextdomain')) {
        // Bind the domain 'contenido' to our locale path
        bindtextdomain('contenido', $localePath);

        // Set the default text domain to 'contenido'
        textdomain('contenido');

        // Half brute-force to set the locale.
        if (!ini_get('safe_mode')) {
            putenv("LANG=$langCode");
        }

        if (defined('LC_MESSAGES')) {
            setlocale(LC_MESSAGES, $langCode);
        }

        setlocale(LC_CTYPE, $langCode);
    }

    $_conI18n['domains']['contenido'] = $localePath;

    $_conI18n['language'] = $langCode;
}

/**
 * Registers a new i18n domain.
 *
 * @param  string  $localePath  Path to the locales
 * @param  string  $domain  Domain to bind to
 * @return string  Returns the translation
 */
function i18nRegisterDomain($domain, $localePath) {
    global $_conI18n;

    if (function_exists('bindtextdomain')) {
        // Bind the domain 'contenido' to our locale path
        bindtextdomain($domain, $localePath);
    }

    $_conI18n['domains'][$domain] = $localePath;
}

/**
 * Strips all unnecessary information from the $accept string.
 * Example: de,nl;q=0.7,en-us;q=0.3 would become an array with de,nl,en-us
 *
 * @param  string  $accept  Comma searated list of languages to accept
 * @return array array with the short form of the accept languages
 */
function i18nStripAcceptLanguages($accept) {
    $languages = explode(',', $accept);
    $shortLanguages = array();
    foreach ($languages as $value) {
        $components = explode(';', $value);
        $shortLanguages[] = $components[0];
    }

    return $shortLanguages;
}

/**
 * Tries to match the language given by $accept to
 * one of the languages in the system.
 *
 * @param  string  $accept  Language to accept
 * @return string The locale key for the given accept string
 */
function i18nMatchBrowserAccept($accept) {
    $available_languages = i18nGetAvailableLanguages();

    // Try to match the whole accept string
    foreach ($available_languages as $key => $value) {
        list($country, $lang, $encoding, $shortaccept) = $value;
        if ($accept == $shortaccept) {
            return $key;
        }
    }

    /* Whoops, we are still here. Let's match the stripped-down string.
      Example: de-ch isn't in the list. Cut it down after the '-' to 'de'
      which should be in the list. */
    $accept = substr($accept, 0, 2);
    foreach ($available_languages as $key => $value) {
        list($country, $lang, $encoding, $shortaccept) = $value;
        if ($accept == $shortaccept) {
            return $key;
        }
    }

    /// Whoops, still here? Seems that we didn't find any language. Return the default (german, yikes)
    return false;
}

/**
 * Returns the available_languages array to prevent globals.
 *
 * @return array All available languages
 */
function i18nGetAvailableLanguages() {
    /* array notes:
      First field: Language
      Second field: Country
      Third field: ISO-Encoding
      Fourth field: Browser accept mapping
      Fifth field: SPAW language
     */
    $aLanguages = array(
        'ar_AA' => array('Arabic', 'Arabic Countries', 'ISO8859-6', 'ar', 'en'),
        'be_BY' => array('Byelorussian', 'Belarus', 'ISO8859-5', 'be', 'en'),
        'bg_BG' => array('Bulgarian', 'Bulgaria', 'ISO8859-5', 'bg', 'en'),
        'cs_CZ' => array('Czech', 'Czech Republic', 'ISO8859-2', 'cs', 'cz'),
        'da_DK' => array('Danish', 'Denmark', 'ISO8859-1', 'da', 'dk'),
        'de_CH' => array('German', 'Switzerland', 'ISO8859-1', 'de-ch', 'de'),
        'de_DE' => array('German', 'Germany', 'ISO8859-1', 'de', 'de'),
        'el_GR' => array('Greek', 'Greece', 'ISO8859-7', 'el', 'en'),
        'en_GB' => array('English', 'Great Britain', 'ISO8859-1', 'en-gb', 'en'),
        'en_US' => array('English', 'United States', 'ISO8859-1', 'en', 'en'),
        'es_ES' => array('Spanish', 'Spain', 'ISO8859-1', 'es', 'es'),
        'fi_FI' => array('Finnish', 'Finland', 'ISO8859-1', 'fi', 'en'),
        'fr_BE' => array('French', 'Belgium', 'ISO8859-1', 'fr-be', 'fr'),
        'fr_CA' => array('French', 'Canada', 'ISO8859-1', 'fr-ca', 'fr'),
        'fr_FR' => array('French', 'France', 'ISO8859-1', 'fr', 'fr'),
        'fr_CH' => array('French', 'Switzerland', 'ISO8859-1', 'fr-ch', 'fr'),
        'hr_HR' => array('Croatian', 'Croatia', 'ISO8859-2', 'hr', 'en'),
        'hu_HU' => array('Hungarian', 'Hungary', 'ISO8859-2', 'hu', 'hu'),
        'is_IS' => array('Icelandic', 'Iceland', 'ISO8859-1', 'is', 'en'),
        'it_IT' => array('Italian', 'Italy', 'ISO8859-1', 'it', 'it'),
        'iw_IL' => array('Hebrew', 'Israel', 'ISO8859-8', 'he', 'he'),
        'nl_BE' => array('Dutch', 'Belgium', 'ISO8859-1', 'nl-be', 'nl'),
        'nl_NL' => array('Dutch', 'Netherlands', 'ISO8859-1', 'nl', 'nl'),
        'no_NO' => array('Norwegian', 'Norway', 'ISO8859-1', 'no', 'en'),
        'pl_PL' => array('Polish', 'Poland', 'ISO8859-2', 'pl', 'en'),
        'pt_BR' => array('Brazillian', 'Brazil', 'ISO8859-1', 'pt-br', 'br'),
        'pt_PT' => array('Portuguese', 'Portugal', 'ISO8859-1', 'pt', 'en'),
        'ro_RO' => array('Romanian', 'Romania', 'ISO8859-2', 'ro', 'en'),
        'ru_RU' => array('Russian', 'Russia', 'ISO8859-5', 'ru', 'ru'),
        'sh_SP' => array('Serbian Latin', 'Yugoslavia', 'ISO8859-2', 'sr', 'en'),
        'sl_SI' => array('Slovene', 'Slovenia', 'ISO8859-2', 'sl', 'en'),
        'sk_SK' => array('Slovak', 'Slovakia', 'ISO8859-2', 'sk', 'en'),
        'sq_AL' => array('Albanian', 'Albania', 'ISO8859-1', 'sq', 'en'),
        'sr_SP' => array('Serbian Cyrillic', 'Yugoslavia', 'ISO8859-5', 'sr-cy', 'en'),
        'sv_SE' => array('Swedish', 'Sweden', 'ISO8859-1', 'sv', 'se'),
        'tr_TR' => array('Turkisch', 'Turkey', 'ISO8859-9', 'tr', 'tr')
    );

    return $aLanguages;
}

function mi18n($string) {
    global $cCurrentModule;

    // dont workd by setup/upgrade
    cInclude('classes', 'contenido/class.module.php');
    cInclude('classes', 'module/class.module.filetranslation.php');

    $contenidoTranslateFromFile = new cModuleFileTranslation($cCurrentModule, true);
    $array = $contenidoTranslateFromFile->getLangarray();

    /*
      if (!is_object($mi18nTranslator)) {
      $mi18nTranslator = new cApiModuleTranslationCollection;
      } */

    return ($array[$string] == '') ? $string : $array[$string];
}

?>