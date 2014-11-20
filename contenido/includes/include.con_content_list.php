<?php

/**
 * This file contains the backend page for displaying all content of an article.
 *
 * @package Core
 * @subpackage Backend
 * @version SVN Revision $Rev:$
 *
 * @author Fulai Zhang
 * @copyright four for business AG <www.4fb.de>
 * @license http://www.contenido.org/license/LIZENZ.txt
 * @link http://www.4fb.de
 * @link http://www.contenido.org
 */
defined('CON_FRAMEWORK') || die('Illegal call: Missing framework initialization - request aborted.');

$backendPath = cRegistry::getBackendPath();
$backendUrl = cRegistry::getBackendUrl();

cInclude('includes', 'functions.str.php');
cInclude('includes', 'functions.pathresolver.php');

if (!isset($idcat)) {
    cRegistry::shutdown();
    return;
}

$edit = 'true';
$scripts = '';
// export only these content types
$allowedContentTypes = array(
    "CMS_HTMLHEAD",
    "CMS_HTML",
    "CMS_TEXT",
    "CMS_LINK",
    "CMS_LINKTARGET",
    "CMS_LINKDESCR",
    "CMS_HEAD",
    "CMS_DATE",
    "CMS_RAW"
);

$versioning = new cContentVersioning();
$versioningState = $versioning->getState();

$page = new cGuiPage("con_content_list");

if (!($perm->have_perm_area_action($area, "savecontype") || $perm->have_perm_area_action_item($area, "savecontype", $idcat) || $perm->have_perm_area_action("con", "deletecontype") || $perm->have_perm_area_action_item("con", "deletecontype", $idcat))) {
    // $page->displayCriticalError(i18n("Permission denied")); (Apparently one of the action files already displays this error message)
    $page->abortRendering();
    $page->render();
    die();
}

