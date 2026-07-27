<?php
declare(strict_types=1);

namespace GDMexico\GoogleTagManager\Block;

use GDMexico\GoogleTagManager\Helper\Data;
use GDMexico\GoogleTagManager\Model\DataLayer;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class Search extends Template
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

    public function canTrack(): bool
    {
        return $this->helper->isEnabled()
            && $this->helper->isTrackSearchEnabled();
    }

    public function isDebugEnabled(): bool
    {
        return $this->helper->isDebugEnabled();
    }

    public function getSearchTerm(): string
    {
        return trim((string)$this->getRequest()->getParam('q', ''));
    }

    public function getCurrencyCode(): string
    {
        return $this->dataLayer->getCurrentCurrency();
    }

    /**
     * Obtiene la colección que Magento/ElasticSuite ya cargó para el listado.
     * No crea una segunda búsqueda contra Elasticsearch/OpenSearch.
     */
    public function getLoadedProductCollection(): ?ProductCollection
    {
        $listBlock = $this->getLayout()->getBlock('search_result_list');

        if (!$listBlock) {
            return null;
        }

        if (method_exists($listBlock, 'getLoadedProductCollection')) {
            $collection = $listBlock->getLoadedProductCollection();
            return $collection instanceof ProductCollection ? $collection : null;
        }

        if (method_exists($listBlock, 'getCollection')) {
            $collection = $listBlock->getCollection();
            return $collection instanceof ProductCollection ? $collection : null;
        }

        return null;
    }

    public function getItems(): array
    {
        $collection = $this->getLoadedProductCollection();

        if (!$collection) {
            return [];
        }

        $items = [];
        $index = $this->getStartingIndex();
        $currency = $this->getCurrencyCode();

        foreach ($collection as $product) {
            $item = [
                'item_id' => (string)$product->getSku(),
                'item_name' => (string)$product->getName(),
                'currency' => $currency,
                'price' => (float)$product->getFinalPrice(),
                'index' => $index,
                'quantity' => 1,
            ];

            $brand = $product->getAttributeText('manufacturer');
            if (is_array($brand)) {
                $brand = implode(', ', $brand);
            }
            if ($brand) {
                $item['item_brand'] = (string)$brand;
            }

            $category = $this->dataLayer->getProductCategoryWithSmallestId($product);
            if ($category !== '') {
                $item['item_category'] = $category;
            }

            $items[] = $item;
            $index++;
        }

        return $items;
    }

    private function getStartingIndex(): int
    {
        $toolbar = $this->getLayout()->getBlock('product_list_toolbar');

        if (!$toolbar) {
            return 1;
        }

        $currentPage = max(1, (int)$toolbar->getCurrentPage());
        $limit = max(1, (int)$toolbar->getLimit());

        return (($currentPage - 1) * $limit) + 1;
    }
}
