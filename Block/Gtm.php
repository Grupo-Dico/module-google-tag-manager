<?php
declare(strict_types=1);

namespace GDMexico\GoogleTagManager\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use GDMexico\GoogleTagManager\Helper\Data;
use GDMexico\GoogleTagManager\Model\DataLayer;

class Gtm extends Template
{
    private Data $helper;
    private DataLayer $dataLayer;

    public function __construct(
        Context $context,
        Data $helper,
        DataLayer $dataLayer,
        array $data = []
    ) {
        $this->helper = $helper;
        $this->dataLayer = $dataLayer;
        parent::__construct($context, $data);
    }

    public function isEnabled(): bool
    {
        return $this->helper->isEnabled();
    }

    public function getGtmId(): string
    {
        return $this->helper->getGtmId();
    }

    public function getDataLayer(): DataLayer
    {
        return $this->dataLayer;
    }

    public function getHelper(): Data
    {
        return $this->helper;
    }

    /**
     * Solo enviar purchase al dataLayer en páginas de confirmación de pedido.
     * Evita disparar purchase en /checkout/cart u otras vistas que cargan el mismo bloque GTM vía default.xml.
     */
    public function isPurchaseOutputAllowedForRequest(): bool
    {
        $name = $this->getRequest()->getFullActionName();
        $allowed = [
            'checkout_onepage_success',
            'checkout_multishipping_success',
            'custom_success_creditcard',
            'custom_success_paypal',
            'custom_success_cash',
            'custom_success_banktransfer',
        ];
        return in_array($name, $allowed, true);
    }
    /**
     * Genera y guarda datos de purchase para una orden
     * Método de respaldo para páginas de success personalizadas
     *
     * @param \Magento\Sales\Model\Order $order
     * @return bool
     */
    public function generatePurchaseData($order): bool
    {
        if (!$this->isEnabled() || !$this->helper->isTrackPurchaseEnabled()) {
            return false;
        }

        if (!$this->isPurchaseOutputAllowedForRequest()) {
            return false;
        }

        if (!$order || !$order->getId()) {
            return false;
        }

        try {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $session = $objectManager->get(\Magento\Framework\Session\SessionManagerInterface::class);
            $customerSession = $objectManager->get(\Magento\Customer\Model\Session::class);
            $checkoutSession = $objectManager->get(\Magento\Checkout\Model\Session::class);
            $logger = $objectManager->get(\GDMexico\GoogleTagManager\Logger\Logger::class);

            // Verificar si ya hay datos
            $existingPurchaseData = $session->getGtmPurchase() ?:
                $customerSession->getGtmPurchase() ?:
                    $checkoutSession->getGtmPurchase();

            if ($existingPurchaseData) {
                return true;
            }

            // Verificar si ya se procesó esta orden
            $sessionKey = 'gtm_processed_order_' . $order->getId();
            $alreadyProcessed = $session->getData($sessionKey) ||
                $customerSession->getData($sessionKey) ||
                $checkoutSession->getData($sessionKey);

            if ($alreadyProcessed) {
                return true;
            }

            // Obtener datos de la orden usando nuestro DataLayer
            $orderData = $this->dataLayer->getOrderData($order);

            // Construir evento GA4 purchase
            $gtmData = [
                'event' => 'purchase',
                'ecommerce' => $orderData
            ];

            $gtmDataJson = json_encode($gtmData, JSON_UNESCAPED_UNICODE);

            // Guardar en MÚLTIPLES sesiones
            $session->setGtmPurchase($gtmDataJson);
            $customerSession->setGtmPurchase($gtmDataJson);
            $checkoutSession->setGtmPurchase($gtmDataJson);

            // MARCAR COMO PROCESADA EN TODAS LAS SESIONES
            $session->setData($sessionKey, true);
            $customerSession->setData($sessionKey, true);
            $checkoutSession->setData($sessionKey, true);

            return true;

        } catch (\Exception $e) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $logger = $objectManager->get(\GDMexico\GoogleTagManager\Logger\Logger::class);
            $logger->error('GTM Block Error generando purchase data: ' . $e->getMessage());
            return false;
        }
    }
}