<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Block\Adminhtml\Page;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use Pynarae\TiktokLandingPages\Controller\Adminhtml\Page\Edit as EditController;
use Pynarae\TiktokLandingPages\Model\Config;
use Pynarae\TiktokLandingPages\Model\Media\AssetStorage;
use Pynarae\TiktokLandingPages\Model\Media\AssetUrlResolver;
use Pynarae\TiktokLandingPages\Model\Official\OfficialTikTokBridge;

class Edit extends Template
{
    protected $_template = 'Pynarae_TiktokLandingPages::page/edit.phtml';

    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly StoreManagerInterface $storeManager,
        private readonly OfficialTikTokBridge $officialTikTokBridge,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly Config $config,
        private readonly AssetUrlResolver $assetUrlResolver,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getModel(): \Pynarae\TiktokLandingPages\Model\LandingPage
    {
        $model = $this->registry->registry(EditController::REGISTRY_KEY);
        if (!$model) {
            $model = \Magento\Framework\App\ObjectManager::getInstance()->create(\Pynarae\TiktokLandingPages\Model\LandingPage::class);
        }

        $persisted = $this->dataPersistor->get('pynarae_tiktok_landing_page');
        if (is_array($persisted) && !empty($persisted)) {
            $persisted = $this->restoreExistingManagedImages($persisted);
            $model->setData(array_merge($model->getData(), $persisted));
            $this->dataPersistor->clear('pynarae_tiktok_landing_page');
        }

        $websiteId = (int)($model->getData('website_id') ?: $this->getDefaultWebsiteId());
        $storeId = (int)$this->storeManager->getWebsite($websiteId)->getDefaultStore()->getId();
        $defaults = [
            'website_id' => $websiteId,
            'is_active' => 1,
            'head_jump_enabled' => 1,
            'manual_button_enabled' => 1,
            'body_auto_jump_enabled' => 1,
            'head_jump_timeout_ms' => $this->config->getDefaultHeadJumpTimeoutMs($storeId),
            'auto_jump_seconds' => $this->config->getDefaultAutoJumpSeconds($storeId),
            'passthrough_params' => $this->config->getDefaultPassthroughParams($storeId),
            'promo_bar_text' => $this->config->getDefaultPromoBarText($storeId),
            'cta_text' => $this->config->getDefaultCtaText($storeId),
            'desktop_message' => $this->config->getDefaultDesktopMessage($storeId),
            'fail_message' => $this->config->getDefaultFailMessage($storeId),
        ];

        foreach ($defaults as $key => $value) {
            if ($model->getData($key) === null || $model->getData($key) === '') {
                $model->setData($key, $value);
            }
        }

        if (($rawPdpUrl = (string)$model->getData('raw_pdp_url')) !== '' &&
            (($model->getData('pc_fallback_url') === null || $model->getData('pc_fallback_url') === '') ||
             ($model->getData('mobile_fallback_url') === null || $model->getData('mobile_fallback_url') === ''))) {
            try {
                $parser = \Magento\Framework\App\ObjectManager::getInstance()->get(\Pynarae\TiktokLandingPages\Model\Deeplink\PdpUrlParser::class);
                $parsed = $parser->parse($rawPdpUrl);
                if ($model->getData('pc_fallback_url') === null || $model->getData('pc_fallback_url') === '') {
                    $model->setData('pc_fallback_url', $parsed['pc_fallback_url']);
                }
                if ($model->getData('mobile_fallback_url') === null || $model->getData('mobile_fallback_url') === '') {
                    $model->setData('mobile_fallback_url', $parsed['mobile_fallback_url']);
                }
            } catch (\Throwable) {
            }
        }

        return $model;
    }

    public function getHeaderText(): string
    {
        return $this->getModel()->getId() ? (string)__('Edit Landing Page') : (string)__('New Landing Page');
    }

    public function getSaveUrl(): string
    {
        return $this->getUrl('*/*/save');
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('*/*/index');
    }

    public function getDuplicateUrl(): ?string
    {
        $m = $this->getModel();
        return $m->getId() ? $this->getUrl('*/*/duplicate', ['id' => $m->getId()]) : null;
    }

    public function getDeleteUrl(): ?string
    {
        $m = $this->getModel();
        return $m->getId() ? $this->getUrl('*/*/delete', ['id' => $m->getId()]) : null;
    }

    public function getWebsites(): array
    {
        $items = [];
        foreach ($this->storeManager->getWebsites() as $website) {
            if ((int)$website->getId() === 0) {
                continue;
            }
            $items[] = ['value' => (int)$website->getId(), 'label' => $website->getName()];
        }
        return $items;
    }

    public function getOfficialInfo(): array
    {
        $websiteId = (int)($this->getModel()->getData('website_id') ?: $this->getDefaultWebsiteId());
        try {
            return $this->officialTikTokBridge->getWebsiteConfig($websiteId);
        } catch (\Throwable) {
            return [
                'official_module_available' => false,
                'connected' => false,
                'pixel_tracking_enabled' => false,
                'advanced_user_tracking' => false,
                'tp_cookie_enabled' => false,
                'pixel_code' => '',
                'bc_id' => '',
                'catalog_id' => '',
                'manage_url' => ''
            ];
        }
    }

    public function getFrontendUrlPreview(): ?string
    {
        $model = $this->getModel();
        $identifier = trim((string)$model->getData('identifier'));
        if ($identifier === '') {
            return null;
        }

        try {
            $website = $this->storeManager->getWebsite((int)$model->getData('website_id'));
            $store = $website->getDefaultStore();
            return rtrim($store->getBaseUrl(), '/') . '/lp/' . ltrim($identifier, '/');
        } catch (\Throwable) {
            return '/lp/' . ltrim($identifier, '/');
        }
    }

    public function yesNoOptions(): array
    {
        return [1 => (string)__('Yes'), 0 => (string)__('No')];
    }

    public function getImagePreviewUrl(?string $value): ?string
    {
        return $this->assetUrlResolver->resolve($value);
    }

    public function getExternalImageFieldValue(?string $value): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }

        return $this->assetUrlResolver->isExternalUrl($value) ? $value : '';
    }

    public function isManagedImage(?string $value): bool
    {
        return $this->assetUrlResolver->isManagedAsset($value);
    }

    public function getAllowedImageExtensionsLabel(): string
    {
        return implode(', ', AssetStorage::ALLOWED_EXTENSIONS);
    }

    public function getMaxUploadSizeMb(): int
    {
        return (int)(AssetStorage::MAX_FILE_SIZE / 1024 / 1024);
    }

    public function getManagedMediaBasePath(): string
    {
        return AssetStorage::BASE_MEDIA_PATH;
    }

    private function getDefaultWebsiteId(): int
    {
        $websites = $this->getWebsites();
        return (int)($websites[0]['value'] ?? 1);
    }

    private function restoreExistingManagedImages(array $persisted): array
    {
        $map = [
            'hero_image_url'   => 'hero_image_url_existing',
            'cta_bg_image_url' => 'cta_bg_image_url_existing',
            'promo_image_url'  => 'promo_image_url_existing',
        ];

        foreach ($map as $field => $existingField) {
            $postedValue = trim((string)($persisted[$field] ?? ''));
            $existingValue = trim((string)($persisted[$existingField] ?? ''));
            $deleteFlag = !empty($persisted[$field . '_delete']);

            if ($deleteFlag) {
                continue;
            }

            if ($postedValue === '' && $existingValue !== '' && $this->assetUrlResolver->isManagedAsset($existingValue)) {
                $persisted[$field] = $existingValue;
            }
        }

        return $persisted;
    }
}