// save / set value from content
if (($action == 'savecontype' || $action == 10)) {
    if ($perm->have_perm_area_action($area, "savecontype") || $perm->have_perm_area_action_item($area, "savecontype", $idcat)) {
        if ($data != '') {
            $data = explode('||', substr($data, 0, -2));
            foreach ($data as $value) {
                $value = explode('|', $value);
                if ($value[3] == '%$%EMPTY%$%') {
                    $value[3] = '';
                } else {
                    $value[3] = str_replace('%$%SEPERATOR%$%', '|', $value[3]);
                }
                conSaveContentEntry($value[0], 'CMS_' . $value[1], $value[2], $value[3]);
            }

            $versioning = new cContentVersioning();
            if ($versioning->getState() != 'advanced') {
                conMakeArticleIndex($idartlang, $idart);
            }

            // restore orginal values
            $data = $_REQUEST['data'];
            $value = $_REQUEST['value'];

            $notification->displayNotification("info", i18n("Changes saved"));
        }

        conGenerateCodeForArtInAllCategories($idart);
    } else {
        $page->displayError(i18n("Permission denied"));
    }
} else if ($action == 'deletecontype') {
    if ($perm->have_perm_area_action($Area, "deletecontype") || $perm->have_perm_area_action_item($area, "deletecontype", $idcat)) {
        if (isset($_REQUEST['idcontent']) && is_numeric($_REQUEST['idcontent'])) {

            $linkedTypes = array(
                4 => 22, // if a CMS_IMG is deleted, the corresponding
                // CMS_IMAGEEDITOR will be deleted too
                22 => 4 // the same goes for the other way round
            );

            switch ($versioningState) {
                case 'simple':
                    $oContentColl = new cApiContentCollection();
                    $content = new cApiContent();
                    $contentVersionColl = new cApiContentVersionCollection();
                    $contentItem = new cApiContent((int) $_REQUEST["idcontent"]);
                    if (isset($linkedTypes[$contentItem->get("idtype")])) {
                        $linkedIds = $oContentColl->getIdsByWhereClause("`idartlang`='" . $idartlang . "' AND `idtype`='" . $linkedTypes[$contentItem->get("idtype")] . "' AND `value`='" . $contentItem->get("value") . "'");
                        foreach ($linkedIds as $linkedId) {
                            $oContentColl->delete($linkedId);
                        }
                    }
                   
                    $artLang = new cApiArticleLanguage($idartlang);
                    $artLangVersion = $versioning->createArticleLanguageVersion($artLang->toArray());
                    $artLangVersion->markAsCurrentVersion(1);
                    
                    $content->loadByPrimaryKey((int) $_REQUEST["idcontent"]);
                    $parameters = $content->toArray();
                    $parameters['deleted'] = 1;
                    $parameters['version'] = $artLangVersion->get('version');
                    $contentVersionColl->create($parameters);
                    $oContentColl->delete((int) $_REQUEST['idcontent']);
                    
                    break;
                case 'advanced':
                    $oContentVersionColl = new cApiContentVersionCollection();
                    $contentVersionItem = new cApiContentVersion((int) $_REQUEST["idcontent"]);
                    /*if (isset($linkedTypes[$contentVersionItem->get("idtype")])) {                      
                        $linkedIds = $oContentVersionColl->getIdsByWhereClause("`idcontent`='" . (int) $_REQUEST["idcontent"] . "' AND `idartlang`='" . $idartlang . "' AND `idtype`='" . $linkedTypes[$contentVersionItem->get("idtype")] . "' AND `value`='" . $contentVersionItem->get("value") . "'");
                      foreach ($linkedIds as $linkedId) {
                        $contentVersionItem->delete($linkedId);
                      }
                     }*/
                    
                    $contentParameters = $contentVersionItem->values;
                    $contentParameters['version'] = $contentParameters['version'] + 1;
                    $contentParameters['deleted'] = 1;
                    unset($contentParameters['idcontentversion']);
                                        
                    $versioning->createContentVersion($contentParameters);
            
                    break;
                case 'disabled':
                    $oContentColl = new cApiContentCollection();
                    $contentItem = new cApiContent((int) $_REQUEST["idcontent"]);
                    if (isset($linkedTypes[$contentItem->get("idtype")])) {
                        $linkedIds = $oContentColl->getIdsByWhereClause("`idartlang`='" . $idartlang . "' AND `idtype`='" . $linkedTypes[$contentItem->get("idtype")] . "' AND `value`='" . $contentItem->get("value") . "'");
                        foreach ($linkedIds as $linkedId) {
                            $oContentColl->delete($linkedId);
                        }
                    }
                    $oContentColl->delete((int) $_REQUEST['idcontent']);

                default:
                    break;
            }

            $notification->displayNotification("info", i18n("Changes saved"));

            conGenerateCodeForArtInAllCategories($idart);
        }
    } else {
        $page->displayError(i18n("Permission denied"));
    }
} else if ($action == 'exportrawcontent') {

    // extended class to add CDATA to content
    class SimpleXMLExtended extends SimpleXMLElement {

        public function addCData($cdata_text) {
            $node = dom_import_simplexml($this);
            $no = $node->ownerDocument;
            $node->appendChild($no->createCDATASection($cdata_text));
        }

    }

    // load article language object
    $cApiArticleLanguage = new cApiArticleLanguage(cSecurity::toInteger($idartlang));
    // create xml element articles
    $articleElement = new SimpleXMLExtended('<?xml version="1.0" encoding="UTF-8"?><articles></articles>');

    // add child element article
    $articleNode = $articleElement->addChild("article");
    $articleNode->addAttribute("id", $cApiArticleLanguage->get('idart'));

    // add seo infos to xml
    $titleNode = $articleNode->addChild("title");
    $titleNode->addCData($cApiArticleLanguage->get('title'));

    $summaryNode = $articleNode->addChild("shortdesc");
    $summaryNode->addCData($cApiArticleLanguage->get('summary'));

    $pageTitleNode = $articleNode->addChild("seo_title");
    $pageTitleNode->addCData($cApiArticleLanguage->get('pagetitle'));

    $seoDescrNode = $articleNode->addChild("seo_description");
    $seoDescrNode->addCData(conGetMetaValue($cApiArticleLanguage->get('idartlang'), 3));

    $keywordsNode = $articleNode->addChild("seo_keywords");
    $keywordsNode->addCData(conGetMetaValue($cApiArticleLanguage->get('idartlang'), 5));

    $copyrightNode = $articleNode->addChild("seo_copyright");
    $copyrightNode->addCData(conGetMetaValue($cApiArticleLanguage->get('idartlang'), 8));

    $seoauthorNode = $articleNode->addChild("seo_author");
    $seoauthorNode->addCData(conGetMetaValue($cApiArticleLanguage->get('idartlang'), 1));

    // load content id's for article
    if ($_POST['versionnumber'] == 'current' || $_POST['versionnumber'] == 'undefined') {
        $conColl = new cApiContentCollection();
        $contentIds = $conColl->getIdsByWhereClause('idartlang = "' . $cApiArticleLanguage->get("idartlang") . '"');
    } else {
        $artLangVersion = new cApiArticleLanguageVersion((int) $_POST['versionnumber']);
        $conVersionColl = new cApiContentVersionCollection();
        $where = "(idcontent, version) IN (
                SELECT idcontent, max(version)
                FROM " . $cfg['tab']['content_version'] 
                . " WHERE idartlang = " . $cApiArticleLanguage->get("idartlang") 
                . " AND version <= " . $artLangVersion->get('version') 
                . " GROUP BY idtype, typeid)";
        $contentIds = $conVersionColl->getIdsByWhereClause($where);
    }
    
    // iterate through content and add get data	
    foreach ($contentIds as $contentId) {
        // load content object
        if ($_POST['versionnumber'] == 'current' || $_POST['versionnumber'] == 'undefined') {
            $content = new cApiContent($contentId);
        } else {
            $content = new cApiContentVersion($contentId);
        }
        // if loaded get data and add to xml
        if ($content->isLoaded()) {
            $type = new cApiType($content->get("idtype"));

            if ($type->isLoaded() && in_array($type->get("type"), $allowedContentTypes)) {
                foreach ($_POST as $key => $contentType) {
                    $key = str_replace($contentType, '', $key);
                    if ($key == $type->get("type") && $contentType == $content->get("typeid")) {
                        // create content element
                        $contentNode = $articleNode->addChild("content");
                        $contentNode->addCData($content->get("value"));
                        $contentNode->addAttribute("type", $type->get("type"));
                        $contentNode->addAttribute("id", $content->get("typeid"));
                    }
                }
            }
        }
    }
    
    // output data as xml
    header('Content-Type: application/xml;');
    header('Content-Disposition: attachment; filename=' . $cApiArticleLanguage->get('title') . ';');
    ob_clean();
    echo $articleElement->asXML();
    exit;
} else if ($action == "importrawcontent") {
    // import raw data into article
    // init vars
    $error = false;

    //get file from request
    $rawDataFile = $_FILES['rawfile']['tmp_name'];

    // check file exist
    if (strlen($rawDataFile) > 0 && isset($_FILES['rawfile'])) {

        // read file from tmp upload folder
        $rawData = file_get_contents($rawDataFile);

        // try init xml and import data
        try {
            $xmlDocument = new SimpleXMLElement($rawData);

            foreach ($xmlDocument->children() as $articleNode) {
                $articleId = $articleNode->attributes()->id;

                // check article id exists in xml
                if ($articleId > 0) {

                    // load article by artice id and language
                    $articleLanguage = new cApiArticleLanguage();
                    $articleLanguage->loadByMany(array("idart" => $articleId, "idlang" => cRegistry::getLanguageId()));

                    $versioning = new cContentVersioning();  
                    $version = NULL;
                    if ($versioning->getState() != 'disabled') {
                        // create article version
                        $artLangVersion = $versioning->createArticleLanguageVersion($articleLanguage->toArray());
                        $artLangVersion->markAsCurrentVersion(1);
                        $version = $artLangVersion->get('version');
                    }
                    
                    // check is article loaded
                    if ($articleLanguage->isLoaded()) {

                        // read xml childrens
                        foreach ($articleNode->children() as $key => $child) {
                            // switch xml tag and exec business logic
                            switch ($key) {
                                case 'title':
                                    $articleLanguage->set("title", $child);
                                    $articleLanguage->store();

                                    break;
                                case 'shortdesc':
                                    $articleLanguage->set("summary", $child);
                                    $articleLanguage->store();

                                    break;
                                case 'seo_title':
                                    $articleLanguage->set("pagetitle", $child);
                                    $articleLanguage->store();

                                    break;
                                case 'seo_description':
                                    conSetMetaValue($articleLanguage->get('idartlang'), 3, $child, $version);

                                    break;
                                case 'seo_keywords':
                                    conSetMetaValue($articleLanguage->get('idartlang'), 5, $child, $child, $version);

                                    break;
                                case 'seo_copyright':
                                    conSetMetaValue($articleLanguage->get('idartlang'), 8, $child, $child, $version);

                                    break;
                                case 'seo_author':
                                    conSetMetaValue($articleLanguage->get('idartlang'), 1, $child, $child, $version);

                                    break;
                                case 'content':
                                    $type = $child->attributes()->type;
                                    $typeid = $child->attributes()->id;

                                    $typeEntry = new cApiType();
                                    $typeEntry->loadBy("type", $type);

                                    if (strlen($type) > 0 && $typeid > 0 && in_array($typeEntry->get("type"), $allowedContentTypes)) {
                                        if (isset($_POST['overwritecontent']) && $_POST['overwritecontent'] == 1) {
                                            conSaveContentEntry($articleLanguage->get('idartlang'), $type, $typeid, $child);
                                        } else {
                                            if ($versioningState == 'simple' || $versioningState == 'disabled') {
                                                $contentEntry = new cApiContent();
                                                $contentEntry->loadByMany(array("idtype" => $typeEntry->get("idtype"), "typeid" => $typeid, "idartlang" => $articleLanguage->get('idartlang')));
                                            } else if ($versioningState == 'advanced') {
                                                $contentEntryVersionCollection = new cApiContentVersionCollection();
                                                $where = 'idtype = ' . $typeEntry->get("idtype") . ' AND typeid = ' . $typeid . ' AND idartlang = ' . $articleLanguage->get('idartlang');
                                                $ids = $contentEntryVersionCollection->getIdsByWhereClause($where);
                                                $contentEntry = new cApiContentVersion(max($ids));
                                                if ($contentEntry->isLoaded()) {
                                                    if ($contentEntry->get('deleted')) {
                                                        $contentEntry = new cApiContent();
                                                    }
                                                }
                                            }
                                            if (!$contentEntry->isLoaded()) {
                                                conSaveContentEntry($articleLanguage->get('idartlang'), $type, $typeid, $child);
                                            }
                                        }
                                    } else {
                                        
                                    }

                                    break;
                                case 'default':
                                    break;
                            }
                        }
                    } else {
                        $page->displayError(i18n("Can not load article"));
                        $error = true;
                    }
                } else {
                    $page->displayError(i18n("Can not find article"));
                    $error = true;
                }
            }
            if ($error === false) {
                $page->displayInfo(i18n("Raw data was imported successfully"));
            }
        } catch (Exception $e) {
            $page->displayError(i18n("Error: The XML file is not valid"));
        }
    } else {
        $page->displayWarning(i18n("Please choose a file"));
    }
}

