<?php

/**
 * This file contains the stat collection and item class.
 *
 * @package    Core
 * @subpackage GenericDB_Model
 * @author     Murat Purc <murat@purc.de>
 * @copyright  four for business AG <www.4fb.de>
 * @license    https://www.contenido.org/license/LIZENZ.txt
 * @link       https://www.4fb.de
 * @link       https://www.contenido.org
 */

defined('CON_FRAMEWORK') || die('Illegal call: Missing framework initialization - request aborted.');

/**
 * Statistic collection
 *
 * @package    Core
 * @subpackage GenericDB_Model
 * @method cApiStat createNewItem
 * @method cApiStat|bool next
 */
class cApiStatCollection extends ItemCollection {
    /**
     * Constructor to create an instance of this class.
     *
     * @throws cInvalidArgumentException
     */
    public function __construct() {
        parent::__construct(cRegistry::getDbTableName('stat'), 'idstat');
        $this->_setItemClass('cApiStat');

        // set the join partners so that joins can be used via link() method
        $this->_setJoinPartner('cApiCategoryArticleCollection');
        $this->_setJoinPartner('cApiLanguageCollection');
        $this->_setJoinPartner('cApiClientCollection');
    }

    /**
     * Tracks a visit.
     * Increments an existing entry or creates a new one.
     *
     * @param int $iIdCatArt
     * @param int $iIdLang
     * @param int $iIdClient
     *
     * @throws cDbException
     * @throws cException
     * @throws cInvalidArgumentException
     */
    public function trackVisit($iIdCatArt, $iIdLang, $iIdClient) {
        $oStat = $this->fetchByCatArtAndLang($iIdCatArt, $iIdLang);
        if (is_object($oStat)) {
            $oStat->increment();
        } else {
            $this->create($iIdCatArt, $iIdLang, $iIdClient);
        }
    }

    /**
     * Creates a stat entry.
     *
     * @param int $iIdCatArt
     * @param int $iIdLang
     * @param int $iIdClient
     * @param int $iVisited [optional]
     *
     * @return cApiStat
     * @throws cDbException
     * @throws cException
     * @throws cInvalidArgumentException
     */
    public function create($iIdCatArt, $iIdLang, $iIdClient, $iVisited = 1) {
        $oItem = $this->createNewItem();

        $oItem->set('visited', $iVisited);
        $oItem->set('idcatart', $iIdCatArt);
        $oItem->set('idlang', $iIdLang);
        $oItem->set('idclient', $iIdClient);
        $oItem->store();

        return $oItem;
    }

    /**
     * Returns a stat entry by category article and language.
     *
     * @param int $iIdCatArt
     * @param int $iIdLang
     * @return cApiStat|NULL
     * @throws cDbException
     * @throws cException
     */
    public function fetchByCatArtAndLang($iIdCatArt, $iIdLang) {
        $where = $this->db->prepare('idcatart = %d AND idlang = %d', $iIdCatArt, $iIdLang);
        $this->select($where);
        return $this->next();
    }

    /**
     * Deletes statistics entries by category article id and language id.
     *
     * @param int $idcatart
     * @param int $idlang
     * @return int
     *         Number of deleted items
     * @throws cDbException
     * @throws cInvalidArgumentException
     */
    public function deleteByCategoryArticleAndLanguage($idcatart, $idlang) {
        $where = $this->db->prepare('idcatart = %d AND idlang = %d', $idcatart, $idlang);
        return $this->deleteByWhereClause($where);
    }
}

/**
 * Statistic item
 *
 * @package    Core
 * @subpackage GenericDB_Model
 */
class cApiStat extends Item
{
    /**
     * Constructor to create an instance of this class.
     *
     * @param mixed $mId [optional]
     *                   Specifies the ID of item to load
     *
     * @throws cDbException
     * @throws cException
     */
    public function __construct($mId = false) {
        parent::__construct(cRegistry::getDbTableName('stat'), 'idstat');
        $this->setFilters([], []);
        if ($mId !== false) {
            $this->loadByPrimaryKey($mId);
        }
    }

    /**
     * Increment and store property 'visited'.
     *
     * @throws cDbException
     * @throws cInvalidArgumentException
     */
    public function increment() {
        $this->set('visited', $this->get('visited') + 1);
        $this->store();
    }

    /**
     * User-defined setter for stat fields.
     *
     * @param string $name
     * @param mixed $value
     * @param bool $bSafe [optional]
     *         Flag to run defined inFilter on passed value
     * @return bool
     */
    public function setField($name, $value, $bSafe = true) {
        switch ($name) {
            case 'idcatart':
            case 'idlang':
            case 'idclient':
            case 'visited':
                $value = cSecurity::toInteger($value);
                break;
        }

        return parent::setField($name, $value, $bSafe);
    }

}
