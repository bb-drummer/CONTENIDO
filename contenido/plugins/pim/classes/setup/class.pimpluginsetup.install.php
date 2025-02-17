<?php

/**
 * This file contains abstract class for installation new plugins
 *
 * @package    Plugin
 * @subpackage PluginManager
 * @author     Frederic Schneider
 * @copyright  four for business AG <www.4fb.de>
 * @license    https://www.contenido.org/license/LIZENZ.txt
 * @link       https://www.4fb.de
 * @link       https://www.contenido.org
 */

defined('CON_FRAMEWORK') || die('Illegal call: Missing framework initialization - request aborted.');

/**
 * Install class for new plugins, extends PimPluginSetup
 *
 * @package    Plugin
 * @subpackage PluginManager
 * @author     frederic.schneider
 */
class PimPluginSetupInstall extends PimPluginSetup {

    // Initializing variables
    // Plugin specific data
    // Foldername of installed plugin
    private $PluginFoldername;

    // All area entries from database in an array
    private $PluginInstalledAreas = [];

    /**
     * @var cApiAreaCollection
     */
    protected $_ApiAreaCollection;

    /**
     * @var cApiActionCollection
     */
    protected $_ApiActionCollection;

    /**
     * @var cApiFileCollection
     */
    protected $_ApiFileCollection;

    /**
     * @var cApiFrameFileCollection
     */
    protected $_ApiFrameFileCollection;

    /**
     * @var cApiNavMainCollection
     */
    protected $_ApiNavMainCollection;

    /**
     * @var cApiNavSubCollection
     */
    protected $_ApiNavSubCollection;

    /**
     * @var cApiTypeCollection
     */
    protected $_ApiTypeCollection;

    // GET and SET methods for installation routine
    /**
     * Set variable for plugin foldername
     *
     * @param string $foldername
     * @return string
     */
    private function _setPluginFoldername($foldername) {
        return $this->PluginFoldername = cSecurity::escapeString($foldername);
    }

    /**
     * Initializing and set variable for cApiAreaCollection
     *
     * @return cApiAreaCollection
     */
    private function _setApiAreaCollection() {
        return $this->_ApiAreaCollection = new cApiAreaCollection();
    }

    /**
     * Initializing and set variable for cApiActionCollection
     *
     * @return cApiActionCollection
     */
    private function _setApiActionCollection() {
        return $this->_ApiActionCollection = new cApiActionCollection();
    }

    /**
     * Initializing and set variable for cApiAFileCollection
     *
     * @return cApiFileCollection
     */
    private function _setApiFileCollection() {
        return $this->_ApiFileCollection = new cApiFileCollection();
    }

    /**
     * Initializing and set variable for cApiFrameFileCollection
     *
     * @return cApiFrameFileCollection
     */
    private function _setApiFrameFileCollection() {
        return $this->_ApiFrameFileCollection = new cApiFrameFileCollection();
    }

    /**
     * Initializing and set variable for cApiNavMainFileCollection
     *
     * @return cApiNavMainCollection
     */
    private function _setApiNavMainCollection() {
        return $this->_ApiNavMainCollection = new cApiNavMainCollection();
    }

    /**
     * Initializing and set variable for cApiNavSubCollection
     *
     * @return cApiNavSubCollection
     */
    private function _setApiNavSubCollection() {
        return $this->_ApiNavSubCollection = new cApiNavSubCollection();
    }

    /**
     * Initializing and set variable for cApiTypeCollection
     *
     * @return cApiTypeCollection
     */
    private function _setApiTypeCollection() {
        return $this->_ApiTypeCollection = new cApiTypeCollection();
    }

    /**
     * Get method for foldername of installed plugin
     *
     * @return string
     */
    protected function _getPluginFoldername() {
        return $this->PluginFoldername;
    }

    /**
     * Get method for installed areas
     *
     * @return array
     */
    protected function _getInstalledAreas() {
        return $this->PluginInstalledAreas;
    }

    /**
     * Get id of nav_main entry
     *
     * @param string $navm
     *
     * @return bool|int
     *
     * @throws cDbException
     * @throws cException
     */
    protected function _getNavMainId($navm = '') {
    	if (!$navm) {
    		return false;
    	}

    	$this->_ApiNavMainCollection->setWhere('name', cSecurity::escapeString($navm));
    	$this->_ApiNavMainCollection->query();

    	if ($this->_ApiNavMainCollection->count() == 0) {
    		return false;
    	} else {
	    	$entry = $this->_ApiNavMainCollection->next();
	    	return $entry->get('idnavm');
    	}
    }

