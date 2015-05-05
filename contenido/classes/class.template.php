<?php
/**
 * This file contains the former template class.
 *
 * @package    Core
 * @subpackage GUI
 * @version    SVN Revision $Rev:$
 *
 * @author     Jan Lengowski, Stefan Jelner
 * @copyright  four for business AG <www.4fb.de>
 * @license    http://www.contenido.org/license/LIZENZ.txt
 * @link       http://www.4fb.de
 * @link       http://www.contenido.org
 */

defined('CON_FRAMEWORK') || die('Illegal call: Missing framework initialization - request aborted.');

/**
 * class Template
 * Light template mechanism
 *
 * @package Core
 * @subpackage GUI
 */
class cTemplate {

    /**
     * Needles (static)
     *
     * @var array
     */
    public $needles = array();

    /**
     * Replacements (static)
     *
     * @var array
     */
    public $replacements = array();

    /**
     * Dyn_Needles (dynamic)
     *
     * @var array
     */
    public $Dyn_needles = array();

    /**
     * Dyn_Replacements (dynamic)
     *
     * @var array
     */
    public $Dyn_replacements = array();


    /**
     * Dynamic counter
     *
     * @var int
     */
    public $dyn_cnt = 0;

    /**
     * Tags array (for dynamic blocks);
     *
     * @var array
     */
    public $tags = array(
        'static' => '{%s}',
        'start' => '<!-- BEGIN:BLOCK -->',
        'end' => '<!-- END:BLOCK -->'
    );

    /**
     * gettext domain (default: contenido)
     *
     * @var string
     */
    protected $_sDomain = 'contenido';

    /**
     * Constructor function
     * @param array|bool $tags
     */
    public function __construct($tags = false) {
        if (is_array($tags)) {
            $this->tags = $tags;
        }

        $this->setEncoding("");
    }

    /**
     * Sets the gettext domain to use for translations in a template
     *
     * @param string $sDomain Sets the domain to use for template translations
     */
    public function setDomain($sDomain) {
        $this->_sDomain = $sDomain;
    }

    /**
     * Set Templates placeholders and values
     *
     * With this method you can replace the placeholders
     * in the static templates with dynamic data.
     *
     * @param string $which 's' for Static or else dynamic
     * @param string $needle Placeholder
     * @param string $replacement Replacement String
     */
    public function set($which, $needle, $replacement) {
        if ($which == 's') {
            // static
            $this->needles[] = sprintf($this->tags['static'], $needle);
            $this->replacements[] = $replacement;
        } else {
            // dynamic
            $this->Dyn_needles[$this->dyn_cnt][] = sprintf($this->tags['static'], $needle);
            $this->Dyn_replacements[$this->dyn_cnt][] = $replacement;
        }
    }

    /**
     * Sets an encoding for the template's head block.
     *
     * @param string $encoding Encoding to set
     */
    public function setEncoding($encoding) {
        $this->_encoding = $encoding;
    }

    /**
     * Iterate internal counter by one
     */
    public function next() {
        $this->dyn_cnt++;
    }

    /**
     * Reset template data
     */
    public function reset() {
        $this->dyn_cnt = 0;
        $this->needles = array();
        $this->replacements = array();
        $this->Dyn_needles = array();
        $this->Dyn_replacements = array();
    }

