<?php

class UltraDev_PagHiperPix_OrderController extends Mage_Core_Controller_Front_Action
{
    /**
     * Recebe a notificação inicial da PagHiper (POST simples, não-JSON),
     * responde com o token do lojista para obter o status completo,
     * e atualiza o pedido no Magento.
     *
     * Antes de chamar a API da PagHiper, valida que o transaction_id
     * recebido já pertence a um pedido gerado por este módulo — evita
     * que POSTs arbitrários (transaction_id inventado) gerem chamadas
     * de saída desnecessárias para a PagHiper.
     *
     * Rota: /paghiperpix/order/update
     */
    public function updateAction()
    {
        $raw = file_get_contents('php://input');
        $raw = preg_replace("/\r|\n/", '', $raw);
        parse_str($raw, $data);

        if (empty($data['transaction_id'])) {
            // fallback: alguns disparos podem vir como JSON puro
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $data = $json;
            }
        }

        $this->helper()->log('PagHiperPix - notificação recebida: ' . json_encode($data));

        if (empty($data['transaction_id'])) {
            $this->getResponse()->setHttpResponseCode(400);
            $this->getResponse()->setBody(json_encode(['error' => 'transaction_id ausente']));
            return;
        }

        $knownOrder = $this->orderHelper()->findOrderByTransactionId($data['transaction_id']);

        if (!$knownOrder) {
            $this->helper()->log(
                'PagHiperPix - notificação descartada: transaction_id ' . $data['transaction_id'] . ' não corresponde a nenhum pedido conhecido.'
            );
            $this->getResponse()->setHttpResponseCode(404);
            $this->getResponse()->setBody(json_encode(['error' => 'transaction_id desconhecido']));
            return;
        }

        $statusRequest = $this->orderHelper()->respondNotification($data);

        if (!$statusRequest || ($statusRequest['result'] ?? null) !== 'success') {
            $this->helper()->log('PagHiperPix - falha ao confirmar notificação: ' . json_encode($statusRequest));
            $this->getResponse()->setHttpResponseCode(400);
            $this->getResponse()->setBody(json_encode(['error' => 'não foi possível confirmar a notificação']));
            return;
        }

        $orderId = $statusRequest['order_id'] ?? null;
        $order = $orderId ? Mage::getModel('sales/order')->loadByIncrementId($orderId) : null;

        if ($order && $order->getId()) {
            $this->orderHelper()->applyStatus($order, $statusRequest);
        }

        $this->getResponse()->setHttpResponseCode(200);
        $this->getResponse()->setBody(json_encode(['success' => true]));
    }

    /**
     * Endpoint de polling usado pelo JS da página de sucesso para saber
     * se o Pix já foi pago, sem precisar recarregar a página.
     *
     * Rota: /paghiperpix/order/checkstatus?order_id=INCREMENT_ID
     */
    public function checkstatusAction()
    {
        $this->getResponse()->setHeader('Content-Type', 'application/json', true);

        $incrementId = $this->getRequest()->getParam('order_id');
        $sessionOrderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();

        // Só permite consultar o próprio pedido da sessão de checkout atual.
        if (!$incrementId || $incrementId !== $sessionOrderId) {
            $this->getResponse()->setHttpResponseCode(403);
            $this->getResponse()->setBody(json_encode(['error' => 'pedido inválido']));
            return;
        }

        $order = Mage::getModel('sales/order')->loadByIncrementId($incrementId);

        if (!$order || !$order->getId()) {
            $this->getResponse()->setHttpResponseCode(404);
            $this->getResponse()->setBody(json_encode(['error' => 'pedido não encontrado']));
            return;
        }

        $status = $order->getData('paghiperpix_status');
        $paid = in_array($status, ['paid', 'completed'], true)
            || $order->getState() === Mage_Sales_Model_Order::STATE_PROCESSING;

        $this->getResponse()->setBody(json_encode([
            'paid'          => $paid,
            'status'        => $status ?: 'pending',
            'order_state'   => $order->getState(),
        ]));
    }

    protected function helper()
    {
        return Mage::helper('ultradev_paghiperpix');
    }

    protected function orderHelper()
    {
        return Mage::helper('ultradev_paghiperpix/order');
    }
}
