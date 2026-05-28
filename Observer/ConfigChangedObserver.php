<?php
declare(strict_types=1);

namespace GDMexico\GoogleTagManager\Observer;

use Magento\Framework\App\Cache\Manager as CacheManager;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface as MessageManager;

/**
 * Limpia automáticamente los tipos de caché afectados por cambios
 * en la sección `google_tag_manager` del admin.
 *
 * Se dispara con el evento `admin_system_config_changed_section_google_tag_manager`
 * (Magento lo lanza al guardar la sección, en el área adminhtml).
 *
 * Tipos limpiados:
 *  - config:     porque cambian valores de Stores > Configuration consumidos por el wrapper Logger.
 *  - block_html: porque los templates `.phtml` del módulo condicionan los `console.*` con PHP.
 *  - full_page:  para que el HTML cacheado del storefront se regenere sin los `console.*`.
 *  - layout:     por seguridad, ante cambios estructurales futuros.
 */
class ConfigChangedObserver implements ObserverInterface
{
    private const CACHE_TYPES = [
        'config',
        'block_html',
        'full_page',
        'layout',
    ];

    private CacheManager $cacheManager;
    private MessageManager $messageManager;

    public function __construct(
        CacheManager $cacheManager,
        MessageManager $messageManager
    ) {
        $this->cacheManager = $cacheManager;
        $this->messageManager = $messageManager;
    }

    public function execute(Observer $observer)
    {
        try {
            $this->cacheManager->clean(self::CACHE_TYPES);
            $this->messageManager->addSuccessMessage(
                __('GTM: cachés (%1) limpiados automáticamente.', implode(', ', self::CACHE_TYPES))
            );
        } catch (\Throwable $e) {
            $this->messageManager->addWarningMessage(
                __('GTM: no se pudo limpiar el caché automáticamente: %1', $e->getMessage())
            );
        }
    }
}