$selectedArticle = NULL;
$editableArticleId = NULL;
$result = array();
$list = array();
$articleType = $versioning->getArticleType($_REQUEST['idArtLangVersion'], (int) $_REQUEST['idartlang'], $action);

switch ($versioningState) {
    case 'simple':

        // get selected article
        $selectedArticle = $versioning->getSelectedArticle($_REQUEST['idArtLangVersion'], (int) $_REQUEST['idartlang'], $articleType);

        // Set as current/editable
        if ($action == 'copyto') {
            if (is_numeric($_REQUEST['idArtLangVersion']) && $articleType == 'editable') {
                $artLangVersion = new cApiArticleLanguageVersion((int) $_REQUEST['idArtLangVersion']);
                $artLangVersion->markAsCurrent();
            }
        }

        // Get Content or Content Version
        if ($articleType == 'current' || $articleType == 'editable') {
            $selectedArticle->loadArticleContent();
        } else if ($articleType == 'version') {
            $selectedArticle->loadArticleVersionContent();
        }
        $result = array_change_key_case($selectedArticle->content, CASE_UPPER);
        $result = $versioning->sortResults($result);
        
        // Set $list
        $list = $versioning->getList((int) $_REQUEST['idartlang'], $articleType);

        // Get version numbers for Select Element
        $optionElementParameters = $versioning->getAllVersionIdArtLangVersionAndLastModified((int) $_REQUEST['idartlang']);
                
        // Create Current and Editable Content Option Element
        $selectElement = new cHTMLSelectElement('articleVersionSelect', '', 'selectVersionElement');
        $optionElement = new cHTMLOptionElement(i18n('Current Version'), 'current');
        if ($articleType == 'current') {
            $optionElement->setSelected(true);
        }
        $selectElement->appendOptionElement($optionElement);

        // Create Content Version Option Elements
        foreach ($optionElementParameters AS $key => $value) {
            $lastModified = $versioning->getTimeDiff($value[key($value)]);
            $optionElement = new cHTMLOptionElement('Version ' . $key . ': ' . $lastModified, key($value));
            if ($articleType == 'version') {
                if ($selectedArticle->get('version') == $key) {
                    $optionElement->setSelected(true);
                }
            }
            $selectElement->appendOptionElement($optionElement);            
        }
        $selectElement->setEvent("onchange", "versionselected.idArtLangVersion.value=$('#selectVersionElement option:selected').val();versionselected.submit()");

        // Create code/output
        $page->set('s', 'ARTICLE_VERSION_SELECTION', $selectElement->toHtml());
        // Set import labels
        if ($articleType != 'version') {
            $page->set('s', 'DISABLED', '');
        } else {
            $page->set('s', 'DISABLED', 'DISABLED');
        }
        // Create markAsCurrent Button/Label
        $page->set('s', 'COPY_LABEL', i18n('Copy Version'));
        $markAsCurrentButton = new cHTMLButton('markAsCurrentButton', i18n('Copy to Current Version'));
        $markAsCurrentButton->setEvent('onclick', "copyto.idArtLangVersion.value=$('#selectVersionElement option:selected').val();copyto.submit()");
        if ($articleType == 'current' || $articleType == 'editable' && $versioningState == 'simple') {
            $markAsCurrentButton->setAttribute('DISABLED');
        }
        $page->set('s', 'SET_AS_CURRENT_VERSION', $markAsCurrentButton->toHtml());

        $versioning_info_text = i18n('<strong>Konfigurationsstufe \'simple\':</strong> Ältere Artikelversionen lassen sich wiederherstellen (Einstellungen sind in Administration/System/System-Konfiguration möglich).');
        $page->set('s', 'VERSIONING_INFO_TEXT', $versioning_info_text);

        break;
    case 'advanced':

        // Set as current/editable
        if ($action == 'copyto') {
            if (is_numeric($_REQUEST['idArtLangVersion']) && $articleType == 'current') {
                $artLangVersion = NULL;                
                $artLangVersion = new cApiArticleLanguageVersion((int) $_REQUEST['idArtLangVersion']);
                if (isset($artLangVersion)) {
                    $artLangVersion->markAsCurrent();
                }
            } else if (is_numeric($_REQUEST['idArtLangVersion']) && $articleType == 'editable') {
                $artLangVersion = new cApiArticleLanguageVersion((int) $_REQUEST['idArtLangVersion']);
                $artLangVersion->markAsEditable();
            } else if ($_REQUEST['idArtLangVersion'] == 'current') {
                $artLang = new cApiArticleLanguage((int) $_REQUEST['idartlang']);
                $artLang->markAsEditable();
            }
        }

        // get selected article
        $selectedArticle = $versioning->getSelectedArticle($_REQUEST['idArtLangVersion'], (int) $_REQUEST['idartlang'], $articleType);

        // Get Content or Content Version and make sort
        if ($articleType == 'current') {
            $selectedArticle->loadArticleContent();
        } else if ($articleType == 'editable' || $articleType == 'version') {
            $selectedArticle->loadArticleVersionContent();
        }
        $result = array_change_key_case($selectedArticle->content, CASE_UPPER);
        $result = $versioning->sortResults($result);

        // Set $list
        $list = $versioning->getList((int) $_REQUEST['idartlang'], $articleType);
        
        // Get version numbers for Select Element
        $optionElementParameters = $versioning->getAllVersionIdArtLangVersionAndLastModified((int) $_REQUEST['idartlang']);

        // Create Current and Editable Content Option Element and Select Element
        $selectElement = new cHTMLSelectElement('articleVersionSelect', '', 'selectVersionElement');

        if (isset($versioning->editableArticleId)) {
            $optionElement = new cHTMLOptionElement(i18n('Editable Version'), $versioning->getEditableArticleId((int) $_REQUEST['idartlang']));
            if ($articleType == 'editable') {
                $optionElement->setSelected(true);
            }
            $selectElement->appendOptionElement($optionElement);
            unset($optionElementParameters[max(array_keys($optionElementParameters))]);
        }


        $optionElement = new cHTMLOptionElement(i18n('Current Version'), 'current');
        if ($articleType == 'current') {
            $optionElement->setSelected(true);
        }
        $selectElement->appendOptionElement($optionElement);

        // Create Content Version Option Elements
        foreach ($optionElementParameters AS $key => $value) {
            $lastModified = $versioning->getTimeDiff($value[key($value)]);
            $optionElement = new cHTMLOptionElement('Revision ' . $key . ': ' . $lastModified, key($value));
            if ($articleType == 'version') {
                if ($selectedArticle->get('version') == $key) {
                    $optionElement->setSelected(true);
                }
            }
            $selectElement->appendOptionElement($optionElement);
        }
        $selectElement->setEvent("onchange", "versionselected.idArtLangVersion.value=$('#selectVersionElement option:selected').val();versionselected.submit()");


        // Create code/output
        $page->set('s', 'ARTICLE_VERSION_SELECTION', $selectElement->toHtml());

        $page->set('s', 'COPY_LABEL', i18n('Copy Version'));
        // Set import labels
        if ($articleType == 'editable') {
            $page->set('s', 'DISABLED', '');
        } else {
            $page->set('s', 'DISABLED', 'DISABLED');
        }

        // Create markAsCurrent Button
        if ($articleType == 'current' || $articleType == 'version') {
            $buttonTitle = i18n('Copy to Editable Version');
        } else if ($articleType == 'editable') {
            $buttonTitle = i18n('Publish Draft');
        }
        $markAsCurrentButton = new cHTMLButton('markAsCurrentButton', $buttonTitle);
        $markAsCurrentButton->setEvent('onclick', "copyto.idArtLangVersion.value=$('#selectVersionElement option:selected').val();copyto.submit()");
        $page->set('s', 'SET_AS_CURRENT_VERSION', $markAsCurrentButton->toHtml());

        $versioning_info_text = i18n(
                '<strong>Konfigurationsstufe \'advanced\':</strong>  '
                . 'Es kann auf frühere Artikelversionen zurückgegriffen werden. '
                . 'Es können äußerdem Entwürfe erstellt und zeitunabhängig veröffentlicht werden (Einstellungen sind in Administration/System/System-Konfiguration möglich).');
        $page->set('s', 'VERSIONING_INFO_TEXT', $versioning_info_text);

        break;
    case 'disabled':
        
        $selectElement = new cHTMLSelectElement('articleVersionSelect', '', 'selectVersionElement');
        $optionElement = new cHTMLOptionElement('Version 10: 11.12.13 14:15:16');
        $selectElement->appendOptionElement($optionElement);
        $selectElement->setAttribute('disabled', 'disabled');
        $page->set('s', 'ARTICLE_VERSION_SELECTION', $selectElement->toHtml());
        
        $buttonTitle = i18n('Copy to Current Version');
        $markAsCurrentButton = new cHTMLButton('markAsCurrentButton', $buttonTitle);
        $markAsCurrentButton->setAttribute('disabled', 'disabled');
        $page->set('s', 'SET_AS_CURRENT_VERSION', $markAsCurrentButton->toHtml());
        
        $versioning_info_text = i18n('Aktiviere die Artikel-Versionierung in den Administration/System/System-Konfiguration. Artikel-Versionierung bedeutet, dass auf frühere Versionen eines Artikels zurückgegriffen werden kann.');
        $page->set('s', 'VERSIONING_INFO_TEXT', $versioning_info_text);  

        // get selected article
        $selectedArticle = $versioning->getSelectedArticle($_REQUEST['idArtLangVersion'], (int) $_REQUEST['idartlang'], $articleType);

        // Get Content/set $result
        $selectedArticle->loadArticleContent();
        $result = array_change_key_case($selectedArticle->content, CASE_UPPER);
        $result = $versioning->sortResults($result);

        // Set $list
        $list = $versioning->getList((int) $_REQUEST['idartlang'], $articleType);

        // Set import labels
        $page->set('s', 'DISABLED', '');
    default:
        break;
}

