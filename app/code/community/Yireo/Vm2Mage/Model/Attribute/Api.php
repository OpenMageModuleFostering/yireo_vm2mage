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

class Yireo_Vm2Mage_Model_Attribute_Api extends Mage_Api_Model_Resource_Abstract
{
    /**
     * Initialize all attributes in the database
     *
     * @param array $data
     * @return array
     */
    public function initialize($data = null)
    {
        // Check for empty data
        if(!is_array($data) || empty($data['name'])) {
            return array(0, "Attribute is not an array");
        }
        // @todo: Option to enable debugging
        #Mage::helper('vm2mage')->debug('VirtueMart attribute', $data);

        // Quick filter for the attribute-name
        $data['name'] = strtolower($data['name']);
        
        // Flag on whether to save this attribute
        $do_save = false;

        // Get an attribute-object
        $product = Mage::getModel('catalog/product');
        $attribute = Mage::getModel('catalog/entity_attribute');
        $attributes = Mage::getResourceModel('eav/entity_attribute_collection')
            ->setEntityTypeFilter($product->getResource()->getTypeId())
            ->addFieldToFilter('attribute_code', $data['name'])
            ->load(false);
        $attribute = $attributes->getFirstItem()->setEntity($product->getResource());
        unset($product,$attributes);

        // Create a new attribute
        if(!$attribute->getId() > 0) {
            $do_save = true;
            $attribute
                ->setAttributeCode($data['name'])
                ->setEntityTypeId(Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId())
                ->setIsComparable(0)
                ->setIsConfigurable(1)
                ->setIsFilterable(0)
                ->setIsFilterableInSearch(0)
                ->setIsGlobal(1)
                ->setIsRequired(0)
                ->setIsSearchable(0)
                ->setIsUnique(0)
                ->setIsUserDefined(1)
                ->setIsUsedForPriceRules(0)
                ->setIsVisibleInAdvancedSearch(0)
                ->setIsVisibleOnFront(0)
                ->setDefaultValueYesno(0)
                ->setUsedInProductListing(0)
                ->setFrontendInput('select')
                ->setFrontendLabel(array($data['label'], $data['label']))
                ->setBackendType($attribute->getBackendTypeByInput('select'))
            ;

            // Add it to the default attribute-set and the first group
            $attributeSetId = Mage::getModel('catalog/product')->getDefaultAttributeSetId();
            $groups = Mage::getModel('eav/entity_attribute_group')
                ->getResourceCollection()
                ->setAttributeSetFilter($attributeSetId)
                ->load();
            foreach($groups as $group) break;
            if(!empty($group)) {
                $attribute->setAttributeSetId($attributeSetId);
                $attribute->setAttributeGroupId($group->getAttributeGroupId());
            }

            // Add other data
            $attribute->setData('backend_model', 'eav/entity_attribute_backend_array');
            $attribute->setData('apply_to', Mage_Catalog_Model_Product_Type::TYPE_SIMPLE);
        }

        // Get the existing attribute-options
        $existing_options = array();
        foreach($attribute->getSource()->getAllOptions(false) as $existing_option) {
            $existing_options[] = $existing_option['label'];
        }

        // Strip these existing attribute-options from the incoming options
        foreach($data['values'] as $index => $value) {
            if(in_array($value, $existing_options)) unset($data['values'][$index]);
        }

        // Set the remaining attribute-options
        if(!empty($data['values'])) {
            $do_save = true;
            $options = Mage::helper('vm2mage/attribute')->valueToOptions($data['values']);
            $attribute->setOption($options);
        }

        // Do not save anything if this is needed
        if($do_save == false) {
            return array(1, "No changes in '".$data['name']."'");
        }

        // Save the attribute
        try {
            $attribute->save();
        } catch(Exception $e) {
            return array(0, $e->getMessage());
        }

        return array(1, "Successfully saved attribute '".$data['name']."'");
    }
}
