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
                $value = preg_replace('/^V2M___/', '', $value);
                $value = base64_decode($value);
            }

            if(!empty($value)) {
                $array[$name] = $value;
            }
        }
        return $array;
    }
}
