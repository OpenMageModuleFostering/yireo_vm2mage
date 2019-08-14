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

class Yireo_Vm2Mage_Model_Widget_Api extends Mage_Api_Model_Resource_Abstract
{
    /**
     * Get a list of stores
     *
     * @access public
     * @param null
     * @return array
     */
    public function getStores()
    {
        $views = Mage::getModel('core/store')->getCollection();

        $res = array();
        foreach ($views as $item) {
            $data['value'] = $item->getData('code');
            $data['label'] = $item->getData('name');
            $res[] = $data;
        }
        return $res;
    }

    /**
     * Get a list of product tax classes
     *
     * @access public
     * @param null
     * @return array
     */
    public function getProductTaxClasses()
    {
        return Mage::getModel('tax/class_source_product')->getAllOptions();
    }

    /**
     * Get a list of customers groups
     *
     * @access public
     * @param null
     * @return array
     */
    public function getCustomerGroups()
    {
        $groups = Mage::getModel('customer/group')->getCollection();

        $res = array();
        foreach ($groups as $item) {
            $data['value'] = $item->getData('customer_group_id');
            $data['label'] = $item->getData('customer_group_code');
            $res[] = $data;
        }
        return $res;
    }
}
