<?php
/**
 * Yireo Vm2Mage for Magento 
 *
 * @author Yireo
 * @package Vm2Mage
 * @copyright Copyright 2014
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
     * @param string $migration_code
     * @return int
     */
    public function getMageId($vm_id, $migration_code = null)
    {
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $table = Mage::getSingleton('core/resource')->getTableName('vm2mage_categories');
        if(!empty($migration_code)) {
            $mage_id = $db->fetchOne( "SELECT `mage_id` FROM `$table` WHERE `vm_id` = '$vm_id' AND `migration_code` = '$migration_code'" );
        } else {
            $mage_id = $db->fetchOne( "SELECT `mage_id` FROM `$table` WHERE `vm_id` = '$vm_id'");
        }
        return $mage_id;
    }

    /*
     * Method to save the relation between a Magento ID and a VirtueMart ID
     *
     * @param int $vm_id
     * @param int $mage_id
     * @param string $migration_code
     * @return bool
     */
    public function saveRelation($vm_id = 0, $mage_id = 0, $migration_code = null)
    {
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $table = Mage::getSingleton('core/resource')->getTableName('vm2mage_categories');
        $query = "SELECT `mage_id` FROM `$table` WHERE `vm_id` = '$vm_id' AND `migration_code` = '$migration_code'";
        $result = $db->fetchOne($query);

        if(!empty($result)) {
            $query = "UPDATE `$table` SET `mage_id`='$mage_id' WHERE `vm_id`='$vm_id' AND `migration_code` = '$migration_code'";
        } else {
            $query = "INSERT INTO `$table` SET `vm_id` = '$vm_id', `mage_id`='$mage_id', `migration_code` = '$migration_code'";
        }
        
        $db->query($query);
        return true;
    }

    /*
     * Method to remove the relation between a Magento ID and a VirtueMart ID
     *
     * @param int $vm_id
     * @param string $migration_code
     * @return bool
     */
    public function removeRelation($vm_id = 0, $migration_code = null)
    {
        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $table = Mage::getSingleton('core/resource')->getTableName('vm2mage_categories');
        $query = "DELETE FROM `$table` WHERE `vm_id` = '$vm_id' AND `migration_code` = '$migration_code'";
        $db->query($query);
        return true;
    }
}

