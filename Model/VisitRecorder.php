<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Model;

use Magento\Framework\App\Request\Http;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Pynarae\TiktokLandingPages\Model\ResourceModel\Visit as VisitResource;

class VisitRecorder
{
    private const TRACKING_KEYS = [
        'ttclid',
        'campaign_id',
        'campaign_name',
        'adgroup_id',
        'adgroup_name',
        'ad_id',
        'ad_name',
        'creative_id',
        'creative_name',
        'aid',
        'aid_name',
        'adid_v2',
        'adid_v2_name',
        'cid',
        'cid_name',
        'placement',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'utm_id',
        'gclid',
        'fbclid',
    ];

    public function __construct(
        private readonly VisitFactory $visitFactory,
        private readonly VisitResource $visitResource,
        private readonly StoreManagerInterface $storeManager,
        private readonly CookieManagerInterface $cookieManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function record(LandingPage $page, Http $request): ?Visit
    {
        try {
            $original = $this->getOriginalRequestData($request);
            $query = $this->collectQueryParams($request, $original['args']);
            $requestUri = $original['request_uri'];
            $ip = $this->getClientIp($request);
            $userAgent = (string)$request->getServer('HTTP_USER_AGENT', '');
            $websiteId = (int)$page->getData('website_id');

            $visit = $this->visitFactory->create();
            $visit->setData([
                'page_id' => (int)$page->getId() ?: null,
                'website_id' => $websiteId,
                'landing_identifier' => (string)$page->getData('identifier'),
                'landing_url' => $this->getLandingUrl($page, $requestUri, $original['host']),
                'request_uri' => $requestUri,
                'request_path' => (string)parse_url($requestUri, PHP_URL_PATH),
                'query_string' => $original['args'],
                'request_id' => $this->firstHeader($request, ['HTTP_X_MYUPONA_LP_REQUEST_ID', 'HTTP_X_REQUEST_ID', 'HTTP_X_AMZN_TRACE_ID']),
                'source_hash' => $this->buildSourceHash($requestUri, $ip, $userAgent),
                'ip' => $ip,
                'cf_ip' => $original['cf_ip'],
                'cf_ray' => $this->firstHeader($request, ['HTTP_X_MYUPONA_LP_CF_RAY', 'HTTP_CF_RAY']),
                'country' => $original['country'],
                'region' => $original['region'],
                'city' => $original['city'],
                'host' => $original['host'],
                'method' => $original['method'],
                'uri' => $original['uri'],
                'user_agent' => $userAgent,
                'device' => $this->detectDevice($userAgent),
                'browser' => $this->detectBrowser($userAgent),
                'referrer' => (string)$request->getServer('HTTP_REFERER', ''),
                'ttclid' => $query['ttclid'] ?: (string)$this->cookieManager->getCookie('ttclid'),
                'ttp' => (string)$this->cookieManager->getCookie('_ttp'),
                'campaign_id' => $this->firstQuery($query, ['campaign_id', 'utm_id']),
                'campaign_name' => $this->firstQuery($query, ['campaign_name', 'utm_campaign']),
                'adgroup_id' => $this->firstQuery($query, ['adgroup_id', 'aid']),
                'adgroup_name' => $this->firstQuery($query, ['adgroup_name', 'aid_name', 'utm_term']),
                'ad_id' => $this->firstQuery($query, ['ad_id', 'adid_v2', 'utm_content']),
                'ad_name' => $this->firstQuery($query, ['ad_name', 'adid_v2_name']),
                'creative_id' => $this->firstQuery($query, ['creative_id', 'cid']),
                'creative_name' => $this->firstQuery($query, ['creative_name', 'cid_name']),
                'placement' => $query['placement'],
                'utm_source' => $query['utm_source'],
                'utm_medium' => $query['utm_medium'],
                'utm_campaign' => $query['utm_campaign'],
                'utm_content' => $query['utm_content'],
                'utm_term' => $query['utm_term'],
                'utm_id' => $query['utm_id'],
                'gclid' => $query['gclid'],
                'fbclid' => $query['fbclid'],
                'product_id' => (string)$page->getData('product_id'),
                'content_name' => (string)($page->getData('content_name') ?: $page->getData('title')),
                'response_status' => 200,
                'extra_json' => $this->encode([
                    'accept_language' => (string)$request->getServer('HTTP_ACCEPT_LANGUAGE', ''),
                    'sec_ch_ua' => (string)$request->getServer('HTTP_SEC_CH_UA', ''),
                    'sec_ch_ua_mobile' => (string)$request->getServer('HTTP_SEC_CH_UA_MOBILE', ''),
                    'sec_ch_ua_platform' => (string)$request->getServer('HTTP_SEC_CH_UA_PLATFORM', ''),
                    'raw_query' => $query,
                    'source' => 'magento_request',
                ]),
                'captured_at' => gmdate('Y-m-d H:i:s'),
            ]);

            $this->visitResource->save($visit);
            return $visit;
        } catch (\Throwable $exception) {
            $this->logger->warning('Unable to record TikTok landing page visit.', [
                'message' => $exception->getMessage(),
                'identifier' => (string)$page->getData('identifier'),
            ]);
            return null;
        }
    }

    private function collectQueryParams(Http $request, string $args): array
    {
        $parsed = [];
        if ($args !== '') {
            parse_str($args, $parsed);
        }

        $values = [];
        foreach (self::TRACKING_KEYS as $key) {
            $raw = $parsed[$key] ?? $request->getParam($key, '');
            $values[$key] = $this->clean((string)$raw, $key === 'ttclid' ? 768 : 255);
        }
        return $values;
    }

    private function firstQuery(array $query, array $keys): string
    {
        foreach ($keys as $key) {
            if (!empty($query[$key])) {
                return (string)$query[$key];
            }
        }
        return '';
    }

    private function clean(string $value, int $maxLength = 255): string
    {
        $value = trim(rawurldecode($value));
        return mb_substr($value, 0, $maxLength);
    }

    private function getOriginalRequestData(Http $request): array
    {
        $requestUri = $this->headerValue($request, 'HTTP_X_MYUPONA_LP_REQUEST_URI', 8192);
        if ($requestUri === '') {
            $requestUri = (string)$request->getRequestUri();
        }

        $args = $this->headerValue($request, 'HTTP_X_MYUPONA_LP_ARGS', 8192);
        if ($args === '' && str_contains($requestUri, '?')) {
            $args = (string)parse_url($requestUri, PHP_URL_QUERY);
        }
        if ($args === '') {
            $args = (string)$request->getServer('QUERY_STRING', '');
        }

        return [
            'request_uri' => $requestUri,
            'args' => $args,
            'host' => $this->firstNonEmpty([
                $this->headerValue($request, 'HTTP_X_MYUPONA_LP_HOST'),
                (string)$request->getServer('HTTP_HOST', ''),
            ], 255),
            'method' => $this->firstNonEmpty([
                $this->headerValue($request, 'HTTP_X_MYUPONA_LP_METHOD', 16),
                (string)$request->getMethod(),
            ], 16),
            'uri' => $this->firstNonEmpty([
                $this->headerValue($request, 'HTTP_X_MYUPONA_LP_URI'),
                (string)parse_url($requestUri, PHP_URL_PATH),
            ], 255),
            'cf_ip' => $this->firstNonEmpty([
                $this->headerValue($request, 'HTTP_X_MYUPONA_LP_CF_EDGE_IP', 64),
                (string)$request->getServer('REMOTE_ADDR', ''),
            ], 64),
            'country' => $this->firstNonEmpty([
                $this->headerValue($request, 'HTTP_X_MYUPONA_LP_COUNTRY', 64),
                (string)$request->getServer('HTTP_CF_IPCOUNTRY', ''),
            ], 64),
            'region' => $this->firstNonEmpty([
                $this->headerValue($request, 'HTTP_X_MYUPONA_LP_REGION', 128),
                (string)$request->getServer('HTTP_CF_REGION', ''),
            ], 128),
            'city' => $this->firstNonEmpty([
                $this->headerValue($request, 'HTTP_X_MYUPONA_LP_CITY', 128),
                (string)$request->getServer('HTTP_CF_IPCITY', ''),
            ], 128),
        ];
    }

    private function getLandingUrl(LandingPage $page, string $requestUri, string $host): string
    {
        if ($host !== '') {
            return 'https://' . $host . $requestUri;
        }

        try {
            $website = $this->storeManager->getWebsite((int)$page->getData('website_id'));
            $store = $website->getDefaultStore();
            return rtrim($store->getBaseUrl(), '/') . $requestUri;
        } catch (\Throwable) {
            return $requestUri;
        }
    }

    private function getClientIp(Http $request): string
    {
        $ip = $this->firstHeader($request, ['HTTP_X_MYUPONA_LP_CLIENT_IP', 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP']);
        if ($ip !== '') {
            return mb_substr($ip, 0, 64);
        }

        $forwardedFor = (string)$request->getServer('HTTP_X_FORWARDED_FOR', '');
        if ($forwardedFor !== '') {
            $parts = array_map('trim', explode(',', $forwardedFor));
            if (!empty($parts[0])) {
                return mb_substr($parts[0], 0, 64);
            }
        }

        return mb_substr((string)$request->getServer('REMOTE_ADDR', ''), 0, 64);
    }

    private function firstHeader(Http $request, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $this->headerValue($request, $key);
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }

    private function headerValue(Http $request, string $key, int $maxLength = 255): string
    {
        $value = trim((string)$request->getServer($key, ''));
        if ($value === '') {
            return '';
        }
        return mb_substr($value, 0, $maxLength);
    }

    private function firstNonEmpty(array $values, int $maxLength): string
    {
        foreach ($values as $value) {
            $value = trim((string)$value);
            if ($value !== '') {
                return mb_substr($value, 0, $maxLength);
            }
        }
        return '';
    }

    private function buildSourceHash(string $requestUri, string $ip, string $userAgent): string
    {
        return hash('sha256', 'live|' . microtime(true) . '|' . $requestUri . '|' . $ip . '|' . $userAgent);
    }

    private function detectDevice(string $userAgent): string
    {
        if (preg_match('/iPad|Tablet/i', $userAgent)) {
            return 'tablet';
        }
        if (preg_match('/Mobi|Android|iPhone|iPod/i', $userAgent)) {
            return 'mobile';
        }
        return $userAgent === '' ? 'unknown' : 'desktop';
    }

    private function detectBrowser(string $userAgent): string
    {
        $ua = strtolower($userAgent);
        return match (true) {
            $ua === '' => 'unknown',
            str_contains($ua, 'tiktok') || str_contains($ua, 'musical_ly') || str_contains($ua, 'bytedance') => 'tiktok-webview',
            str_contains($ua, 'edg/') => 'edge',
            str_contains($ua, 'chrome/') || str_contains($ua, 'crios/') => 'chrome',
            str_contains($ua, 'safari/') && !str_contains($ua, 'chrome/') => 'safari',
            str_contains($ua, 'firefox/') => 'firefox',
            default => 'other',
        };
    }

    private function encode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}
