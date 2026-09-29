<?php declare(strict_types=1);

namespace Netformic\ProductFinder;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

/**
 * Main plugin class for NetformicProductFinder.
 *
 * @package NetformicProductFinder
 */
class NetformicProductFinder extends Plugin
{
    /**
     * Uninstalls the plugin and cleans up user data if requested.
     *
     * @param UninstallContext $uninstallContext
     * @return void
     */
    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        // Cleanup custom database tables or configurations here if applicable in the future.
    }
}
