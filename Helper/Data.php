<?php
namespace GDMexico\GoogleTagManager\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    const XML_PATH_ENABLED = 'google_tag_manager/general/enabled';
    const XML_PATH_GTM_ID = 'google_tag_manager/general/gtm_id';
    const XML_PATH_TRACK_PAGE_VIEW = 'google_tag_manager/events/track_page_view';
    const XML_PATH_TRACK_PRODUCT_VIEW = 'google_tag_manager/events/track_product_view';
    const XML_PATH_TRACK_CATEGORY_VIEW = 'google_tag_manager/events/track_category_view';
    const XML_PATH_TRACK_SEARCH = 'google_tag_manager/events/track_search';
    const XML_PATH_TRACK_ADD_TO_CART = 'google_tag_manager/events/track_add_to_cart';
    const XML_PATH_TRACK_PURCHASE = 'google_tag_manager/events/track_purchase';
    const XML_PATH_DEBUG_ENABLED = 'google_tag_manager/debug/enabled';
    const XML_PATH_GATEWAY_ENABLED = 'google_tag_manager/gateway/enabled';
    const XML_PATH_GATEWAY_PATH = 'google_tag_manager/gateway/path';

    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    public function isEnabled($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getGtmId($storeId = null): string
    {
        $gtmId = $this->scopeConfig->getValue(
            self::XML_PATH_GTM_ID,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $gtmId ? trim($gtmId) : '';
    }

    public function isTrackPageViewEnabled($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_TRACK_PAGE_VIEW,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isTrackProductViewEnabled($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_TRACK_PRODUCT_VIEW,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isTrackCategoryViewEnabled($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_TRACK_CATEGORY_VIEW,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isTrackSearchEnabled($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_TRACK_SEARCH,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isTrackAddToCartEnabled($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_TRACK_ADD_TO_CART,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isTrackPurchaseEnabled($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_TRACK_PURCHASE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Indica si el logging de debug del módulo está habilitado.
     * Controla tanto los logs PHP (var/log/system.log) como los console.* en frontend.
     */
    public function isDebugEnabled($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_DEBUG_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
    /**
     * Indica si Google Tag Gateway está habilitado.
     */
    public function isGatewayEnabled($storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_GATEWAY_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Obtiene el measurement path configurado para Google Tag Gateway.
     */
    public function getGatewayPath($storeId = null): string
    {
        $path = (string)$this->scopeConfig->getValue(
            self::XML_PATH_GATEWAY_PATH,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $path = trim($path);

        if ($path === '') {
            return '/metrics/';
        }

        return '/' . trim($path, '/') . '/';
    }
}