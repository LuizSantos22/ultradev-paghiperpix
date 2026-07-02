<?php

class UltraDev_PagHiperPix_Helper_Order extends Mage_Core_Helper_Abstract
{
    /**
     * Monta o array de dados exigido pelo endpoint pix.paghiper.com/invoice/create/
     *
     * @param string $orderIncrementId
     * @return array
     */
    public function createOrderArray($orderIncrementId)
    {
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);

        if (!$order || !$order->getId()) {
            return [];
        }

        $address = $order->getBillingAddress();
        $taxvat = $order->getCustomerTaxvat() ?: $address->getVatId();

        $data = [
            'order_id'           => $order->getIncrementId(),
            'payer_email'        => $order->getCustomerEmail(),
            'payer_name'         => $order->getCustomerName() ?: $address->getName(),
            'payer_cpf_cnpj'     => preg_replace('/\D/', '', (string) $taxvat),
            'payer_phone'        => preg_replace('/\D/', '', (string) $address->getTelephone()),
            'notification_url'   => $this->helper()->getNotificationUrl(),
            'discount_cents'     => (int) round($order->getDiscountAmount() * -1 * 100),
            'shipping_price_cents' => (int) round($order->getShippingAmount() * 100),
            'shipping_methods'   => $order->getShippingDescription(),
            'fixed_description'  => false,
            'days_due_date'      => $this->helper()->getDaysDueDate(),
            'items'              => [],
        ];

        foreach ($order->getAllVisibleItems() as $item) {
            $data['items'][] = [
                'description' => $item->getName(),
                'quantity'    => (int) ($item->getQtyToShip() ?: $item->getQtyOrdered() ?: 1),
                'item_id'     => $item->getSku(),
                'price_cents' => (int) round($item->getPrice() * 100),
            ];
        }