    // Begin of installation routine
    /**
     * Construct function
     */
    public function __construct() {
        parent::__construct();

        // cApiClasses
        $this->_setApiAreaCollection();
        $this->_setApiActionCollection();
        $this->_setApiFileCollection();
        $this->_setApiFrameFileCollection();
        $this->_setApiNavMainCollection();
        $this->_setApiNavSubCollection();
        $this->_setApiTypeCollection();
    }

    /**
     * Installation method
     *
     * @throws cException
     */
    public function install() {
        // Does this plugin already exist?
        $this->_installCheckUuid();

        // Requirement checks
        $this->_installCheckRequirements();

        // Dependencies checks
        $this->_installCheckDependencies();

        // Add new plugin: *_plugins
        $this->_installAddPlugin();

        // Get all area names from database
        $this->_installFillAreas();

        // Add new CONTENIDO areas: *_area
        $this->_installAddAreas();

        // Add new CONTENIDO actions: *_actions
        $this->_installAddActions();

        // Add new CONTENIDO frames: *_frame_files and *_files
        $this->_installAddFrames();

        // Add new CONTENIDO main navigations: *_nav_main
        $this->_installAddNavMain();

        // Add new CONTENIDO sub navigations: *_nav_sub
        $this->_installAddNavSub();

        // Add specific sql queries, run only if we have no update sql file
        if (parent::_getUpdateSqlFileExist() === false) {
            $this->_installAddSpecificSql();
        }

        // Add new CONTENIDO content types: *_type
        $this->_installAddContentTypes();

        // Add new modules
        $this->_installAddModules();

        // Add plugin dir for uploaded plugins
        if (parent::getMode() == 2) {
            $this->_installAddDir();
        }

        // Success message for new plugins
        // Get only for extracted (1) and installed mode (2)
        if (parent::getMode() <= 2) {
            parent::info(i18n('The plugin has been successfully installed. To apply the changes please login into backend again.', 'pim'));
        }
    }

    /**
     * Check uuId: You can install a plugin only for one time
     *
     * @throws cException
     */
    private function _installCheckUuid() {
        $this->_pimPluginCollection->setWhere('uuid', parent::$XmlGeneral->uuid);
        $this->_pimPluginCollection->query();
        if ($this->_pimPluginCollection->count() > 0) {
            parent::error(i18n('You can install this plugin only for one time.', 'pim'));
        }
    }

    /**
     * This function checks requirements for one plugin
     *
     * @throws cException
     */
    private function _installCheckRequirements() {
        // Check min CONTENIDO version
        if (version_compare(CON_VERSION, parent::$XmlRequirements->contenido->attributes()->minversion, '<')) {
            parent::error(sprintf(i18n('You have to install CONTENIDO <strong>%s</strong> or higher to install this plugin!', 'pim'), parent::$XmlRequirements->contenido->attributes()->minversion));
        }

        // Check max CONTENIDO version
        if (parent::$XmlRequirements->contenido->attributes()->maxversion) {
            if (version_compare(CON_VERSION, parent::$XmlRequirements->contenido->attributes()->maxversion, '>')) {
                parent::error(sprintf(i18n('Your current CONTENIDO version is to new - max CONTENIDO version: %s', 'pim'), parent::$XmlRequirements->contenido->attributes()->maxversion));
            }
        }

        // Check PHP version
        if (version_compare(phpversion(), parent::$XmlRequirements->attributes()->php, '<')) {
            parent::error(sprintf(i18n('You have to install PHP <strong>%s</strong> or higher to install this plugin!', 'pim'), parent::$XmlRequirements->attributes()->php));
        }

        // Check extensions
        if (count(parent::$XmlRequirements->extension) != 0) {
            for ($i = 0; $i < count(parent::$XmlRequirements->extension); $i++) {
                if (!extension_loaded(parent::$XmlRequirements->extension[$i]->attributes()->name)) {
                    parent::error(sprintf(i18n('The plugin could not find the PHP extension <strong>%s</strong>. Because this is required by the plugin, it can not be installed.', 'pim'), parent::$XmlRequirements->extension[$i]->attributes()->name));
                }
            }
        }

        // Check classes
        if (count(parent::$XmlRequirements->class) != 0) {
            for ($i = 0; $i < count(parent::$XmlRequirements->class); $i++) {
                if (!class_exists(parent::$XmlRequirements->class[$i]->attributes()->name)) {
                    parent::error(sprintf(i18n('The plugin could not find the class <strong>%s</strong>. Because this is required by the plugin, it can not be installed.', 'pim'), parent::$XmlRequirements->class[$i]->attributes()->name));
                }
            }
        }

        // Check functions
        if (count(parent::$XmlRequirements->function) != 0) {
            for ($i = 0; $i < count(parent::$XmlRequirements->function); $i++) {
                if (!function_exists(parent::$XmlRequirements->function[$i]->attributes()->name)) {
                    parent::error(sprintf(i18n('The plugin could not find the function <strong>%s</strong>. Because this is required by the plugin, it can not be installed.', 'pim'), parent::$XmlRequirements->function[$i]->attributes()->name));
                }
            }
        }
    }