//$currentTypes = _getCurrentTypes($currentTypes, $aList);
// print_r($currentTypes);
// create Layoutcode
// if ($action == 'con_content') {
// @fulai.zhang: Mark submenuitem 'Editor' in the CONTENIDO Backend (Area:
// Contenido --> Articles --> Editor)
$markSubItem = markSubMenuItem(4, true);

// Include tiny class
include($backendPath . 'external/wysiwyg/tinymce3/editorclass.php');
$oEditor = new cTinyMCEEditor('', '');
$oEditor->setToolbar('inline_edit');

// Get configuration for popup und inline tiny
$sConfigInlineEdit = $oEditor->getConfigInlineEdit();
$sConfigFullscreen = $oEditor->getConfigFullscreen();

// Replace vars in Script
// Set urls to file browsers
$page->set('s', 'IMAGE', $backendUrl . 'frameset.php?area=upl&contenido=' . $sess->id . '&appendparameters=imagebrowser');
$page->set('s', 'FILE', $backendUrl . 'frameset.php?area=upl&contenido=' . $sess->id . '&appendparameters=filebrowser');
$page->set('s', 'FLASH', $backendUrl . 'frameset.php?area=upl&contenido=' . $sess->id . '&appendparameters=imagebrowser');
$page->set('s', 'MEDIA', $backendUrl . 'frameset.php?area=upl&contenido=' . $sess->id . '&appendparameters=imagebrowser');
$page->set('s', 'FRONTEND', cRegistry::getFrontendUrl());

