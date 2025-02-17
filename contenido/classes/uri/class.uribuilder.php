<?php

/**
 * This file contains the abstract uri builder class.
 *
 * @package    Core
 * @subpackage Frontend_URI
 * @author     Rudi Bieller
 * @copyright  four for business AG <www.4fb.de>
 * @license    https://www.contenido.org/license/LIZENZ.txt
 * @link       https://www.4fb.de
 * @link       https://www.contenido.org
 */

defined('CON_FRAMEWORK') || die('Illegal call: Missing framework initialization - request aborted.');

/**
 * URI builder class.
 *
 * @package    Core
 * @subpackage Frontend_URI
 */
abstract class cUriBuilder {

    /**
     * Holds final value of built URL.
     *
     * @var string
     */
    protected $sUrl; // needed in this context

    /**
     * Holds URL that is used as base for an absolute path
     * E.g. https://contenido.org/
     *
     * @var string
     */
    protected $sHttpBasePath; // needed in this context

    /**
     * Implementation of Singleton.
     *
     * It is meant to be an abstract function but not declared as abstract,
     * because PHP Strict Standards are against abstract static functions.
     *
     * @throws cBadMethodCallException
     *         if child class has not implemented this function
     */

    public static function getInstance() {
        throw new cBadMethodCallException("Child class has to implement this function");
    }

    /**
     * Set http base path.
     * E.g. https://contenido.org/
     *
     * @param string $sBasePath
     */
    public function setHttpBasePath($sBasePath) {
        $this->sHttpBasePath = (string) $sBasePath;
    }

    /**
     * Return http base path.
     * E.g. https://contenido.org/
     *
     * @return string
     */
    public function getHttpBasePath() {
        return $this->sHttpBasePath;
    }

    /**
     * Builds a URL in index-a-1.html style.
     *
     * Index keys of $aParams will be used as "a", corresponding values
     * as "1" in this sample.
     *
     * @param array $aParams
     * @param bool $bUseAbsolutePath [optional]
     * @throws cInvalidArgumentException
     */
    abstract public function buildUrl(array $aParams, $bUseAbsolutePath = false);

    /**
     * Return built URL.
     *
     * @return string
     */
    public function getUrl() {
        return (string) $this->sUrl;
    }

}
