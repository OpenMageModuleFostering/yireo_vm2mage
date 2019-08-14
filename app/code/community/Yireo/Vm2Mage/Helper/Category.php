<?php
/**
 * Yireo Vm2Mage for Magento 
 *
 * @author Yireo
 * @package Vm2Mage
 * @copyright Copyright 2011
 * @license Open Source License
 * @link http://www.yireo.com
 */

/**
 * Vm2Mage helper
 */
class Yireo_Vm2Mage_Helper_Category extends Yireo_Vm2Mage_Helper_Data
{
    /*
     * Method to get the Magento ID belonging to a specific VirtueMart ID
     *
     * @param int $vm_id
     * @return int
     */
    public function getMageId($vm_id)
    {
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $table = Mage::getSingleton('core/resource')->getTableName('vm2mage_categories');
        $mage_id = $db->fetchOne( "SELECT `mage_id` FROM `$table` WHERE `vm_id` = '$vm_id'" );
        return $mage_id;
    }

    /*
     * Method to save the relation between a Magento ID and a VirtueMart ID
     *
     * @param int $vm_id
     * @param int $mage_id
     * @return bool
     */
    public function saveRelation($vm_id = 0, $mage_id = 0)
    {
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $table = Mage::getSingleton('core/resource')->getTableName('vm2mage_categories');
        $result = $db->fetchOne( "SELECT `mage_id` FROM `$table` WHERE `vm_id` = '$vm_id'" );

        if($result) {
            $query = "UPDATE `$table` SET `mage_id`='$mage_id' WHERE `vm_id`='$vm_id'";
        } else {
            $query = "INSERT INTO `$table` SET `vm_id` = '$vm_id', `mage_id`='$mage_id'";
        }
        $db->query($query);
        return true;
    }
}

