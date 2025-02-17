<?php

/**
 * This file contains the cContentTypeFilelist class.
 *
 * @package    Core
 * @subpackage ContentType
 * @author     Dominik Ziegler
 * @author     Timo Trautmann
 * @author     Simon Sprankel
 * @copyright  four for business AG <www.4fb.de>
 * @license    https://www.contenido.org/license/LIZENZ.txt
 * @link       https://www.4fb.de
 * @link       https://www.contenido.org
 */

defined('CON_FRAMEWORK') || die('Illegal call: Missing framework initialization - request aborted.');

cInclude('includes', 'functions.con.php');
cInclude('includes', 'functions.upl.php');

/**
 * Content type CMS_FILELIST which lets the editor select some folders or files.
 * The corresponding files are then shown in the frontend.
 *
 * @package    Core
 * @subpackage ContentType
 */
class cContentTypeFilelist extends cContentTypeAbstractTabbed {

    /**
     * Format string for date filters.
     *
     * @var string
     */
    private $_dateFormat = 'DD.MM.YYYY';

    /**
     * Default file extensions.
     *
     * @var array
     */
    private $_fileExtensions = [
        'gif',
        'jpeg',
        'jpg',
        'png',
        'doc',
        'xls',
        'pdf',
        'txt',
        'zip',
        'ppt'
    ];

    /**
     * Meta data identifiers.
     *
     * @var array
     */
    private $_metaDataIdents = [
        'description' => 'Description',
        'medianame' => 'Media name',
        'copyright' => 'Copyright',
        'keywords' => 'Keywords',
        'internal_notice' => 'Internal notes'
    ];

    /**
     * Date fields.
     *
     * @var array
     */
    private $_dateFields = [
        'ctime' => 'creationdate',
        'mtime' => 'modifydate'
    ];

    /**
     * Placeholders for labels in frontend.
     * Important: This must be a static array!
     *
     * @var array
     */
    protected static $_translations = [
        'LABEL_FILESIZE',
        'LABEL_UPLOAD_DATE'
    ];

    /**
     * Constructor to create an instance of this class.
     *
     * Initialises class attributes and handles store events.
     *
     * @param string $rawSettings
     *         the raw settings in an XML structure or as plaintext
     * @param int    $id
     *         ID of the content type, e.g. 3 if CMS_TEASER[3] is used
     * @param array  $contentTypes
     *         array containing the values of all content types
     *
     * @throws cDbException
     * @throws cException
     */
    function __construct($rawSettings, $id, array $contentTypes) {
        // set props
        $this->_type = 'CMS_FILELIST';
        $this->_prefix = 'filelist';
        $this->_settingsType = 'xml';
        $this->_formFields = [
            'filelist_title',
            'filelist_style',
            'filelist_directories',
            'filelist_incl_subdirectories',
            'filelist_manual',
            'filelist_sort',
            'filelist_incl_metadata',
            'filelist_extensions',
            'filelist_sortorder',
            'filelist_filesizefilter_from',
            'filelist_filesizefilter_to',
            'filelist_ignore_extensions',
            'filelist_manual_files',
            'filelist_filecount'
        ];

        // call parent constructor
        parent::__construct($rawSettings, $id, $contentTypes);

        // dynamically add form fields based on the meta data identifiers
        foreach ($this->_metaDataIdents as $identName => $translation) {
            $this->_formFields[] = 'filelist_md_' . $identName . '_limit';
        }

        // dynamically add form fields based on the date fields
        $dateFormFields = [];
        foreach ($this->_dateFields as $dateField) {
            $this->_formFields[] = 'filelist_' . $dateField . 'filter_from';
            $dateFormFields[] = 'filelist_' . $dateField . 'filter_from';
            $this->_formFields[] = 'filelist_' . $dateField . 'filter_to';
            $dateFormFields[] = 'filelist_' . $dateField . 'filter_to';
        }

        // if form is submitted, store the current file list settings
        // notice: there is also a need, that filelist_id is the same (case:
        // more than one cms file list is used on the same page
        $postAction = $_POST['filelist_action'] ?? '';
        $postId = cSecurity::toInteger($_POST['filelist_id'] ?? '0');
        if ($postAction === 'store' && $postId == $this->_id) {
            // convert the date form fields to timestamps
            foreach ($dateFormFields as $dateFormField) {
                $value = $_POST[$dateFormField] ?? '';
                if ($value != '' && $value != $this->_dateFormat && cString::getStringLength($value) == 10) {
                    $valueSplit = explode('.', $value);
                    $timestamp = mktime(0, 0, 0, $valueSplit[1], $valueSplit[0], $valueSplit[2]);
                } else {
                    $timestamp = 0;
                }
                $_POST[$dateFormField] = $timestamp;
            }

            $this->getConfiguredFiles();
            $this->_storeSettings();
        }
    }

    /**
     * Returns all translation strings for mi18n.
     *
     * @param array $translationStrings
     *         translation strings
     * @return array
     *         updated translation string
     */
    public static function addModuleTranslations(array $translationStrings) {
        foreach (self::$_translations as $value) {
            $translationStrings[] = $value;
        }

        return $translationStrings;
    }

    /**
     * Reads all settings from the $_rawSettings attribute (XML or plaintext)
     * and stores them in the $_settings attribute (associative array or
     * plaintext).
     */
    protected function _readSettings() {
        parent::_readSettings();
        // convert the timestamps to dates
        $dateFormFields = [];
        foreach ($this->_dateFields as $dateField) {
            $dateFormFields[] = 'filelist_' . $dateField . 'filter_from';
            $dateFormFields[] = 'filelist_' . $dateField . 'filter_to';
        }
        foreach ($dateFormFields as $dateFormField) {
            $value = cSecurity::toInteger($this->getSetting($dateFormField, 0));
            if ($value == 0) {
                $value = $this->_dateFormat;
            } else {
                $value = date('d.m.Y', $value);
            }
            $this->setSetting($dateFormField, $value);
        }
    }

