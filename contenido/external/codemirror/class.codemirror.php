<?php

/**
 * This file defines the CodeMirror editor integration class.
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

/**
 * Class for handling and displaying CodeMirror
 *
 * @package    Core
 * @subpackage Backend
 */
class CodeMirror {

    /**
     * Properties which were used to init CodeMirror
     *
     * @var array
     */
    private $_properties = [];

    /**
     * HTML-ID of textarea which is replaced by CodeMirror
     *
     * @var string
     */
    private $_textareaId;

    /**
     * defines if textarea is used or not (by system/client/user property)
     *
     * @var boolean
     */
    private $_activated = true;

    /**
     * defines if js-script for CodeMirror is included on rendering process
     *
     * @var boolean
     */
    private $_addScript;

    /**
     * The CONTENIDO configuration array
     *
     * @var array
     */
    private $_cfg;

    /**
     * Language of CodeMirror
     *
     * @var string
     */
    private $_language;

    /**
     * Syntax of CodeMirror
     *
     * @var string
     */
    private $_syntax;

    /**
     * Constructor of CodeMirror initializes class variables
     *
     * @param string $id - The id of textarea which is replaced by editor
     * @param string $syntax - Name of syntax highlighting which is used (html,
     *        css, js, php, ...)
     * @param string $lang - lang which is used into editor. Notice NOT
     *        CONTENIDO language id
     *        ex: de, en ... To get it from CONTENIDO language use:
     *        substr(strtolower($belang), 0, 2) in backend
     * @param bool $addScript - defines if CodeMirror script is included or
     *        not
     *        interesting when there is more than only one editor on page
     * @param array $cfg - The CONTENIDO configuration array
     * @param bool $editable - Optional defines if content is editable or not
     */
    public function __construct($id, $syntax, $lang, $addScript, $cfg, $editable = true) {
        // init class variables
        $this->_cfg        = (array)$cfg;
        $this->_addScript  = (boolean)$addScript;
        $this->_textareaId = (string)$id;
        $this->_language   = (string)$lang;
        $this->_syntax     = (string)$syntax;

        // make content not editable if not allowed
        if (!$editable) {
            $this->setProperty('readOnly', 'true', true);
        }

        $this->setProperty('lineNumbers', 'true', true);
        $this->setProperty('lineWrapping', 'true', true);
        $this->setProperty('matchBrackets', 'true', true);
        $this->setProperty('indentUnit', 4, true);
        $this->setProperty('indentWithTabs', 'true', true);
        $this->setProperty('enterMode', 'keep', false);
        $this->setProperty('tabMode', 'shift', false);

        // internal function which appends more properties to $this->setProperty
        // which where defined by user or sysadmin in system-properties /
        // client settings / user settings ...
        $this->_getSystemProperties();
    }

    /**
     * Function gets properties from CONTENIDO for CodeMirror and stores it into
     * $this->setProperty so user is able to overwrite standard settings or
     * append other settings.
     * Function also checks if CodeMirror is activated or deactivated
     * by user.
     */
    private function _getSystemProperties() {
        // check if editor is disabled or enabled by user/admin
        if (getEffectiveSetting('codemirror', 'activated', 'true') == 'false') {
            $this->_activated = false;
        }

        $userSettings = getEffectiveSettingsByType('codemirror');
        foreach ($userSettings as $key => $value) {
            if ($key != 'activated') {
                if ($value == 'true' || $value == 'false' || is_numeric($value)) {
                    $this->setProperty($key, $value, true);
                } else {
                    $this->setProperty($key, $value, false);
                }
            }
        }
    }

    /**
     * Function for setting a property for CodeMirror to $this->setProperty
     * existing properties were overwritten
     *
     * @param string $name - Name of CodeMirror property
     * @param string $value - Value of CodeMirror property
     * @param bool $isNumeric - Defines if value is numeric or not
     *        in case of a numeric value, there is no need to use
     *        quotes
     */
    public function setProperty($name, $value, $isNumeric = false) {
        // datatype check
        $name = (string) $name;
        $value = (string) $value;
        $isNumeric = (boolean) $isNumeric;

        // generate a new array for new property
        $record = [
            'name'       => $name,
            'value'      => $value,
            'is_numeric' => $isNumeric,
        ];

        // append it to class variable $this->aProperties
        // when key already exists, overwrite it
        $this->_properties[$name] = $record;
    }