    /**
     * Check dependencies to other plugins (dependencies-Tag at plugin.xml)
     *
     * @throws cException
     * @throws cInvalidArgumentException
     */
    private function _installCheckDependencies() {
        $dependenciesCount = count(parent::$XmlDependencies);

        for ($i = 0; $i < $dependenciesCount; $i++) {
            $attributes = [];

            // Build attributes
            foreach (parent::$XmlDependencies->depend[$i]->attributes() as $key => $value) {
                $attributes[$key] = $value;
            }

            // Security check
            $depend = cSecurity::escapeString(parent::$XmlDependencies->depend[$i]);

            if ($depend == "") {
                return;
            }

            // Add attributes "uuid", "min_version" and "max_version" to an array
            $attributes = [
                'uuid' => cSecurity::escapeString($attributes['uuid']),
                'minversion' => cSecurity::escapeString($attributes['min_version'] ?? ''),
                'maxversion' => cSecurity::escapeString($attributes['max_version'] ?? '')
            ];

            $this->_pimPluginCollection->setWhere('uuid', $attributes['uuid']);
            $this->_pimPluginCollection->setWhere('active', '1');
            $this->_pimPluginCollection->query();
            if ($this->_pimPluginCollection->count() == 0) {
                parent::error(sprintf(i18n('This plugin required the plugin <strong>%s</strong>.', 'pim'), $depend));
            }

            $plugin = $this->_pimPluginCollection->next();

            // Check min plugin version
            if (parent::$XmlDependencies->depend[$i]->attributes()->minversion) {
                if (version_compare($plugin->get("version"), parent::$XmlDependencies->depend[$i]->attributes()->minversion, '<')) {
                    parent::error(sprintf(i18n('You have to install<strong>%s %</strong> or higher to install this plugin!', 'pim'), $depend, parent::$XmlDependencies->depend[$i]->attributes()->minversion));
                }
            }

            // Check max plugin version
            if (parent::$XmlDependencies->depend[$i]->attributes()->maxversion) {
                if (version_compare($plugin->get("version"),  parent::$XmlDependencies->depend[$i]->attributes()->maxversion, '>')) {
                    parent::error(sprintf(i18n('You have to install <strong>%s %s</strong> or lower to install this plugin!', 'pim'), $depend, parent::$XmlDependencies->depend[$i]->attributes()->maxversion));
                }
            }
        }
    }

    /**
     * Add entries at *_plugins
     *
     * @throws cDbException
     * @throws cException
     * @throws cInvalidArgumentException
     */
    private function _installAddPlugin() {
        // Add entry at *_plugins
        $pimPlugin = $this->_pimPluginCollection->create(
            cSecurity::toString(parent::$XmlGeneral->plugin_name),
            cSecurity::toString(parent::$XmlGeneral->description),
            cSecurity::toString(parent::$XmlGeneral->author),
            cSecurity::toString(parent::$XmlGeneral->copyright),
            cSecurity::toString(parent::$XmlGeneral->mail),
            cSecurity::toString(parent::$XmlGeneral->website),
            cSecurity::toString(parent::$XmlGeneral->version),
            cSecurity::toString(parent::$XmlGeneral->plugin_foldername),
            cSecurity::toString(parent::$XmlGeneral->uuid),
            cSecurity::toString(parent::$XmlGeneral->attributes()->active)
        );

        // Get Id of new plugin
        $pluginId = $pimPlugin->get('idplugin');

        // Set pluginId
        parent::setPluginId($pluginId);

        // Set foldername of new plugin
        $this->_setPluginFoldername(parent::$XmlGeneral->plugin_foldername);
    }

