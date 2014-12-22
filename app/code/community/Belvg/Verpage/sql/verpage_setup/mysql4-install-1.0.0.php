<?php
/**
 * BelVG LLC.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the EULA
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://store.belvg.com/BelVG-LICENSE-COMMUNITY.txt
 *
 *******************************************************************
 * @category   Belvg
 * @package    Belvg_Verpage
 * @version    1.0.2
 * @copyright  Copyright (c) 2010 - 2013 BelVG LLC. (http://www.belvg.com)
 * @license    http://store.belvg.com/BelVG-LICENSE-COMMUNITY.txt
 */
$installer = $this;
$installer->startSetup();
$entityTypes = array('catalog_category', 'catalog_product');
foreach ($entityTypes as $type) {
    $entityTypeId     = $installer->getEntityTypeId($type);
    $attributeSetId   = $installer->getDefaultAttributeSetId($entityTypeId);
    $attributeGroupId = $installer->getDefaultAttributeGroupId($entityTypeId, $attributeSetId);
    $installer->addAttribute($type, 'verpage_' . $type, array(
        'type'     => 'int',
        'label'    => 'Verification Page',
        'input'    => 'select',
        'source'   => 'eav/entity_attribute_source_boolean',
        'global'   => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'visible'           => TRUE,
        'required'          => FALSE,
        'user_defined'      => FALSE,
        'default'           => 0
    ));
    $installer->addAttributeToGroup(
        $entityTypeId,
        $attributeSetId,
        $attributeGroupId,
        'verpage_' . $type,
        '999'
    );
}

$installer->endSetup();