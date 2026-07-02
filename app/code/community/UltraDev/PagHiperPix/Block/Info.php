<?php

class UltraDev_PagHiperPix_Block_Info extends Mage_Payment_Block_Info
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('ultradev/paghiperpix/payment/info/paghiperpix.phtml');
    }
}
