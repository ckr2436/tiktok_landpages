<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Model\Frontend;

use Magento\Store\Model\StoreManagerInterface;
use Pynarae\TiktokLandingPages\Model\LandingPage;
use Pynarae\TiktokLandingPages\Model\Official\OfficialTikTokBridge;

class ConfigBuilder
{
    public function __construct(
        private readonly OfficialTikTokBridge $officialTikTokBridge,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function build(LandingPage $page, array $env): array
    {
        $websiteId = (int)$page->getData('website_id');
        $official = $this->officialTikTokBridge->getWebsiteConfig($websiteId);
        $pixelCode = (string)($page->getData('pixel_code_override') ?: $official['pixel_code']);
        $currentStore = $this->storeManager->getStore();

        return [
            'page' => [
                'id' => (int)$page->getId(),
                'website_id' => $websiteId,
                'title' => (string)$page->getData('title'),
                'identifier' => (string)$page->getData('identifier'),
                'frontend_path' => '/lp/' . ltrim((string)$page->getData('identifier'), '/'),
                'frontend_url' => rtrim($currentStore->getBaseUrl(), '/') . '/lp/' . ltrim((string)$page->getData('identifier'), '/'),
                'meta_title' => (string)($page->getData('meta_title') ?: $page->getData('title')),
                'meta_description' => (string)($page->getData('meta_description') ?: $page->getData('subheadline') ?: ''),
            ],
            'env' => $env,
            'official' => $official,
            'pixel' => [
                'enabled' => (bool)$official['pixel_tracking_enabled'] && $pixelCode !== '',
                'pixel_code' => $pixelCode,
                'tp_cookie_enabled' => (bool)$official['tp_cookie_enabled'],
                'advanced_user_tracking' => (bool)$official['advanced_user_tracking'],
            ],
            'tiktok' => [
                'raw_pdp_url' => (string)$page->getData('raw_pdp_url'),
                'product_id' => (string)$page->getData('product_id'),
                'source' => (string)($page->getData('source') ?: 'anchor'),
                'pc_fallback_url' => (string)$page->getData('pc_fallback_url'),
                'mobile_fallback_url' => (string)$page->getData('mobile_fallback_url'),
                'content_name' => (string)($page->getData('content_name') ?: $page->getData('title')),
            ],
            'behavior' => [
                'head_jump_enabled' => (bool)$page->getData('head_jump_enabled'),
                'manual_button_enabled' => (bool)$page->getData('manual_button_enabled'),
                'body_auto_jump_enabled' => (bool)$page->getData('body_auto_jump_enabled'),
                'head_jump_timeout_ms' => max(0, (int)$page->getData('head_jump_timeout_ms')),
                'auto_jump_seconds' => max(0, (int)$page->getData('auto_jump_seconds')),
                'passthrough_params' => $this->normalizePassthrough((string)$page->getData('passthrough_params')),
            ],
            'content' => [
                'promo_bar_text' => (string)$page->getData('promo_bar_text'),
                'headline' => (string)$page->getData('headline'),
                'subheadline' => (string)$page->getData('subheadline'),
                'cta_text' => (string)$page->getData('cta_text'),
                'desktop_message' => (string)$page->getData('desktop_message'),
                'fail_message' => (string)$page->getData('fail_message'),
                'hero_image_url' => (string)$page->getData('hero_image_url'),
                'cta_bg_image_url' => (string)$page->getData('cta_bg_image_url'),
                'promo_image_url' => (string)$page->getData('promo_image_url'),
            ],
        ];
    }

    public function encodeForScript(array $config): string
    {
        return json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?: '{}';
    }

    private function normalizePassthrough(string $params): array
    {
        $items = array_filter(array_map('trim', explode(',', $params)));
        return array_values(array_unique($items));
    }
}