    /**
     * Fetch and set all area names from database
     *
     * @throws cDbException
     * @throws cException
     */
    private function _installFillAreas() {
        $this->_ApiAreaCollection->select(NULL, NULL, 'name');
        while (($areas = $this->_ApiAreaCollection->next()) !== false) {
            $this->PluginInstalledAreas[] = $areas->get('name');
        }
    }

    /**
     * Add entries at *_area
     *
     * @throws cDbException
     * @throws cException
     * @throws cInvalidArgumentException
     */
    private function _installAddAreas() {
        $elements = parent::$XmlArea->area;
        if (empty($elements)) {
            return;
        }

        // Get Id of plugin
        $pluginId = parent::_getPluginId();

        foreach ($elements as $element) {
            // Initializing attributes array
            $attributes = [];

            // Build attributes
            foreach ($element->attributes() as $key => $value) {
                $attributes[$key] = $value;
            }

            // Security check
            $area = cSecurity::escapeString($element);

            // Add attributes "parent", "relevant" and "menuless" to an array
            $attributes = [
                'parent' => isset($attributes['parent']) ? cSecurity::escapeString($attributes['parent']) : '',
            	'relevant' => isset($attributes['relevant']) ? cSecurity::toInteger($attributes['relevant']) : 0,
                'menuless' => isset($attributes['menuless']) ? cSecurity::toInteger($attributes['menuless']) : 0
            ];

            // Fix for parent and relevant attributes
            if (empty($attributes['parent'])) {
                $attributes['parent'] = 0;
            }

            if (empty($attributes['relevant'])) {
            	$attributes['relevant'] = 1;
            }

            // Create a new entry
            $item = $this->_ApiAreaCollection->create($area, $attributes['parent'], $attributes['relevant'], 1, $attributes['menuless']);

            // Set a relation
            $this->_pimPluginRelationsCollection->create($item->get('idarea'), $pluginId, 'area');

            // Add new area to all area array
            $this->PluginInstalledAreas[] = $area;
        }
    }

    /**
     * Add entries at *_actions
     *
     * @throws cDbException
     * @throws cException
     * @throws cInvalidArgumentException
     */
    private function _installAddActions() {
        $elements = parent::$XmlActions->action;
        if (empty($elements)) {
            return;
        }

        // Initializing attributes array
        $attributes = [];

        // Get Id of plugin
        $pluginId = parent::_getPluginId();

        foreach ($elements as $element) {
            // Build attributes
            foreach ($element->attributes() as $key => $value) {
                $attributes[$key] = $value;
            }

            // Set relevant value if it is empty
            if (empty($attributes['relevant'])) {
                $attributes['relevant'] = 1;
            }

            // Add attributes "area" and "relevant" to an safe array
            $attributes = [
                'area' => cSecurity::escapeString($attributes['area']),
                'relevant' => cSecurity::toInteger($attributes['relevant'])
            ];

            // Security check for action name
            $action = cSecurity::escapeString($element);

            // Check for valid area
            if (!in_array($attributes['area'], $this->_getInstalledAreas())) {
                parent::error(sprintf(i18n('Defined area <strong>%s</strong> are not found on your CONTENIDO installation. Please contact your plugin author.', 'pim'), $attributes['area']));
            }

            // Create a new entry
            $item = $this->_ApiActionCollection->create($attributes['area'], $action, '', '', '', $attributes['relevant']);

            // Set a relation
            $this->_pimPluginRelationsCollection->create($item->get('idaction'), $pluginId, 'action');
        }
    }

    /**
     * Add entries at *_frame_files and *_files
     *
     * @throws cDbException
     * @throws cException
     * @throws cInvalidArgumentException
     */
    private function _installAddFrames() {
        $elements = parent::$XmlFrames->frame;
        if (empty($elements)) {
            return;
        }

        // Initializing attributes array
        $attributes = [];

        // Get Id of plugin
        $pluginId = parent::_getPluginId();

        foreach ($elements as $element) {
            // Build attributes with security checks
            foreach ($element->attributes() as $sKey => $sValue) {
                $attributes[$sKey] = cSecurity::escapeString($sValue);
            }

            // Check for valid area
            if (!in_array($attributes['area'], $this->_getInstalledAreas())) {
                parent::error(sprintf(i18n('Defined area <strong>%s</strong> are not found on your CONTENIDO installation. Please contact your plugin author.', 'pim'), $attributes['area']));
            }

            // Create a new entry at *_files
            $file = $this->_ApiFileCollection->create($attributes['area'], $attributes['name'], $attributes['filetype']);

            // Create a new entry at *_frame_files
            if (!empty($attributes['frameId'])) {
                $item = $this->_ApiFrameFileCollection->create($attributes['area'], $attributes['frameId'], $file->get('idfile'));

				// Set a relation
				$this->_pimPluginRelationsCollection->create($item->get('idframefile'), $pluginId, 'framefl');
            }
        }
    }

