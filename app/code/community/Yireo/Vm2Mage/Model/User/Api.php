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
        // Check for empty data
        if(!is_array($data)) {
            return array(0, "Data is not an array");
        }

        // Decode all values
        $data = Mage::helper('vm2mage')->decode($data);
        Mage::helper('vm2mage')->debug('VirtueMart user', $data);

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
                ->setCreatedAt($data['created_at'])
                ->setUpdatedAt($data['modified_at'])
            ;

        } else {
            $customer->setData($customer->getData())
                ->setId($customerId)
            ;
            $isNew = false;
        }

        // Set common attributes
        $customer->setData($customer->getData())
            ->setName($data['name'])
            ->setEmail($data['email'])
            ->setFirstname($data['first_name'])
            ->setMiddlename($data['middle_name'])
            ->setLastname($data['last_name'])
            ->setStoreId($storeId)
            ->setWebsiteId($websiteId)
        ;

        // Set the group and tax class
        if(isset($data['customer_group_id'])) {
            $customer->setGroupId($data['customer_group_id']);
            $customer->setTaxClassId(Mage::getModel('customer/group')->getTaxClassId($data['customer_group_id']));
        }

        // Set the taxvat 
        if(!empty($data['taxvat'])) $customer->setTaxvat($data['taxvat']);

        // Set the password (but only if it's a plain MD5-string)
        if(preg_match('/^([0-9a-fA-F]{32})$/', $data['password'])) {
            $hash = $data['password'].':';
            $customer->setPasswordHash($hash);
        }

        // Try to safe this customer to the database
        try {
            $customer->save();
        } catch(Exception $e) {
            return array(0, $e->getMessage());
        }

        // Save the address
        if(isset($data['addresses']) && !empty($data['addresses'])) {
            foreach($data['addresses'] as $address) {
                $rt = $this->saveAddress($customer, $address);
            }
        } else {
            $rt = $this->saveAddress($customer, $data);
        }

        if(!empty($rt)) {
            return $rt;
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
    private function saveAddress($customer, $data)
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
        //Mage::helper('vm2mage')->debug('Magento address', $address->debug());

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
        $street = trim($data['address_1']);
        if(!empty($data['address_2'])) $street .= "\n".$data['address_2'];

        // Load the country and region
        $country = Mage::getModel('directory/country')->loadByCode($data['country']);
        $region = Mage::getModel('directory/region')->loadByCode($data['state'], $country->getId());

        // Set all needed values
        $address
            ->setIsPrimaryBilling($is_billing)
            ->setIsPrimaryShipping($is_shipping)
            ->setIsDefaultBilling($is_billing)
            ->setIsDefaultShipping($is_shipping)
            ->setTelephone($data['phone_1'])
            ->setFirstname($data['first_name'])
            ->setMiddlename($data['middle_name'])
            ->setLastname($data['last_name'])
            ->setCompany($data['company'])
            ->setFax($data['fax'])
            ->setStreet($street)
            ->setCity($data['city'])
            ->setPostcode($data['zip'])
            ->setRegion($data['state'])
            ->setCountry($data['country'])
        ;

        // Load the taxvat if available
        if(!empty($data['taxvat'])) $address->setVatId($data['taxvat']);

        // Load the country and region
        if(!empty($country)) $address->setCountryId($country->getId());
        if(!empty($region)) $address->setRegionId($region->getId());

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
        $sku = trim($data['product_sku']);
        if(empty($sku)) {
            return null;
        }

        $productId = Mage::getModel('catalog/product')->getIdBySku($sku);
        if(empty($productId) || $productId == 0) {
            return null;
        }
        
        try {
            $nickname = $customer->getFirstname();
            if(empty($nickname)) $nickname = '-';

            $review = Mage::getModel('review/review');
            $review->setEntityId(Mage_Review_Model_Review::ENTITY_PRODUCT);
            $review->setEntityPkValue($productId);
            $review->setCustomerId($customer->getId());
            $review->setStoreId($customer->getStoreId());
            $review->setStores(array($customer->getStoreId()));
            $review->setTitle('-');
            $review->setNickname($nickname);
            $review->setDetail($data['comment']);
            // drop $data['user_rating']

            $review->setCreatedAt(date('Y-m-d H:i:s', $data['time']));
            if(strtolower($data['published']) == 'y') {
                $review->setStatusId(1);
            } else {
                $review->setStatusId(2);
            }

            $rt = $review->save();

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

                // Set all important values
                $model->setCustomerId($customer_id);
                $model->setOrderId($order['order_id']);
                $model->setOrderNumber($order['order_number']);
                $model->setOrderTotal($order['order_total']);
                $model->setOrderCurrency($order_currency);
                $model->setOrderTax($order['order_tax']);
                $model->setOrderTaxDetails($order['order_tax_details']);
                $model->setOrderShipping($order['order_shipping']);
                $model->setOrderShippingTax($order['order_shipping_tax']);
                $model->setCouponDiscount($order['coupon_discount']);
                $model->setOrderDiscount($order['order_discount']);
                $model->setOrderStatusName($order['order_status_name']);
                $model->setOrderStatus($order['order_status']);
                $model->setCreateDate($order['cdate']);
                $model->setModifyDate($order['mdate']);
                $model->setShipMethodId($order['ship_method_id']);
                $model->setCustomerNote($order['customer_note']);
                $model->setPaymentMethod($order['payment_method']);
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