// Add tiny options
$page->set('s', 'TINY_OPTIONS', $sConfigInlineEdit);
$page->set('s', 'TINY_FULLSCREEN', $sConfigFullscreen);
$page->set('s', 'IDARTLANG', $idartlang);
$page->set('s', 'CLOSE', i18n('Close editor'));
$page->set('s', 'SAVE', i18n('Close editor and save changes'));
$page->set('s', 'QUESTION', i18n('Do you want to save changes?'));

// Add export and import translations
$page->set('s', 'EXPORT_RAWDATA', i18n('Export raw data'));
$page->set('s', 'IMPORT_RAWDATA', i18n('Import raw data'));
$page->set('s', 'EXPORT_LABEL', i18n('Raw data export'));
$page->set('s', 'IMPORT_LABEL', i18n('Raw data import'));
$page->set('s', 'OVERWRITE_DATA_LABEL', i18n('Overwrite data'));

if (getEffectiveSetting('system', 'insite_editing_activated', 'true') == 'false') {
    $page->set('s', 'USE_TINY', '');
} else {
    $page->set('s', 'USE_TINY', '1');
}

// Show path of selected category to user
$breadcrumb = renderBackendBreadcrumb($syncoptions, true, true);
$page->set('s', 'CATEGORY', $breadcrumb);
if (count($result) <= 0) {
    $page->displayInfo(i18n('Article has no raw data'));
} else {
    foreach ($result AS $type => $typeIdValue) {
        foreach ($typeIdValue AS $typeId => $value) {
            if (($articleType == 'editable' || $articleType == 'current' && ($versioningState == 'disabled' || $versioningState == 'simple'))) {
                $class = '';
                $formattedVersion = '';
            } else if ($articleType == 'current' || $articleType == 'version') {
                $class = ' noactive';
                $formattedVersion = '';
            }
            $page->set('d', 'EXTRA_CLASS', $class);
            $page->set('d', 'NAME', $type);
            $page->set('d', 'ID_TYPE', $typeId);
            $page->set('d', 'FORMATTED_VERSION', $formattedVersion);
            if (in_array($type, $allowedContentTypes)) {
                $page->set('d', 'EXPORT_CONTENT', '<input type="checkbox" class="rawtypes" name="' . $type . $typeId . '" value="' . $typeId . '" checked="checked">');
                $page->set('d', 'EXPORT_CONTENT_LABEL', i18n("Export"));
            } else {
                $page->set('d', 'EXPORT_CONTENT', '');
                $page->set('d', 'EXPORT_CONTENT_LABEL', '');
            }
            $page->next();
        }
    }
}