    /**
     * Generate the template and print/return it.
     * (do translations sequentially to save memory!!!)
     *
     * @param string $template Either template string or template file path
     * @param bool $return Return or print template
     * @param bool $note Echo "Generated by ... " Comment
     * @return string|void
     *         Complete template string or nothing
     */
    public function generate($template, $return = false, $note = false) {
        global $cCurrentModule, $cfg, $frontend_debug;

        $moduleHandler = NULL;
        if (!is_null($cCurrentModule)) {
            $moduleHandler = new cModuleHandler($cCurrentModule);
        }

        // Check if the template is a file or a string
        if (!@is_file($template)) {
            if (is_object($moduleHandler) && is_file($moduleHandler->getTemplatePath($template))) {
                // Module directory has higher priority
                $content = $moduleHandler->getFilesContent('template', '', $template);
                if ($frontend_debug['template_display']) {
                    echo('<!-- CTEMPLATE ' . $template . ' -->');
                }
            } else {
                // Template is a string (it is a reference to save memory!!!)
                $content = &$template;
            }
        } else {
            if (is_object($moduleHandler) && is_file($moduleHandler->getTemplatePath($template))) {
                // Module directory has higher priority
                $content = $moduleHandler->getFilesContent('template', '', $template);
            } else {
                // Template is a file in template directory
                $content = implode('', file($template));
            }
        }

        $content = (($note) ? "<!-- Generated by CONTENIDO " . CON_VERSION . "-->\n" : "") . $content;

        // CEC for template pre processing
        $content = cApiCecHook::executeAndReturn('Contenido.Template.BeforeParse', $content, $this);

        $pieces = array();

        // Replace i18n strings before replacing other placeholders
        $this->replacei18n($content, 'i18n');
        $this->replacei18n($content, 'trans');

        // If content has dynamic blocks
        $startQ = preg_quote($this->tags['start'], '/');
        $endQ = preg_quote($this->tags['end'], '/');
        if (preg_match('/^.*' . $startQ . '.*?' . $endQ . '.*$/s', $content)) {
            // Split everything into an array
            preg_match_all('/^(.*)' . $startQ . '(.*?)' . $endQ . '(.*)$/s', $content, $pieces);
            // Safe memory
            array_shift($pieces);
            $content = '';
            // Now combine pieces together
            // Start block
            $content .= str_replace($this->needles, $this->replacements, $pieces[0][0]);
            unset($pieces[0][0]);

            // Generate dynamic blocks
            for ($a = 0; $a < $this->dyn_cnt; $a++) {
                $content .= str_replace($this->Dyn_needles[$a], $this->Dyn_replacements[$a], $pieces[1][0]);
            }
            unset($pieces[1][0]);

            // End block
            $content .= str_replace($this->needles, $this->replacements, $pieces[2][0]);
            unset($pieces[2][0]);
        } else {
            $content = str_replace($this->needles, $this->replacements, $content);
        }

        if ($this->_encoding != '') {
//            $content = str_replace("</head>", '<meta http-equiv="Content-Type" content="text/html; charset=' . $this->_encoding . '">' . "\n" . '</head>', $content);
        }

        if ($return) {
            return $content;
        } else {
            echo $content;
        }
    }

    /**
     * Replaces a named function with the translated variant
     *
     * @param string $template Contents of the template to translate (it is
     *        reference to save memory!!!)
     * @param string $functionName Name of the translation function (e.g. i18n)
     */
    public function replacei18n(&$template, $functionName) {
        $container = array();

        // Be sure that php code stays unchanged
        $php_matches = array();
        /*
         * if (preg_match_all('/<\?(php)?((.)|(\s))*?\?>/i', $template,
         * $php_matches)) { $x = 0; foreach ($php_matches[0] as $php_match) {
         * $x++; $template = str_replace($php_match , '{PHP#' . $x . '#PHP}',
         * $template); $container[$x] = $php_match; } }
         */

        $functionNameQ = preg_quote($functionName, '/');

        // If template contains functionName + parameter store all matches
        $matches = array();
        preg_match_all('/' . $functionNameQ . "\\(([\\\"\\'])(.*?)\\1\\)/s", $template, $matches);

        $matches = array_values(array_unique($matches[2]));
        for ($a = 0; $a < count($matches); $a++) {
            $template = preg_replace('/' . $functionNameQ . "\\([\\\"\\']" . preg_quote($matches[$a], '/') . "[\\\"\\']\\)/s", i18n($matches[$a], $this->_sDomain), $template);
        }

        // Change back php placeholder
        if (is_array($container)) {
            foreach ($container as $x => $php_match) {
                $template = str_replace('{PHP#' . $x . '#PHP}', $php_match, $template);
            }
        }
    }

}
