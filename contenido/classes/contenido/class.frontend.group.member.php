<?php

/**
 * This file contains the frontend group memeber collection and item class.
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
 * Frontend group member collection
 *
 * @package    Core
 * @subpackage GenericDB_Model
 * @method cApiFrontendGroupMember createNewItem
 * @method cApiFrontendGroupMember|bool next
 */
class cApiFrontendGroupMemberCollection extends ItemCollection {
    /**
     * Constructor to create an instance of this class.
     *
     * @throws cInvalidArgumentException
     */
    public function __construct() {
        parent::__construct(cRegistry::getDbTableName('frontendgroupmembers'), 'idfrontendgroupmember');
        $this->_setItemClass('cApiFrontendGroupMember');

        // set the join partners so that joins can be used via link() method
        $this->_setJoinPartner('cApiFrontendGroupCollection');
        $this->_setJoinPartner('cApiFrontendUserCollection');
    }

    /**
     * Creates a new association
     *
     * @todo Should return null in case of failure
     *
     * @param int $idfrontendgroup
     *         specifies the frontend group
     * @param int $idfrontenduser
     *         specifies the frontend user
     *
     * @return cApiFrontendGroupMember|false
     * @throws cDbException
     * @throws cException
     * @throws cInvalidArgumentException
     */
    public function create($idfrontendgroup, $idfrontenduser) {
        $this->select('idfrontendgroup = ' . (int) $idfrontendgroup . ' AND idfrontenduser = ' . (int) $idfrontenduser);

        if ($this->next()) {
            return false;
        }

        $item = $this->createNewItem();

        $item->set('idfrontenduser', $idfrontenduser);
        $item->set('idfrontendgroup', $idfrontendgroup);
        $item->store();

        return $item;
    }

    /**
     * Removes an association
     *
     * @param int $idfrontendgroup
     *         Specifies the frontend group
     * @param int $idfrontenduser
     *         Specifies the frontend user
     *
     * @throws cDbException
     * @throws cException
     * @throws cInvalidArgumentException
     */
    public function remove($idfrontendgroup, $idfrontenduser) {
        $this->select('idfrontendgroup = ' . (int) $idfrontendgroup . ' AND idfrontenduser = ' . (int) $idfrontenduser);

        if (($item = $this->next()) !== false) {
            $this->delete($item->get('idfrontendgroupmember'));
        }
    }

    /**
     * Returns all users in a single group
     *
     * @param int  $idfrontendgroup
     *                        specifies the frontend group
     * @param bool $asObjects [optional]
     *                        Specifies if the function should return objects
     * @return array
     *                        List of frontend user ids or cApiFrontendUser items
     *
     * @throws cDbException
     * @throws cException
     */
    public function getUsersInGroup($idfrontendgroup, $asObjects = true) {
        $this->select('idfrontendgroup = ' . (int) $idfrontendgroup);

        $objects = [];

        while (($item = $this->next()) !== false) {
            if ($asObjects) {
                $user = new cApiFrontendUser();
                $user->loadByPrimaryKey($item->get('idfrontenduser'));
                $objects[] = $user;
            } else {
                $objects[] = $item->get('idfrontenduser');
            }
        }

        return $objects;
    }
}

/**
 * Frontend group member item
 *
 * @package    Core
 * @subpackage GenericDB_Model
 */
class cApiFrontendGroupMember extends Item
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
        parent::__construct(cRegistry::getDbTableName('frontendgroupmembers'), 'idfrontendgroupmember');
        if ($mId !== false) {
            $this->loadByPrimaryKey($mId);
        }
    }
}
