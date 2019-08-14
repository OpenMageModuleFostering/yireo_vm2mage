<?php
/**
 * Yireo Vm2Mage for Magento 
 *
 * @author Yireo
 * @package Vm2Mage
 * @copyright Copyright 2013
 * @license Open Source License
 * @link http://www.yireo.com
 */

/**
 * Vm2Mage helper
 */
class Yireo_Vm2Mage_Helper_Image extends Yireo_Vm2Mage_Helper_Data
{
    /*
     * Get remote content
     *
     * @param string $url
     * @return string
     */
    public function getRemoteContent($url = null)
    {
        if(empty($url)) {
            return null;
        }

        if(function_exists('curl_init')) {
            $conn = curl_init($url) ;
            curl_setopt($conn, CURLOPT_HEADER, 0);
            curl_setopt($conn, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($conn, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($conn, CURLOPT_SSL_VERIFYPEER, 0);
            $content = curl_exec($conn);
            curl_close($conn);
            return $content;
    
        } elseif(ini_get( 'allow_url_fopen')) {
            $content = file_get_contents($url);
            return $content;
        }

        Mage::helper('vm2mage')->debug('No download-methods available');
        return null;
    }
    
    /*
     * Add all remote images to this product 
     *
     * @param Mage_Catalog_Model_Product $product
     * @param array $images
     * @return Mage_Catalog_Model_Product $product
     */
    public function addImages($product = null, $images = null)
    {
        // Option to renew images or not
        $renewImages = (bool)Mage::getStoreConfig('vm2mage/settings/renew_images');

        // Renew images
        if($renewImages) {
            Mage::helper('vm2mage')->debug('NOTICE: Removing existing images');

            // Check whether the images are already there
            $galleryImages = $product->getMediaGalleryImages();
            if(is_object($galleryImages) && $galleryImages->count() >= count($images)) {
                $entityTypeId = Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId();
                $mediaGalleryAttribute = Mage::getModel('catalog/resource_eav_attribute')->loadByCode($entityTypeId, 'media_gallery');
                foreach ($galleryImages as $galleryImage) {
                    $mediaGalleryAttribute->getBackend()->removeImage($product, $galleryImage->getFile());
                }
                $product->save();
            }

            // Loop through the images and create them
            if(is_array($images) && !empty($images)) {
                foreach($images as $image) {

                    if(empty($image['label'])) $image['label'] = $product->getName();
                    if(empty($image['file'])) $image['file'] = null;
                    if(empty($image['md5sum'])) $image['md5sum'] = null;

                    $imageTypes = array();
                    if($image['type'] == 'full_image') {
                        $imageTypes[] = 'image';
                        $imageTypes[] = 'small_image';
                        if(count($images) == 1) $imageTypes[] = 'thumbnail';
                    } elseif($image['type'] == 'thumb_image') {
                        $imageTypes[] = 'thumbnail';
                    }

                    $result = self::addLocalImage($product, $image['file'], $image['label'], $imageTypes);
                    if($result != false) {
                        $product = $result;
                    } else {
                        $result = self::addRemoteImage($product, $image['url'], $image['md5sum'], $image['type'], $image['label'], $imageTypes);
                        if($result != false) {
                            $product = $result;
                        }
                    }
                }
            }
        }

        return $product;
    }

    /*
     * Add a remote image to this product (and check its md5sum)
     *
     * @param Mage_Catalog_Model_Product $product
     * @param string $url
     * @param string $md5sum
     * @param string $type
     * @param string $label thumb_image|full_image|gallery
     * @return Mage_Catalog_Model_Product $product
     */
    public function addRemoteImage($product = null, $url = null, $md5sum = null, $type = null, $label = null, $types = array())
    {

        // Try to create the import-directory it it does not exist
        $base_dir = Mage::getBaseDir('media').DS.'import';
        if(!is_dir($base_dir)) @mkdir($base_dir);

        // If this fails, return without creating images
        if(!is_dir($base_dir)) {
            Mage::helper('vm2mage')->debug('ERROR: Image folder does not exist', $base_dir);
            return false;
        }

        // Create a temporary file
        $tmp_file = $base_dir.DS.basename($url);

        // Get the remote image 
        $tmp_content = self::getRemoteContent($url);
        if(empty($tmp_content)) {
            Mage::helper('vm2mage')->debug('ERROR: Image-download from '.$url.' returns empty');
            @unlink($tmp_file);
            return false;
        }

        // Write it to the temporary file
        file_put_contents($tmp_file, $tmp_content);

        // Check the MD5 sum of this file
        if(!empty($md5sum) && md5_file($tmp_file) != $md5sum) {
            Mage::helper('vm2mage')->debug('ERROR: image-download does not match MD5', $tmp_file);
            return false;
        }

        // Add the image to the gallery
        $product = $product->addImageToMediaGallery($tmp_file, $types, true, false);

        // Clean temporary file if needed
        if(file_exists($tmp_file)) {
            unlink($tmp_file);
        }

        // Return the changed product-object
        return $product;
    }

    /*
     * Add a remote image to this product (and check its md5sum)
     *
     * @param Mage_Catalog_Model_Product $product
     * @param string $file
     * @param string $type
     * @param string $label thumb_image|full_image|gallery
     * @return Mage_Catalog_Model_Product $product
     */
    public function addLocalImage($product = null, $file = null, $label = null, $types = array())
    {
        // Check if local-image-loading is enabled
        if(Mage::getStoreConfig('vm2mage/settings/local_images') == 0) {
            return false;
        }

        // Try to create the import-directory it it does not exist
        $base_dir = Mage::getBaseDir('media').DS.'import';
        if(is_dir($base_dir) == false) {
            @mkdir($base_dir);
        } 

        // If this fails, return without creating images
        if(is_dir($base_dir) == false) {
            Mage::helper('vm2mage')->debug('ERROR: Image folder does not exist', $base_dir);
            return false;
        }

        $readable = false;
        try { $readable = @is_readable($file); } catch(Exception $e) {}
        if($readable == false) {
            Mage::helper('vm2mage')->debug('ERROR: Image is not readable', $file);
            return false;
        }

        // Create a temporary file
        $tmp_file = $base_dir.DS.basename($file);

        // Get the remote image and write it to the temporary file
        if(@copy($file, $tmp_file) == false) {
            Mage::helper('vm2mage')->debug('ERROR: Copy new image to image-folder failed', $base_dir);
            return false;
        }

        // Add the image to the gallery
        $product = $product->addImageToMediaGallery($tmp_file, $types, true, false);

        // Clean temporary file if needed
        if(file_exists($tmp_file)) {
            unlink($tmp_file);
        }

        // Return the changed product-object
        return $product;
    }
}
