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

/**
 * Observer para asegurar que los datos de purchase estén disponibles
 * en las páginas personalizadas de success
 */
class CustomSuccessPageObserver implements ObserverInterface
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
            // Verificar si la ruta actual es una de las páginas personalizadas de success
            $request = $observer->getEvent()->getRequest();
            $fullActionName = $request->getFullActionName();
            $pathInfo = $request->getPathInfo();
            $requestUri = $request->getRequestUri();
            $moduleName = $request->getModuleName();
            $controllerName = $request->getControllerName();
            $actionName = $request->getActionName();
            
            // Log para debugging
            $this->logger->info('GTM CustomSuccessPage: Verificando ruta - fullActionName: ' . $fullActionName . 
                ', pathInfo: ' . $pathInfo . ', requestUri: ' . $requestUri .
                ', module: ' . $moduleName . ', controller: ' . $controllerName . ', action: ' . $actionName);
            
            // Rutas de las páginas personalizadas de success
            $customSuccessRoutes = [
                'custom_success_creditcard',
                'custom_success_paypal',
                'custom_success_cash',
                'custom_success_banktransfer',
                'custom/success/creditcard',
                'custom/success/paypal',
                'custom/success/cash',
                'custom/success/banktransfer',
                'success/creditcard',
                'success/paypal',
                'success/cash',
                'success/banktransfer'
            ];

            // Verificar si es una de las rutas personalizadas
            $isCustomSuccessPage = false;
            
            // Verificar por fullActionName (ej: custom_success_creditcard o custom_success_banktransfer)
            foreach ($customSuccessRoutes as $route) {
                if (stripos($fullActionName, $route) !== false) {
                    $isCustomSuccessPage = true;
                    $this->logger->info('GTM CustomSuccessPage: Ruta detectada por fullActionName: ' . $route);
                    break;
                }
            }
            
            // Verificar por pathInfo o requestUri (ej: /custom/success/creditcard o /custom/success/banktransfer)
            if (!$isCustomSuccessPage) {
                if (stripos($pathInfo, '/custom/success/') !== false || 
                    stripos($requestUri, '/custom/success/') !== false ||
                    stripos($pathInfo, '/success/') !== false ||
                    stripos($requestUri, '/success/') !== false) {
                    $isCustomSuccessPage = true;
                    $this->logger->info('GTM CustomSuccessPage: Ruta detectada por pathInfo/requestUri');
                }
            }
            
            // Verificar por módulo, controlador y acción
            if (!$isCustomSuccessPage) {
                if ($moduleName === 'custom' && $controllerName === 'success') {
                    $isCustomSuccessPage = true;
                    $this->logger->info('GTM CustomSuccessPage: Ruta detectada por módulo/controlador');
                }
            }
            
            // Verificar también por el actionName específico
            if (!$isCustomSuccessPage) {
                $successActions = ['banktransfer', 'creditcard', 'paypal', 'cash'];
                if (in_array($actionName, $successActions)) {
                    $isCustomSuccessPage = true;
                    $this->logger->info('GTM CustomSuccessPage: Ruta detectada por actionName: ' . $actionName);
                }
            }

            if (!$isCustomSuccessPage) {
                return;
            }
            
            $this->logger->info('GTM CustomSuccessPage: ✅ Página personalizada de success detectada - fullActionName: ' . $fullActionName);
            // Verificar si ya hay datos de purchase en la sesión
            $existingPurchaseData = $this->session->getGtmPurchase() ?:
                $this->customerSession->getGtmPurchase() ?:
                    $this->checkoutSession->getGtmPurchase();

            if ($existingPurchaseData) {
                return;
            }

            // Si no hay datos, intentar obtener la orden y generarlos
            $order = $this->checkoutSession->getLastRealOrder();

            if (!$order || !$order->getId()) {
                return;
            }

            // Verificar si ya se procesó esta orden
            $sessionKey = 'gtm_processed_order_' . $order->getId();
            $alreadyProcessed = $this->session->getData($sessionKey) ||
                $this->customerSession->getData($sessionKey) ||
                $this->checkoutSession->getData($sessionKey);

            if ($alreadyProcessed) {
                return;
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
            try {
                $this->session->setGtmPurchase($gtmDataJson);
            } catch (\Exception $e) {
                // Silenciar errores
            }
            
            try {
                $this->customerSession->setGtmPurchase($gtmDataJson);
            } catch (\Exception $e) {
                // Silenciar errores
            }
            
            try {
                $this->checkoutSession->setGtmPurchase($gtmDataJson);
            } catch (\Exception $e) {
                // Silenciar errores
            }

            // MARCAR COMO PROCESADA EN TODAS LAS SESIONES
            $this->session->setData($sessionKey, true);
            $this->customerSession->setData($sessionKey, true);
            $this->checkoutSession->setData($sessionKey, true);

        } catch (\Exception $e) {
            // Solo loggear errores críticos
            $this->logger->error('GTM CustomSuccessPage Error: ' . $e->getMessage());
        }
    }
}

