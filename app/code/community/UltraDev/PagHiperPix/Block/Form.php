<?php

class UltraDev_PagHiperPix_Block_Form extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('ultradev/paghiperpix/payment/form/paghiperpix.phtml');
    }
}