    private function _getSyntaxScripts() {
        $modes = [];

        $syntax = $this->_syntax;
        if ($syntax == 'js' || $syntax == 'html' || $syntax == 'php') {
            $modes[] = 'javascript';
        }

        if ($syntax == 'css' || $syntax == 'html' || $syntax == 'php') {
            $modes[] = 'css';
        }

        if ($syntax == 'html' || $syntax == 'php') {
            $modes[] = 'xml';
        }

        if ($syntax == 'php') {
            $modes[] = 'php';
            $modes[] = 'clike';
        }

        if ($syntax == 'html') {
            $modes[] = 'htmlmixed';
        }

        $js = '';
        $conPath = $this->_cfg['path']['contenido_fullhtml'];
        $pathTemplate = 'external/codemirror/mode/%s/%s.js';
        foreach ($modes as $mode) {
            $path = sprintf($pathTemplate, $mode, $mode);
            $js .= cHTMLScript::external($conPath . cAsset::backend($path)) . PHP_EOL;
        }

        return $js;
    }

    private function _getSyntaxName() {
        if ($this->_syntax == 'php') {
            return 'application/x-httpd-php';
        }

        if ($this->_syntax == 'html') {
            return 'text/html';
        }

        if ($this->_syntax == 'css') {
            return 'text/css';
        }

        if ($this->_syntax == 'js') {
            return 'text/javascript';
        }
    }

    /**
     * Function renders js_script for inclusion into a header of a html file
     *
     * @return string - js_script for CodeMirror
     */
    public function renderScript() {
        // if editor is disabled, there is no need to render this script
        if (!$this->_activated) {
            return '';
        }

        // if external js file for editor should be included, do this here
        $js = '';
        if ($this->_addScript) {
            $conPath = $this->_cfg['path']['contenido_fullhtml'];
            $path = $conPath . 'external/codemirror/';

            $language = $this->_language;
            if (!file_exists($this->_cfg['path']['contenido'] . 'external/codemirror/lib/lang/' . $language . '.js')) {
                $language = 'en';
            }

            $js .= cHTMLScript::external($conPath . cAsset::backend('external/codemirror/lib/lang/' . $language . '.js')) . PHP_EOL;
            $js .= cHTMLScript::external($conPath . cAsset::backend('external/codemirror/lib/codemirror.js')) . PHP_EOL;
            $js .= cHTMLScript::external($conPath . cAsset::backend('external/codemirror/lib/util/foldcode.js')) . PHP_EOL;
            $js .= cHTMLScript::external($conPath . cAsset::backend('external/codemirror/lib/util/dialog.js')) . PHP_EOL;
            $js .= cHTMLScript::external($conPath . cAsset::backend('external/codemirror/lib/util/searchcursor.js')) . PHP_EOL;
            $js .= cHTMLScript::external($conPath . cAsset::backend('external/codemirror/lib/util/search.js')) . PHP_EOL;
            $js .= cHTMLScript::external($conPath . cAsset::backend('external/codemirror/lib/contenido_integration.js')) . PHP_EOL;
            $js .= $this->_getSyntaxScripts();
            $js .= cHTMLLinkTag::stylesheet($conPath . cAsset::backend('external/codemirror/lib/codemirror.css')) . PHP_EOL;
            $js .= cHTMLLinkTag::stylesheet($conPath . cAsset::backend('external/codemirror/lib/util/dialog.css')) . PHP_EOL;
            $js .= cHTMLLinkTag::stylesheet($conPath . cAsset::backend('external/codemirror/lib/contenido_integration.css')) . PHP_EOL;
        }

        // define template for CodeMirror script
        $js .= <<<JS
<script type="text/javascript">
(function(Con, $) {
    $(function() {
        if (!$('#{ID}')[0]) {
            // Node is missing, nothing to initialize here...
            return;
        }
        Con.CodeMirrorHelper.init('{ID}', {
            extraKeys: {
                'F11': function() {
                    Con.CodeMirrorHelper.toggleFullscreenEditor('{ID}');
                },
                'Esc': function() {
                    Con.CodeMirrorHelper.toggleFullscreenEditor('{ID}');
                }
            }
            {PROPERTIES}
        });
    });
})(Con, Con.$);
</script>
JS;

        $this->setProperty('mode', $this->_getSyntaxName(), false);
        $this->setProperty('theme', 'default ' . $this->_textareaId, false);

        // get all stored properties and convert it in order to insert it into
        // CodeMirror js template
        $properties = '';
        foreach ($this->_properties as $property) {
            if ($property['is_numeric']) {
                $properties .= ', ' . $property['name'] . ': ' . $property['value'];
            } else {
                $properties .= ', ' . $property['name'] . ': "' . $property['value'] . '"';
            }
        }

        // fill js template
        $textareaId = $this->_textareaId;
        $jsResult = str_replace('{ID}', $textareaId, $js);
        return str_replace('{PROPERTIES}', $properties, $jsResult);
    }

}
