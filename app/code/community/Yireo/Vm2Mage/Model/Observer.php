<?php
/**
 * Vm2Mage
 *
 * @author Yireo
 * @package Vm2Mage
 * @copyright Copyright 2014
 * @license Open Source License
 * @link http://www.yireo.com
 */

class Yireo_Vm2Mage_Model_Observer 
{
    /**
     * Event "catalog_category_delete_after" - after a specific category has been deleted
     */
    public function catalogCategoryDeleteAfter($observer)
    {
        // Get objects from the event
        $category = $observer->getEvent()->getCategory();

        $db = Mage::getSingleton('core/resource')->getConnection('core_write');
        $table = Mage::getSingleton('core/resource')->getTableName('vm2mage_categories');

        $db->query("DELETE FROM `$table` WHERE `mage_id`=".(int)$category->getId());
    }
}
