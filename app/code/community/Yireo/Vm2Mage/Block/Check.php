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

/*
 * Vm2Mage class for the check-block
 */
class Yireo_Vm2Mage_Block_Check extends Mage_Core_Block_Template
{
    private $sytem_checks = array();

    const CHECK_OK = 'ok';
    const CHECK_WARNING = 'warning';
    const CHECK_ERROR = 'error';

    /*
     * Constructor method
     *
     * @access public
     * @param null
     * @return null
     */
    public function _construct()
    {
        parent::_construct();
        $this->setTemplate('vm2mage/check.phtml');
    }

    /*
     * Helper to return the header of this page
     *
     * @access public
     * @param string $title
     * @return string
     */
    public function getHeader($title = null)
    {
        return 'Vm2Mage - '.$this->__($title);
    }

    /*
     * Helper to return the menu
     *
     * @access public
     * @param null
     * @return string
     */
    public function getMenu()
    {
        return null;
    }

    /*
     * Helper to add a check to this list
     *
     * @access public
     * @param null
     * @return string
     */
    private function addResult($group, $check, $status = 0, $description = '')
    {
        $checks = $this->system_checks;
        $checks[$group][] = array(
            'check' => $this->__($check),
            'status' => $status,
            'description' => $this->__($description),
        );

        $this->system_checks = $checks;
        return;
    }

    /*
     * Check the license key
     *
     * @access public
     * @param null
     * @return string
     */
    public function getChecks()
    {
        $result = (version_compare(phpversion(), '5.2.8', '>=')) ? self::CHECK_OK : self::CHECK_ERROR;
        $this->addResult('system', 'PHP version', $result, "PHP version 5.2.8 or higher is needed. A latest PHP version is always recommended.");

        $current = ini_get('memory_limit');
        $result = (version_compare($current, '255M', '>')) ? self::CHECK_OK : self::CHECK_WARNING;
        $this->addResult('system', 'PHP memory', $result, "The minimum requirement for Magento itself is 256Mb. Current memory: ".$current);

        $result = (function_exists('json_decode')) ? self::CHECK_OK : self::CHECK_ERROR;
        $this->addResult('system', 'JSON', $result, 'The JSON-extension for PHP is needed');

        $result = (function_exists('curl_init')) ? self::CHECK_OK : self::CHECK_ERROR;
        $this->addResult('system', 'CURL', $result, 'The CURL-extension for PHP is needed');

        $result = (function_exists('simplexml_load_string')) ? self::CHECK_OK : self::CHECK_ERROR;
        $this->addResult('system', 'SimpleXML', $result, 'The SimpleXML-extension for PHP is needed');

        $result = (in_array('ssl', stream_get_transports())) ? self::CHECK_OK : self::CHECK_WARNING;
        $this->addResult('system', 'OpenSSL', $result, 'PHP support for OpenSSL is needed if you want to use HTTPS');

        $result = (function_exists('iconv')) ? self::CHECK_OK : self::CHECK_ERROR;
        $this->addResult('system', 'iconv', $result, 'The iconv-extension for PHP is needed');

        $result = (ini_get('safe_mode')) ? self::CHECK_ERROR : self::CHECK_OK;
        $this->addResult('system', 'Safe Mode', $result, 'PHP Safe Mode is strongly outdated and not supported by either Joomla! or Magento');

        $result = (ini_get('magic_quotes_gpc')) ? self::CHECK_ERROR : self::CHECK_OK;
        $this->addResult('system', 'Magic Quotes GPC', $result, 'Magic Quotes GPC is outdated and should be disabled');

        $remote_domain = 'api.yireo.com';
        $result = (@fsockopen($remote_domain, 80, $errno, $errmsg, 5)) ? self::CHECK_OK : self::CHECK_ERROR;
        $this->addResult('system', 'Firewall', $result, 'Firewall needs to allow outgoing access on port 80.');

        $logfile = Mage::helper('vm2mage')->getDebugLog();
        $result = (@is_writable($logfile)) ? self::CHECK_OK : self::CHECK_ERROR;
        $this->addResult('system', 'Logfile', $result, 'Logfile "'.$logfile.'" should be writable');

        $import_dir = Mage::getBaseDir('media').DS.'import';
        if(!is_dir($import_dir)) @mkdir($import_dir);
        $result = (@is_writable($import_dir)) ? self::CHECK_OK : self::CHECK_ERROR;
        $this->addResult('system', 'Import folder', $result, 'Import-folder "'.$import_dir.'" should be writable');

        $catalog_dir = Mage::getBaseDir('media').DS.'catalog';
        if(!is_dir($catalog_dir)) @mkdir($catalog_dir);
        $result = (@is_writable($catalog_dir)) ? self::CHECK_OK : self::CHECK_ERROR;
        $this->addResult('system', 'Catalog folder', $result, 'Catalog-folder "'.$catalog_dir.'" should be writable');

        $collection = Mage::getResourceModel('api/user_collection');
        $result = ($collection->count() > 0) ? self::CHECK_OK : self::CHECK_ERROR;
        $this->addResult('conf', 'API-user', $result, 'You should create an API-user with API resource-access');

        $config = Mage::app()->getConfig()->getModuleConfig('Yireo_Vm2Mage');
        if(!empty($config) && !empty($config->version)) {
            $version = (string)$config->version;
            $result = self::CHECK_OK;
        } else {
            $result = self::CHECK_ERROR;
            $version = '[unknown]';
        }
        $this->addResult('conf', 'Vm2Mage version', $result, 'Module version: '.$version);

        $config = Mage::app()->getConfig()->getModuleConfig('Yireo_VmOrder');
        if(!empty($config) && !empty($config->version)) {
            $version = (string)$config->version;
            $result = self::CHECK_OK;
        } else {
            $result = self::CHECK_ERROR;
            $version = '[unknown]';
        }
        $this->addResult('conf', 'VmOrder version', $result, 'Module version: '.$version);

        $count = Mage::getResourceModel('catalog/product_collection')->count();
        $this->addResult('stats', 'Products', self::CHECK_OK, 'Product collection count: '.(int)$count);

        $count = Mage::getResourceModel('catalog/category_collection')->count();
        $this->addResult('stats', 'Categories', self::CHECK_OK, 'Category collection count: '.(int)$count);

        $count = Mage::getResourceModel('customer/customer_collection')->count();
        $this->addResult('stats', 'Customers', self::CHECK_OK, 'Customer collection count: '.(int)$count);

        try {
            $collection = Mage::getResourceModel('vmorder/order_collection');
            if(empty($collection)) {
                $result = self::CHECK_ERROR;
                $count = 0;
            } else {
                $result = self::CHECK_OK;
                $count = $collection->count();
            }
        } catch(Exception $e) {
            $result = self::CHECK_ERROR;
            $count = -1;
        }
        $this->addResult('stats', 'VmOrder', $result, 'VmOrder order-collection count: '.(int)$count);

        return $this->system_checks;
    }
}
