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

$installer = $this;
$installer->startSetup();
$installer->run("
CREATE TABLE IF NOT EXISTS {$this->getTable('vm2mage_categories')} (
  `vm_id` int(11) NOT NULL,
  `mage_id` int(11) NOT NULL,
  UNIQUE KEY `vm_id` (`vm_id`,`mage_id`)
) ENGINE=MyISAM;

");
$installer->endSetup();
