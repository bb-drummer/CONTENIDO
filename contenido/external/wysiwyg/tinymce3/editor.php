<?php

/**
 * Main editor file for CONTENIDO
 *
 * @package    Core
 * @subpackage Backend
 * @author     Martin Horwath <horwath@dayside.net>
 * @copyright  four for business AG <www.4fb.de>
 * @license    https://www.contenido.org/license/LIZENZ.txt
 * @link       https://www.4fb.de
 * @link       https://www.contenido.org
 */

defined('CON_FRAMEWORK') || die('Illegal call: Missing framework initialization - request aborted.');

/**
 * @var cAuth $auth
 * @var array $cfgClient
 * @var array $a_content
 * @var int $client
 * @var string $type
 * @var int $typenr
 */

// include editor config/combat file
include(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'config.php');
cInclude('external', 'wysiwyg/tinymce3/editorclass.php');

// name of textarea element
if (isset($type)) {
    $editor_name = $type;
} else {
    $editor_name = 'content';
}

// we are in backendedit mode at this point, so set some variables to reflect that
$edit = true;
$contenido = 1;

// if editor is called from any include.CMS_*.html file use available content from $a_content
$editorContent = $a_content[$type][$typenr] ?? '';

$editorContent = str_replace('src="upload', 'src="' . $cfgClient[$client]['path']['htmlpath'] . 'upload', $editorContent);

$editorContent = conHtmlSpecialChars($editorContent);

$cTinyMCEEditor = new cTinyMCEEditor($editor_name, $editorContent);

switch ($type) {
    case 'CMS_HTML':
        $editor_height = getEffectiveSetting('wysiwyg', 'tinymce-height-html', false);
        if ($editor_height == false) {
            $editor_height = getEffectiveSetting('tinymce', 'contenido_height_html', false);
        }
        break;
    case 'CMS_HTMLHEAD':
        $editor_height = getEffectiveSetting('wysiwyg', 'tinymce-height-head', false);
        if ($editor_height == false) {
            $editor_height = getEffectiveSetting('tinymce', 'contenido_height_head', false);
        }
        break;
    default:
        $editor_height = false;
}

if ($editor_height !== false) {
    $cTinyMCEEditor->setSetting(null, 'height', $editor_height, true);
}

/*
TODO:

-> see editor_template.js
-> create own theme template engine
-> maybe change the way icons are displayed
*/

$currentuser = new cApiUser($auth->auth['uid']);

if ($currentuser->getField('wysi') == 1) {
    echo $cTinyMCEEditor->getScripts();
    echo $cTinyMCEEditor->getEditor();
} else {
    $oTextarea = new cHTMLTextarea($editor_name, $editorContent);
    $oTextarea->setId($editor_name);

    $editor_width  = getEffectiveSetting('wysiwyg', 'tinymce-width',  '600');
    $editor_height = getEffectiveSetting('wysiwyg', 'tinymce-height', '480');

    $oTextarea->setStyle('width: '.$editor_width.'px; height: '.$editor_height.'px;');

    echo $oTextarea->render();
}