        return $data;
    }

    /**
     * Cria a cobrança Pix na PagHiper (pix.paghiper.com/invoice/create/)
     *
     * @param array $data
     * @return array ['success' => bool, 'additional' => array, 'message' => string]
     */
    public function generate($data)
    {
        $data['apiKey'] = $this->helper()->getApiKey();

        $url = $this->helper()->getApiUrl() . 'invoice/create/';

        $response = $this->request($url, $data);

        if (!$response || !isset($response['pix_create_request'])) {
            $this->helper()->log('PagHiperPix - resposta inválida ao criar Pix: ' . json_encode($response));
            return ['success' => false, 'message' => 'Resposta inválida da PagHiper'];
        }

        $result = $response['pix_create_request'];

        if (($result['result'] ?? null) !== 'success') {
            $this->helper()->log('PagHiperPix - erro ao criar Pix #' . $data['order_id'] . ': ' . ($result['response_message'] ?? 'erro desconhecido'));
            return ['success' => false, 'message' => $result['response_message'] ?? 'Erro ao gerar o Pix'];
        }

        $pixCode = $result['pix_code'] ?? [];

        $additional = [
            'paghiperpix_transactionid' => $result['transaction_id'] ?? '',
            'paghiperpix_qrcodebase64'  => $pixCode['qrcode_base64'] ?? '',
            'paghiperpix_qrcodeurl'     => $pixCode['qrcode_image_url'] ?? '',
            'paghiperpix_emv'           => $pixCode['emv'] ?? '',
            'paghiperpix_status'        => $result['status'] ?? 'pending',
            'paghiperpix_duedate'       => $result['due_date'] ?? '',
        ];

        $order = Mage::getModel('sales/order')->loadByIncrementId($data['order_id']);
        $this->addInformation($order, $additional);

        return ['success' => true, 'additional' => $additional];
    }

    /**
     * Consulta o status atual de uma transação (pix.paghiper.com/invoice/status/)
     *
     * @param string $transactionId
     * @return array|null
     */
    public function checkStatus($transactionId)
    {
        $url = $this->helper()->getApiUrl() . 'invoice/status/';

        $data = [
            'token'          => $this->helper()->getToken(),
            'apiKey'         => $this->helper()->getApiKey(),
            'transaction_id' => $transactionId,
        ];

        $response = $this->request($url, $data);

        if (!$response || !isset($response['status_request'])) {
            return null;
        }

        return $response['status_request'];
    }

    /**
     * Responde a notificação inicial da PagHiper com o token do lojista,
     * conforme exigido pelo fluxo de notificação de status do Pix.
     *
     * @param array $notificationData Dados recebidos no POST inicial (apiKey, notification_id, transaction_id, notification_date, source_api)
     * @return array|null status_request completo devolvido pela PagHiper
     */
    public function respondNotification($notificationData)
    {
        $url = $this->helper()->getApiUrl() . 'invoice/notification/';

        $data = [
            'token'             => $this->helper()->getToken(),
            'apiKey'            => $notificationData['apiKey'] ?? $this->helper()->getApiKey(),
            'transaction_id'    => $notificationData['transaction_id'] ?? '',
            'notification_id'   => $notificationData['notification_id'] ?? '',
        ];

        $response = $this->request($url, $data);

        if (!$response || !isset($response['status_request'])) {
            return null;
        }

        return $response['status_request'];
    }

    /**
     * Aplica o status/transação retornados pela PagHiper no pedido Magento.
     *
     * @param Mage_Sales_Model_Order $order
     * @param array $statusRequest
     */
    public function applyStatus($order, array $statusRequest)
    {
        if (!$order || !$order->getId()) {
            return;
        }

        $status = $statusRequest['status'] ?? null;
        $pixCode = $statusRequest['pix_code'] ?? [];

        $additional = ['paghiperpix_status' => $status];

        if (!empty($pixCode['qrcode_base64'])) {
            $additional['paghiperpix_qrcodebase64'] = $pixCode['qrcode_base64'];
        }
        if (!empty($pixCode['emv'])) {
            $additional['paghiperpix_emv'] = $pixCode['emv'];
        }
        if (!empty($pixCode['qrcode_image_url'])) {
            $additional['paghiperpix_qrcodeurl'] = $pixCode['qrcode_image_url'];
        }

        $this->addInformation($order, $additional);

        if (
            in_array($status, ['paid', 'completed'], true)
            && $order->getInvoiceCollection()->count() <= 0
        ) {
            $order->setState(
                Mage_Sales_Model_Order::STATE_PROCESSING,
                true,
                'Pedido aprovado via Pix (PagHiper).',
                true
            )->save();

            if ($order->canInvoice()) {
                $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
                $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
                $invoice->register();
                $invoice->save();

                Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder())
                    ->save();
            }
        } elseif ($status === 'canceled' && $order->getState() === Mage_Sales_Model_Order::STATE_NEW) {
            if ($order->canCancel()) {
                $order->cancel()->save();
            }
        }
    }

    public function addInformation($order, array $additional)
    {
        if ($order && $order->getId() && count($additional) >= 1) {
            $payment = $order->getPayment();
            foreach ($additional as $key => $value) {
                $order->setData($key, $value);
                $payment->setAdditionalInformation($key, $value);
            }
            $payment->save();
            $order->save();
        }
    }

    /**
     * Executa a chamada HTTP POST (JSON) para a API da PagHiper.
     *
     * @param string $url
     * @param array $data
     * @return array|null
     */
    protected function request($url, array $data)
    {
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json; charset=utf-8',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

        $result = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($errno || $result === false) {
            $this->helper()->log('PagHiperPix - erro cURL ao chamar ' . $url . ': ' . $error);
            return null;
        }

        $decoded = json_decode($result, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->helper()->log('PagHiperPix - resposta não é JSON válido de ' . $url . ': ' . $result);
            return null;
        }

        return $decoded;
    }

    protected function helper()
    {
        return Mage::helper('ultradev_paghiperpix');
    }
}
