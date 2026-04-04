<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Model\Media;

use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

class AssetUrlResolver
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly AssetStorage $assetStorage
    ) {
    }

    public function resolve(?string $value, ?int $storeId = null): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        if ($this->isAbsoluteUrl($value) || str_starts_with($value, '//') || str_starts_with($value, 'data:')) {
            return $value;
        }

        $baseMediaUrl = rtrim(
            $this->storeManager->getStore($storeId)->getBaseUrl(UrlInterface::URL_TYPE_MEDIA),
            '/'
        );

        return $baseMediaUrl . '/' . ltrim($value, '/');
    }

    public function isExternalUrl(?string $value): bool
    {
        $value = trim((string)$value);
        if ($value === '') {
            return false;
        }

        return $this->isAbsoluteUrl($value) || str_starts_with($value, '//');
    }

    public function isManagedAsset(?string $value): bool
    {
        return $this->assetStorage->isManagedAsset($value);
    }

    private function isAbsoluteUrl(string $value): bool
    {
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower((string)parse_url($value, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true);
    }
}
