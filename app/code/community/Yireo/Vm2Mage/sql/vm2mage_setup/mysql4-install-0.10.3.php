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

$installer = $this;
$installer->startSetup();
$installer->run("
CREATE TABLE IF NOT EXISTS {$this->getTable('vm2mage_categories')} (
  `vm_id` int(11) NOT NULL,
  `mage_id` int(11) NOT NULL,
  `migration_code` varchar(32) NOT NULL,
  UNIQUE KEY `vm_id` (`vm_id`,`mage_id`, `migration_code`)
) ENGINE=MyISAM;

");
$installer->endSetup();
