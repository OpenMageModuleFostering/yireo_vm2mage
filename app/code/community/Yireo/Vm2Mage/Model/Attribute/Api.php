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
        Mage::helper('vm2mage')->init();

        // Check for empty data
        if(!is_array($data) || empty($data['name'])) {
            return array(0, "Attribute is not an array");
        }
        
        // Debugging
        //Mage::helper('vm2mage')->debug('VirtueMart attribute', $data);
        
        // Flags
        $isNew = false;
        $doSave = false;
        if(Mage::getStoreConfig('vm2mage/settings/delete_attributes') == 1) $doSave = true;

		// Convert the attribute-code
		$attributeCode = $data['name'];
		$attributeCode = Mage::helper('vm2mage/attribute')->convertAttributeCode($attributeCode);

        // Get an attribute-object
        $product = Mage::getModel('catalog/product');
        $attributes = Mage::getResourceModel('eav/entity_attribute_collection')
            ->setEntityTypeFilter($product->getResource()->getTypeId())
            ->addFieldToFilter('attribute_code', $attributeCode)
            ->load(false);
        $attribute = $attributes->getFirstItem();
        $attribute->setEntity($product->getResource());

        // Delete the attribute if it already exists
        if($attribute->getId() > 0 && Mage::getStoreConfig('vm2mage/settings/delete_attributes') == 1) {
            $model = Mage::getModel('catalog/resource_eav_attribute');
            $model->load($attribute->getId());
            try {
                $model->delete();
            } catch(Exception $e) {}

            $attribute = $attributes->getFirstItem();
            $attribute->setEntity($product->getResource());
        }

        // Unset the variables to make sure they don't interfer
        unset($product,$attributes);

        // Create a new attribute
        if(!$attribute->getId() > 0) {

            $doSave = true;
            $isNew = true;
    
            if(empty($data['values'])) {
                $frontendInput = 'boolean';
                $defaultValueYesNo = 1;
                $backendModel = 'catalog/product_attribute_backend_boolean';
                $sourceModel = 'eav/entity_attribute_source_boolean';
            } else {
                $frontendInput = 'select';
                $defaultValueYesNo = 0;
                $backendModel = 'eav/entity_attribute_backend_array';
                $sourceModel = 'eav/entity_attribute_source_table';
            }

            $configurable = (!empty($data['configurable']) && $data['configurable'] == 1) ? 1 : 0;

            $attribute
                ->setAttributeCode($attributeCode)
                ->setEntityTypeId(Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId())
                ->setIsComparable(0)
                ->setIsConfigurable($configurable)
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
                ->setDefaultValueYesno($defaultValueYesNo)
                ->setUsedInProductListing(0)
                ->setFrontendInput($frontendInput)
                ->setFrontendLabel(array($data['label'], $data['label']))
                ->setBackendType($attribute->getBackendTypeByInput($frontendInput))
                ->setBackendModel($backendModel)
                ->setSourceModel($sourceModel)
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
            $attribute->setData('apply_to', 0);
            //$attribute->setData('apply_to', Mage_Catalog_Model_Product_Type::TYPE_SIMPLE);
        }

        // Get the existing attribute-options
        $existing_options = array();
        foreach($attribute->getSource()->getAllOptions(false) as $existing_option) {
            $existing_options[] = $existing_option['label'];
        }

        // Strip these existing attribute-options from the incoming options
        if(!empty($data['values'])) {
            foreach($data['values'] as $index => $value) {
                if(empty($value)) continue;
                if(in_array($value, $existing_options)) unset($data['values'][$index]);
            }
        }

        // Set the remaining attribute-options
        if(!empty($data['values'])) {
            $doSave = true;
            $options = Mage::helper('vm2mage/attribute')->valueToOptions($data['values']);
            $attribute->setOption($options);
        }

        // Do not save anything if this is needed
        if($doSave == false) {
            return array(1, "No changes in '".$attributeCode."'");
        }

        // Save the attribute
        try {
            $attribute->save();
        } catch(Exception $e) {
            return array(0, '['.$attributeCode.'] '.$e->getMessage());
        }

        if($isNew) {
            return array(1, "Created attribute '".$attributeCode."'");
        } else {
            return array(1, "Updated attribute '".$attributeCode."'");
        }
    }
}
