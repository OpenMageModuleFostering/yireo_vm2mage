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

$installer = $this;
$installer->startSetup();
$installer->run("
ALTER TABLE {$this->getTable('vm2mage_categories')} ADD  `migration_code` VARCHAR(32) NOT NULL AFTER `mage_id`;
");
$installer->endSetup();
