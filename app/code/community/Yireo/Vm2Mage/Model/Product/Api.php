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
        Mage::helper('vm2mage')->init();

        // Check for empty data
        if(!is_array($data)) {
            //Mage::helper('vm2mage')->debug('VirtueMart product', $data);
            return array(0, 'Data is not an array');
        }

        // Decode all values
        $data = Mage::helper('vm2mage')->decode($data);
        //Mage::helper('vm2mage')->debug('VirtueMart product', $data);

        // Determine the product-type
        if(isset($data['has_children']) && $data['has_children'] > 0 && !empty($data['attributes_sku'])) {
            $typeId = Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE;
        } elseif(isset($data['has_children']) && $data['has_children'] > 0) {
            $typeId = Mage_Catalog_Model_Product_Type::TYPE_GROUPED;
        } else {
            $typeId = Mage_Catalog_Model_Product_Type::TYPE_SIMPLE;
        }

        // Determine the children of this product already exist
        if(isset($data['has_children']) && $data['has_children'] > 0) {
            foreach($data['children'] as $child) {
                $childId = Mage::getModel('catalog/product')->getIdBySku($child['sku']);
                if($childId > 0) {
                    return array(0, 'Child product has not been created yet. Skipping parent.');
                }
            }
        }

        // Optionally lock the indexer
        Mage::getSingleton('index/indexer')->lockIndexer();

        // Get a clean product-object
        $product = Mage::getModel('catalog/product');

        // Try to get the productId by looking for the SKU
        $sku = $data['sku'];
        $productId = Mage::getModel('catalog/product')->getIdBySku($sku);
        $storeId = Mage::helper('vm2mage')->getStoreId($data);
        $taxId = (isset($data['product_tax_id'])) ? $data['product_tax_id'] : 0;

        // Only set this store if its not the default Store View
        if (Mage::app()->isSingleStoreMode() == false) {
            if($storeId != Mage::helper('vm2mage')->getDefaultStoreId($storeId)) {
                $product->setStoreId($storeId);
            }
        }

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
                ->setAttributeSetId($product->getDefaultAttributeSetId())
            ;

            // Set the proper type-ID
            $product->setTypeId($typeId);
        }

        // Make sure the status is set
        if(!isset($data['status'])) {
            $data['status'] = Mage_Catalog_Model_Product_Status::STATUS_ENABLED;
        } elseif($data['status'] == 0) {
            $data['status'] = Mage_Catalog_Model_Product_Status::STATUS_DISABLED;
        }

        // Make sure descriptions match
        if(empty($data['short_description'])) $data['short_description'] = $data['name'];
        if(empty($data['description'])) $data['description'] = $data['short_description'];

        // Set common attributes
        $product->setData($product->getData())
            ->setWebsiteIds(array(Mage::getModel('core/store')->load($storeId)->getWebsiteId()))
            ->setSku($data['sku'])
            ->setName($data['name'])
            ->setDescription($data['description'])
            ->setShortDescription($data['short_description'])
            ->setStatus($data['status'])
            ->setTaxClassId($taxId)
        ;

        // Set for single store mode
        if (Mage::app()->isSingleStoreMode()) {
            $product->setWebsiteIds(array(Mage::app()->getStore(true)->getWebsite()->getId()));
        }

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

        // Set the attributes
        if(isset($data['attributes'])) {
            foreach($data['attributes'] as $name => $value) {
                //Mage::helper('vm2mage')->debug('Product attribute', $name);
                $product = Mage::helper('vm2mage/attribute')->addAttributeToProduct($product, $name, $value);
            }
        }

        // Set the price
        if(isset($data['price']['product_price'])) {
            $product->setPrice((float)$data['price']['product_price']);
        }

        // Set the special price
        if(isset($data['special_price']['price'])) {
            $product->setSpecialPrice((float)$data['special_price']['price']);
        }

        // Set the special price from-date
        if(isset($data['special_price']['start_date'])) {
            $tmp_date = strtotime($data['special_price']['start_date']);
            if($tmp_date > 0) {
                $product->setSpecialFromDate($data['special_price']['start_date']);
            }
        } else {
            $product->setSpecialFromDate(null);
        }

        // Set the special price from-date
        if(isset($data['special_price']['end_date'])) {
            $tmp_date = strtotime($data['special_price']['end_date']);
            if($tmp_date > 0) {
                $product->setSpecialToDate($data['special_price']['end_date']);
            }
        } else {
            $product->setSpecialToDate(null);
        }

        // Set the tier pricing
        if(!empty($data['all_prices']) && count($data['all_prices']) > 1) {
            $tierPrices = array();
            foreach($data['all_prices'] as $price) {

                if(!isset($price['price_quantity_start'])) continue;
                if($price['price_quantity_start'] < 1) continue;
                if($price['price_quantity_end'] < 1) continue;
                
                $customerGroup = Mage_Customer_Model_Group::CUST_GROUP_ALL;
                $customerAllGroups = 1;
                // @todo: Use a mapping for customer_groups
                //if($price['shopper_group_default'] == 0) {
                //    $customerAllGroups = 1;
                //}

                $tierPrices[] = array(
                    'website_id' => 0,
                    'all_groups' => $customerAllGroups,
                    'cust_group' => $customerGroup,
                    'price_qty' => $price['price_quantity_start'],
                    'price' => $price['product_price'],
                );
            }
            $product->setTierPrice($tierPrices);
        }

        // Handle the stock
        if($typeId == Mage_Catalog_Model_Product_Type::TYPE_GROUPED) {
            $data['in_stock'] = null;
        }

        // Convert the category-IDs
        if(!empty($data['category_ids'])) {
            $category_ids = array();
            foreach($data['category_ids'] as $vm_id) {
                if(isset($data['migration_code'])) {
                    $id = Mage::helper('vm2mage/category')->getMageId($vm_id, $data['migration_code']);
                } else {
                    $id = Mage::helper('vm2mage/category')->getMageId($vm_id);
                }
                if($id > 0) $category_ids[] = $id;
            }
        }
            
        if(empty($category_ids)) {
            $category_ids = array(1);     
        }

        $product->setCategoryIds($category_ids);

        // Set the mass-update flags to prevent reindexing for now
        $product->setIsMassupdate(true);
        $product->setExcludeUrlRewrite(true);
            
        // Add the related products
        if(!empty($data['related_products'])) {
            try {
                $product = Mage::helper('vm2mage/product')->addRelatedProducts($product, $data['related_products']);
            } catch(Exception $e) {
                return array(0, $e->getMessage());
            }
        }

        // Try to save this product to the database
        try {
            $request = Mage::app()->getFrontController()->getRequest();
            Mage::dispatchEvent('catalog_product_prepare_save', array('product' => $product, 'request' => $request));
            $product->getResource()->save($product);
            $product = $product->load($product->getId());
        } catch(Exception $e) {
            return array(0, '['.$sku.'] '.$e->getMessage());
        }

        // Set the stock
        $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);
        if(!empty($data['in_stock']) && $data['in_stock'] > 0) {
            $stockItem->setData('qty', (int) $data['in_stock']);
            $stockItem->setData('is_in_stock', 1);
            $stockItem->setData('manage_stock', 1);
            $stockItem->setData('use_config_manage_stock', 0);
        } else {
            $stockItem->setData('manage_stock', 0);
            $stockItem->setData('use_config_manage_stock', 0);
        }

        if(!$stockItem->getId() > 0) {
            $stockItem->setData('stock_id', 1);
            $stockItem->setData('product_id', $product->getId());
        }

        $stockItem->save();
        $product->save();

        // Set the Custom Options
        if(isset($data['custom_options']) || isset($data['custom_option'])) {

            // Merge all options
            $custom_options = array();
            if(isset($data['custom_option'])) $custom_options[] = $data['custom_option'];
            if(!empty($data['custom_options'])) $custom_options = array_merge($custom_options, $data['custom_options']);
            
            // Remove all current options
            $options = $product->getOptions();
            if(!empty($options)) {
                foreach($options as $option) { 
                    $option->delete(); 
                } 
            }
            
            // Add the custom options again
            $new_options = array();
            foreach($custom_options as $custom_option) {
                $option = Mage::helper('vm2mage/product')->addCustomOptionToProduct($product, $custom_option);
                //Mage::helper('vm2mage')->debug('Product Custom Option', $option);

                $product->setHasOptions(1);
                $productOption = Mage::getModel('catalog/product_option')
                    ->setProductId($product->getId())
                    ->setStoreId($product->getStoreId())
                    ->addData($option);
                $productOption->save();
                $product->addOption($productOption);
            }

            // Try to save this product to the database
            try {
                $request = Mage::app()->getFrontController()->getRequest();
                Mage::dispatchEvent('catalog_product_prepare_save', array('product' => $product, 'request' => $request));
                $product->getResource()->save($product);
                $product = $product->load($product->getId());
            } catch(Exception $e) {
                return array(0, '['.$sku.'] '.$e->getMessage());
            }
        }

        // Configure this product as configurable product
        if(isset($data['has_children']) && $data['has_children'] > 0) {
            if($typeId == Mage_Catalog_Model_Product_Type::TYPE_GROUPED) {
                $rs = Mage::helper('vm2mage/product')->addChildrenToGrouped($data['children'], $product);
            } elseif($typeId == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
                $rs = Mage::helper('vm2mage/product')->addChildrenToConfigurable($data['children'], $data['attributes_sku'], $product);
            }

            if(!empty($rs)) {
                return $rs;
            }
        }

        // Get the remote images
        if(isset($data['images'])) {
            try {
                Mage::helper('vm2mage/image')->addImages($product, $data['images']);
            } catch(Exception $e) {
                return array(0, $e->getMessage());
            }
        }

        // Apply rules
        Mage::getModel('catalogrule/rule')->applyAllRulesToProduct($product->getId());

        // Return true by default
        if($isNew) {
            return array(1, "Created new product ".$product->getName()." [".$product->getId()."]", $data['id']);
        } else {
            return array(1, "Updated product ".$product->getName()." [".$product->getId()."]", $data['id']);
        }
    }
}
