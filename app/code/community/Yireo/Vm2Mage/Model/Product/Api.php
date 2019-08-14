<?php
/**
 * Vm2Mage
 *
 * @author Yireo
 * @package Vm2Mage
 * @copyright Copyright 2011
 * @license Open Source License
 * @link http://www.yireo.com
 */

class Yireo_Vm2Mage_Model_Product_Api extends Mage_Catalog_Model_Product_Api
{
    /**
     * Update a product using a basic array of values
     *
     * @param array $data
     * @return array
     */
    public function migrate($data = null)
    {
        // Check for empty data
        if(!is_array($data)) {
            #Mage::helper('vm2mage')->debug('VirtueMart product', $data);
            return array(0, "Data is not an array");
        }

        // Decode all values
        $data = Mage::helper('vm2mage')->decode($data);
        // @todo: Option to enable debugging
        #Mage::helper('vm2mage')->debug('VirtueMart product', $data);

        // @todo: Optionally lock the indexer
        //Mage::getSingleton('index/indexer')->lockIndexer();

        // Get a clean product-object
        $product = Mage::getModel('catalog/product');

        // Try to get the productId by looking for the SKU
        $sku = $data['sku'];
        $productId = Mage::getModel('catalog/product')->getIdBySku($sku);
        $storeId = (isset($data['store_id'])) ? $data['store_id'] : 1;
        $taxId = (isset($data['product_tax_id'])) ? $data['product_tax_id'] : 0;

        // Load the product by its productId
        if($productId > 0) {
            $isNew = false;
            $product->load($productId);

        // The SKU and/or ID can not be found, so this is a new product
        } else {
            $isNew = true;
            $product->setData($product->getData())
                ->setId(null)
                ->setCreatedAt(null)
                ->setUpdatedAt(null)
                ->setStoreId($storeId)
                ->setAttributeSetId($product->getDefaultAttributeSetId())
            ;

            if($data['has_children'] > 0) {
                $product->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE);
            } else {
                $product->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE);
            }
        }

        // Make sure the status is set
        if(!isset($data['status'])) {
            $data['status'] = 1;
        }
            $data['status'] = 1;

        // Make sure there is a short-description
        if(empty($data['short_description'])) {
            $data['short_description'] = $data['name'];
        }

        // Set common attributes
        $product->setData($product->getData())
            ->setStoreId($storeId)
            ->setWebsiteIds(array(Mage::getModel('core/store')->load($storeId)->getWebsiteId()))
            ->setSku($data['sku'])
            ->setName($data['name'])
            ->setDescription($data['description'])
            ->setShortDescription($data['short_description'])
            ->setStatus($data['status'])
            ->setTaxClassId($taxId)
        ;

        // Set weight
        if(isset($data['weight'])) {
            $product->setWeight($data['weight']);
        }

        // Set visibility
        if(isset($data['visibility']) && $data['visibility'] == 'none') {
            $product->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE);
        } else {
            $product->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH);
        }

        // Set Meta-information
        if(!empty($data['metadesc'])) {
            $product->setMetaDescription(htmlspecialchars($data['metadesc']));
        } else {
            $product->setMetaDescription(strip_tags($product->getDescription()));
        }

        if(!empty($data['metakey'])) {
            $product->setMetaKeyword(htmlspecialchars($data['metakey']));
        }

        if(!empty($data['metatitle'])) {
            $product->setMetaTitle(htmlspecialchars($data['metatitle']));
        } else {
            $product->setMetaTitle($product->getTitle());
        }

        // Set the custom attributes
        if(isset($data['attributes'])) {
            foreach($data['attributes'] as $name => $value) {
                #Mage::helper('vm2mage')->debug('Product attribute', $name);
                $product = Mage::helper('vm2mage/attribute')->addAttributeToProduct($product, $name, $value);
            }
        }

        // Set the price
        // @todo: Include support for tier pricing
        if(isset($data['price']['product_price'])) {
            $product->setPrice($data['price']['product_price']);
        }

        // Set the special price
        if(isset($data['special_price']['price'])) {
            $product->setSpecialPrice($data['special_price']['price']);
        }

        // Set the special price from-date
        if(isset($data['special_price']['start_date'])) {
            $product->setSpecialFromDate($data['special_price']['start_date']);
        }

        // Set the special price from-date
        if(isset($data['special_price']['end_date'])) {
            $product->setSpecialToDate($data['special_price']['end_date']);
        }

        // Handle the stock
        if(isset($data['in_stock']) && $data['in_stock'] > 0) {
            $stockData = $product->getStockData();
            #Mage::helper('vm2mage')->debug('VirtueMart product stock-data', $stockData);
            $stockData['qty'] = $data['in_stock'];
            $stockData['is_in_stock'] = 1;
            $stockData['manage_stock'] = 1;
            $stockData['use_config_manage_stock'] = 0;
            $product->setStockData($stockData);
        }

        // Convert the category-IDs
        if(!empty($data['category_ids'])) {
            $category_ids = array();
            foreach($data['category_ids'] as $vm_id) {
                $id = Mage::helper('vm2mage/category')->getMageId($vm_id);
                if($id > 0) $category_ids[] = $id;
            }

            if(!empty($category_ids)) {
                $product->setCategoryIds($category_ids);
            }
        }

        // Set the mass-update flags to prevent reindexing for now
        $product->setIsMassupdate(true);
        $product->setExcludeUrlRewrite(true);
            
        // Get the remote images
        if(isset($data['images'])) {
            try {
                $product = Mage::helper('vm2mage/image')->addImages($product, $data['images']);
            } catch(Exception $e) {
                return array(0, $e->getMessage());
            }
        }

        // Try to save this product to the database
        try {
            $request = Mage::app()->getFrontController()->getRequest();
            Mage::dispatchEvent('catalog_product_prepare_save', array('product' => $product, 'request' => $request));
            $product->save();
        } catch(Exception $e) {
            return array(0, $e->getMessage());
        }

        // Configure this product as configurable product
        if($data['has_children'] > 0) {
            $rs = Mage::helper('vm2mage/product')->addChildrenToConfigurable($data['children'], $data['attributes_sku'], $product);
            if(!empty($rs)) {
                return $rs;
            }
        }

        // Return true by default
        if($isNew) {
            return array(1, "Created new product ".$product->getName()." [".$product->getId()."]", $data['id']);
        } else {
            return array(1, "Updated product ".$product->getName()." [".$product->getId()."]", $data['id']);
        }
    }
}
