<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Controller\Adminhtml\Page;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Pynarae\TiktokLandingPages\Api\LandingPageRepositoryInterface;
use Pynarae\TiktokLandingPages\Model\Config;
use Pynarae\TiktokLandingPages\Model\Deeplink\PdpUrlParser;
use Pynarae\TiktokLandingPages\Model\LandingPageFactory;
use Pynarae\TiktokLandingPages\Model\Media\AssetStorage;
use Pynarae\TiktokLandingPages\Model\ResourceModel\LandingPage\CollectionFactory;

class Save extends AbstractPage
{
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        private readonly LandingPageRepositoryInterface $landingPageRepository,
        private readonly LandingPageFactory $landingPageFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly PdpUrlParser $pdpUrlParser,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        private readonly AssetStorage $assetStorage,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $data = $this->getRequest()->getPostValue();
        if (!$data) {
            return $this->_redirect('*/*/index');
        }

        $id = isset($data['entity_id']) ? (int)$data['entity_id'] : 0;

        try {
            $model = $id ? $this->landingPageRepository->getById($id) : $this->landingPageFactory->create();
            $websiteId = (int)($data['website_id'] ?? 0);
            if ($websiteId <= 0) {
                throw new LocalizedException(__('Please choose a website.'));
            }

            $identifier = $this->normalizeIdentifier((string)($data['identifier'] ?? ''));
            if ($identifier === '') {
                throw new LocalizedException(__('Identifier is required.'));
            }

            $title = trim((string)($data['title'] ?? ''));
            if ($title === '') {
                throw new LocalizedException(__('Title is required.'));
            }

            $this->assertUniqueIdentifier($identifier, $websiteId, $id);
            $parsed = $this->pdpUrlParser->parse((string)($data['raw_pdp_url'] ?? ''));
            $storeId = (int)$this->storeManager->getWebsite($websiteId)->getDefaultStore()->getId();

            $pendingManagedAssetCleanup = [];

            $heroImage = $this->resolveImageValue(
                valueField: 'hero_image_url',
                fileField: 'hero_image_file',
                deleteField: 'hero_image_url_delete',
                existingField: 'hero_image_url_existing',
                subDirectory: 'hero',
                modelValue: (string)$model->getData('hero_image_url'),
                data: $data,
                pendingCleanup: $pendingManagedAssetCleanup
            );

            $ctaBgImage = $this->resolveImageValue(
                valueField: 'cta_bg_image_url',
                fileField: 'cta_bg_image_file',
                deleteField: 'cta_bg_image_url_delete',
                existingField: 'cta_bg_image_url_existing',
                subDirectory: 'cta',
                modelValue: (string)$model->getData('cta_bg_image_url'),
                data: $data,
                pendingCleanup: $pendingManagedAssetCleanup
            );

            $promoImage = $this->resolveImageValue(
                valueField: 'promo_image_url',
                fileField: 'promo_image_file',
                deleteField: 'promo_image_url_delete',
                existingField: 'promo_image_url_existing',
                subDirectory: 'promo',
                modelValue: (string)$model->getData('promo_image_url'),
                data: $data,
                pendingCleanup: $pendingManagedAssetCleanup
            );

            $model->setData('website_id', $websiteId);
            $model->setData('title', $title);
            $model->setData('identifier', $identifier);
            $model->setData('is_active', isset($data['is_active']) ? (int)$data['is_active'] : 1);
            $model->setData('template_code', 'standard');
            $model->setData('raw_pdp_url', $parsed['raw_pdp_url']);
            $model->setData('product_id', $parsed['product_id']);
            $model->setData('source', $parsed['source']);
            $model->setData('pc_fallback_url', $parsed['pc_fallback_url']);
            $model->setData('mobile_fallback_url', $parsed['mobile_fallback_url']);
            $model->setData('pixel_code_override', $this->nullIfEmpty((string)($data['pixel_code_override'] ?? '')));
            $model->setData('content_name', $this->nullIfEmpty((string)($data['content_name'] ?? '')) ?: $title);
            $model->setData('promo_bar_text', $this->nullIfEmpty((string)($data['promo_bar_text'] ?? '')) ?: $this->config->getDefaultPromoBarText($storeId));
            $model->setData('headline', $this->nullIfEmpty((string)($data['headline'] ?? '')) ?: $title);
            $model->setData('subheadline', $this->nullIfEmpty((string)($data['subheadline'] ?? '')));
            $model->setData('cta_text', $this->nullIfEmpty((string)($data['cta_text'] ?? '')) ?: $this->config->getDefaultCtaText($storeId));
            $model->setData('desktop_message', $this->nullIfEmpty((string)($data['desktop_message'] ?? '')) ?: $this->config->getDefaultDesktopMessage($storeId));
            $model->setData('fail_message', $this->nullIfEmpty((string)($data['fail_message'] ?? '')) ?: $this->config->getDefaultFailMessage($storeId));
            $model->setData('hero_image_url', $heroImage);
            $model->setData('cta_bg_image_url', $ctaBgImage);
            $model->setData('promo_image_url', $promoImage);
            $model->setData('head_jump_enabled', isset($data['head_jump_enabled']) ? (int)$data['head_jump_enabled'] : 1);
            $model->setData('manual_button_enabled', isset($data['manual_button_enabled']) ? (int)$data['manual_button_enabled'] : 1);
            $model->setData('body_auto_jump_enabled', isset($data['body_auto_jump_enabled']) ? (int)$data['body_auto_jump_enabled'] : 1);
            $model->setData('head_jump_timeout_ms', max(0, (int)($data['head_jump_timeout_ms'] ?? $this->config->getDefaultHeadJumpTimeoutMs($storeId))));
            $model->setData('auto_jump_seconds', max(0, (int)($data['auto_jump_seconds'] ?? $this->config->getDefaultAutoJumpSeconds($storeId))));
            $model->setData('passthrough_params', $this->normalizePassthrough((string)($data['passthrough_params'] ?? $this->config->getDefaultPassthroughParams($storeId))));
            $model->setData('meta_title', $this->nullIfEmpty((string)($data['meta_title'] ?? '')));
            $model->setData('meta_description', $this->nullIfEmpty((string)($data['meta_description'] ?? '')));

            $this->landingPageRepository->save($model);
            $this->cleanupManagedAssets($pendingManagedAssetCleanup, $model);
            $this->messageManager->addSuccessMessage(__('Landing page saved.'));
            $this->dataPersistor->clear('pynarae_tiktok_landing_page');

            if ($this->getRequest()->getParam('back')) {
                return $this->_redirect('*/*/edit', ['id' => $model->getId()]);
            }

            return $this->_redirect('*/*/index');
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->dataPersistor->set('pynarae_tiktok_landing_page', $data);
            return $this->_redirect('*/*/edit', ['id' => $id ?: null]);
        }
    }

    private function resolveImageValue(
        string $valueField,
        string $fileField,
        string $deleteField,
        string $existingField,
        string $subDirectory,
        ?string $modelValue,
        array $data,
        array &$pendingCleanup
    ): ?string {
        $modelValue = $this->nullIfEmpty((string)$modelValue);
        $postedExistingValue = $this->nullIfEmpty((string)($data[$existingField] ?? ''));
        $currentValue = $modelValue ?? $postedExistingValue;

        if (!empty($data[$deleteField])) {
            if ($currentValue !== null) {
                $pendingCleanup[] = $currentValue;
            }
            return null;
        }

        if ($this->assetStorage->hasUpload($fileField)) {
            $uploaded = $this->assetStorage->saveUploadedImage($fileField, $subDirectory);
            if ($currentValue !== null && $currentValue !== $uploaded) {
                $pendingCleanup[] = $currentValue;
            }
            return $uploaded;
        }

        $externalValue = $this->nullIfEmpty((string)($data[$valueField] ?? ''));
        if ($externalValue !== null) {
            $this->assertValidExternalImageUrl($externalValue);
            if ($currentValue !== null && $currentValue !== $externalValue) {
                $pendingCleanup[] = $currentValue;
            }
            return $externalValue;
        }

        return $currentValue;
    }

    private function assertValidExternalImageUrl(string $url): void
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new LocalizedException(__('Please enter a valid external image URL.'));
        }

        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new LocalizedException(__('Only http and https image URLs are allowed.'));
        }

        if (mb_strlen($url) > 2048) {
            throw new LocalizedException(__('The external image URL is too long.'));
        }
    }

    private function normalizeIdentifier(string $identifier): string
    {
        $identifier = strtolower(trim($identifier));
        $identifier = preg_replace('/[^a-z0-9\-_]+/', '-', $identifier) ?: '';
        return trim($identifier, '-_');
    }

    private function nullIfEmpty(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private function normalizePassthrough(string $value): string
    {
        $items = array_filter(array_map('trim', explode(',', $value)));
        $items = array_values(array_unique($items));
        return implode(',', $items);
    }

    private function assertUniqueIdentifier(string $identifier, int $websiteId, int $currentId): void
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('identifier', $identifier)
            ->addFieldToFilter('website_id', $websiteId);

        if ($currentId > 0) {
            $collection->addFieldToFilter('entity_id', ['neq' => $currentId]);
        }

        if ($collection->getSize() > 0) {
            throw new LocalizedException(__('A landing page with this identifier already exists for the selected website.'));
        }
    }

    private function cleanupManagedAssets(array $pendingCleanup, DataObject $model): void
    {
        $pendingCleanup = array_values(array_unique(array_filter(array_map(
            fn ($value) => $this->nullIfEmpty((string)$value),
            $pendingCleanup
        ))));

        if ($pendingCleanup === []) {
            return;
        }

        $cleanupErrors = [];
        foreach ($pendingCleanup as $value) {
            try {
                $this->assetStorage->deleteIfManaged($value);
            } catch (\Throwable $cleanupException) {
                $cleanupErrors[] = sprintf('%s: %s', $value, $cleanupException->getMessage());
            }
        }

        if ($cleanupErrors !== []) {
            $this->logger->warning(
                'Landing page saved but one or more replaced managed assets could not be removed.',
                [
                    'landing_page_id' => (int)$model->getData('entity_id'),
                    'landing_page_title' => (string)$model->getData('title'),
                    'cleanup_errors' => $cleanupErrors,
                ]
            );
        }
    }
}