    /**
     * Add entries at *_nav_main
     *
     * @throws cDbException
     * @throws cException
     * @throws cInvalidArgumentException
     */
    private function _installAddNavMain() {
        $elements = parent::$XmlNavMain->nav;
        if (empty($elements)) {
            return;
        }

        $db = cRegistry::getDb();

    	// Initializing attributes array
    	$attributes = [];

        // Get Id of plugin
        $pluginId = parent::_getPluginId();

        // Get idnavm information to build a new id
        $idnavm = 0;
        $sql = 'SELECT MAX(`idnavm`) AS id FROM ' . cRegistry::getDbTableName('nav_main');
        $db->query($sql);
        if ($db->nextRecord()) {
            $idnavm = $db->f('id');
        }
        // id must be over 10.000
        if ($idnavm < 10000) {
            $idnavm = 10000;
        }

        foreach ($elements as $element) {
        	// Security check for location
        	$location = cSecurity::escapeString($element);

        	// Build attributes with security checks
            foreach ($element->attributes() as $sKey => $sValue) {
                $attributes[$sKey] = cSecurity::escapeString($sValue);
            }

            // Fallback for older plugins
            if (!$attributes['name']) {
            	$attributes['name'] = cString::toLowerCase($location);
            	$attributes['name'] = str_replace('/', '', $attributes['name']);
            }

            // Create new idnavm
            $idnavm = $idnavm + 10;

            // Removed the last number at idnavm
            $idnavm = cString::getPartOfString($idnavm, 0, cString::getStringLength($idnavm) - 1);

            // Last number is always a zero
            $idnavm = cSecurity::toInteger($idnavm . 0);

            // Create a new entry at *_nav_main
            $this->_ApiNavMainCollection->create($attributes['name'], $location, $idnavm);

            // Set a relation
            $this->_pimPluginRelationsCollection->create($idnavm, $pluginId, 'navm');
        }
    }

    /**
     * Add entries at *_nav_sub
     *
     * @throws cDbException
     * @throws cException
     * @throws cInvalidArgumentException
     */
    private function _installAddNavSub() {
        $elements = parent::$XmlNavSub->nav;
        if (empty($elements)) {
            return;
        }

        // Initializing attributes array
        $attributes = [];

        // Get Id of plugin
        $pluginId = parent::_getPluginId();

        foreach ($elements as $element) {
            // Build attributes
            foreach ($element->attributes() as $key => $value) {
                $attributes[$key] = $value;
            }

            // Convert area to string
            $attributes['area'] = cSecurity::toString($attributes['area']);

            // Check for valid area
            if (!in_array($attributes['area'], $this->_getInstalledAreas())) {
                parent::error(sprintf(i18n('Defined area <strong>%s</strong> are not found on your CONTENIDO installation. Please contact your plugin author.', 'pim'), $attributes['area']));
            }

            // If navm attribute is a string get its id
            if (!preg_match('/[^a-zA-Z]/u', $attributes['navm'])) {
            	$navm = $this->_getNavMainId($attributes['navm']);;
            	if ($navm === false) {
            		parent::error(sprintf(i18n('Can not find <strong>%s</strong> entry at nav_main table on your CONTENIDO installation. Please contact your plugin author.', 'pim'), $attributes['navm']));
            	} else {
            		$attributes['navm'] = $navm;
            	}
            }

            // Create a new entry at *_nav_sub
            $item = $this->_ApiNavSubCollection->create($attributes['navm'], $attributes['area'], $attributes['level'], $element->__toString(), 1);

            // Set a relation
            $this->_pimPluginRelationsCollection->create($item->get('idnavs'), $pluginId, 'navs');
        }
    }

