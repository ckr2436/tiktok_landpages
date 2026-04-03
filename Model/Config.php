<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_BASE = 'pynarae_tiktoklandingpages/defaults/';

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function getDefaultAutoJumpSeconds(?int $storeId = null): int
    {
        return (int)$this->getValue('auto_jump_seconds', $storeId);
    }

    public function getDefaultHeadJumpTimeoutMs(?int $storeId = null): int
    {
        return (int)$this->getValue('head_jump_timeout_ms', $storeId);
    }

    public function getDefaultPassthroughParams(?int $storeId = null): string
    {
        return (string)$this->getValue('passthrough_params', $storeId);
    }

    public function getDefaultPromoBarText(?int $storeId = null): string
    {
        return (string)$this->getValue('promo_bar_text', $storeId);
    }

    public function getDefaultCtaText(?int $storeId = null): string
    {
        return (string)$this->getValue('cta_text', $storeId);
    }

    public function getDefaultDesktopMessage(?int $storeId = null): string
    {
        return (string)$this->getValue('desktop_message', $storeId);
    }

    public function getDefaultFailMessage(?int $storeId = null): string
    {
        return (string)$this->getValue('fail_message', $storeId);
    }

    private function getValue(string $field, ?int $storeId = null): mixed
    {
        return $this->scopeConfig->getValue(self::XML_PATH_BASE . $field, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
