<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Model\Frontend;

use Magento\Framework\HTTP\PhpEnvironment\Request;

class EnvironmentDetector
{
    public function detect(Request $request): array
    {
        $ua = (string)$request->getServer('HTTP_USER_AGENT', '');
        return [
            'user_agent' => $ua,
            'is_mobile' => (bool)preg_match('/Mobi|Android|iPhone|iPad|iPod/i', $ua),
            'is_desktop' => !(bool)preg_match('/Mobi|Android|iPhone|iPad|iPod/i', $ua),
            'is_tiktok_webview' => (bool)preg_match('/TikTok|TTWebView|musical_ly|ByteLocale|ByteFullLocale/i', $ua),
        ];
    }
}
