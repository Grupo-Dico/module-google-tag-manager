<?php
declare(strict_types=1);

namespace GDMexico\GoogleTagManager\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Checkout\Model\Session as CheckoutSession;
use GDMexico\GoogleTagManager\Model\DataLayer;
use GDMexico\GoogleTagManager\Helper\Data;
use Psr\Log\LoggerInterface;

class CheckoutSuccessObserver implements ObserverInterface
{
    private SessionManagerInterface $session;
    private CustomerSession $customerSession;
    private CheckoutSession $checkoutSession;
    private DataLayer $dataLayer;
    private Data $helper;
    private LoggerInterface $logger;

    public function __construct(
        SessionManagerInterface $session,
        CustomerSession $customerSession,
        CheckoutSession $checkoutSession,
        DataLayer $dataLayer,
        Data $helper,
        LoggerInterface $logger
    ) {
        $this->session = $session;
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
        $this->dataLayer = $dataLayer;
        $this->helper = $helper;
        $this->logger = $logger;
    }

    public function execute(Observer $observer)
    {
        if (!$this->helper->isEnabled()) {
            return;
        }

        if (!$this->helper->isTrackPurchaseEnabled()) {
            return;
        }

        try {
            $order = null;

            // MÉTODO 1: Obtener orden del evento (sales_order_place_after)
            if ($observer->getEvent()->getOrder()) {
                $order = $observer->getEvent()->getOrder();
            }

            // MÉTODO 2: Obtener de checkout session (checkout_onepage_controller_success_action)
            if (!$order) {
                $order = $this->checkoutSession->getLastRealOrder();
            }

            // MÉTODO 3: Buscar en quote del evento (checkout_submit_all_after)
            if (!$order && $observer->getEvent()->getQuote()) {
                $quote = $observer->getEvent()->getQuote();
                if ($quote && $quote->getReservedOrderId()) {
                    $orderFactory = \Magento\Framework\App\ObjectManager::getInstance()
                        ->get(\Magento\Sales\Model\OrderFactory::class);
                    $order = $orderFactory->create()->loadByIncrementId($quote->getReservedOrderId());
                }
            }

            if (!$order || !$order->getId()) {
                return;
            }

            // PREVENCIÓN DE DUPLICADOS MEJORADA
            $sessionKey = 'gtm_processed_order_' . $order->getId();

            // Verificar en TODAS las sesiones si ya se procesó
            $alreadyProcessed = $this->session->getData($sessionKey) ||
                $this->customerSession->getData($sessionKey) ||
                $this->checkoutSession->getData($sessionKey);

            if ($alreadyProcessed) {
                $this->logger->info('GTM CheckoutSuccess: Orden ' . $order->getIncrementId() . ' ya procesada anteriormente - SALTANDO duplicado');
                return;
            }

            $this->logger->info('GTM CheckoutSuccess: Procesando orden: ' . $order->getIncrementId() . ' (ID: ' . $order->getId() . ') - Evento: ' . $eventName);

            // Obtener datos de la orden usando nuestro DataLayer
            $orderData = $this->dataLayer->getOrderData($order);

            // Construir evento GA4 purchase
            $gtmData = [
                'event' => 'purchase',
                'ecommerce' => $orderData
            ];

            $this->logger->info('GTM CheckoutSuccess: Datos GTM creados para orden: ' . $orderData['transaction_id']);
            $this->logger->info('GTM CheckoutSuccess: Total items: ' . count($orderData['items']));
            $this->logger->info('GTM CheckoutSuccess: Valor total: ' . $orderData['value'] . ' ' . $orderData['currency']);

            $gtmDataJson = json_encode($gtmData, JSON_UNESCAPED_UNICODE);

            // Guardar en MÚLTIPLES sesiones
            try {
                $this->session->setGtmPurchase($gtmDataJson);
                $this->logger->info('GTM CheckoutSuccess: Datos guardados en session estándar');
            } catch (\Exception $e) {
                $this->logger->warning('GTM CheckoutSuccess: Error guardando en session estándar: ' . $e->getMessage());
            }
            
            try {
                $this->customerSession->setGtmPurchase($gtmDataJson);
                $this->logger->info('GTM CheckoutSuccess: Datos guardados en customerSession');
            } catch (\Exception $e) {
                $this->logger->warning('GTM CheckoutSuccess: Error guardando en customerSession: ' . $e->getMessage());
            }
            
            try {
                $this->checkoutSession->setGtmPurchase($gtmDataJson);
                $this->logger->info('GTM CheckoutSuccess: Datos guardados en checkoutSession');
            } catch (\Exception $e) {
                $this->logger->warning('GTM CheckoutSuccess: Error guardando en checkoutSession: ' . $e->getMessage());
            }

            // MARCAR COMO PROCESADA EN TODAS LAS SESIONES (prevenir duplicados futuros)
            $this->session->setData($sessionKey, true);
            $this->customerSession->setData($sessionKey, true);
            $this->checkoutSession->setData($sessionKey, true);

            // Verificar guardado
            $saved1 = $this->session->getGtmPurchase();
            $saved2 = $this->customerSession->getGtmPurchase();
            $saved3 = $this->checkoutSession->getGtmPurchase();

            $this->logger->info('GTM CheckoutSuccess: ✅ PROCESADO EXITOSAMENTE');
            $this->logger->info('GTM CheckoutSuccess: Verificación - session: ' . ($saved1 ? 'SÍ (' . strlen($saved1) . ' chars)' : 'NO'));
            $this->logger->info('GTM CheckoutSuccess: Verificación - customerSession: ' . ($saved2 ? 'SÍ (' . strlen($saved2) . ' chars)' : 'NO'));
            $this->logger->info('GTM CheckoutSuccess: Verificación - checkoutSession: ' . ($saved3 ? 'SÍ (' . strlen($saved3) . ' chars)' : 'NO'));

        } catch (\Exception $e) {
            // Solo loggear errores críticos
            $this->logger->error('GTM CheckoutSuccess Error: ' . $e->getMessage());
        }
    }
}