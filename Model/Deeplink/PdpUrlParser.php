<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Model\Deeplink;

use Magento\Framework\Exception\LocalizedException;

class PdpUrlParser
{
    /**
     * @return array{raw_pdp_url:string,product_id:string,source:string,pc_fallback_url:string,mobile_fallback_url:string}
     * @throws LocalizedException
     */
    public function parse(string $rawUrl): array
    {
        $url = trim($rawUrl);
        if ($url === '') {
            return [
                'raw_pdp_url' => '',
                'product_id' => '',
                'source' => '',
                'pc_fallback_url' => '',
                'mobile_fallback_url' => '',
            ];
        }

        $parts = parse_url($url);
        if (empty($parts['host']) || !str_contains((string)$parts['host'], 'tiktok.com')) {
            throw new LocalizedException(__('Please enter a valid TikTok PDP URL.'));
        }

        $path = (string)($parts['path'] ?? '');
        if (!str_contains($path, '/shop/pdp/')) {
            throw new LocalizedException(__('The URL must be a TikTok Shop PDP URL.'));
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        $productId = (string)end($segments);
        if (!preg_match('/^\d+$/', $productId)) {
            throw new LocalizedException(__('Could not parse product_id from the TikTok PDP URL.'));
        }

        $query = [];
        parse_str((string)($parts['query'] ?? ''), $query);
        $source = isset($query['source']) && $query['source'] !== '' ? (string)$query['source'] : 'anchor';

        return [
            'raw_pdp_url' => $url,
            'product_id' => $productId,
            'source' => $source,
            'pc_fallback_url' => 'https://www.tiktok.com/shop/pdp/' . $productId,
            'mobile_fallback_url' => $url,
        ];
    }
}
