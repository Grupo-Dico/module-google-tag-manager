<?php
declare(strict_types=1);

namespace GDMexico\GoogleTagManager\Model;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Category;
use Magento\Sales\Model\Order;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Psr\Log\LoggerInterface;

class DataLayer
{
    private PricingHelper $pricingHelper;
    private StoreManagerInterface $storeManager;
    private ProductRepositoryInterface $productRepository;
    private CategoryRepositoryInterface $categoryRepository;
    private LoggerInterface $logger;

    public function __construct(
        PricingHelper $pricingHelper,
        StoreManagerInterface $storeManager,
        ProductRepositoryInterface $productRepository,
        CategoryRepositoryInterface $categoryRepository,
        LoggerInterface $logger
    ) {
        $this->pricingHelper = $pricingHelper;
        $this->storeManager = $storeManager;
        $this->productRepository = $productRepository;
        $this->categoryRepository = $categoryRepository;
        $this->logger = $logger;
    }

    public function getProductData(Product $product): array
    {
        $store = $this->storeManager->getStore();

        return [
            'item_id' => $product->getSku(),
            'item_name' => $product->getName(),
            'currency' => $store->getCurrentCurrencyCode(),
            'price' => (float)$product->getFinalPrice(),
            'item_category' => $this->getProductCategoryWithSmallestId($product),
            'item_brand' => $product->getAttributeText('manufacturer') ?: '',
        ];
    }

    public function getCategoryData(Category $category): array
    {
        return [
            'item_list_id' => (string)$category->getId(),
            'item_list_name' => $category->getName(),
        ];
    }

    public function getProductItemData(Product $product, Category $category = null, int $index = null): array
    {
        $itemData = [
            'item_id' => $product->getSku(),
            'item_name' => $product->getName(),
            'currency' => $this->getCurrentCurrency(),
            'price' => (float)$product->getFinalPrice(),
            'item_category' => $category ?  $this->getProductCategoryWithSmallestId($product) : $category->getName(),
        ];

        if ($category) {
            $itemData['item_list_name'] = $category->getName();
            $itemData['item_list_id'] = (string)$category->getId();
        }

        if ($index !== null) {
            $itemData['index'] = $index;
        }

        // Agregar marca si existe
        $brand = $product->getAttributeText('manufacturer');
        if ($brand) {
            $itemData['item_brand'] = $brand;
        }

        return $itemData;
    }

    public function getProductCategoryWithSmallestId(Product $product): string
    {
        try {
            $categoryCollection = $product->getCategoryCollection()
                ->addAttributeToSelect('name')
                ->addFieldToFilter('is_active', 1)
                ->setOrder('entity_id', 'ASC')
                ->setPageSize(1);

            if ($categoryCollection->getSize() > 0) {
                $category = $categoryCollection->getFirstItem();
                return $category->getName() ?: '';
            }
        } catch (\Exception $e) {
            // Log error but don't break functionality
            return '';
        }

        return '';
    }

    public function getCurrentCurrency(): string
    {
        return $this->storeManager->getStore()->getCurrentCurrencyCode();
    }

    /**
     * Obtiene los datos de la orden formateados para GA4
     *
     * @param Order $order
     * @return array
     */
    public function getOrderData(Order $order): array
    {
        $items = [];
        $itemIndex = 0;

        foreach ($order->getAllVisibleItems() as $item) {
            try {
                // Obtener el producto del catálogo
                $product = $this->productRepository->get($item->getSku());

                // Obtener la categoría principal del producto
                $categoryName = $this->getProductCategory($product);

                $itemData = [
                    'item_id' => $item->getSku(),
                    'item_name' => $item->getName(),
                    'affiliation' => $order->getStoreName(),
                    'coupon' => $order->getCouponCode() ?: '',
                    'discount' => (float) $item->getDiscountAmount(),
                    'index' => $itemIndex,
                    'item_brand' => $product->getAttributeText('manufacturer') ?: '',
                    'item_category' => $categoryName, // Categoría principal
                    'price' => (float) $item->getPrice(),
                    'quantity' => (int) $item->getQtyOrdered()
                ];

                // Agregar subcategorías si es necesario (item_category2, item_category3, etc.)
                $categories = $this->getProductCategoryHierarchy($product);
                if (!empty($categories)) {
                    for ($i = 0; $i < min(count($categories), 5); $i++) {
                        if ($i == 0) {
                            $itemData['item_category'] = $categories[$i];
                        } else {
                            $itemData['item_category' . ($i + 1)] = $categories[$i];
                        }
                    }
                }

                $items[] = $itemData;
                $itemIndex++;

            } catch (\Exception $e) {
                $this->logger->error('Error al obtener datos del producto: ' . $e->getMessage());

                // Datos mínimos si hay error
                $items[] = [
                    'item_id' => $item->getSku(),
                    'item_name' => $item->getName(),
                    'item_category' => '', // Vacío si no se puede obtener
                    'price' => (float) $item->getPrice(),
                    'quantity' => (int) $item->getQtyOrdered()
                ];
            }
        }

        // Calcular totales
        $tax = (float) $order->getTaxAmount();
        $shipping = (float) $order->getShippingAmount();
        $total = (float) $order->getGrandTotal();

        return [
            'currency' => $order->getOrderCurrencyCode(),
            'value' => $total,
            'tax' => $tax,
            'shipping' => $shipping,
            'transaction_id' => $order->getIncrementId(),
            'affiliation' => $order->getStoreName(),
            'coupon' => $order->getCouponCode() ?: '',
            'items' => $items
        ];
    }

