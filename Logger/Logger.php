<?php
declare(strict_types=1);

namespace GDMexico\GoogleTagManager\Logger;

use GDMexico\GoogleTagManager\Helper\Data as Helper;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

/**
 * Wrapper alrededor del LoggerInterface estándar de Magento.
 *
 * Sólo emite mensajes cuando el toggle `google_tag_manager/debug/enabled` está activo
 * en la configuración del admin (Stores > Configuration > GD México > Google Tag Manager).
 * Si el flag está apagado, todo el logging del módulo queda silenciado.
 */
class Logger extends AbstractLogger implements LoggerInterface
{
    private LoggerInterface $logger;
    private Helper $helper;

    public function __construct(LoggerInterface $logger, Helper $helper)
    {
        $this->logger = $logger;
        $this->helper = $helper;
    }

    /**
     * @param mixed   $level
     * @param string  $message
     * @param mixed[] $context
     */
    public function log($level, $message, array $context = []): void
    {
        if (!$this->helper->isDebugEnabled()) {
            return;
        }

        $this->logger->log($level, $message, $context);
    }
}
