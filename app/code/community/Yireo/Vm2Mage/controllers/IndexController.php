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

    /**
     * Delete all categories
     *
     * @access public
     * @param null
     * @return null
     */
    public function deleteCategoriesAction()
    {
        Mage::getSingleton('index/indexer')->lockIndexer();

        $categories = Mage::getModel('catalog/category')->getCollection();
        foreach($categories as $category) {
            if($category->getParentId() == 0) continue;
            if($category->getLevel() <= 1) continue;
            $category->delete();
        }

        $resource = Mage::getSingleton('core/resource');
        $writeConnection = $resource->getConnection('core_write');
        $tableName = $resource->getTableName('vm2mage_categories');
        $query = 'TRUNCATE TABLE '.$tableName;
        $writeConnection->query($query);

        Mage::getSingleton('index/indexer')->unlockIndexer();

        Mage::getModel('adminhtml/session')->addSuccess('Categories deleted');
        $url = Mage::getModel('adminhtml/url')->getUrl('vm2mage/index/index');
        $this->getResponse()->setRedirect($url);
    }

    /**
     * Delete all products
     *
     * @access public
     * @param null
     * @return null
     */
    public function deleteProductsAction()
    {
        Mage::getSingleton('index/indexer')->lockIndexer();

        $products = Mage::getModel('catalog/product')->getCollection();
        foreach($products as $product) {
            $product->setIsMassupdate(true);
            $product->setExcludeUrlRewrite(true);
            $product->delete();
        }

        Mage::getSingleton('index/indexer')->unlockIndexer();

        // @todo: Generate notice to reindex all indices
        // @todo: Flush cache

        Mage::getModel('adminhtml/session')->addSuccess('Products deleted');
        $url = Mage::getModel('adminhtml/url')->getUrl('vm2mage/index/index');
        $this->getResponse()->setRedirect($url);
    }
}
