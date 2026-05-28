<?php
declare(strict_types=1);

namespace GDMexico\GoogleTagManager\Controller\Ajax;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Checkout\Model\Session as CheckoutSession;
use GDMexico\GoogleTagManager\Helper\Data;
use Psr\Log\LoggerInterface;

class GetEvents implements HttpGetActionInterface
{
    private JsonFactory $resultJsonFactory;
    private SessionManagerInterface $session;
    private CustomerSession $customerSession;
    private CheckoutSession $checkoutSession;
    private Data $helper;
    private LoggerInterface $logger;

    public function __construct(
        JsonFactory $resultJsonFactory,
        SessionManagerInterface $session,
        CustomerSession $customerSession,
        CheckoutSession $checkoutSession,
        Data $helper,
        LoggerInterface $logger
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->session = $session;
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
        $this->helper = $helper;
        $this->logger = $logger;
    }

    public function execute()
    {
        $this->logger->info('GTM GetEvents: Controlador AJAX ejecutado');

        $result = $this->resultJsonFactory->create();

        if (!$this->helper->isEnabled()) {
            $this->logger->info('GTM GetEvents: GTM está deshabilitado');
            return $result->setData(['error' => 'GTM disabled']);
        }

        $response = [];

        // Intentar obtener de diferentes tipos de sesión
        $addToCartData = null;

        // 1. Sesión estándar
        $addToCartData = $this->session->getGtmAddToCart();
        $this->logger->info('GTM GetEvents: Session estándar: ' . ($addToCartData ?: 'NULL'));

        // 2. Customer session
        if (!$addToCartData) {
            $addToCartData = $this->customerSession->getGtmAddToCart();
            $this->logger->info('GTM GetEvents: Customer session: ' . ($addToCartData ?: 'NULL'));
        }

        // 3. Checkout session
        if (!$addToCartData) {
            $addToCartData = $this->checkoutSession->getGtmAddToCart();
            $this->logger->info('GTM GetEvents: Checkout session: ' . ($addToCartData ?: 'NULL'));
        }

        if ($addToCartData) {
            $response['add_to_cart'] = $addToCartData;

            // Limpiar de todas las sesiones
            $this->session->unsGtmAddToCart();
            $this->customerSession->unsGtmAddToCart();
            $this->checkoutSession->unsGtmAddToCart();

            $this->logger->info('GTM GetEvents: Evento add_to_cart enviado y limpiado');
        }

        // Verificar eventos de purchase
        $purchaseData = $this->session->getGtmPurchase();
        if (!$purchaseData) {
            $purchaseData = $this->checkoutSession->getGtmPurchase();
        }

        if ($purchaseData) {
            $response['purchase'] = $purchaseData;
            $this->session->unsGtmPurchase();
            $this->checkoutSession->unsGtmPurchase();
            $this->logger->info('GTM GetEvents: Evento purchase enviado');
        }

        $this->logger->info('GTM GetEvents: Respuesta final: ' . json_encode($response));

        return $result->setData($response);
    }
}