    /**
     * Add specific sql queries
     *
     * @throws cDbException
     * @throws cInvalidArgumentException
     */
    private function _installAddSpecificSql() {
        $cfg = cRegistry::getConfig();
        $db = cRegistry::getDb();

        if (parent::getMode() == 1) {
            // Plugin is already extracted
            $tempSqlFilename = $cfg['path']['contenido'] . $cfg['path']['plugins'] . $this->_getPluginFoldername() . DIRECTORY_SEPARATOR . 'plugin_install.sql';
        } elseif (parent::getMode() == 2 || parent::getMode() == 4) {
            // Plugin is uploaded or / and update mode
            $tempSqlFilename = parent::$_PimPluginArchiveExtractor->extractArchiveFileToVariable('plugin_install.sql', 0);
        } else {
            $tempSqlFilename = '';
        }

        // skip using plugin_install.sql if it does not exist
        if (empty($tempSqlFilename) || !cFileHandler::exists($tempSqlFilename)) {
            return;
        }

        $tempSqlContent = cFileHandler::read($tempSqlFilename);
        $tempSqlContent = str_replace("\r\n", "\n", $tempSqlContent);
        $tempSqlContent = explode("\n", $tempSqlContent);
        $tempSqlLines = count($tempSqlContent);

        $pattern = '/^(CREATE TABLE IF NOT EXISTS|INSERT INTO|UPDATE|ALTER TABLE) `?' . parent::SQL_PREFIX . '`?\b/';

        for ($i = 0; $i < $tempSqlLines; $i++) {
            if (preg_match($pattern, $tempSqlContent[$i])) {
                $tempSqlContent[$i] = str_replace(parent::SQL_PREFIX, $cfg['sql']['sqlprefix'] . '_pi', $tempSqlContent[$i]);
                $db->query($tempSqlContent[$i]);
            }
        }
    }

    /**
     * Add content types (*_type)
     *
     * @throws cDbException
     * @throws cException
     * @throws cInvalidArgumentException
     */
    private function _installAddContentTypes() {
        $elements = parent::$XmlContentType->type;
        if (empty($elements)) {
            return;
        }

        // Get Id of plugin
        $pluginId = parent::_getPluginId();

        $pattern = '/^CMS_.+/';

        foreach ($elements as $element) {
            $type = cSecurity::toString($element);

            if (preg_match($pattern, $type)) {
                // Create new content type
                $item = $this->_ApiTypeCollection->create($type, '');

                // Set a relation
                $this->_pimPluginRelationsCollection->create($item->get('idtype'), $pluginId, 'ctype');
            }
        }
    }

    /**
     * Add modules
     *
     * @return bool
     *
     * @throws cDbException
     * @throws cException
     * @throws cInvalidArgumentException
     */
    private function _installAddModules() {
        $cfg = cRegistry::getConfig();
        $module = new cApiModule();

        // Set path to modules path
        $modulesPath = $cfg['path']['contenido'] . $cfg['path']['plugins'] . $this->_getPluginFoldername() . DIRECTORY_SEPARATOR . "modules" . DIRECTORY_SEPARATOR;

        if (!is_dir($modulesPath)) {
            return false;
        }

        foreach (new DirectoryIterator($modulesPath) as $modulesFiles) {
            if (cString::getPartOfString($modulesFiles->getBasename(), -4) == ".zip") {
                // Import founded module
                $module->import($modulesFiles->getBasename(), $modulesFiles->getBasename(), false);
            }
        }

        cDirHandler::recursiveRmdir($modulesPath);
    }

    /**
     * Add plugin dir
     *
     * @throws cInvalidArgumentException
     */
    private function _installAddDir() {
        $cfg = cRegistry::getConfig();

        // Build the new plugin dir
        $tempPluginDir = $cfg['path']['contenido'] . $cfg['path']['plugins'] . parent::$XmlGeneral->plugin_foldername . DIRECTORY_SEPARATOR;

        // Set destination path
        try {
            parent::$_PimPluginArchiveExtractor->setDestinationPath($tempPluginDir);
        } catch (cException $e) {
            parent::$_PimPluginArchiveExtractor->destroyTempFiles();
        }

        // Extract Zip archive files into the new plugin dir
        try {
            parent::$_PimPluginArchiveExtractor->extractArchive();
        } catch (cException $e) {
            parent::$_PimPluginArchiveExtractor->destroyTempFiles();
        }
    }

}
