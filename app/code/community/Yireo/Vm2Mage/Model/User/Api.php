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

class Yireo_Vm2Mage_Model_User_Api extends Mage_Customer_Model_Customer_Api
{
    /**
     * Update a product using a basic array of values
     *
     * @param array $data
     * @return array
     */
    public function migrate($data = null)
    {
        // Option to renew customers or not
        $renewCustomers = (bool)Mage::getStoreConfig('vm2mage/settings/renew_customers');

        // Check for empty data
        if(!is_array($data)) {
            return array(0, "Data is not an array");
        }

        // Decode all values
        $data = Mage::helper('vm2mage')->decode($data);
        #Mage::helper('vm2mage')->debug('VirtueMart user', $data);

        // Check for email
        if(empty($data['email'])) {
            return array(0, "Data contains no email");
        }

        // Get a clean customer-object
        $storeId = (isset($data['store_id'])) ? $data['store_id'] : 1;
        $websiteId = Mage::getModel('core/store')->load($storeId)->getWebsiteId();
        $customer = Mage::getModel('customer/customer')->setStoreId($storeId)->setWebsiteId($websiteId);
        try { 
            $customer->setStoreId($storeId)->setWebsiteId($websiteId)->loadByEmail($data['email']); 
        } catch(Exception $e) {
            return array(0, "API error: ".$e->getMessage());
        }

        // The ID is empty, so this is a new customer
        $customerId = $customer->getId();
        if(!$customerId > 0) {
            $isNew = true;
            $customer->setData($customer->getData())
                ->setId(null)
            ;
            if(!empty($data['created_at'])) $customer->setCreatedAt($data['created_at']);
            if(!empty($data['modified_at'])) $customer->setUpdatedAt($data['modified_at']);

        } else {
            $customer->setData($customer->getData())
                ->setId($customerId)
            ;
            $isNew = false;
        }

        // Fix bogus dates
        $createdAt = strtotime($customer->getCreatedAt());
        $updatedAt = strtotime($customer->getUpdatedAt());
        $birthOfJoomla = strtotime('01 January 1991');
        if($createdAt < $birthOfJoomla || $createdAt > time()) $customer->setCreatedAt(strftime('%Y-%m-%d %H:%M:%S', time()));
        if($updatedAt < $birthOfJoomla || $updatedAt > time()) $customer->setUpdatedAt(strftime('%Y-%m-%d %H:%M:%S', time()));

        // Set common fields (vmField => mageField)
        $fields = array(
            'name' => 'name',
            'email' => 'email',
            'first_name' => 'firstname',
            'middle_name' => 'middlename',
            'last_name' => 'lastname',
            'taxvat' => 'taxvat',
        );
        foreach($fields as $vmField => $mageField) {
            if(isset($data[$vmField])) $customer->setData($mageField, $data[$vmField]);
        }

        // Set store-ID and website-ID
        $customer->setStoreId($storeId);
        $customer->setWebsiteId($websiteId);

        // Set the group and tax class
        if(isset($data['customer_group_id'])) {
            $customer->setGroupId($data['customer_group_id']);
            $customer->setTaxClassId(Mage::getModel('customer/group')->getTaxClassId($data['customer_group_id']));
        }

        // Set the password 
        if(isset($data['password'])) {
            $customer->setPasswordHash($data['password']);
        }

        // Only save if allowed 
        if($customerId < 1 || $renewCustomers == true) {

            // Try to safe this customer to the database
            try {
                $customer->save();
            } catch(Exception $e) {
                return array(0, $e->getMessage());
            }

            // Save the address
            if(isset($data['addresses']) && !empty($data['addresses'])) {
                foreach($data['addresses'] as $address) {
                    $rt = $this->saveAddress($customer, $address, $data);
                }
            } else {
                $rt = $this->saveAddress($customer, $data);
            }

            if(!empty($rt)) {
                return $rt;
            }
        }

        // Save the orders
        $rt = $this->saveOrders($customer, $data);
        if(!empty($rt)) {
            return $rt;
        }

        // Save the prduct reviews
        if(isset($data['product_reviews']) && !empty($data['product_reviews'])) {
            foreach($data['product_reviews'] as $review) {
                $rt = $this->saveProductReviews($customer, $review);
            }
        }

        // Return true by default
        if($isNew) {
            return array(1, "Created new customer ".$customer->getEmail());
        } else {
            return array(1, "Updated customer ".$customer->getEmail());
        }
    }

