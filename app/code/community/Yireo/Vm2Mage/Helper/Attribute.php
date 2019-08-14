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
            $index = 'option_'.$i;
            $options['value'][$index] = array($value, $value);
            $i++;
        }

        return $options;
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
    public function addAttributeToProduct($product = null, $attribute_code = null, $attribute_value = null)
    {
        $attribute = Mage::helper('vm2mage/attribute')->getAttributeByCode($attribute_code);
        if(empty($attribute)) {
            return $product;
        }
        
        $options = $attribute->getSource()->getAllOptions();
        if(empty($options)) {
            return $product;
        }
    
        foreach($options as $option) {
            if($option['label'] == $attribute_value) {
                $value = $option['value'];
                break;
            }
        }

        if(empty($value)) {
            return $product;
        }

        $method = Mage::helper('vm2mage')->stringToMethod($attribute_code);
        if(!empty($method)) {
            try {
                $product->$method($value);
            } catch(Exception $e) {
                Mage::helper('vm2mage')->debug('Error when setting attribute "'.$attribute_code.'" on product "'.$product->getName().'"');
            }
        }

        return $product;
    }
}
