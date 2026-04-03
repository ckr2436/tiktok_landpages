<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Model\Official;

use Magento\Framework\Module\Manager as ModuleManager;
use Tiktok\Tiktok\Model\ScopeManagerBuilder;

class OfficialTikTokBridge
{
    public function __construct(
        private readonly ScopeManagerBuilder $scopeManagerBuilder,
        private readonly ModuleManager $moduleManager
    ) {
    }

    public function isOfficialModuleAvailable(): bool
    {
        return $this->moduleManager->isEnabled('Tiktok_Tiktok');
    }

    public function getWebsiteConfig(int $websiteId): array
    {
        $scopeManager = $this->scopeManagerBuilder->create($websiteId);
        return [
            'official_module_available' => $this->isOfficialModuleAvailable(),
            'connected' => $scopeManager->isEnabled(),
            'pixel_tracking_enabled' => (bool)$scopeManager->isPixelTrackingEnabled(),
            'advanced_user_tracking' => (bool)$scopeManager->isAdvancedUserTrackingEnabled(),
            'tp_cookie_enabled' => (bool)$scopeManager->isTpCookieEnabled(),
            'pixel_code' => (string)($scopeManager->getPixelCode() ?? ''),
            'bc_id' => (string)($scopeManager->getBcId() ?? ''),
            'catalog_id' => (string)($scopeManager->getCatalogId() ?? ''),
            'manage_url' => (string)($scopeManager->getManageUrl() ?? ''),
        ];
    }
}
