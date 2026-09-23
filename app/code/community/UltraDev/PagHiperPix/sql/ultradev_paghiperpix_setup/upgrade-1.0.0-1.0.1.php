<?php

/** @var Mage_Sales_Model_Resource_Setup $this */
$this->startSetup();

if (!$this->getAttribute(Mage_Sales_Model_Order::ENTITY, 'paghiperpix_viewurl', 'attribute_id')) {
    $this->addAttribute(Mage_Sales_Model_Order::ENTITY, 'paghiperpix_viewurl', [
        'type'             => 'text',
        'input'            => 'textarea',
        'backend'          => '',
        'frontend'         => '',
        'label'            => 'PagHiper Pix - Link de visualização',
        'class'            => '',
        'global'           => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'visible'          => false,
        'required'         => false,
        'user_defined'     => false,
        'default'          => '',
        'searchable'       => false,
        'filterable'       => false,
        'comparable'       => false,
        'visible_on_front' => false,
        'unique'           => false,
    ]);
}

$this->endSetup();
