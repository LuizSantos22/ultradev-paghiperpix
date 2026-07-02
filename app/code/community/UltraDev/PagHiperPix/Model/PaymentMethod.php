<?php

class UltraDev_PagHiperPix_Model_PaymentMethod extends Mage_Payment_Model_Method_Abstract
{
    protected $_code            = 'ultradev_paghiperpix';
    protected $_formBlockType   = 'ultradev_paghiperpix/form';
    protected $_infoBlockType   = 'ultradev_paghiperpix/info';

    protected $_canOrder             = true;
    protected $_isInitializeNeeded   = false;
    protected $_canAuthorize         = false;
    protected $_canCapture           = false;
    protected $_allowCurrencyCode    = ['BRL'];

    public function getInformation()
    {
        return $this->getConfigData('information');
    }

    /**
     * Chamado pelo core do Magento (payment_action = order) ao fechar o pedido.
     * Gera a cobrança Pix na PagHiper e salva QR code / EMV no pedido.
     *
     * @param Varien_Object $payment
     * @param float $amount
     * @return $this
     */
    public function order(Varien_Object $payment, $amount)
    {
        if (!$this->canOrder()) {
            return $this;
        }

        $order = $payment->getOrder();
        $orderIncrementId = $order->getIncrementId();

        $data = $this->helper()->createOrderArray($orderIncrementId);

        if (empty($data)) {
            Mage::throwException('Não foi possível montar os dados do pedido para gerar o Pix.');
        }

        $result = $this->helper()->generate($data);

        if (empty($result['success'])) {
            Mage::throwException(
                $result['message'] ?? 'Não foi possível gerar o Pix. Tente novamente em instantes.'
            );
        }

        foreach ($result['additional'] as $key => $value) {
            $payment->setAdditionalInformation($key, $value);
        }

        return $this;
    }

    protected function helper()
    {
        return Mage::helper('ultradev_paghiperpix/order');
    }
}
