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
class Yireo_Vm2Mage_Helper_Data extends Mage_Core_Helper_Abstract
{
    /*
     * Helper-method to quickly log a debug-entry
     *
     * @param string $string
     * @param mixed $mixed
     * @return null
     */
    public function stringtoMethod($string = null, $prefix = 'set')
    {
        if(empty($string) || is_numeric($string) || !strlen($string) > 2) {
            return false;
        }

        return $prefix.ucfirst($string);
    }

    /*
     * Helper-method to return the log filename
     *
     * @param null
     * @return string
     */
    public function getDebugLog()
    {
        // Try to create the log-directory if it does not exist
        $log_dir = Mage::getBaseDir().DS.'var'.DS.'log';
        if(!is_dir($log_dir) && !is_writable($log_dir)) {
            @mkdir(Mage::getBaseDir().DS.'var');
            @mkdir($log_dir);
        }

        $log_file = $log_dir.DS.'vm2mage.log';
        if(!file_exists($log_file)) {
            @touch($log_file);
        }
        
        return $log_file;
    }

    /*
     * Helper-method to quickly log a debug-entry
     *
     * @param string $string
     * @param mixed $mixed
     * @return null
     */
    public function debug($string, $mixed = null)
    {
        // Disable by setting
        if(Mage::getStoreConfig('vm2mage/settings/debug_log') == 0) {
            return false;
        }

        // Construct the debug-string
        if($mixed) {
            $string .= ': '.var_export($mixed, true);
        }

        $logfile = self::getDebugLog();
        if(@is_writable($logfile)) {
            @file_put_contents($logfile, $string."\n", FILE_APPEND);
        }
    }

    /*
     * Helper-method to initialize debugging
     *
     * @param string $string
     * @param mixed $mixed
     * @return null
     */
    public function initDebug()
    {
        // Disable by setting
        if(Mage::getStoreConfig('vm2mage/settings/debug_log') == 0) {
            return false;
        }

        ini_set('display_errors', 1);
        Mage::setIsDeveloperMode(true);
        return true;
    }

    /*
     * Recursive function to encode a value
     */
    public function encode($array = null)
    {
        foreach($array as $name => $value) {
            if(is_array($value)) {
                $value = Mage::helper('vm2mage')->encode($value);
            } elseif(!empty($value)) {
                $value = base64_encode($value);
            }

            $array[$name] = $value;
        }
        return $array;
    }

    /*
     * Recursive function to decode a value
     */
    public function decode($array = null)
    {
        foreach($array as $name => $value) {
            if(is_array($value)) {
                $value = Mage::helper('vm2mage')->decode($value);
            } elseif(!empty($value) && preg_match('/^V2M___/', $value)) {
                $value = Mage::helper('vm2mage')->decodeString($value);
            }

            $name = Mage::helper('vm2mage')->decodeString($name);
            if(!empty($value)) {
                $array[$name] = $value;
            }
        }
        return $array;
    }

    /*
     * Recursive function to decode a value
     */
    public function decodeString($string = null)
    {
        if(!empty($string) && preg_match('/^V2M___/', $string)) {
            $string = preg_replace('/^V2M___/', '', $string);
            $string = base64_decode($string);
        }

        return $string;
    }
}
