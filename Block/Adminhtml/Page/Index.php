<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Block\Adminhtml\Page;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use Pynarae\TiktokLandingPages\Model\Official\OfficialTikTokBridge;
use Pynarae\TiktokLandingPages\Model\ResourceModel\LandingPage\CollectionFactory;

class Index extends Template
{
    protected $_template = 'Pynarae_TiktokLandingPages::page/index.phtml';

    public function __construct(
        Context $context,
        private readonly CollectionFactory $collectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly OfficialTikTokBridge $officialTikTokBridge,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getPages(): array
    {
        $collection = $this->collectionFactory->create();
        $collection->setOrder('updated_at', 'DESC');
        return $collection->getItems();
    }

    public function getAddUrl(): string { return $this->getUrl('*/*/new'); }
    public function getEditUrl(int $id): string { return $this->getUrl('*/*/edit', ['id' => $id]); }
    public function getDeleteUrl(int $id): string { return $this->getUrl('*/*/delete', ['id' => $id]); }
    public function getDuplicateUrl(int $id): string { return $this->getUrl('*/*/duplicate', ['id' => $id]); }

    public function getFrontendUrl($page): string
    {
        try {
            $website = $this->storeManager->getWebsite((int)$page->getData('website_id'));
            $store = $website->getDefaultStore();
            return rtrim($store->getBaseUrl(), '/') . '/lp/' . ltrim((string)$page->getData('identifier'), '/');
        } catch (\Throwable) {
            return '/lp/' . ltrim((string)$page->getData('identifier'), '/');
        }
    }

    public function getWebsiteName(int $websiteId): string
    {
        try {
            return (string)$this->storeManager->getWebsite($websiteId)->getName();
        } catch (\Throwable) {
            return (string)$websiteId;
        }
    }

    public function getOfficialPixelSummary(int $websiteId): string
    {
        try {
            $cfg = $this->officialTikTokBridge->getWebsiteConfig($websiteId);
            if (!$cfg['official_module_available']) {
                return (string)__('Official module unavailable');
            }
            if (!$cfg['connected']) {
                return (string)__('Official module not connected');
            }
            if (!$cfg['pixel_tracking_enabled']) {
                return (string)__('Pixel tracking disabled');
            }
            return $cfg['pixel_code'] ?: (string)__('Pixel code missing');
        } catch (\Throwable) {
            return (string)__('Unable to read official pixel');
        }
    }
}
