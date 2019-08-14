<?php
/**
 * Vm2Mage
 *
 * @author Yireo
 * @package Vm2Mage
 * @copyright Copyright 2013
 * @license Open Source License
 * @link http://www.yireo.com
 */

/**
 * Vm2Mage admin controller
 */
class Yireo_Vm2Mage_IndexController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Common method to initialize each action
     *
     * @access protected
     * @param null
     * @return $this
     */
    protected function _initAction()
    {
        // Load the layout
        $this->loadLayout()
            ->_setActiveMenu('system/tools/vm2mage')
            ->_addBreadcrumb(Mage::helper('adminhtml')->__('System'), Mage::helper('adminhtml')->__('System'))
            ->_addBreadcrumb(Mage::helper('adminhtml')->__('Tools'), Mage::helper('adminhtml')->__('Tools'))
            ->_addBreadcrumb(Mage::helper('adminhtml')->__('Vm2Mage System Check'), Mage::helper('adminhtml')->__('Vm2Mage System Check'))
        ;

        return $this;
    }

    /**
     * System Check page 
     *
     * @access public
     * @param null
     * @return null
     */
    public function indexAction()
    {
        $this->_initAction()
            ->_addContent($this->getLayout()->createBlock('vm2mage/check'))
            ->renderLayout();
    }
}
