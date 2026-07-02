<?php

/**
 * $this é uma instância de Mage_Sales_Model_Resource_Setup,
 * conforme declarado em etc/config.xml (<resources><ultradev_paghiperpix_setup><setup><class>).
 *
 * @var Mage_Sales_Model_Resource_Setup $this
 */
$this->startSetup();

$attributes = [
    'paghiperpix_transactionid' => 'PagHiper Pix Transaction ID',
    'paghiperpix_qrcodebase64'  => 'PagHiper Pix QR Code (base64)',
    'paghiperpix_qrcodeurl'     => 'PagHiper Pix QR Code Image URL',
    'paghiperpix_emv'           => 'PagHiper Pix Copia e Cola (EMV)',
    'paghiperpix_status'        => 'PagHiper Pix Status',
    'paghiperpix_duedate'       => 'PagHiper Pix Vencimento',
];

foreach ($attributes as $code => $label) {
    if (!$this->getAttribute(Mage_Sales_Model_Order::ENTITY, $code, 'attribute_id')) {
        $this->addAttribute(Mage_Sales_Model_Order::ENTITY, $code, [
            'type'             => 'text',
            'input'            => 'textarea',
            'backend'          => '',
            'frontend'         => '',
            'label'            => $label,
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
}

$this->endSetup();
