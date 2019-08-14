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
class Yireo_Vm2Mage_Helper_Product extends Yireo_Vm2Mage_Helper_Data
{
    /*
     * Helper-method to convert a regular array to the options-structure required by the Attribute-model
     *
     * @param array $values
     * @return array
     */
    public function addChildrenToConfigurable($children = array(), $attributeCodes = array(), $product = null)
    {
        // Create the children first as Simple Products and them to usable arrays
        $childIds = array();
        foreach($children as $child) {
            $childId = Mage::getModel('catalog/product')->getIdBySku($child['sku']);

            if(empty($child['price']['product_price'])) {
                $child['price']['product_price'] = $product->getPrice();
            }
            
            Mage::getModel('vm2mage/product_api')->migrate($child);
            $childId = Mage::getModel('catalog/product')->getIdBySku($child['sku']);

            $childIds[] = $childId;
        }

        // Clean up the Configurable Product data first
        if ($product->getId() > 0) {
            $resource = Mage::getSingleton('core/resource');
            $write = $resource->getConnection('core_write');
            $table = $resource->getTableName('catalog/product_super_attribute');
            $write->delete($table, 'product_id = ' . $product->getId());
        }

        // Insert the Simple Products that belong in this Configurable Product
        $loader = Mage::getResourceModel('catalog/product_type_configurable')->load($product, null);
        $loader->saveProducts($product, $childIds);

        // Gather the attribute-objects for use in this Configurable Product
        $attributeData = array();
        $productData = array();
        $attributeIds = array();
        $i = 0;
        foreach($attributeCodes as $attributeCode) {
			$attributeCode = Mage::helper('vm2mage/attribute')->convertAttributeCode($attributeCode);
            $attribute = $product->getResource()->getAttribute($attributeCode);
            if(!empty($attribute) && $product->getTypeInstance()->canUseAttribute($attribute)) {
                if(!in_array($attribute->getAttributeId(), $attributeIds)) {
                    $attributeIds[] = $attribute->getAttributeId();
                }
            }
        }

        if(!empty($childIds)) {
            foreach($childIds as $childId) {
                $productData[$childId] = array();
                foreach($attributeIds as $attributeId) {
                    $productData[$childId][] = array('attribute_id' => $attributeId);
                }
            }
        }

        // Insert the used product-attributes if they do not exist yet
        $currentProductAttributeIds = $product->getTypeInstance()->getUsedProductAttributeIds();
        if(empty($currentProductAttributeIds)) {
            $product->getTypeInstance()->setUsedProductAttributeIds($attributeIds);
        }

        // Insert the attribute-data
        $attributeData = $product->getTypeInstance()->getConfigurableAttributesAsArray();
        foreach($attributeData as $key => $attribute) {
            if(empty($attribute['label'])) {
                $attributeData[$key]['label'] = $attribute['frontend_label'];
            }
        }
        $product->setConfigurableAttributesData($attributeData, $product);
        $product->setCanSaveConfigurableAttributes(true);
        $product->setCanSaveCustomOptions(true);

        // Insert the product-data
        if(!empty($productData)) {
            $product->setConfigurableProductsData($productData, $product);
        }

        // Try to safe this product to the database
        try {
            $product->save();
        } catch(Exception $e) {
            return array(0, $e->getMessage());
        }
    }

    /*
     * Helper-method to add child-products to a grouped product
     *
     * @param array $values
     * @return array
     */
    public function addChildrenToGrouped($children = array(), $product = null)
    {
        // Create the children first as Simple Products and them to usable arrays
        $childIds = array();
        foreach($children as $child) {
            $childId = Mage::getModel('catalog/product')->getIdBySku($child['sku']);

            // @todo: Should we re-migrate the product or not?
            //if(empty($childId)) {
                if(empty($child['price']['product_price'])) $child['price']['product_price'] = $product->getPrice();
                Mage::getModel('vm2mage/product_api')->migrate($child);
                $childId = Mage::getModel('catalog/product')->getIdBySku($child['sku']);
            //}
            $childIds[] = $childId;
        }

        // Insert the Simple Products that belong in this Configurable Product
        foreach($childIds as $childId) {
            $productsLinks = Mage::getModel('catalog/product_link_api');
            $productsLinks->assign('grouped', $product->getId(), $childId);
        }

        // Try to safe this product to the database
        try {
            $product->save();
        } catch(Exception $e) {
            return array(0, $e->getMessage());
        }
    }

    /*
     * Helper-method to add a list of product-SKUs to a product as related products
     *
     * @param array $values
     * @return array
     */
    public function addRelatedProducts($product, $children = array())
    {
        $relatedProductsData = array();
        if(!empty($children)) {
            $i = 0;
            foreach($children as $child_sku) {
                $relatedProductId = Mage::getModel('catalog/product')->getIdBySku($child_sku);
                if($relatedProductId > 0) {
                    $relatedProductsData[$relatedProductId] = array('position' => $i);
                    $i++;
                }
            }
        }

        if(!empty($relatedProductsData)) {
            $product->setRelatedLinkData($relatedProductsData);
        }

        return $product;
    }

    /*
     * Helper-method to add custom options to a product
     *
     * @param Mage_Catalog_Model_Product $product
     * @param string $name
     * @param array $values
     * @return $product
     */
    public function addCustomOptionToProduct($product, $custom_option)
    {
        if (!isset($custom_option['name'])) {
            return $product;
        }

        $name = $custom_option['name'];
        $values = $custom_option['values'];
        $ordering = $custom_option['ordering'];

        $option = array(
            'title' => $name,
            'type' => 'drop_down',
            'is_require' => 1,
            'sort_order' => $ordering,
            'values' => array(),
        );

        foreach($values as $value) {
            if(!isset($value['price'])) $value['price'] = null;
            $option['values'][] = array(
                'title' => $value['label'],
                'price' => $value['price'],
                'price_type' => 'fixed',
                'sku' => '',
                'sort_order' => $value['ordering'],
            );
        }

        return $option;
    }
}
