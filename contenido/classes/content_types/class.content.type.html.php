<?php

/**
 * This file contains the cContentTypeHtml class.
 *
 * @package    Core
 * @subpackage ContentType
 * @author     Simon Sprankel
 * @copyright  four for business AG <www.4fb.de>
 * @license    https://www.contenido.org/license/LIZENZ.txt
 * @link       https://www.4fb.de
 * @link       https://www.contenido.org
 */

defined('CON_FRAMEWORK') || die('Illegal call: Missing framework initialization - request aborted.');

/**
 * Content type CMS_HTML which lets the editor enter HTML with the help of a
 * WYSIWYG editor.
 *
 * @package    Core
 * @subpackage ContentType
 */
class cContentTypeHtml extends cContentTypeAbstract
{

    /**
     * Constructor to create an instance of this class.
     *
     * Initialises class attributes and handles store events.
     *
     * @param string $rawSettings
     *         the raw settings in an XML structure or as plaintext
     * @param int $id
     *         ID of the content type, e.g. 3 if CMS_DATE[3] is used
     * @param array $contentTypes
     *         array containing the values of all content types
     */
    public function __construct($rawSettings, $id, array $contentTypes)
    {
        // call parent constructor
        parent::__construct($rawSettings, $id, $contentTypes);

        // set props
        $this->_type = 'CMS_HTML';
        $this->_prefix = 'html';
    }

    /**
     * Generates the code which should be shown if this content type is shown in
     * the frontend.
     *
     * @return string
     *         escaped HTML code which should be shown if content type is shown in frontend
     */
    public function generateViewCode()
    {
        return $this->_encodeForOutput($this->_rawSettings);
    }

    /**
     * Generates the code which should be shown if this content type is edited.
     *
     * @return string
     *         escaped HTML code which should be shown if content type is edited
     * @throws cDbException
     * @throws cException
     * @throws cInvalidArgumentException
     */
    public function generateEditCode()
    {
        $wysiwygDiv = new cHTMLDiv();

        // generate the div ID - format: TYPEWITHOUTCMS_TYPEID_ID
        // important because it is used to save the content accordingly
        $id = str_replace('CMS_', '', $this->_type) . '_';
        $db = cRegistry::getDb();
        $sql = "SELECT `idtype` FROM `%s` WHERE `type` = '%s'";
        $db->query($sql, $this->_cfg['tab']['type'], $this->_type);
        $db->nextRecord();
        $id .= $db->f('idtype') . '_' . $this->_id;
        $wysiwygDiv->setID($id);
        $wysiwygDiv->setClass(htmlentities($this->_type));

        $wysiwygDiv->setEvent('Focus', "this.style.border='1px solid #bb5577';");
        $wysiwygDiv->setEvent('Blur', "this.style.border='1px dashed #bfbfbf';");
        $wysiwygDiv->appendStyleDefinitions([
            'border' => '1px dashed #bfbfbf',
            'direction' => langGetTextDirection($this->_lang),
            'min-height' => '20px'
        ]);
        $wysiwygDiv->updateAttribute('contentEditable', 'true');
        if (cString::getStringLength($this->_rawSettings) == 0) {
            $wysiwygDiv->setContent('&nbsp;');
        } else {
            $wysiwygDiv->setContent($this->_rawSettings);
        }

        // construct edit button
        $editLink = $this->_session->url(
            $this->_cfg['path']['contenido_fullhtml'] . 'external/backendedit/'
            . 'front_content.php?action=10&idcat=' . $this->_idCat
            . '&idart=' . $this->_idArt . '&idartlang=' . $this->_idArtLang
            . '&type=' . $this->_type . '&typenr=' . $this->_id .
            '&client=' . $this->_client
        );
        $editAnchor = new cHTMLLink('#');
        $editAnchor->setAttribute('onclick', "javascript:Con.Tiny.setContent('" . $this->_idArtLang . "','" . $editLink . "'); return false;");
        $editAnchor->setClass('con_img_button con_img_button_content_type');
        $editButton = new cHTMLImage($this->_cfg['path']['contenido_fullhtml'] . $this->_cfg['path']['images'] . 'but_edithtml.gif');
        $editButton->appendStyleDefinition('margin-right', '2px');
        $editButton->setClass('con_img');
        $editAnchor->setContent($editButton);

        // construct save button
        $saveAnchor = new cHTMLLink('#');
        $saveAnchor->setAttribute('onclick', "javascript:Con.Tiny.setContent('" . $this->_idArtLang . "', '0'); return false;");
        $saveAnchor->setClass('con_img_button con_img_button_content_type');
        $saveButton = new cHTMLImage($this->_cfg['path']['contenido_fullhtml'] . $this->_cfg['path']['images'] . 'but_ok.gif');
        $saveButton->setClass('con_img');
        $saveAnchor->setContent($saveButton);

        $editAnchorWrap = new cHTMLSpan();
        $editAnchorWrap->setClass('con_content_type_controls');
        $editAnchorWrap->appendContent($editAnchor);

        $saveAnchorWrap = new cHTMLSpan();
        $saveAnchorWrap->setClass('con_content_type_controls');
        $saveAnchorWrap->appendContent($saveAnchor);

        return $this->_encodeForOutput($wysiwygDiv->render() . $editAnchorWrap->render() . $saveAnchorWrap->render());
    }

    /**
     * This content type and its derived types can be edited by a WYSIWYG editor
     *
     * @return bool
     */
    public function isWysiwygCompatible()
    {
        return true;
    }

}
