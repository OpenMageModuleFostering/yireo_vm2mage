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

class Yireo_Vm2Mage_Model_Category_Api extends Mage_Catalog_Model_Category_Api
{
    /**
     * Update a category using a basic array of values
     *
     * @param array $data
     * @return array
     */
    public function migrate($data= null)
    {
        Mage::helper('vm2mage')->init();

        // Check for empty data
        if(!is_array($data)) {
            return array(0, "Data is not an array");
        }

        // Decode all values
        $data = Mage::helper('vm2mage')->decode($data);
        Mage::helper('vm2mage')->debug('VirtueMart category', $data);

        // Get a clean category-object
        $category = Mage::getModel('catalog/category');

        // Try to load the category 
        $categoryId = Mage::helper('vm2mage/category')->getMageId($data['id'], $data['migration_code']);
        if(!empty($categoryId)) {
            $category->load($categoryId);
        }

        // Intialize the parentId
        $parentId = 0;

        // Get the parent by taking it from VirtueMart
        if(isset($data['parent_id']) && $data['parent_id'] > 0) {
            $parentId = Mage::helper('vm2mage/category')->getMageId($data['parent_id'], $data['migration_code']);
            $parentCategory = Mage::getModel('catalog/category')->load($parentId);
            if(!$parentCategory->getId() > 0) {
                Mage::helper('vm2mage/category')->removeRelation($data['parent_id'], $data['migration_code']);
            }
        }

        // Take the parent from Magento
        $storeId = (isset($data['store_id'])) ? $data['store_id'] : 0;
        if($parentId == 0 && !empty($storeId)) {
            $parentId = Mage::app()->getStore($storeId)->getRootCategoryId();
        }

        // Load the default
        if($parentId == 0) {
            $parentId = Mage::app()->getAnyStoreView()->getRootCategoryId();
        }

        // Load the parent category
        $parentCategory = Mage::getModel('catalog/category')->load($parentId);
        if($parentCategory->getId() == 0) {
            $parentId = Mage::app()->getAnyStoreView()->getRootCategoryId();
            $parentCategory = Mage::getModel('catalog/category')->load($parentId);
        }

        // Detect whether this is a new category or not
        if(!$category->getName()) {
            $isNew = true;
            $category->setData($category->getData())
                ->setId(null)
                ->setCreatedAt(null)
                ->setUpdatedAt(null)
                ->setStoreId($storeId)
                ->setParentId($parentId) 
                ->setPath($parentCategory->getPath())
                ->setAttributeSetId($category->getDefaultAttributeSetId())
            ;

        } else {
            $isNew = false;
            $category->setData($category->getData())
                ->setParentId($parentId)
                ->setLevel($parentCategory->getLevel() + 1)
                ->setPath($parentCategory->getPath().'/'.$category->getId())
            ;
        }

        $state = (isset($data['status'])) ? $data['status'] : $data['published'];

        // Set common attributes
        $category->setData($category->getData())
            ->setName($data['name'])
            ->setDescription($data['description'])
            ->setIsActive($state)
        ;

        // Assign all the products properly to this category
        if(!empty($data['products'])) {
            $positions = $category->getProductsPosition();
            $product = Mage::getModel('catalog/product');
            foreach($data['products'] as $productData) {
                $productSku = $productData['sku'];
                $productOrdering = $productData['ordering'];
                $productId = (int)$product->getIdBySku($productSku); 
                if($productId > 0) $positions[$productId] = $productOrdering;
            }
            $category->setPostedProducts($positions);
        }

        // @todo: Get the remote images

        // Try to safe this category to the database
        try {
            $category->save();
        } catch(Exception $e) {
            return array(0, $e->getMessage());
        }

        // Move this category
        if(!in_array($parentId, array(0, $category->getParentId(), $category->getId()))) {
            try {
                $category->move($category->getId(), $parentId);
            } catch(Exception $e) {
                // Do nothing
            }
        }

        // Save this category-relation within Vm2Mage
        Mage::helper('vm2mage/category')->saveRelation($data['id'], $category->getId(), $data['migration_code']);

        // Return true by default
        if($isNew) {
            return array(1, "Created new category ".$category->getName(), $data['id']);
        } else {
            return array(1, "Updated category ".$category->getName(), $data['id']);
        }
    }

    /**
     * Retrieve list of categorys with basic info (id, sku, type, set, name)
     *
     * @param array $filters
     * @param string $store
     * @return array
     */
    public function items($filters = null, $store = null)
    {
        $collection = Mage::getModel('catalog/category')->getCollection()
            ->setStoreId($this->_getStoreId($store))
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('url_key')
            ->addAttributeToSelect('is_active')
        ;

        /*
         * Filter the results
         */
        if (is_array($filters)) {
            try {
                foreach ($filters as $field => $value) {
                    if (isset($this->_filtersMap[$field])) {
                        $field = $this->_filtersMap[$field];
                    }

                    $collection->addFieldToFilter($field, $value);
                }
            } catch (Mage_Core_Exception $e) {
                $this->_fault('filters_invalid', $e->getMessage());
            }
        }

        $result = array();

        foreach ($collection as $category) {
            $result[] = array( // Basic category data
                'category_id'    => $category->getId(),
                'name'          => $category->getName(),
                'is_active'     => 1,
                'description'   => $category->getDescription(),
                'url_key'       => $category->getUrlKey(),
                'url'           => $category->getCategoryUrl(false),
            );
        }

        return $result;
    }
}