// breadcrumb onclick
if (!isset($syncfrom)) {
    $syncfrom = -1;
}
$syncoptions = $syncfrom;
$page->set('s', 'SYNCHOPTIONS', $syncoptions);

$page->set('s', 'IDART', $idart);
$page->set('s', 'IDCAT', $idcat);
$page->set('s', 'IDLANG', $lang);
$page->set('s', 'IDARTLANG', $idartlang);
$page->set('s', 'IDCLIENT', $client);

$code = _processCmsTags($list, $result, true, $page->render(NULL, true), $articleType, $versioningState, $selectedArticle->get('version'));

if ($code == '0601') {
    markSubMenuItem('1');
    $code = "<script type='text/javascript'>location.href = '" . $backendUrl . "main.php?frame=4&area=con_content_list&action=con_content&idart=" . $idart . "&idcat=" . $idcat . "&contenido=" . $contenido . "'; /*console.log(location.href);*/</script>";
} else {
    // inject some additional markup
    $code = cString::iReplaceOnce("</head>", "$markSubItem $scripts\n<meta http-equiv=\"Content-Type\" content=\"text/html; charset=$encoding[$lang]\"></head>", $code);
}

if ($cfg['debug']['codeoutput']) {
    cDebug::out(conHtmlSpecialChars($code));
}