    /**
     * Save the customer address
     */
    private function saveAddress($customer, $data, $customerData)
    {
        // Load both addressses 
        $shippingAddress = $customer->getPrimaryShippingAddress();
        $billingAddress = $customer->getPrimaryBillingAddress();

        // Determine the address-types
        if(isset($data['address_type']) && strtolower($data['address_type']) == 'st') {
            $address = $shippingAddress;
            $is_shipping = true;

        } else {
            $address = $billingAddress;
            $is_billing = true;
        }
        #Mage::helper('vm2mage')->debug('Magento address', $address->debug());

        // Some extra overrides
        $is_shipping = (empty($shippingAddress)) ? true : false;
        $is_billing = (empty($billingAddress)) ? true : false;
        $is_billing = true;
        $is_shipping = true;

        // Load the address
        if(empty($address)) {
            $address = Mage::getModel('customer/address');
        }

        // Compile the street
        $street = null;
        if(!empty($data['address_1'])) $street .= $data['address_1'];
        if(!empty($data['address_2'])) $street .= "\n".$data['address_2'];

        // Don't continue with empty street
        if(empty($street)) {
            return false;
        }

        // Load the country
        $country = null;
        if(empty($data['country']) && !empty($customerData['country'])) $data['country'] = $customerData['country'];
        if(!empty($data['country'])) $country = Mage::getModel('directory/country')->loadByCode($data['country']);

        // Load the region
        $region = null;
        if(empty($data['state']) && !empty($customerData['state'])) $data['state'] = $customerData['state'];
        if(empty($data['state']) && !empty($customerData['region'])) $data['state'] = $customerData['region'];
        if(!empty($data['state'])) $region = Mage::getModel('directory/region')->loadByCode($data['state'], $country->getId());
        Mage::log('isse: '.$data['state'].' / '.$country->getId());
        Mage::log('sisse: '.$region->getId());

        // Set basic values
        $address
            ->setIsPrimaryBilling($is_billing)
            ->setIsPrimaryShipping($is_shipping)
            ->setIsDefaultBilling($is_billing)
            ->setIsDefaultShipping($is_shipping)
            ->setStreet($street)
        ;

        // Set other fields (vmField => mageField)
        $fields = array(
            'phone_1' => 'telephone',
            'first_name' => 'firstname',
            'middle_name' => 'middlename',
            'last_name' => 'lastname',
            'company' => 'company',
            'fax' => 'fax',
            'city' => 'city',
            'zip' => 'postcode',
            'country' => 'country',
            'taxvat' => 'vat_id',
        );
        foreach($fields as $vmField => $mageField) {
            if(isset($data[$vmField])) {
                $address->setData($mageField, $data[$vmField]);
            }
        }

        // Set the country 
        if(!empty($country)) {
            $address->setCountryId($country->getId());
        }

        // Set the region
        if(!empty($region)) {
            $address->setRegionId($region->getId());
            $address->setRegionName($region->getName());
        }

        // Set the customer if needed
        if(!$address->getCustomerId() > 0) $address->setCustomerId($customer->getId());

        // Save the address
        try {
            if($address->getId() > 0) {
                $address->save();
            } else {
                $address->save();
                $customer->addAddress($address);
                $customer->save();
            }

        } catch(Exception $e) {
            Mage::helper('vm2mage')->debug('Exception', $e->getMessage());
            return array(0, $e->getMessage());
        }

        return null;
    }

