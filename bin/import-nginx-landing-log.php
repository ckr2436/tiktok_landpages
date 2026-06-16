<?php
declare(strict_types=1);

use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ResourceConnection;

require dirname(__DIR__, 5) . '/app/bootstrap.php';

$params = parseArgs($argv);
$file = $params['file'] ?? '/var/log/nginx/myupona_tiktok_landing.json';
$limit = isset($params['limit']) ? max(0, (int)$params['limit']) : 0;

if (!is_readable($file)) {
    fwrite(STDERR, "Log file is not readable: {$file}\n");
    exit(1);
}

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
try {
    $objectManager->get(\Magento\Framework\App\State::class)->setAreaCode('adminhtml');
} catch (\Throwable) {
}

/** @var ResourceConnection $resource */
$resource = $objectManager->get(ResourceConnection::class);
$connection = $resource->getConnection();
$visitTable = $resource->getTableName('pynarae_tiktok_landing_visit');
$pageTable = $resource->getTableName('pynarae_tiktok_landing_page');
$pages = loadPages($connection, $pageTable);

$handle = fopen($file, 'rb');
$imported = 0;
$updated = 0;
$skipped = 0;
$invalid = 0;

while (($line = fgets($handle)) !== false) {
    $line = trim($line);
    if ($line === '') {
        continue;
    }

    $row = json_decode($line, true);
    if (!is_array($row)) {
        $invalid++;
        continue;
    }

    $identifier = value($row, 'landing_page');
    if ($identifier === '') {
        $skipped++;
        continue;
    }

    $requestUri = value($row, 'request_uri');
    $queryArgs = parseQueryString(value($row, 'args'));
    $sourceHash = hash('sha256', 'nginx|' . value($row, 'ts') . '|' . value($row, 'ip') . '|' . $requestUri . '|' . value($row, 'ua'));
    $page = $pages[$identifier] ?? [];
    $host = value($row, 'host') ?: 'myupona.com';
    $landingUrl = $requestUri !== '' ? 'https://' . $host . $requestUri : '';
    $capturedAt = parseTime(value($row, 'ts'));
    $data = [
        'page_id' => $page['entity_id'] ?? null,
        'website_id' => $page['website_id'] ?? 1,
        'landing_identifier' => $identifier,
        'landing_url' => $landingUrl,
        'request_uri' => $requestUri,
        'request_path' => (string)parse_url($requestUri, PHP_URL_PATH),
        'query_string' => value($row, 'args'),
        'request_id' => value($row, 'request_id'),
        'source_hash' => $sourceHash,
        'ip' => value($row, 'ip'),
        'cf_ip' => value($row, 'cf_edge_ip') ?: value($row, 'cf_ip'),
        'cf_ray' => value($row, 'cf_ray'),
        'country' => value($row, 'country'),
        'region' => value($row, 'region'),
        'city' => value($row, 'city'),
        'host' => $host,
        'method' => value($row, 'method'),
        'uri' => value($row, 'uri'),
        'user_agent' => value($row, 'ua'),
        'device' => detectDevice(value($row, 'ua')),
        'browser' => detectBrowser(value($row, 'ua')),
        'referrer' => value($row, 'referer'),
        'ttclid' => substrValue(value($row, 'ttclid'), 768),
        'campaign_id' => pick($row, $queryArgs, ['campaign_id', 'utm_id']),
        'campaign_name' => pick($row, $queryArgs, ['campaign_name', 'utm_campaign']),
        'adgroup_id' => pick($row, $queryArgs, ['adgroup_id', 'aid']),
        'adgroup_name' => pick($row, $queryArgs, ['adgroup_name', 'aid_name', 'utm_term']),
        'ad_id' => pick($row, $queryArgs, ['ad_id', 'adid_v2', 'utm_content']),
        'ad_name' => pick($row, $queryArgs, ['ad_name', 'adid_v2_name']),
        'creative_id' => pick($row, $queryArgs, ['creative_id', 'cid']),
        'creative_name' => pick($row, $queryArgs, ['creative_name', 'cid_name']),
        'placement' => pick($row, $queryArgs, ['placement']),
        'utm_source' => pick($row, $queryArgs, ['utm_source']),
        'utm_medium' => pick($row, $queryArgs, ['utm_medium']),
        'utm_campaign' => pick($row, $queryArgs, ['utm_campaign']),
        'utm_content' => pick($row, $queryArgs, ['utm_content']),
        'utm_term' => pick($row, $queryArgs, ['utm_term']),
        'utm_id' => pick($row, $queryArgs, ['utm_id']),
        'gclid' => value($row, 'gclid'),
        'fbclid' => value($row, 'fbclid'),
        'product_id' => $page['product_id'] ?? null,
        'content_name' => ($page['content_name'] ?? '') ?: ($page['title'] ?? null),
        'response_status' => (int)(value($row, 'status') ?: 200),
        'request_time' => value($row, 'request_time') !== '' ? (float)value($row, 'request_time') : (value($row, 'rt') !== '' ? (float)value($row, 'rt') : null),
        'extra_json' => json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'captured_at' => $capturedAt,
    ];

    $existingId = findExistingVisitId($connection, $visitTable, $sourceHash, value($row, 'ttclid'), $capturedAt);
    if ($existingId) {
        $connection->update($visitTable, $data, ['visit_id = ?' => $existingId]);
        $updated++;
        continue;
    }

    $connection->insert($visitTable, $data);
    $imported++;
    if ($limit > 0 && $imported >= $limit) {
        break;
    }
}
fclose($handle);