    /**
     * Generates the code which should be shown if this content type is shown in
     * the frontend.
     *
     * @return string
     *         escaped HTML code which should be shown if content type is shown in frontend
     */
    public function generateViewCode() {
        $code = '<?php
            $fileList = new cContentTypeFilelist(\'%s\', %s, %s);
            echo $fileList->generateFileListCode();
        ?>';

        $code = $this->_wrapPhpViewCode($code);

        // escape ' to avoid accidentally ending the string in $code
        return sprintf($code, str_replace('\'', '\\\'', $this->_rawSettings), $this->_id, '[]');
    }

    /**
     * Returns a list of configured files.
     * @return array
     * @throws cDbException
     * @throws cException
     */
    public function getConfiguredFiles() {
        $files = [];
        $fileList = [];

        if ($this->getSetting('filelist_manual') === 'true' && !empty($this->getSetting('filelist_manual_files'))) {
            $tempFileList = $this->getSetting('filelist_manual_files');

            // Check if manual selected file exists, otherwise ignore them. Write only existing files into fileList array.
            // Manual selected setting can be a string or an array!
            if (!is_array($tempFileList)) {
                $tempFileList = [$tempFileList];
            }
            foreach ($tempFileList as $filename) {
                if (cFileHandler::exists($this->_uploadPath . $filename)) {
                    $fileList[] = $filename;
                }
            }
        } elseif (is_array($this->getSetting('filelist_directories')) && count($this->getSetting('filelist_directories')) > 0) {
            $directories = $this->getSetting('filelist_directories');

            if ($this->getSetting('filelist_incl_subdirectories') === 'true') {
                foreach ($directories as $directoryName) {
                    $directories = $this->_getAllSubdirectories($directoryName, $directories);
                }
            }

            // strip duplicate directories to save performance
            $directories = array_unique($directories);
            if (count($directories) < 1) {
                return [];
            }

            foreach ($directories as $directoryName) {
                if (cDirHandler::exists($this->_uploadPath . $directoryName)) {
                    if (false !== $handle = cDirHandler::read($this->_uploadPath . $directoryName . '/', false, false, true)) {
                        foreach ($handle as $entry) {
                            $fileList[] = $directoryName . '/' . $entry;
                        }
                    }
                }
            }
        } else {
            return [];
        }

        if (count($fileList) > 0) {
            $files = $this->_applyFileFilters($fileList);
        }
        unset($fileList);

        $limitedFiles = [];
        if (count($files) > 0) {
            // sort the files
            if ($this->getSetting('filelist_sortorder') === 'desc') {
                krsort($files);
            } else {
                ksort($files);
            }

            $i = 1;
            $fileListCount = cSecurity::toInteger($this->getSetting('filelist_filecount'));
            foreach ($files as $key => $filenameData) {
                if (($fileListCount != 0 && $i <= $fileListCount) || $fileListCount == 0) {
                    if ($this->getSetting('filelist_incl_metadata') === 'true') {
                        $metaData = [];
                        // load upload and upload meta object
                        $upload = new cApiUpload();
                        $upload->loadByMany([
                            'filename' => $filenameData['filename'],
                            'dirname' => $filenameData['path'] . '/',
                            'idclient' => $this->_client
                        ]);
                        $uploadMeta = new cApiUploadMeta();
                        $uploadMeta->loadByMany([
                            'idupl' => $upload->get('idupl'),
                            'idlang' => $this->_lang
                        ]);

                        foreach ($this->_metaDataIdents as $identName => $translation) {
                            $string = $uploadMeta->get($identName);
                            $limit = $this->getSetting('filelist_md_' . $identName . '_limit');

                            // Cut the string only, when the limit for identName
                            // is active and the string length is more than the
                            // setting
                            if ($limit > 0 && cString::getStringLength($string) > $limit) {
                                $metaData[$identName] = cString::trimAfterWord(cSecurity::unFilter($string), $limit) . '...';
                            } else {
                                $metaData[$identName] = cSecurity::unFilter($string);
                            }
                        }

                        $filenameData['metadata'] = $metaData;
                    } else {
                        $filenameData['metadata'] = [];
                    }

                    // Define new array for files
                    // If filelist_filecount is defined, this array has the same
                    // size as "filelist_filecount" setting value (0 = no limit)
                    $limitedFiles[$key] = $filenameData;
                    $i++;
                }
            }
        }

        return $limitedFiles;
    }

    /**
     * Function is called in edit- and viewmode in order to generate code for
     * output.
     *
     * @return string
     *         generated code
     * @throws cDbException
     * @throws cException
     * @throws cInvalidArgumentException
     */
    public function generateFileListCode() {
        $code = '';
        if ($this->getSetting('filelist_style') === '') {
            return $code;
        }
        $template = new cTemplate();
        $template->set('s', 'TITLE', conHtmlSpecialChars($this->getSetting('filelist_title')));

        $files = $this->getConfiguredFiles();

        if (count($files) > 0) {
            foreach ($files as $filenameData) {
                $this->_fillFileListTemplateEntry($filenameData, $template);
            }

            // generate template
            $code = $template->generate(
                $this->_cfgClient[$this->_client]['path']['frontend'] . 'templates/' . $this->getSetting('filelist_style'),
                true
            );
        }

        return $code;
    }

    /**
     * Gets all subdirectories recursively.
     *
     * @param string $directoryPath
     *         path to directory
     * @param array $directories
     *         already found directories
     * @return array
     *         containing all subdirectories and the initial directories
     */
    private function _getAllSubdirectories($directoryPath, array $directories) {
        $handle = cDirHandler::read($this->_uploadPath . $directoryPath . '/', false, true);

        if (false !== $handle) {
            foreach ($handle as $entry) {
                if (cFileHandler::fileNameBeginsWithDot($entry) === false) {
                    $directories[] = $directoryPath . '/' . $entry;
                    $directories = $this->_getAllSubdirectories($directoryPath . '/' . $entry, $directories);
                }
            }
        }

        return $directories;
    }

    /**
     * Removes all files not matching the filter criteria.
     *
     * @param array $fileList
     *         files which should be filtered
     * @return array
     *         with filtered files
     */
    private function _applyFileFilters(array $fileList) {
        $files = [];
        $fileSizeFilterFrom = cSecurity::toInteger($this->getSetting('filelist_filesizefilter_from'));
        $fileSizeFilterTo = cSecurity::toInteger($this->getSetting('filelist_filesizefilter_to'));
        $ignoreExtensions = $this->getSetting('filelist_ignore_extensions');
        $extensions = $this->getSetting('filelist_extensions');
        if (!is_array($extensions)) {
            $extensions = [$extensions];
        }
        $fileListSort = $this->getSetting('filelist_sort');

        foreach ($fileList as $fullName) {
            $fileName = basename($fullName);
            $directoryName = str_replace('/' . $fileName, '', $fullName);

            // checking the extension stuff
            $extensionName = cFileHandler::getExtension($fileName);
            if ($ignoreExtensions === 'true' || count($extensions) == 0
                || ($ignoreExtensions === 'false' && in_array($extensionName, $extensions))
                || ($ignoreExtensions === 'false' && $extensionName == $extensions)) {

                // Prevent errors with not existing files
                if (!cFileHandler::exists($this->_uploadPath . $directoryName . '/' . $fileName)) {
                    return [];
                }

                // checking filesize filter
                $fileStats = stat($this->_uploadPath . $directoryName . '/' . $fileName);
                $fileSize = $fileStats['size'];

                $fileSizeMib = $fileSize / 1024 / 1024;
                if (($fileSizeFilterFrom == 0 && $fileSizeFilterTo == 0)
                    || ($fileSizeFilterFrom <= $fileSizeMib && $fileSizeFilterTo >= $fileSizeMib)) {
                    if ($this->_applyDateFilters($fileStats)) {
                        $creationDate = $fileStats['ctime'];
                        $modifyDate = $fileStats['mtime'];
                        // conditional stuff is completed, start sorting
                        // inclusive fix CON_2605 for files created at the same time
                        // or with the same filesize
                        switch ($fileListSort) {
                            case 'filesize':
                                if (!isset($files[$fileSize])) {
                                    $indexName = $fileSize . '_' . mt_rand(0, mt_getrandmax());
                                } else {
                                    $indexName = $fileSize;
                                }
                                break;
                            case 'createdate':
                                if (!isset($files[$creationDate])) {
                                    $indexName = $creationDate . '_' . mt_rand(0, mt_getrandmax());
                                } else {
                                    $indexName = $creationDate;
                                }
                                break;
                            case 'modifydate':
                                if (!isset($files[$modifyDate])) {
                                    $indexName = $modifyDate . '_' . mt_rand(0, mt_getrandmax());
                                } else {
                                    $indexName = $modifyDate;
                                }
                                break;
                            case 'filename':
                            default:
                                $indexName = cString::toLowerCase($directoryName . $fileName);
                        }

                        $files[$indexName] = [];
                        $files[$indexName]['filename'] = $fileName;
                        $files[$indexName]['path'] = $directoryName;
                        $files[$indexName]['extension'] = $extensionName;
                        $files[$indexName]['filesize'] = $fileSize;
                        $files[$indexName]['filemodifydate'] = $modifyDate;
                        $files[$indexName]['filecreationdate'] = $creationDate;
                    }
                }
            }
        }

        return $files;
    }

    /**
     * Checks whether the file passes the date filters.
     *
     * @param array $fileStats
     *         file information
     * @return bool
     *         whether the file passes the date filters
     */
    private function _applyDateFilters(array $fileStats) {
        foreach ($this->_dateFields as $index => $dateField) {
            $date = $fileStats[$index];

            $dateFilterFrom = $this->getSetting('filelist_' . $dateField . 'filter_from');
            $dateFilterTo = $this->getSetting('filelist_' . $dateField . 'filter_to');
            if (empty($dateFilterFrom) || $dateFilterFrom === $this->_dateFormat) {
                $dateFilterFrom = 0;
            }
            if (empty($dateFilterTo) || $dateFilterTo === $this->_dateFormat) {
                $dateFilterTo = 0;
            }
            if ($dateFilterFrom == 0 && $dateFilterTo == 0
                || $dateFilterTo == 0 && $date >= $dateFilterFrom
                || $dateFilterFrom == 0 && $date <= $dateFilterTo
                || $dateFilterFrom != 0 && $dateFilterTo != 0 && $date >= $dateFilterFrom && $date <= $dateFilterTo
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Method to fill single entry (file) of the file list.
     *
     * @param array $fileData
     *         information about the file
     * @param cTemplate $template
     *         reference to the used template object
     * @throws cInvalidArgumentException
     */
    private function _fillFileListTemplateEntry(array $fileData, cTemplate &$template) {
        $filename = $fileData['filename'];
        $directoryName = $fileData['path'];

        if (empty($filename) || empty($directoryName)) {
            cWarning(__FILE__, __LINE__, "Empty directory '$directoryName' and or filename '$filename'");
            return;
        }

        $fileLink = $this->_cfgClient[$this->_client]['upl']['htmlpath'] . $directoryName . '/' . $filename;
        $filePath = $this->_cfgClient[$this->_client]['upl']['path'] . $directoryName . '/' . $filename;

        // If file is an image (extensions gif, jpg, jpeg, png) scale it
        // otherwise use default png image
        switch ($fileData['extension']) {
            case 'gif':
            case 'jpg':
            case 'jpeg':
            case 'png':
                $imgSrc = cApiImgScale($filePath, 148, 74);
                break;
            default:
                $imgSrc = $this->_cfgClient[$this->_client]['path']['htmlpath'] . 'images/misc/download_misc.png';
                break;
        }

        $filesize = $fileData['filesize'];
        $metaData = $fileData['metadata'];

        if ($this->getSetting('filelist_incl_metadata') === 'true' && count($metaData) != 0) {
            $template->set('d', 'FILEMETA_DESCRIPTION', $metaData['description']);
            $template->set('d', 'FILEMETA_MEDIANAME', $metaData['medianame']);
            $template->set('d', 'FILEMETA_KEYWORDS', $metaData['keywords']);
            $template->set('d', 'FILEMETA_INTERNAL_NOTICE', $metaData['internal_notice']);
            $template->set('d', 'FILEMETA_COPYRIGHT', $metaData['copyright']);
        } else {
            $template->set('d', 'FILEMETA_DESCRIPTION', '');
            $template->set('d', 'FILEMETA_MEDIANAME', '');
            $template->set('d', 'FILEMETA_KEYWORDS', '');
            $template->set('d', 'FILEMETA_INTERNAL_NOTICE', '');
            $template->set('d', 'FILEMETA_COPYRIGHT', '');
        }

        $template->set('d', 'FILETHUMB', $imgSrc);
        $template->set('d', 'FILENAME', $filename);
        $template->set('d', 'FILESIZE', humanReadableSize($filesize));
        $template->set('d', 'FILEEXTENSION', $fileData['extension']);
        $template->set('d', 'FILECREATIONDATE', date('d.m.Y', $fileData['filecreationdate']));
        $template->set('d', 'FILEMODIFYDATE', date('d.m.Y', $fileData['filemodifydate']));
        $template->set('d', 'FILEDIRECTORY', $directoryName);
        $template->set('d', 'FILELINK', $fileLink);

        foreach (self::$_translations as $translationString) {
            $template->set('d', $translationString, mi18n($translationString));
        }

        $template->next();
    }

    /**
     * Generates the code which should be shown if this content type is edited.
     *
     * @return string
     *         escaped HTML code which should be shown if content type is edited
     * @throws cInvalidArgumentException|cException
     */
    public function generateEditCode() {
        $template = new cTemplate();
        $template->set('s', 'ID', $this->_id);
        $template->set('s', 'IDARTLANG', $this->_idArtLang);
        $template->set('s', 'FIELDS', "'" . implode("','", $this->_formFields) . "'");

        $templateTabs = new cTemplate();
        $templateTabs->set('s', 'PREFIX', $this->_prefix);

        // create code for external tab
        $templateTabs->set('d', 'TAB_ID', 'directories');
        $templateTabs->set('d', 'TAB_CLASS', 'directories');
        $templateTabs->set('d', 'TAB_CONTENT', $this->_generateTabDirectories());
        $templateTabs->next();

        // create code for internal tab
        $templateTabs->set('d', 'TAB_ID', 'general');
        $templateTabs->set('d', 'TAB_CLASS', 'general');
        $templateTabs->set('d', 'TAB_CONTENT', $this->_generateTabGeneral());
        $templateTabs->next();

        // create code for file tab
        $templateTabs->set('d', 'TAB_ID', 'filter');
        $templateTabs->set('d', 'TAB_CLASS', 'filter');
        $templateTabs->set('d', 'TAB_CONTENT', $this->_generateTabFilter());
        $templateTabs->next();

        // create code for manual tab
        $templateTabs->set('d', 'TAB_ID', 'manual');
        $templateTabs->set('d', 'TAB_CLASS', 'manual');
        $templateTabs->set('d', 'TAB_CONTENT', $this->_generateTabManual());
        $templateTabs->next();

        $codeTabs = $templateTabs->generate(
            $this->_cfg['path']['contenido'] . 'templates/standard/template.cms_abstract_tabbed_edit_tabs.html',
            true
        );

        // construct the top code of the template
        $templateTop = new cTemplate();
        $templateTop->set('s', 'ICON', 'images/but_editlink.gif');
        $templateTop->set('s', 'ID', $this->_id);
        $templateTop->set('s', 'PREFIX', $this->_prefix);
        $templateTop->set('s', 'HEADLINE', i18n('File list settings'));
        $codeTop = $templateTop->generate(
            $this->_cfg['path']['contenido'] . 'templates/standard/template.cms_abstract_tabbed_edit_top.html',
            true
        );

        // define the available tabs
        $tabMenu = [
            'directories' => i18n('Directories'),
            'general' => i18n('General'),
            'filter' => i18n('Filter'),
            'manual' => i18n('Manual')
        ];

        // construct the bottom code of the template
        $templateBottom = new cTemplate();
        $templateBottom->set('s', 'PATH_FRONTEND', $this->_cfgClient[$this->_client]['path']['htmlpath']);
        $templateBottom->set('s', 'ID', $this->_id);
        $templateBottom->set('s', 'PREFIX', $this->_prefix);
        $templateBottom->set('s', 'IDARTLANG', $this->_idArtLang);
        $templateBottom->set('s', 'FIELDS', "'" . implode("','", $this->_formFields) . "'");
        $templateBottom->set('s', 'SETTINGS', json_encode($this->getSettings()));
        $templateBottom->set(
            's', 'JS_CLASS_SCRIPT',
            $this->_cfg['path']['contenido_fullhtml'] . cAsset::backend('scripts/content_types/cmsFilelist.js')
        );
        $templateBottom->set('s', 'JS_CLASS_NAME', 'Con.cContentTypeFilelist');
        $codeBottom = $templateBottom->generate(
            $this->_cfg['path']['contenido'] . 'templates/standard/template.cms_abstract_tabbed_edit_bottom.html',
            true
        );

        // construct the whole template code
        $code = $this->generateViewCode();
        $code .= $this->_encodeForOutput($codeTop);
        $code .= $this->_generateTabMenuCode($tabMenu);
        $code .= $this->_encodeForOutput($codeTabs);
        $code .= $this->_generateActionCode();
        $code .= $this->_encodeForOutput($codeBottom);

        return $code;
    }

    /**
     * Generates code for the directories tab.
     *
     * @return string
     *         the code for the directories tab
     * @throws cInvalidArgumentException|cException
     */
    private function _generateTabDirectories() {
        // wrapper containing content of directories tab
        $wrapper = new cHTMLDiv();
        $wrapperContent = [];

        $wrapperContent[] = new cHTMLParagraph(i18n('Source directory'), 'head_sub');

        $directoryList = new cHTMLDiv('', 'directoryList', 'directoryList' . '_' . $this->_id);
        $liRoot = new cHTMLListItem('root', 'root last');
        $directoryListCode = $this->generateDirectoryList($this->buildDirectoryList());
        $liRoot->setContent([
            '<em>Uploads</em>',
            $directoryListCode
        ]);
        $conStrTree = new cHTMLList('ul', 'con_str_tree', 'con_str_tree', $liRoot);
        $directoryList->setContent($conStrTree);
        $wrapperContent[] = $directoryList;

        $wrapper->setContent($wrapperContent);

        return $wrapper->render();
    }

    /**
     * Generates code for the general tab.
     *
     * @return string
     *         the code for the general link tab
     * @throws cInvalidArgumentException|cException
     */
    private function _generateTabGeneral() {
        // wrapper containing content of general tab
        $wrapper = new cHTMLDiv();
        $wrapperContent = [];

        $wrapperContent[] = new cHTMLParagraph(i18n('General settings'), 'head_sub');

        $wrapperContent[] = new cHTMLLabel(i18n('File list title'), 'filelist_title_' . $this->_id);
        $wrapperContent[] = new cHTMLTextbox('filelist_title_' . $this->_id, conHtmlSpecialChars($this->getSetting('filelist_title')), '', '', 'filelist_title_' . $this->_id);
        $wrapperContent[] = new cHTMLLabel(i18n('File list style'), 'filelist_style_' . $this->_id);
        $wrapperContent[] = $this->_generateStyleSelect();
        $wrapperContent[] = new cHTMLLabel(i18n('File list sort'), 'filelist_sort_' . $this->_id);
        $wrapperContent[] = $this->_generateSortSelect();
        $wrapperContent[] = new cHTMLLabel(i18n('Sort order'), 'filelist_sortorder_' . $this->_id);
        $wrapperContent[] = $this->_generateSortOrderSelect();
        $wrapperContent[] = new cHTMLLabel(i18n('Include subdirectories?'), 'filelist_incl_subdirectories_' . $this->_id);
        $wrapperContent[] = new cHTMLCheckbox('filelist_incl_subdirectories_' . $this->_id, '', 'filelist_incl_subdirectories_' . $this->_id, ($this->getSetting('filelist_incl_subdirectories') === 'true'));
        $wrapperContent[] = new cHTMLLabel(i18n('Include meta data?'), 'filelist_incl_metadata_' . $this->_id);
        $wrapperContent[] = new cHTMLCheckbox('filelist_incl_metadata_' . $this->_id, '', 'filelist_incl_metadata_' . $this->_id, ($this->getSetting('filelist_incl_metadata') === 'true'));
        $div = new cHTMLDiv($this->_generateMetaDataList());
        $div->setID('metaDataList');
        $wrapperContent[] = $div;

        $wrapper->setContent($wrapperContent);

        return $wrapper->render();
    }

    /**
     * Generates a select box containing the filelist styles.
     *
     * @return string
     *         rendered cHTMLSelectElement
     */
    private function _generateStyleSelect() {
        $htmlSelect = new cHTMLSelectElement('filelist_style_' . $this->_id, '', 'filelist_style_' . $this->_id);

        $htmlSelectOption = new cHTMLOptionElement(i18n('Default style'), 'cms_filelist_style_default.html', true);
        $htmlSelect->appendOptionElement($htmlSelectOption);
        $additionalOptions = getEffectiveSettingsByType('cms_filelist_style');
        $options = [];
        foreach ($additionalOptions as $key => $value) {
            $options[$value] = $key;
        }
        $htmlSelect->autoFill($options);
        $htmlSelect->setDefault($this->getSetting('filelist_style'));
        return $htmlSelect->render();
    }

    /**
     * Generates a select box containing the sort options.
     *
     * @return string
     *         rendered cHTMLSelectElement
     */
    private function _generateSortSelect() {
        $htmlSelect = new cHTMLSelectElement('filelist_sort_' . $this->_id, '', 'filelist_sort_' . $this->_id);

        $htmlSelectOption = new cHTMLOptionElement(i18n('File name'), 'filename', true);
        $htmlSelect->appendOptionElement($htmlSelectOption);

        $htmlSelectOption = new cHTMLOptionElement(i18n('File size'), 'filesize', false);
        $htmlSelect->appendOptionElement($htmlSelectOption);

        $htmlSelectOption = new cHTMLOptionElement(i18n('Date created'), 'createdate', false);
        $htmlSelect->appendOptionElement($htmlSelectOption);

        $htmlSelectOption = new cHTMLOptionElement(i18n('Date modified'), 'modifydate', false);
        $htmlSelect->appendOptionElement($htmlSelectOption);

        $htmlSelect->setDefault($this->getSetting('filelist_sort'));

        return $htmlSelect->render();
    }

    /**
     * Generates a select box containing the sort order options (asc/desc).
     *
     * @return string
     *         rendered cHTMLSelectElement
     */
    private function _generateSortOrderSelect() {
        $htmlSelect = new cHTMLSelectElement('filelist_sortorder_' . $this->_id, '', 'filelist_sortorder_' . $this->_id);

        $htmlSelectOption = new cHTMLOptionElement(i18n('Ascending'), 'asc', true);
        $htmlSelect->appendOptionElement($htmlSelectOption);

        $htmlSelectOption = new cHTMLOptionElement(i18n('Descending'), 'desc', false);
        $htmlSelect->appendOptionElement($htmlSelectOption);

        // set default value
        $htmlSelect->setDefault($this->getSetting('filelist_sortorder'));

        return $htmlSelect->render();
    }

    /**
     * Generates a list of meta data.
     *
     * @return string
     *         HTML code showing a list of meta data
     * @throws cInvalidArgumentException
     */
    private function _generateMetaDataList() {
        $template = new cTemplate();

        foreach ($this->_metaDataIdents as $identName => $translation) {
            $metaDataLimit = $this->getSetting('filelist_md_' . $identName . '_limit');
            if (!isset($metaDataLimit) || $metaDataLimit === '') {
                $metaDataLimit = 0;
            }

            $template->set('d', 'METADATA_NAME', $identName);
            $template->set('d', 'METADATA_DISPLAYNAME', i18n($translation));
            $template->set('d', 'METADATA_LIMIT', $metaDataLimit);
            $template->set('d', 'ID', $this->_id);

            $template->next();
        }

        return $template->generate(
            $this->_cfg['path']['contenido'] . 'templates/standard/template.cms_filelist_metadata_limititem.html',
            true
        );
    }

    /**
     * Generates code for the filter tab.
     *
     * @return string
     *         the code for the filter link tab
     */
    private function _generateTabFilter() {
        // wrapper containing content of filter tab
        $wrapper = new cHTMLDiv();
        $wrapperContent = [];

        $wrapperContent[] = new cHTMLParagraph(i18n('Filter settings'), 'head_sub');

        $wrapperContent[] = new cHTMLLabel(i18n('Displayed file extensions'), 'filelist_extensions_' . $this->_id);
        $wrapperContent[] = $this->_generateExtensionSelect();
        $wrapperContent[] = '<br>';
        $link = new cHTMLLink('#');
        $link->setID('filelist_all_extensions');
        $link->setContent(i18n('Select all entries'));
        $wrapperContent[] = $link;
        $wrapperContent[] = new cHTMLLabel(i18n('Ignore selection (use all)'), 'filelist_ignore_extensions_' . $this->_id, 'filelist_ignore_extensions');
        $wrapperContent[] = new cHTMLCheckbox('filelist_ignore_extensions_' . $this->_id, '', 'filelist_ignore_extensions_' . $this->_id, ($this->getSetting('filelist_ignore_extensions') !== 'false'));

        $wrapperContent[] = new cHTMLLabel(i18n('File size limit (in MiB)'), 'filelist_filesizefilter_from_' . $this->_id);
        $default = (!empty($this->getSetting('filelist_filesizefilter_from'))) ? $this->getSetting('filelist_filesizefilter_from') : '0';
        $wrapperContent[] = new cHTMLTextbox('filelist_filesizefilter_from_' . $this->_id, $default, '', '', 'filelist_filesizefilter_from_' . $this->_id);
        $wrapperContent[] = new cHTMLSpan('&nbsp;-&nbsp;');
        $default = (!empty($this->getSetting('filelist_filesizefilter_to'))) ? $this->getSetting('filelist_filesizefilter_to') : '0';
        $wrapperContent[] = new cHTMLTextbox('filelist_filesizefilter_to_' . $this->_id, $default, '', '', 'filelist_filesizefilter_to_' . $this->_id);

        $wrapperContent[] = new cHTMLLabel(i18n('Creation date limit'), 'filelist_creationdatefilter_from_' . $this->_id);
        $default = (!empty($this->getSetting('filelist_creationdatefilter_from'))) ? $this->getSetting('filelist_creationdatefilter_from') : $this->_dateFormat;
        $wrapperContent[] = new cHTMLTextbox('filelist_creationdatefilter_from_' . $this->_id, $default, '', '', 'filelist_creationdatefilter_from_' . $this->_id);
        $wrapperContent[] = new cHTMLSpan('&nbsp;-&nbsp;');
        $default = (!empty($this->getSetting('filelist_creationdatefilter_to'))) ? $this->getSetting('filelist_creationdatefilter_to') : $this->_dateFormat;
        $wrapperContent[] = new cHTMLTextbox('filelist_creationdatefilter_to_' . $this->_id, $default, '', '', 'filelist_creationdatefilter_to_' . $this->_id);

        $wrapperContent[] = new cHTMLLabel(i18n('Modify date limit'), 'filelist_modifydatefilter_from_' . $this->_id);
        $default = (!empty($this->getSetting('filelist_modifydatefilter_from'))) ? $this->getSetting('filelist_modifydatefilter_from') : $this->_dateFormat;
        $wrapperContent[] = new cHTMLTextbox('filelist_modifydatefilter_from_' . $this->_id, $default, '', '', 'filelist_modifydatefilter_from_' . $this->_id);
        $wrapperContent[] = new cHTMLSpan('&nbsp;-&nbsp;');
        $default = (!empty($this->getSetting('filelist_modifydatefilter_to'))) ? $this->getSetting('filelist_modifydatefilter_to') : $this->_dateFormat;
        $wrapperContent[] = new cHTMLTextbox('filelist_modifydatefilter_to_' . $this->_id, $default, '', '', 'filelist_modifydatefilter_to_' . $this->_id);

        $wrapperContent[] = new cHTMLLabel(i18n('File count'), 'filelist_filecount_' . $this->_id);
        $default = (!empty($this->getSetting('filelist_filecount'))) ? $this->getSetting('filelist_filecount') : '0';
        $wrapperContent[] = new cHTMLTextbox('filelist_filecount_' . $this->_id, $default, '', '', 'filelist_filecount_' . $this->_id);

        $wrapper->setContent($wrapperContent);

        return $wrapper->render();
    }

    /**
     * Generates a select box containing the file extensions.
     *
     * @return string
     *         rendered cHTMLSelectElement
     */
    private function _generateExtensionSelect() {
        $htmlSelect = new cHTMLSelectElement(
            'filelist_extensions_' . $this->_id, '', 'filelist_extensions_' . $this->_id,
            ($this->getSetting('filelist_ignore_extensions') !== 'false'), '', '', 'manual'
        );

        // set other variable options manually
        foreach ($this->_fileExtensions as $fileExtension) {
            $htmlSelectOption = new cHTMLOptionElement(
                uplGetFileTypeDescription($fileExtension) . ' (.' . $fileExtension . ')', $fileExtension, false
            );
            $htmlSelectOption->setAlt(uplGetFileTypeDescription($fileExtension) . ' (.' . $fileExtension . ')');
            $htmlSelect->appendOptionElement($htmlSelectOption);
        }

        $additionalOptions = getEffectiveSettingsByType('cms_filelist_extensions');
        foreach ($additionalOptions as $label => $extension) {
            $htmlSelectOption = new cHTMLOptionElement($label . ' (.' . $extension . ')', $extension);
            $htmlSelectOption->setAlt($label . ' (.' . $extension . ')');
            $htmlSelect->appendOptionElement($htmlSelectOption);
        }

        // set default values
        $extensions = (is_array($this->getSetting('filelist_extensions'))) ? $this->getSetting('filelist_extensions') : [
            $this->getSetting('filelist_extensions')
        ];
        $htmlSelect->setSelected($extensions);
        $htmlSelect->setMultiselect();
        $htmlSelect->setSize(5);

        return $htmlSelect->render();
    }

    /**
     * Checks whether the directory defined by the given directory
     * information is the currently active directory.
     *
     * @param array $dirData
     *         directory information
     * @return bool
     *         whether the directory is the currently active directory
     */
    protected function _isActiveDirectory(array $dirData) {
        return is_array($this->getSetting('filelist_directories'))
            && in_array($dirData['path'] . $dirData['name'], $this->getSetting('filelist_directories'));
    }

    /**
     * Checks whether the directory defined by the given directory information
     * should be shown expanded.
     *
     * @param array $dirData
     *         directory information
     * @return bool
     *         whether the directory should be shown expanded
     */
    protected function _shouldDirectoryBeExpanded(array $dirData) {
        if (is_array($this->getSetting('filelist_directories'))) {
            foreach ($this->getSetting('filelist_directories') as $directoryName) {
                if (preg_match('#^' . $dirData['path'] . $dirData['name'] . '/.*#', $directoryName)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Generates code for the manual tab.
     *
     * @return string
     *         the code for the manual link tab
     * @throws cInvalidArgumentException|cException
     */
    private function _generateTabManual() {
        // wrapper containing content of manual tab
        $wrapper = new cHTMLDiv();
        $wrapperContent = [];

        $wrapperContent[] = new cHTMLParagraph(i18n('Manual settings'), 'head_sub');

        $wrapperContent[] = new cHTMLLabel(i18n('Use manual file list?'), 'filelist_manual_' . $this->_id);
        $wrapperContent[] = new cHTMLCheckbox(
            'filelist_manual_' . $this->_id, '', 'filelist_manual_' . $this->_id, ($this->getSetting('filelist_manual') === 'true')
        );

        $manualDiv = new cHTMLDiv();
        $manualDiv->setID('manual_filelist_setting');
        $manualDiv->appendStyleDefinition('display', 'none');
        $divContent = [];
        $divContent[] = new cHTMLParagraph(i18n('Existing files'), 'head_sub');
        $divContent[] = $this->_generateExistingFileSelect();
        $divContent[] = new cHTMLSpan(
            i18n('Already configured entries can be deleted by using double click'), 'filelist_manual_' . $this->_id
        );
        $divContent[] = new CHTMLSpan('<br><br>', 'filelist_manual_' . $this->_id);
        $divContent[] = new cHTMLParagraph(i18n('Add file'), 'head_sub');
        $divContent[] = new cHTMLLabel(i18n('Directory'), '');

        // directory navigation
        $directoryList = new cHTMLDiv('', 'directoryList', 'directoryList_' . $this->_id . '_manual');
        $liRoot = new cHTMLListItem('root', 'last');
        $directoryListCode = $this->generateDirectoryList($this->buildDirectoryList());
        $liRoot->setContent([
            '<em>Uploads</em>',
            $directoryListCode
        ]);
        $conStrTree = new cHTMLList('ul', 'con_str_tree', 'con_str_tree', $liRoot);
        $directoryList->setContent($conStrTree);
        $divContent[] = $directoryList;

        $divContent[] = new cHTMLLabel(i18n('File'), 'filelist_filename_' . $this->_id, 'filelist_filename');
        $divContent[] = $this->generateFileSelect();
        $image = new cHTMLImage($this->_cfg['path']['contenido_fullhtml'] . 'images/but_art_new.gif');
        $image->setAttribute('id', 'add_file');
        $image->appendStyleDefinition('cursor', 'pointer');
        $divContent[] = $image;

        $manualDiv->setContent($divContent);
        $wrapperContent[] = $manualDiv;

        $wrapper->setContent($wrapperContent);

        return $wrapper->render();
    }

    /**
     * Generate a select box containing the already existing files in the manual
     * tab.
     *
     * @return string
     *         rendered cHTMLSelectElement
     */
    private function _generateExistingFileSelect() {
        // $tempSelectedFiles = $this->getSetting('filelist_manual_files');
        // Check if manual selected file exists, otherwise ignore them
        // Write only existing files into selectedFiles array
        // if (is_array($tempSelectedFiles)) {
        //     foreach ($tempSelectedFiles as $filename) {
        //         if (cFileHandler::exists($this->_uploadPath . $filename)) {
        //             $selectedFiles[] = $filename;
        //         }
        //     }
        // }

        // If we have wasted selected files, update settings
        // if (count($tempSelectedFiles) != count($selectedFiles)) {
        //     $this->getSetting('filelist_manual_files') = $selectedFiles;
        //     $this->_storeSettings();
        // }

        $htmlSelect = new cHTMLSelectElement(
            'filelist_manual_files_' . $this->_id, '', 'filelist_manual_files_' . $this->_id, false, null, '', 'manual'
        );

        if (is_array($this->getSetting('filelist_manual_files'))) { // More than one entry
            foreach ($this->getSetting('filelist_manual_files') as $selectedFile) {
                $splits = explode('/', $selectedFile);
                $splitCount = count($splits);
                $fileName = $splits[$splitCount - 1];
                $htmlSelectOption = new cHTMLOptionElement($fileName, $selectedFile, true);
                $htmlSelectOption->setAlt($fileName);
                $htmlSelect->appendOptionElement($htmlSelectOption);
            }
        } elseif (!empty($this->getSetting('filelist_manual_files'))) { // Only one entry
            $splits = explode('/', $this->getSetting('filelist_manual_files'));
            $splitCount = count($splits);
            $fileName = $splits[$splitCount - 1];
            $htmlSelectOption = new cHTMLOptionElement($fileName, $this->getSetting('filelist_manual_files'), true);
            $htmlSelectOption->setAlt($fileName);
            $htmlSelect->appendOptionElement($htmlSelectOption);
        }

        // set default values
        $htmlSelect->setMultiselect();
        $htmlSelect->setSize(5);

        return $htmlSelect->render();
    }

    /**
     * Generate a select box containing all files for the manual tab.
     *
     * @SuppressWarnings docBlocks
     * @param string $directoryPath [optional]
     *         Path to directory of the files
     * @return string
     *         rendered cHTMLSelectElement
     */
    public function generateFileSelect($directoryPath = '') {
        $htmlSelect = new cHTMLSelectElement(
            'filelist_filename_' . $this->_id, '', 'filelist_filename_' . $this->_id,
            false, null, '', 'filelist_filename'
        );

        $files = [];
        if ($directoryPath != '') {
            $handle = cDirHandler::read($this->_uploadPath . $directoryPath);
            if (false !== $handle) {
                foreach ($handle as $entry) {
                    if (cFileHandler::isFile($this->_uploadPath . $directoryPath . '/' . $entry)) {
                        $file = [];
                        $file['name'] = $entry;
                        $file['path'] = $directoryPath . '/' . $entry;
                        $files[] = $file;
                    }
                }
            }
        }

        usort($files, function($a, $b) {
            $a = cString::toLowerCase($a['name']);
            $b = cString::toLowerCase($b['name']);
            if ($a < $b) {
                return -1;
            } elseif ($a > $b) {
                return 1;
            } else {
                return 0;
            }
        });

        $i = 0;
        foreach ($files as $file) {
            $htmlSelectOption = new cHTMLOptionElement($file['name'], $file['path']);
            $htmlSelect->addOptionElement($i, $htmlSelectOption);
            $i++;
        }

        if ($i === 0) {
            $htmlSelectOption = new cHTMLOptionElement(i18n('No files found'), '');
            $htmlSelectOption->setAlt(i18n('No files found'));
            $htmlSelectOption->setDisabled(true);
            $htmlSelect->addOptionElement($i, $htmlSelectOption);
            $htmlSelect->setDisabled(true);
            $htmlSelect->setDefault('');
        }

        return $htmlSelect->render();
    }

    /**
     * Generates a directory list from the given directory information (which is
     * typically built by {@link cContentTypeAbstract::buildDirectoryList}).
     * Special modified for Ajax call
     *
     * @param array $dirs
     *         directory information
     *
     * @return string
     *         HTML code showing a directory list
     * @throws cInvalidArgumentException
     */
    public function generateAjaxDirectoryList(array $dirs) {
        $template = new cTemplate();
        $i = 1;

        foreach ($dirs as $dirData) {
            // set the active class if this is the chosen directory
            $divClass = ($this->_isActiveDirectory($dirData)) ? 'active' : '';
            $template->set('d', 'DIVCLASS', $divClass);

            $template->set('d', 'TITLE', $dirData['path'] . $dirData['name']);
            $template->set('d', 'DIRNAME', $dirData['name']);

            $liClasses = [];
            $template->set('d', 'SUBDIRLIST', $this->generateAjaxDirectoryList($dirData['sub']));

            if ($i === count($dirs)) {
                $liClasses[] = 'last';
            }
            $template->set('d', 'LICLASS', implode(' ', $liClasses));

            $i++;
            $template->next();
        }

        return $template->generate(
            $this->_cfg['path']['contenido'] . 'templates/standard/template.cms_filelist_dirlistitem.html',
            true
        );
    }

}
