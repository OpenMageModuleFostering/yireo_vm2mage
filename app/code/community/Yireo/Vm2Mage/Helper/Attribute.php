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
class Yireo_Vm2Mage_Helper_Attribute extends Yireo_Vm2Mage_Helper_Data
{
    /*
     * Helper-method to convert a regular array to the options-structure required by the Attribute-model
     *
     * @param array $values
     * @return array
     */
    public function valueToOptions($values = array())
    {
        $options = array('value' => array());
        $i = 1;
        foreach($values as $value) {
            if(empty($value)) continue;
            $index = 'option_'.$i;
            $options['value'][$index] = array($value, $value);
            $i++;
        }

        return $options;
    }
    
    /*
     * Method to convert the attribute-code
     *
     * @param string $attributeCode
     * @return string
     */
    public function convertAttributeCode($attributeCode = null)
    {
        $attributeCode = preg_replace('/([^a-zA-Z0-9\-\_]+)/', '_', $attributeCode);
        return strtolower($attributeCode);
    }

    /*
     * Method to get an attribute by its code
     *
     * @param Mage_Catalog_Model_Product $product
     * @param string $attributeCode
     * @return Mage_Catalog_Model_Product
     */
    public function getAttributeByCode($attributeCode = null)
    {
		$attributeCode = Mage::helper('vm2mage/attribute')->convertAttributeCode($attributeCode);
        $attribute = Mage::getModel('eav/entity_attribute')->loadByCode('catalog_product',$attributeCode);
        return $attribute;
    }

    /*
     * Method to add an attribute value to a product
     *
     * @param Mage_Catalog_Model_Product $product
     * @param string $attribute_code
     * @param string $attribute_value
     * @return Mage_Catalog_Model_Product`
     */
    public function addAttributeToProduct($product = null, $attributeCode = null, $attributeValue = null)
    {
		$attributeCode = Mage::helper('vm2mage/attribute')->convertAttributeCode($attributeCode);
        $attribute = Mage::helper('vm2mage/attribute')->getAttributeByCode($attributeCode);
        if(empty($attribute)) {
            return $product;
        }
        
        try {
			$options = $attribute->getSource()->getAllOptions();
		} catch(Exception $e) {
			Mage::helper('vm2mage')->debug('Error when loading product-options', $e->getMessage());
			return $product;
	    }
	
        if(empty($options)) {
            return $product;
        }
    
        foreach($options as $option) {
            if($option['label'] == $attributeValue) {
                $value = $option['value'];
                break;
            }
        }

        if(empty($value)) {
            return $product;
        }

        $method = Mage::helper('vm2mage')->stringToMethod($attributeCode);
        if(!empty($method)) {
            try {
                $product->$method($value);
            } catch(Exception $e) {
                Mage::helper('vm2mage')->debug('Error when setting attribute "'.$attributeCode.'" on product "'.$product->getName().'"');
            }
        }

        return $product;
    }
}