    /**
     * Save the product reviews
     */
    private function saveProductReviews($customer, $data)
    {
        if(empty($data['product_sku'])) {
            return null;
        }

        $sku = trim($data['product_sku']);
        if(empty($sku)) {
            return null;
        }

        $productId = Mage::getModel('catalog/product')->getIdBySku($sku);
        if(empty($productId) || $productId == 0) {
            return null;
        }

        // Detect if this review is already present
        $collection = Mage::getModel('review/review')->getCollection()
            ->addFieldToFilter('entity_id', Mage_Review_Model_Review::ENTITY_PRODUCT)
            ->addFieldToFilter('entity_pk_value', $productId)
            ->addFieldToFilter('customer_id', $customer->getId())
        ;
        if($collection->count() > 0) {
            $review = $collection->getFirstItem();
            $reviewId = $review->getId();
        } else {
            $reviewId = null;
        }
        
        try {
            $nickname = $customer->getFirstname();
            if(empty($nickname)) $nickname = '-';
            if(empty($data['user_rating'])) $data['user_rating'] = 4;

            $review = Mage::getModel('review/review');
            if($reviewId > 0) {
                $review->load($reviewId);
            } else {
                $review->setEntityId(Mage_Review_Model_Review::ENTITY_PRODUCT);
                $review->setEntityPkValue($productId);
                $review->setCustomerId($customer->getId());
                $storeId = Mage::app()->getStore($customer->getStoreId())->getId();
                $review->setStoreId($storeId);
                $review->setStores(array($storeId));
            }

            $review->setTitle('-');
            $review->setNickname($nickname);
            $review->setDetail($data['comment']);
            $review->setRatingSummary($data['user_rating']);

            $review->setCreatedAt(date('Y-m-d H:i:s', $data['time']));
            if(strtolower($data['published']) == 'y') {
                $review->setStatusId(Mage_Review_Model_Review::STATUS_APPROVED);
            } else {
                $review->setStatusId(Mage_Review_Model_Review::STATUS_PENDING);
            }

            $rt = $review->save();
            $review->aggregate();

        } catch(Exception $e) {
            Mage::helper('vm2mage')->debug('Exception', $e->getMessage());
        }
    }

    /**
     * Save the customer orders
     */
    private function saveOrders($customer, $data)
    {
        // Check if there are any orders to migrate
        if(!isset($data['orders'])) {
            return false;
        }

        // Determine a default currency
        if(isset($data['default_currency'])) {
            $default_currency = $data['default_currency'];
        } else {
            $default_currency = 'USD';
        }

        // Only use the order-data
        $orders = $data['orders'];
        if(is_array($orders) && !empty($orders)) {
            foreach($orders as $order) {
        
                // Load the customer
                $customer_id = $customer->getId();

                // Load the existing record
                $model = Mage::getModel('vmorder/order')->load($order['order_id'], 'order_id');
                $order_currency = (!empty($order['order_currency'])) ? $order['order_currency'] : $default_currency;
                $order_status_name = (!empty($order['order_status_name'])) ? $order['order_status_name'] : $order['order_status'];

                // Set all important values
                $model->setCustomerId($customer_id);
                $model->setOrderId($order['order_id']);
                $model->setOrderNumber($order['order_number']);
                $model->setOrderTotal($order['order_total']);
                $model->setOrderSubtotal($order['order_subtotal']);
                $model->setOrderCurrency($order_currency);
                $model->setOrderTax($order['order_tax']);
                $model->setOrderTaxDetails($order['order_tax_details']);
                $model->setOrderShipping($order['order_shipping']);
                $model->setOrderShippingTax($order['order_shipping_tax']);
                $model->setCouponDiscount($order['coupon_discount']);
                $model->setOrderDiscount($order['order_discount']);
                $model->setOrderStatusName($order['order_status_name']);
                $model->setOrderStatus($order['order_status']);
                $model->setCreateDate($order['create_date']);
                $model->setModifyDate($order['modify_date']);
                $model->setCustomerNote($order['customer_note']);
                $model->setPaymentMethod($order['payment_method']);
                $model->setShipMethodId($order['shipment_method']);
                $model->save(); // skipped: order_id, vendor_id, user_info_id, ip-address

                // Loop through the order-items and save them as well
                if(!empty($order['items']) && is_array($order['items'])) {
                    foreach($order['items'] as $item) {

                        $product_id = Mage::getModel('catalog/product')->getIdBySku($item['order_item_sku']);
                        $item_currency = (!empty($order['order_item_currency'])) ? $order['order_item_currency'] : $default_currency;

                        // Load the existing record
                        $model = Mage::getModel('vmorder/order_item')->load($item['order_item_id'], 'order_item_id');
                        $model->setOrderItemId($item['order_item_id']);
                        $model->setOrderId($item['order_id']);
                        $model->setProductId($product_id);
                        $model->setProductName($item['order_item_name']);
                        $model->setProductSku($item['order_item_sku']);
                        $model->setProductQuantity($item['product_quantity']);
                        $model->setProductItemPrice($item['product_item_price']);
                        $model->setProductFinalPrice($item['product_final_price']);
                        $model->setProductCurrency($item_currency);
                        $model->save();
                    }
                }
                
            }
        }

    }
}
