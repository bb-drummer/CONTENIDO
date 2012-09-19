<?php
/**
 * Project:
 * CONTENIDO Content Management System
 *
 * Description:
 * CONTENIDO Content Functions
 *
 * Requirements:
 * @con_notice Please add only stuff which is relevant for the frontend
 *             AND the backend. This file should NOT contain any backend editing
 *             functions to improve frontend performance:
 *
 *
 * @package    CONTENIDO Backend Includes
 * @version    1.3.9
 * @author     Timo A. Hummel
 * @copyright  four for business AG <www.4fb.de>
 * @license    http://www.contenido.org/license/LIZENZ.txt
 * @link       http://www.4fb.de
 * @link       http://www.contenido.org
 * @since      file available since CONTENIDO release <= 4.6
 */

if (!defined('CON_FRAMEWORK')) {
    die('Illegal call');
}

/**
 * Generates the code for one article
 *
 * @param int $idcat Id of category
 * @param int $idart Id of article
 * @param int $lang Id of language
 * @param int $client Id of client
 * @param int $layout Layout-ID of alternate Layout (if false, use associated layout)
 * @param bool $save  Flag to persist generated code in database
 * @return string The generated code or "0601" if neither article nor category configuration
 *                was found
 */
function conGenerateCode($idcat, $idart, $lang, $client, $layout = false, $save = true, $contype = true) {
    global $cfg, $frontend_debug;

    // @todo make generator configurable
    $codeGen = cCodeGeneratorFactory::getInstance($cfg['code_generator']['name']);
    if (isset($frontend_debug) && is_array($frontend_debug)) {
        $codeGen->setFrontendDebugOptions($frontend_debug);
    }

    $code = $codeGen->generate($idcat, $idart, $lang, $client, $layout, $save, $contype);

    // execute CEC hook
    $code = cApiCecHook::executeAndReturn('Contenido.Content.conGenerateCode', $code);

    return $code;
}

/**
 * Returns the idartlang for a given article and language
 *
 * @param  int  $idart ID of the article
 * @param  int  $idlang ID of the language
 * @return mixed idartlang of the article or false if nothing was found
 *
 * @author Timo A. Hummel <Timo.Hummel@4fb.de>
 * @copyright four for business AG 2003
 */
function getArtLang($idart, $idlang) {
    $oArtLangColl = new cApiArticleLanguageCollection();
    $idartlang = $oArtLangColl->getIdByArticleIdAndLanguageId($idart, $idlang);
    return ($idartlang) ? $idartlang : false;
}

/**
 * Returns all available meta tag types
 *
 * @return  array  Assoziative meta tags list
 */
function conGetAvailableMetaTagTypes() {
    $oMetaTypeColl = new cApiMetaTypeCollection();
    $oMetaTypeColl->select();
    $aMetaTypes = array();

    while (($oMetaType = $oMetaTypeColl->next()) !== false) {
        $rs = $oMetaType->toArray();
        $aMetaTypes[$rs['idmetatype']] = array(
            'metatype' => $rs['metatype'],
            'fieldtype' => $rs['fieldtype'],
            'maxlength' => $rs['maxlength'],
            'fieldname' => $rs['fieldname'],
        );
    }

    return $aMetaTypes;
}

/**
 * Get the meta tag value for a specific article
 *
 * @param int $idartlang ID of the article
 * @param int $idmetatype Metatype-ID
 * @return  string
 */
function conGetMetaValue($idartlang, $idmetatype) {
    static $oMetaTagColl;
    if (!isset($oMetaTagColl)) {
        $oMetaTagColl = new cApiMetaTagCollection();
    }

    if ((int) $idartlang <= 0) {
        return '';
    }

    $oMetaTag = $oMetaTagColl->fetchByArtLangAndMetaType($idartlang, $idmetatype);
    if (is_object($oMetaTag)) {
        return stripslashes($oMetaTag->get('metavalue'));
    } else {
        return '';
    }
}

/**
 * Set the meta tag value for a specific article.
 *
 * @param  int  $idartlang ID of the article
 * @param  int  $idmetatype Metatype-ID
 * @param  string  $value Value of the meta tag
 * @return bool whether the meta value has been saved successfully
 */
function conSetMetaValue($idartlang, $idmetatype, $value) {
    static $metaTagColl;
    if (!isset($metaTagColl)) {
        $metaTagColl = new cApiMetaTagCollection();
    }

    $metaTag = $metaTagColl->fetchByArtLangAndMetaType($idartlang, $idmetatype);
    $artLang = new cApiArticleLanguage($idartlang);
    $artLang->set('lastmodified', date('Y-m-d H:i:s'));
    $artLang->store();
    if (is_object($metaTag)) {
        return $metaTag->updateMetaValue($value);
    } else {
        $metaTagColl->create($idartlang, $idmetatype, $value);
        return true;
    }
}

/**
 * (re)generate keywords for all articles of a given client (with specified language)
 * @param int $client Client
 * @param int $lang Language of a client
 * @return void
 *
 * @author Willi Man
 * Created   :   12.05.2004
 * Modified  :   13.05.2004
 * @copyright four for business AG 2003
 */
