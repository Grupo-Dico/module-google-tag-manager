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

class AddToCartObserver implements ObserverInterface
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
        if (!$this->helper->isEnabled() || !$this->helper->isTrackAddToCartEnabled()) {
            return;
        }

        try {
            $product = null;
            $request = null;
            $qty = 1;

            // PRIORIDAD 1: Obtener cantidad DIRECTAMENTE del request (más confiable)
            if ($observer->getEvent()->getRequest()) {
                $request = $observer->getEvent()->getRequest();

                // Buscar cantidad en diferentes parámetros del formulario
                $possibleQtyParams = ['qty', 'quantity', 'product_qty'];
                foreach ($possibleQtyParams as $param) {
                    $paramValue = $request->getParam($param);
                    if ($paramValue && (int)$paramValue > 0) {
                        $qty = (int)$paramValue;
                        $this->logger->info('GTM: Cantidad obtenida del request[' . $param . ']: ' . $qty);
                        break;
                    }
                }
            }

            // Obtener producto
            if ($observer->getEvent()->getProduct()) {
                $product = $observer->getEvent()->getProduct();
                $this->logger->info('GTM: Producto obtenido del evento estándar: ' . $product->getSku());
            }

            // PRIORIDAD 2: Si no tenemos cantidad del request, obtenerla del quote item
            // PERO verificar si es una nueva adición o una actualización
            if ($observer->getEvent()->getQuoteItem()) {
                $quoteItem = $observer->getEvent()->getQuoteItem();

                if (!$product) {
                    $product = $quoteItem->getProduct();
                }

                // Solo usar cantidad del quote item si no obtuvimos del request
                // Y solo si el item es nuevo (no una actualización)
                if ($qty === 1 && $quoteItem->getQty() > 0) {
                    // Verificar si es un item nuevo o actualización
                    $originalQty = $quoteItem->getOrigData('qty') ?: 0;
                    $currentQty = $quoteItem->getQty();

                    if ($originalQty == 0) {
                        // Es un item completamente nuevo
                        $qty = (int)$currentQty;
                        $this->logger->info('GTM: Cantidad de item nuevo: ' . $qty);
                    } else {
                        // Es una actualización, calcular la diferencia
                        $qtyAdded = $currentQty - $originalQty;
                        if ($qtyAdded > 0) {
                            $qty = (int)$qtyAdded;
                            $this->logger->info('GTM: Cantidad agregada (diferencia): ' . $qty);
                        }
                    }
                }
            }

            if (!$product) {
                $this->logger->warning('GTM: No se pudo obtener el producto');
                return;
            }

            $this->logger->info('GTM: Producto final: ' . $product->getSku() . ', Cantidad final: ' . $qty);

            // Obtener categoría desde campo personalizado feed-category
            $feedCategory = null;
            $feedCategory = (string)($product->getData('feed_category') ?: '');
            if (!$feedCategory) {
                try {
                    $cats = $product->getCategoryCollection()->addAttributeToSelect('name');
                    foreach ($cats as $c) { $feedCategory = (string)$c->getName(); break; }
                } catch (\Throwable $e) { /* ignore */ }
            }

            // Construir datos del producto para GA4
            $productData = [
                'item_id' => $product->getSku(),
                'item_name' => $product->getName(),
                'currency' => $this->dataLayer->getCurrentCurrency(),
                'price' => (float)$product->getFinalPrice(),
                'quantity' => $qty  // Esta es la cantidad AGREGADA, no la total
            ];

            // Agregar categoría
            if ($feedCategory) {
                $productData['item_category'] = $feedCategory;
            } else {
                $categoryName = $this->dataLayer->getProductCategoryWithSmallestId($product);
                if ($categoryName) {
                    $productData['item_category'] = $categoryName;
                }
            }

            // Agregar marca si existe
            $brand = $product->getAttributeText('manufacturer');
            if ($brand) {
                $productData['item_brand'] = $brand;
            }

            $gtmData = [
                'event' => 'add_to_cart',
                'ecommerce' => [
                    'currency' => $this->dataLayer->getCurrentCurrency(),
                    'value' => (float)($productData['price'] * $qty),
                    'items' => [$productData]
                ]
            ];

            $this->logger->info('GTM: Datos finales creados: ' . json_encode($gtmData));

            $gtmDataJson = json_encode($gtmData, JSON_UNESCAPED_UNICODE);

            // Guardar en múltiples sesiones para asegurar compatibilidad
            $this->session->setGtmAddToCart($gtmDataJson);
            $this->customerSession->setGtmAddToCart($gtmDataJson);
            $this->checkoutSession->setGtmAddToCart($gtmDataJson);

        } catch (\Exception $e) {
            $this->logger->error('GTM AddToCart Error: ' . $e->getMessage());
            $this->logger->error('GTM AddToCart Stack trace: ' . $e->getTraceAsString());
        }
    }
}