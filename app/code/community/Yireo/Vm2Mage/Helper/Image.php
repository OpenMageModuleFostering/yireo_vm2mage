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

    public function convertImages($images)
    {
        if(!is_array($images) || empty($images)) {
            return $images;
        }
        
        $newImages = array(); 
        foreach($images as $imageIndex => $image) {

            if(empty($image['file']) && empty($image['url'])) {
                unset($images[$imageIndex]);
                continue;
            }

            if(!isset($image['md5sum'])) $image['md5sum'] = null;
            if(!isset($image['file'])) $image['file'] = null;
            if(!isset($image['type'])) $image['type'] = null;
            if(!isset($image['url'])) $image['url'] = null;
            if(!isset($image['types'])) $image['types'] = array();

            if($image['type'] == 'full_image') {
                $image['types'][] = 'image';
                $image['types'][] = 'small_image';
            }

            if($image['type'] == 'thumb_image') {
                $image['types'][] = 'thumbnail';
            }

            if($image['type'] == 'gallery') {
                $image['types'][] = 'gallery';
            }

            unset($image['type']);

            $hash = md5($image['file'].$image['url']);
            if (!isset($newImages[$hash])) {
                $newImages[$hash] = $image;
            } else {
                $newImages[$hash]['types'] = array_merge($newImages[$hash]['types'], $image['types']);
            }
        }

        return $newImages;
    }
    
    /*
     * Add all remote images to this product 
     *
     * @param Mage_Catalog_Model_Product $product
     * @param array $images
     * @return bool
     */
    public function addImages(&$product = null, $images = null)
    {
        // Option to renew images or not
        $renewImages = (bool)Mage::getStoreConfig('vm2mage/settings/renew_images');

        // Renew images
        if($renewImages) {

            // Check whether the images are already there
            $galleryImages = $product->getMediaGalleryImages();
            if(is_object($galleryImages) && $galleryImages->count() > 0) {
                Mage::helper('vm2mage')->debug('NOTICE: Removing existing images');
                $entityTypeId = Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId();
                $mediaGalleryAttribute = Mage::getModel('catalog/resource_eav_attribute')->loadByCode($entityTypeId, 'media_gallery');
                foreach ($galleryImages as $galleryImage) {
                    $mediaGalleryAttribute->getBackend()->removeImage($product, $galleryImage->getFile());
                }
                $product->save();
            }
        }

        // Detect images first
        $images = $this->convertImages($images);

        // Loop through the images and create them
        $migratedFiles = array();
        if(is_array($images) && !empty($images)) {
            foreach($images as $image) {

                if(!empty($image['md5sum']) && in_array($image['md5sum'], $migratedFiles)) continue;
                if(empty($image['label'])) $image['label'] = $product->getName();

                $imageTypes = $image['types'];

                if(count($images) == 1) {
                    $imageTypes[] = 'thumbnail';
                    $imageTypes[] = 'image';
                    $imageTypes[] = 'small_image';
                }

                $imageTypes = array_unique($imageTypes);

                $result = self::addLocalImage($product, $image['file'], $image['label'], $imageTypes);
                if($result != false) {
                    $migratedFiles[] = $image['md5sum'];
                } elseif(!empty($image['url'])) {
                    $result = self::addRemoteImage($product, $image['url'], $image['md5sum'], $image['label'], $imageTypes);
                    if($result != false) {
                        $migratedFiles[] = $image['md5sum'];
                    }
                }
            }
        }

        // Save the product
        $product->save();

        return true;
    }

    /*
     * Add a remote image to this product (and check its md5sum)
     *
     * @param Mage_Catalog_Model_Product $product
     * @param string $url
     * @param string $md5sum
     * @param string $label thumb_image|full_image|gallery
     * @return bool
     */
    public function addRemoteImage(&$product = null, $url = null, $md5sum = null, $label = null, $types = array())
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
        $tmp_basename = md5($product->getId().$url).'.'.preg_replace('/(.*)\.(gif|jpg|jpeg|png)/', '\2', strtolower($url));
        $tmp_file = $base_dir.DS.$tmp_basename;

        // Get the remote image 
        $tmp_content = self::getRemoteContent($url);
        if(empty($tmp_content)) {
            Mage::helper('vm2mage')->debug('ERROR: Image-download from '.$url.' returns empty');
            @unlink($tmp_file);
            return false;
        }

        // Write it to the temporary file
        file_put_contents($tmp_file, $tmp_content);

        // Check whether the new file exists
        if(file_exists($tmp_file) == false) {
            Mage::helper('vm2mage')->debug('ERROR: Copy new image to image-folder succeeded, but still not image', $tmp_file);
            return false;
        }

        // Check the MD5 sum of this file
        if(!empty($md5sum) && md5_file($tmp_file) != $md5sum) {
            Mage::helper('vm2mage')->debug('ERROR: image-download does not match MD5', $tmp_file);
            return false;
        }

        // Add the image to the gallery
        $product = $product->addImageToMediaGallery($tmp_file, $types, false, false);

        // Clean temporary file if needed
        if(file_exists($tmp_file)) {
            @unlink($tmp_file);
        }

        // Return the changed product-object
        return true;
    }

    /*
     * Add a remote image to this product (and check its md5sum)
     *
     * @param Mage_Catalog_Model_Product $product
     * @param string $file
     * @param string $type
     * @param string $label thumb_image|full_image|gallery
     * @return bool
     */
    public function addLocalImage(&$product = null, $file = null, $label = null, $types = array())
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

        // Check for empty images
        if(filesize($file) == 0) {
            Mage::helper('vm2mage')->debug('ERROR: Source-image has a size of 0', $file);
            return false;
        }

        // Get the remote image and write it to the temporary file
        $tmp_basename = md5($product->getId().$file).'.'.preg_replace('/(.*)\.(gif|jpg|jpeg|png)/', '\2', strtolower($file));
        $tmp_file = $base_dir.DS.$tmp_basename;
        if(@copy($file, $tmp_file) == false) {
            Mage::helper('vm2mage')->debug('ERROR: Copy new image to image-folder failed', $base_dir);
            return false;
        }

        // Check whether the new file exists
        if(file_exists($tmp_file) == false) {
            Mage::helper('vm2mage')->debug('ERROR: Copy new image to image-folder succeeded, but still not image', $tmp_file);
            return false;
        }

        // Add the image to the gallery
        Mage::helper('vm2mage')->debug('NOTICE: ['.$product->getSku().'] Adding new image', $tmp_file);
        $product = $product->addImageToMediaGallery($tmp_file, $types, false, false);

        // Clean temporary file if needed
        if(file_exists($tmp_file)) {
            @unlink($tmp_file);
        }

        // Return the changed product-object
        return true;
    }
}