echo "Imported: {$imported}\n";
echo "Updated: {$updated}\n";
echo "Skipped: {$skipped}\n";
echo "Invalid: {$invalid}\n";

function parseArgs(array $argv): array
{
    $params = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (str_starts_with($arg, '--') && str_contains($arg, '=')) {
            [$key, $value] = explode('=', substr($arg, 2), 2);
            $params[$key] = $value;
        }
    }
    return $params;
}

function loadPages($connection, string $pageTable): array
{
    $rows = $connection->fetchAll(
        $connection->select()->from($pageTable, ['entity_id', 'website_id', 'identifier', 'product_id', 'content_name', 'title'])
    );
    $pages = [];
    foreach ($rows as $row) {
        $pages[(string)$row['identifier']] = $row;
    }
    return $pages;
}

function value(array $row, string $key): string
{
    $value = isset($row[$key]) ? trim((string)$row[$key]) : '';
    return $value === '-' ? '' : rawurldecode($value);
}

function parseQueryString(string $args): array
{
    if ($args === '') {
        return [];
    }
    $parsed = [];
    parse_str($args, $parsed);
    return is_array($parsed) ? $parsed : [];
}

function pick(array $row, array $queryArgs, array $keys): string
{
    foreach ($keys as $key) {
        $value = value($row, $key);
        if ($value !== '') {
            return $value;
        }
        if (isset($queryArgs[$key])) {
            $queryValue = trim((string)$queryArgs[$key]);
            if ($queryValue !== '' && $queryValue !== '-') {
                return mb_substr($queryValue, 0, 255);
            }
        }
    }
    return '';
}

function substrValue(string $value, int $length): string
{
    return mb_substr($value, 0, $length);
}

function findExistingVisitId($connection, string $visitTable, string $sourceHash, string $ttclid, string $capturedAt): int
{
    $existing = (int)$connection->fetchOne(
        $connection->select()->from($visitTable, 'visit_id')->where('source_hash = ?', $sourceHash)->limit(1)
    );
    if ($existing) {
        return $existing;
    }

    $ttclid = value(['ttclid' => $ttclid], 'ttclid');
    if ($ttclid === '') {
        return 0;
    }

    $prefix = mb_substr($ttclid, 0, 255);
    $from = gmdate('Y-m-d H:i:s', strtotime($capturedAt) - 600);
    $to = gmdate('Y-m-d H:i:s', strtotime($capturedAt) + 600);
    return (int)$connection->fetchOne(
        $connection->select()
            ->from($visitTable, 'visit_id')
            ->where('ttclid IN (?)', array_values(array_unique([$ttclid, $prefix])))
            ->where('captured_at BETWEEN ? AND ?', $from, $to)
            ->order('visit_id DESC')
            ->limit(1)
    );
}

function parseTime(string $value): string
{
    $timestamp = $value !== '' ? strtotime($value) : false;
    return gmdate('Y-m-d H:i:s', $timestamp ?: time());
}

function detectDevice(string $userAgent): string
{
    if (preg_match('/iPad|Tablet/i', $userAgent)) {
        return 'tablet';
    }
    if (preg_match('/Mobi|Android|iPhone|iPod/i', $userAgent)) {
        return 'mobile';
    }
    return $userAgent === '' ? 'unknown' : 'desktop';
}

function detectBrowser(string $userAgent): string
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
