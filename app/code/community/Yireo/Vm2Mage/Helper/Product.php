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
class Yireo_Vm2Mage_Helper_Product extends Yireo_Vm2Mage_Helper_Data
{
    /*
     * Helper-method to convert a regular array to the options-structure required by the Attribute-model
     *
     * @param array $values
     * @return array
     */
    public function addChildrenToConfigurable($children = array(), $attribute_codes = array(), $product = null)
    {
        // Create the children first as Simple Products and them to usable arrays
        $childIds = array();
        foreach($children as $child) {
            $childId = Mage::getModel('catalog/product')->getIdBySku($child['sku']);
            if(empty($childId)) {
                Mage::getModel('vm2mage/product_api')->migrate($child);
                $childId = Mage::getModel('catalog/product')->getIdBySku($child['sku']);
            }
            $childIds[] = $childId;
        }

        // Insert the Simple Products that belong in this Configurable Product
        $loader = Mage::getResourceModel('catalog/product_type_configurable')->load($product);
        $loader->saveProducts($product, $childIds);

        // Gather the attribute-objects for use in this Configurable Product
        $attributeData = array();
        $productData = array();
        $attributeIds = array();
        $i = 0;
        foreach($attribute_codes as $attribute_code) {
            $attribute = $product->getResource()->getAttribute($attribute_code);
            if($product->getTypeInstance()->canUseAttribute($attribute)) {
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
}

