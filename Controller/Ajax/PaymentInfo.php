<?php
declare(strict_types=1);

namespace GDMexico\GoogleTagManager\Controller\Ajax;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use GDMexico\GoogleTagManager\Model\DataLayer;
use GDMexico\GoogleTagManager\Helper\Data;
use Psr\Log\LoggerInterface;

/**
 * Devuelve el bloque ecommerce (items + totales) del quote actual para que el
 * JS del checkout construya el evento add_payment_info con el mismo detalle de
 * items que begin_checkout, sin recalcularlo en el front.
 */
class PaymentInfo implements HttpGetActionInterface
{
    private JsonFactory $resultJsonFactory;
    private CheckoutSession $checkoutSession;
    private DataLayer $dataLayer;
    private Data $helper;
    private LoggerInterface $logger;

    public function __construct(
        JsonFactory $resultJsonFactory,
        CheckoutSession $checkoutSession,
        DataLayer $dataLayer,
        Data $helper,
        LoggerInterface $logger
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->checkoutSession = $checkoutSession;
        $this->dataLayer = $dataLayer;
        $this->helper = $helper;
        $this->logger = $logger;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        if (!$this->helper->isEnabled() || !$this->helper->isTrackAddPaymentInfoEnabled()) {
            return $result->setData(['enabled' => false]);
        }

        try {
            $quote = $this->checkoutSession->getQuote();

            if (!$quote || !$quote->getId()) {
                return $result->setData(['enabled' => true, 'ecommerce' => null]);
            }

            $ecommerce = $this->dataLayer->getQuoteEcommerceData($quote);

            if (empty($ecommerce['items'])) {
                return $result->setData(['enabled' => true, 'ecommerce' => null]);
            }

            return $result->setData([
                'enabled' => true,
                'ecommerce' => $ecommerce,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('GTM PaymentInfo Error: ' . $e->getMessage());
            return $result->setData(['enabled' => true, 'ecommerce' => null]);
        }
    }
}
