<?php

/**
 * This file contains the article class for the plugin content allocation.
 *
 * @package    Plugin
 * @subpackage ContentAllocation
 * @author     Marco Jahn
 * @copyright  four for business AG <www.4fb.de>
 * @license    https://www.contenido.org/license/LIZENZ.txt
 * @link       https://www.4fb.de
 * @link       https://www.contenido.org
 */

defined('CON_FRAMEWORK') || die('Illegal call: Missing framework initialization - request aborted.');

plugin_include('repository', 'custom/FrontendNavigation.php');

/**
 * Article class for content allocation
 *
 * @package    Plugin
 * @subpackage ContentAllocation
 */
class pApiContentAllocationArticle extends pApiTree {

    /**
     * @var object cTemplate
     */
    protected $_tpl = null;

    /**
     * @var string
     */
    protected $_template = '';

    /**
     * @var array
     */
    protected $_load = [];

    /**
     * pApiContentAllocationArticle constructor
     *
     * @param string $uuid
     *
     * @throws cDbException
     * @throws cException
     */
    public function __construct($uuid) {
        $cfg = cRegistry::getConfig();

        parent::__construct($uuid);
        $this->_tpl = new cTemplate();
        $this->_template = $cfg['pica']['treetemplate_article'];
    }

    /**
     * Old constructor
     *
     * @deprecated [2016-02-11]
     *                This method is deprecated and is not needed any longer. Please use __construct() as constructor function.
     *
     * @param string $uuid
     *
     * @return pApiContentAllocationArticle
     * @throws cDbException
     * @throws cException
     */
    public function pApiContentAllocationArticle($uuid) {
        cDeprecated('This method is deprecated and is not needed any longer. Please use __construct() as constructor function.');
        return $this->__construct($uuid);
    }

    /**
     * Builed an render tree
     *
     * @param $tree
     * @return array
     */
    protected function _buildRenderTree($tree) {

        $result = [];
        foreach ($tree as $item_tmp) {
            $item = [];

            $expandCollapseImg = 'images/spacer.gif';
            $expandCollapse = '<img class="borderless vAlignMiddle" src="'.$expandCollapseImg.'" alt="" width="11" height="11">';

            $item['ITEMNAME'] = $expandCollapse . ' ' . $item_tmp['name'];

            $item['ITEMINDENT'] = $item_tmp['level'] * 15 + 3;

            // set checked!
            $checked = '';
            if (in_array($item_tmp['idpica_alloc'], $this->_load)) {
                $checked = ' checked="checked"';
            }
            $item['CHECKBOX'] = '<input type="checkbox" name="allocation[]" value="'.$item_tmp['idpica_alloc'].'" '.$checked.'>';

            $result[] = $item;

            if (count($item_tmp['children'])) {
                $children = $this->_buildRenderTree($item_tmp['children']);
                $result = array_merge($result, $children);
            }
        }

        return $result;
    }

    /**
     * Set method for load
     *
     * @param array $load
     */
    public function setChecked($load) {
        $this->_load = $load;
    }

    /**
     * Render tree
     *
     * @param bool $return
     *
     * @return bool|object|void
     * @throws cInvalidArgumentException|cException
     */
    function renderTree($return = true) {
        $this->_tpl->reset();

        $tree = $this->fetchTree();
        if ($tree === false) {
            return false;
        }

        $tree = $this->_buildRenderTree($tree);

        $even = true;
        foreach ($tree as $item) {
            $even = !$even;
            $bgColor = ($even) ? '#FFFFFF' : '#F1F1F1';
            $this->_tpl->set('d', 'BACKGROUND_COLOR', $bgColor);
            foreach ($item as $key => $value) {
                $this->_tpl->set('d', $key, $value);
            }
            $this->_tpl->next();
        }

        $this->_tpl->set('s', "CATEGORY", i18n("Category", 'content_allocation'));

        if ($return === true) {
            return $this->_tpl->generate($this->_template, true);
        } else {
            $this->_tpl->generate($this->_template);
        }
    }
}