    /**
     * Obtiene la categoría principal del producto
     *
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     * @return string
     */
    private function getProductCategory($product): string
    {
        try {
            $categoryIds = $product->getCategoryIds();

            if (empty($categoryIds)) {
                return '';
            }

            // Obtener la primera categoría que no sea la raíz
            foreach ($categoryIds as $categoryId) {
                try {
                    $category = $this->categoryRepository->get($categoryId);

                    // Ignorar categorías raíz (nivel 0 o 1)
                    if ($category->getLevel() > 1) {
                        return $category->getName();
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            return '';

        } catch (\Exception $e) {
            $this->logger->error('Error al obtener categoría del producto: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Obtiene la jerarquía completa de categorías del producto
     * Útil para item_category, item_category2, item_category3, etc.
     *
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     * @return array
     */
    private function getProductCategoryHierarchy($product): array
    {
        try {
            $categoryIds = $product->getCategoryIds();

            if (empty($categoryIds)) {
                return [];
            }

            $deepestPath = [];
            $maxLevel = 0;

            // Encontrar la categoría más profunda
            foreach ($categoryIds as $categoryId) {
                try {
                    $category = $this->categoryRepository->get($categoryId);

                    if ($category->getLevel() > $maxLevel) {
                        $maxLevel = $category->getLevel();
                        $pathIds = explode('/', $category->getPath());

                        // Construir el path completo de nombres
                        $path = [];
                        foreach ($pathIds as $pathId) {
                            if ($pathId == 1 || $pathId == 2) {
                                continue; // Saltar Root y Default Category
                            }

                            try {
                                $parentCategory = $this->categoryRepository->get($pathId);
                                if ($parentCategory->getLevel() > 1) {
                                    $path[] = $parentCategory->getName();
                                }
                            } catch (\Exception $e) {
                                continue;
                            }
                        }

                        if (!empty($path)) {
                            $deepestPath = $path;
                        }
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            return $deepestPath;

        } catch (\Exception $e) {
            $this->logger->error('Error al obtener jerarquía de categorías: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Método alternativo usando colecciones (más eficiente para múltiples productos)
     *
     * @param Order $order
     * @return array
     */
    public function getOrderDataWithCategories(Order $order): array
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $categoryCollection = $objectManager->create(\Magento\Catalog\Model\ResourceModel\Category\CollectionFactory::class);

        // Precargar todas las categorías necesarias
        $allCategoryIds = [];
        foreach ($order->getAllVisibleItems() as $item) {
            try {
                $product = $this->productRepository->get($item->getSku());
                $allCategoryIds = array_merge($allCategoryIds, $product->getCategoryIds());
            } catch (\Exception $e) {
                continue;
            }
        }

        $categories = [];
        if (!empty($allCategoryIds)) {
            $collection = $categoryCollection->create()
                ->addAttributeToSelect('name')
                ->addAttributeToFilter('entity_id', ['in' => array_unique($allCategoryIds)])
                ->addAttributeToFilter('level', ['gt' => 1]);

            foreach ($collection as $category) {
                $categories[$category->getId()] = $category->getName();
            }
        }

        // Construir items con categorías precargadas
        $items = [];
        $itemIndex = 0;

        foreach ($order->getAllVisibleItems() as $item) {
            try {
                $product = $this->productRepository->get($item->getSku());
                $categoryIds = $product->getCategoryIds();

                // Obtener primera categoría disponible
                $categoryName = '';
                foreach ($categoryIds as $catId) {
                    if (isset($categories[$catId])) {
                        $categoryName = $categories[$catId];
                        break;
                    }
                }

                $items[] = [
                    'item_id' => $item->getSku(),
                    'item_name' => $item->getName(),
                    'item_category' => $categoryName,
                    'affiliation' => $order->getStoreName(),
                    'coupon' => $order->getCouponCode() ?: '',
                    'discount' => (float) $item->getDiscountAmount(),
                    'index' => $itemIndex,
                    'item_brand' => $product->getAttributeText('manufacturer') ?: '',
                    'price' => (float) $item->getPrice(),
                    'quantity' => (int) $item->getQtyOrdered()
                ];

                $itemIndex++;

            } catch (\Exception $e) {
                $this->logger->error('Error procesando item: ' . $e->getMessage());
                continue;
            }
        }

        return [
            'currency' => $order->getOrderCurrencyCode(),
            'value' => (float) $order->getGrandTotal(),
            'tax' => (float) $order->getTaxAmount(),
            'shipping' => (float) $order->getShippingAmount(),
            'transaction_id' => $order->getIncrementId(),
            'affiliation' => $order->getStoreName(),
            'coupon' => $order->getCouponCode() ?: '',
            'items' => $items
        ];
    }
}