function conGenerateKeywords($client, $lang) {
    global $cfg;

    static $oDB;
    if (!isset($oDB)) {
        $oDB = cRegistry::getDb();
    }

    $options = array('img', 'link', 'linktarget', 'swf'); // cms types to be excluded from indexing

    $sql = 'SELECT a.idart, b.idartlang FROM ' . $cfg['tab']['art'] . ' AS a, ' . $cfg['tab']['art_lang'] . ' AS b
            WHERE a.idart=b.idart AND a.idclient=' . (int) $client . ' AND b.idlang=' . (int) $lang;

    $oDB->query($sql);

    $aArticles = array();
    while ($oDB->next_record()) {
        $aArticles[$oDB->f('idart')] = $oDB->f('idartlang');
    }

    foreach ($aArticles as $artid => $artlangid) {
        $aContent = conGetContentFromArticle($artlangid);
        if (count($aContent) > 0) {
            $oIndex = new cSearchIndex($oDB);
            $oIndex->lang = $lang;
            $oIndex->start($artid, $aContent, 'auto', $options);
        }
    }
}

/**
 * Get content from article by article language.
 * @param int $iIdArtLang ArticleLanguageId of an article (idartlang)
 * @return array Array with content of an article indexed by content-types as follows:
 *               - $arr[type][typeid] = value;
 *
 * @author Willi Man
 * Created   :   12.05.2004
 * Modified  :   13.05.2004
 * @copyright four for business AG 2003
 */
function conGetContentFromArticle($iIdArtLang) {
    global $cfg;

    static $oDB;
    if (!isset($oDB)) {
        $oDB = cRegistry::getDb();
    }

    $aContent = array();

    $sql = 'SELECT * FROM ' . $cfg['tab']['content'] . ' AS A, ' . $cfg['tab']['art_lang'] . ' AS B, ' . $cfg['tab']['type'] . ' AS C
            WHERE A.idtype=C.idtype AND A.idartlang=B.idartlang AND A.idartlang=' . (int) $iIdArtLang;
    $oDB->query($sql);
    while ($oDB->next_record()) {
        $aContent[$oDB->f('type')][$oDB->f('typeid')] = $oDB->f('value');
    }

    return $aContent;
}

/**
 * Returns list of all used modules by template id
 *
 * @param  int $idtpl  Template id
 * @return  array  Assoziative array where the key is the number and value the module id
 */
function conGetUsedModules($idtpl) {
    $modules = array();

    $oContainerColl = new cApiContainerCollection();
    $oContainerColl->select('idtpl = ' . (int) $idtpl, '', 'number ASC');
    while (($oContainer = $oContainerColl->next()) !== false) {
        $modules[(int) $oContainer->get('number')] = (int) $oContainer->get('idmod');
    }

    return $modules;
}

/**
 * Returns list of all configured container by template configuration id
 *
 * @param  int  $idtplcfg  Template configuration id
 * @return  array  Assoziative array where the key is the number and value the container
 *                 configuration
 */
function conGetContainerConfiguration($idtplcfg) {
    $configuration = array();

    $oContainerConfColl = new cApiContainerConfigurationCollection();
    $oContainerConfColl->select('idtplcfg = ' . (int) $idtplcfg, '', 'number ASC');
    while (($oContainerConf = $oContainerConfColl->next()) !== false) {
        $configuration[(int) $oContainerConf->get('number')] = $oContainerConf->get('container');
    }

    return $configuration;
}

/**
 * Returns category article id
 *
 * @param  int  $idcat
 * @param  int  $idart
 * @return  int|null
 */
function conGetCategoryArticleId($idcat, $idart) {
    global $cfg, $db;

    // Get idcatart, we need this to retrieve the template configuration
    $sql = 'SELECT idcatart FROM `%s` WHERE idcat = %d AND idart = %d';
    $sql = $db->prepare($sql, $cfg['tab']['cat_art'], $idcat, $idart);
    $db->query($sql);

    return ($db->next_record()) ? $db->f('idcatart') : null;
}

/**
 * Returns template configuration id for a configured article.
 *
 * @param  int  $idart
 * @param  int  $idcat  NOT used
 * @param  int  $lang
 * @param  int  $client
 * @return  int|null
 */
function conGetTemplateConfigurationIdForArticle($idart, $idcat, $lang, $client) {
    global $cfg, $db;

    // Retrieve template configuration id
    $sql = "SELECT a.idtplcfg AS idtplcfg FROM `%s` AS a, `%s` AS b WHERE a.idart = %d "
         . "AND a.idlang = %d AND b.idart = a.idart AND b.idclient = %d";
    $sql = $db->prepare($sql, $cfg['tab']['art_lang'], $cfg['tab']['art'], $idart, $lang, $client);
    $db->query($sql);

    return ($db->next_record()) ? $db->f('idtplcfg') : null;
}

/**
 * Returns template configuration id for a configured category
 *
 * @param  int  $idcat
 * @param  int  $lang
 * @param  int  $client
 * @return  int|null
 */
function conGetTemplateConfigurationIdForCategory($idcat, $lang, $client) {
    global $cfg, $db;

    // Retrieve template configuration id
    $sql = "SELECT a.idtplcfg AS idtplcfg FROM `%s` AS a, `%s` AS b WHERE a.idcat = %d AND "
         . "a.idlang = %d AND b.idcat = a.idcat AND b.idclient = %d";
    $sql = $db->prepare($sql, $cfg['tab']['cat_lang'], $cfg['tab']['cat'], $idcat, $lang, $client);
    $db->query($sql);

    return ($db->next_record()) ? $db->f('idtplcfg') : null;
}