// show ContentTypeList
chdir(cRegistry::getFrontendPath());
eval("?>\n" . $code . "\n<?php\n");
// }
cRegistry::shutdown();

/**
 * Processes replacements of all existing CMS_...
 * tags within passed code
 *
 * @param array $list CMS_...tags list
 * @param array $contentList all CMS variables
 * @param bool $saveKeywords Flag to save collected keywords during replacement
 *        process.
 * @param array $contentList Assoziative list of CMS variables
 */
function _processCmsTags($list, $contentList, $saveKeywords = true, $layoutCode, $articleType, $versioningState, $version) {
    // #####################################################################
    // NOTE: Variables below are required in included/evaluated content type
    // codes!
    global $db, $db2, $sess, $cfg, $code, $cfgClient, $encoding, $notification;

    // NOTE: Variables below are additionally required in included/evaluated
    // content type codes within backend edit mode!
    global $edit, $editLink, $belang;

    $idcat = $_REQUEST['idcat'];
    $idart = $_REQUEST['idart'];
    $lang = $_REQUEST['lang'];
    $client = $_REQUEST['client'];
    $idartlang = $_REQUEST['idartlang'];
    $contenido = $_REQUEST['contenido'];

    // Get locked status (article freeze)
    $cApiArticleLanguage = new cApiArticleLanguage(cSecurity::toInteger($idartlang));
    $locked = $cApiArticleLanguage->getField('locked');

    // If article is locked show notification
    if ($locked == 1) {
        $notification->displayNotification('warning', i18n('This article is currently frozen and can not be edited!'));
    }

    if (!is_object($db2)) {
        $db2 = cRegistry::getDb();
    }
    // End: Variables required in content type codes
    // #####################################################################

    $match = array();
    $keycode = array();

    // $a_content is used by included/evaluated content type codes below
    $a_content = $contentList;

    // Select  cms_type entries existing in selected article
    if (empty($list)) {
        $list[0] = 0;       
    }
    
    $_typeList = array();
    $oTypeColl = new cApiTypeCollection();
    $oTypeColl->select('idtype IN (' . implode(',', array_map(function($i) {
                            return (int) $i;
                        }, array_keys($list))) . ')');
    if (0 < $oTypeColl->count()) {
        while ($oType = $oTypeColl->next()) {
            $_typeList[] = $oType->toObject();
        }       
    }


    // Replace all CMS_TAGS[]
    foreach ($_typeList as $_typeItem) {

        $key = strtolower($_typeItem->type);
        $type = $_typeItem->type;

        // Try to find all CMS_{type}[{number}] values, e. g. CMS_HTML[1]
        // $tmp = preg_match_all('/(' . $type . ')\[+([a-z0-9_]+)+\]/i',
        // $this->_layoutCode, $match);
        $tmp = preg_match_all('/(' . $type . '\[+(\d)+\])/i', $layoutCode, $match);

        $a_[$key] = $match[2]; //all typeids
        //$tmp = preg_match_all('/(' . $type . '\[+(\d)+\]' . '\[+(\d)+\])/i', $layoutCode, $match);
        //$b_[$key] = $match[3]; //version numbers
        //$c = array_combine($a_[$key],$b_[$key]); //key=unique=nur neueste version
        //$a_[$key] = $match[0];	
        //$success = array_walk($a_[$key], 'extractNumber');

        $search = array();
        $replacements = array();

        $backendPath = cRegistry::getBackendPath();

        $typeCodeFile = $backendPath . 'includes/type/code/include.' . $type . '.code.php';
        $cTypeClassFile = $backendPath . 'classes/content_types/class.content.type.' . strtolower(str_replace('CMS_', '', $type)) . '.php';
        // classname format: CMS_HTMLHEAD -> cContentTypeHtmlhead
        $className = 'cContentType' . ucfirst(strtolower(str_replace('CMS_', '', $type)));

        foreach ($a_[$key] as $val) {
            if (cFileHandler::exists($cTypeClassFile)) {
                $tmp = $a_content[$_typeItem->type][$val];
                $cTypeObject = new $className($tmp, $val, $a_content);
                if (cRegistry::isBackendEditMode() && $locked == 0 && $articleType == 'editable' || ($articleType == 'current' && ($versioningState == 'disabled' || $versioningState == 'simple'))) {
                    $tmp = $cTypeObject->generateEditCode();
                } else if ($articleType == 'current' || $articleType == 'version') {
                    $tmp = $cTypeObject->generateViewCode();
                }
            } else if (cFileHandler::exists($typeCodeFile)) {
                // include CMS type code
                include($typeCodeFile);
            } elseif (!empty($_typeItem->code)) {
                // old version, evaluate CMS type code
                cDeprecated("Move code for $type from table into file system (contenido/includes/type/code/)");
                eval($_typeItem->code);
            }

            $versioning = new cContentVersioning(); //als Parameter �bergeben oder einzelne Strings/Ints �bergeben?
            $idcontent = $versioning->getContentId(cSecurity::toInteger($_REQUEST["idartlang"]), cSecurity::toInteger($val), cSecurity::toString($type), $versioningState, $articleType, $version);

            $backendUrl = cRegistry::getBackendUrl();
            $num = $val;
            $search[$num] = sprintf('%s[%s]', $type, $val);

            $path = $backendUrl . 'main.php?area=con_content_list&action=deletecontype&changeview=edit&idart=' . $idart . '&idartlang=' . $idartlang . '&idcat=' . $idcat . '&client=' . $client . '&lang=' . $lang . '&frame=4&contenido=' . $contenido . '&idcontent=' . $idcontent;
            if ($_typeItem->idtype == 20 || $_typeItem->idtype == 21) {
                $tmp = str_replace('";?>', '', $tmp);
                $tmp = str_replace('<?php echo "', '', $tmp);
                // echo
                // "<textarea>"."?".">\n".stripslashes($tmp)."\n\";?"."><"."?php\n"."</textarea>";
            }

            if ($locked == 0 && $articleType == 'editable' || $articleType == 'current' && ($versioningState == 'disabled' || $versioningState == 'simple')) { // No freeze				
                $replacements[$num] = $tmp . '<a href="#" onclick="Con.showConfirmation(\'' . i18n("Are you sure you want to delete this content type from this article?") . '\', function() { Con.Tiny.setContent(\'1\',\'' . $path . '\'); });">
			<img border="0" src="' . $backendUrl . 'images/delete.gif">
			</a>';
                $keycode[$type][$num] = $tmp . '<a href="#" onclick="Con.showConfirmation(\'' . i18n("Are you sure you want to delete this content type from this article?") . '\', function() { Con.Tiny.setContent(\'1\',\'' . $path . '\'); });">
			<img border="0" src="' . $backendUrl . 'images/delete.gif">
			</a>';
            } else { // Freeze status
                $replacements[$num] = $tmp;
                $keycode[$type][$num] = $tmp;
            }
        }
        $code = str_ireplace($search, $replacements, $layoutCode);
        // execute CEC hook
        $code = cApiCecHook::executeAndReturn('Contenido.Content.conGenerateCode', $code);
        $layoutCode = stripslashes($code);
    }
    $layoutCode = str_ireplace("<<", "[", $layoutCode);
    $layoutCode = str_ireplace(">>", "]", $layoutCode);
    return $layoutCode;
}
