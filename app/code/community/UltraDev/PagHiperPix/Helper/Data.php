<?php

class UltraDev_PagHiperPix_Helper_Data extends Mage_Core_Helper_Abstract
{
    const XML_PATH_PAYMENT = 'payment/ultradev_paghiperpix/';

    public function getApiUrl()
    {
        return 'https://pix.paghiper.com/';
    }

    public function getApiKey()
    {
        return trim((string) Mage::getStoreConfig(self::XML_PATH_PAYMENT . 'apikey'));
    }

    public function getToken()
    {
        return trim((string) Mage::getStoreConfig(self::XML_PATH_PAYMENT . 'token'));
    }

    public function getConfig($field)
    {
        return Mage::getStoreConfig(self::XML_PATH_PAYMENT . $field);
    }

    public function getDaysDueDate()
    {
        $days = (int) $this->getConfig('days_due_date');
        return $days > 0 ? $days : 1;
    }

    public function getPollingInterval()
    {
        $seconds = (int) $this->getConfig('polling_interval');
        return $seconds >= 3 ? $seconds : 6;
    }

    public function getNotificationUrl()
    {
        return Mage::getUrl('paghiperpix/order/update', ['_secure' => Mage::app()->getStore()->isCurrentlySecure()]);
    }

    public function getCheckStatusUrl()
    {
        return Mage::getUrl('paghiperpix/order/checkstatus', ['_secure' => Mage::app()->getStore()->isCurrentlySecure()]);
    }

    public function log($message, $level = null)
    {
        Mage::log($message, $level, 'paghiperpix.log');
    }
}
