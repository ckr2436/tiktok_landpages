<?php
declare(strict_types=1);

$options = getopt('', ['file::', 'days::', 'top::', 'json::', 'help::']);

if (isset($options['help'])) {
    echo "Usage: php analyze-nginx-landing-log.php [--file=/var/log/nginx/myupona_tiktok_landing.json] [--days=7] [--top=20] [--json=1]\n";
    exit(0);
}

$file = (string)($options['file'] ?? '/var/log/nginx/myupona_tiktok_landing.json');
$days = max(1, (int)($options['days'] ?? 7));
$top = max(5, (int)($options['top'] ?? 20));
$jsonOutput = (string)($options['json'] ?? '') === '1';
$since = time() - ($days * 86400);

$stats = [
    'file' => $file,
    'days' => $days,
    'since' => date('c', $since),
    'total' => 0,
    'invalid_lines' => 0,
    'unique_ips' => 0,
    'avg_request_time' => 0,
    'p50_request_time' => 0,
    'p95_request_time' => 0,
    'status' => [],
    'landing_page' => [],
    'campaign_id' => [],
    'adgroup_id' => [],
    'ad_id' => [],
    'placement' => [],
    'utm_source' => [],
    'utm_medium' => [],
    'utm_campaign' => [],
    'utm_content' => [],
    'referrer_host' => [],
    'device' => [],
    'browser' => [],
    'by_day' => [],
    'by_hour' => [],
    'errors' => [],
];

if (!is_file($file)) {
    fwrite(STDERR, "Log file not found: {$file}\n");
    exit(1);
}

$ips = [];
$times = [];

$fh = fopen($file, 'rb');
if (!$fh) {
    fwrite(STDERR, "Cannot open log file: {$file}\n");
    exit(1);
}

while (($line = fgets($fh)) !== false) {
    $line = trim($line);
    if ($line === '') {
        continue;
    }

    $row = json_decode($line, true);
    if (!is_array($row)) {
        $stats['invalid_lines']++;
        continue;
    }

    $ts = isset($row['ts']) ? strtotime((string)$row['ts']) : false;
    if ($ts === false || $ts < $since) {
        continue;
    }

    $stats['total']++;
    $ip = val($row, 'ip', 'unknown');
    $ips[$ip] = true;

    inc($stats['status'], (string)($row['status'] ?? 'unknown'));
    inc($stats['landing_page'], val($row, 'landing_page', 'unknown'));
    inc($stats['campaign_id'], val($row, 'campaign_id', '(none)'));
    inc($stats['adgroup_id'], val($row, 'adgroup_id', '(none)'));
    inc($stats['ad_id'], val($row, 'ad_id', '(none)'));
    inc($stats['placement'], val($row, 'placement', '(none)'));
    inc($stats['utm_source'], val($row, 'utm_source', '(none)'));
    inc($stats['utm_medium'], val($row, 'utm_medium', '(none)'));
    inc($stats['utm_campaign'], val($row, 'utm_campaign', '(none)'));
    inc($stats['utm_content'], val($row, 'utm_content', '(none)'));
    inc($stats['referrer_host'], refHost(val($row, 'referer', '(direct)')));
    inc($stats['device'], deviceFromUa(val($row, 'ua', '')));
    inc($stats['browser'], browserFromUa(val($row, 'ua', '')));
    inc($stats['by_day'], date('Y-m-d', $ts));
    inc($stats['by_hour'], date('Y-m-d H:00', $ts));

    $requestTime = isset($row['request_time']) ? (float)$row['request_time'] : 0.0;
    if ($requestTime > 0) {
        $times[] = $requestTime;
    }

    $status = (int)($row['status'] ?? 0);
    if ($status >= 400) {
        $key = $status . ' ' . val($row, 'request_uri', '');
        inc($stats['errors'], $key);
    }
}
fclose($fh);

$stats['unique_ips'] = count($ips);
foreach (['status', 'landing_page', 'campaign_id', 'adgroup_id', 'ad_id', 'placement', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'referrer_host', 'device', 'browser', 'by_day', 'by_hour', 'errors'] as $key) {
    arsort($stats[$key]);
    $stats[$key] = array_slice($stats[$key], 0, $top, true);
}

if ($times) {
    sort($times);
    $stats['avg_request_time'] = round(array_sum($times) / count($times), 4);
    $stats['p50_request_time'] = percentile($times, 0.50);
    $stats['p95_request_time'] = percentile($times, 0.95);
}

if ($jsonOutput) {
    echo json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(0);
}

echo "MYUPONA TikTok Landing Page Log Analysis\n";
echo "Log file: {$stats['file']}\n";
echo "Window: last {$stats['days']} day(s), since {$stats['since']}\n";
echo "Total hits: {$stats['total']}\n";
echo "Unique IPs: {$stats['unique_ips']}\n";
echo "Request time: avg={$stats['avg_request_time']}s p50={$stats['p50_request_time']}s p95={$stats['p95_request_time']}s\n";
echo "Invalid JSON lines: {$stats['invalid_lines']}\n\n";

printTop('Status', $stats['status']);
printTop('Landing Pages', $stats['landing_page']);
printTop('Campaign IDs', $stats['campaign_id']);
printTop('Adgroup IDs', $stats['adgroup_id']);
printTop('Ad IDs', $stats['ad_id']);
printTop('Placements', $stats['placement']);
printTop('UTM Source', $stats['utm_source']);
printTop('UTM Medium', $stats['utm_medium']);
printTop('UTM Campaign', $stats['utm_campaign']);
printTop('Referrer Hosts', $stats['referrer_host']);
printTop('Device', $stats['device']);
printTop('Browser', $stats['browser']);
printTop('By Day', $stats['by_day']);
printTop('Recent Error URLs', $stats['errors']);

function inc(array &$bucket, string $key): void
{
    $key = $key === '' ? '(none)' : $key;
    $bucket[$key] = ($bucket[$key] ?? 0) + 1;
}

function val(array $row, string $key, string $fallback): string
{
    $value = isset($row[$key]) ? trim((string)$row[$key]) : '';
    if ($value === '' || $value === '-') {
        return $fallback;
    }
    return rawurldecode($value);
}

function refHost(string $referer): string
{
    if ($referer === '' || $referer === '-' || $referer === '(direct)') {
        return '(direct)';
    }
    $host = parse_url($referer, PHP_URL_HOST);
    return is_string($host) && $host !== '' ? strtolower($host) : '(unknown)';
}

function deviceFromUa(string $ua): string
{
    $ua = strtolower($ua);
    if ($ua === '') {
        return 'unknown';
    }
    if (str_contains($ua, 'ipad') || str_contains($ua, 'tablet')) {
        return 'tablet';
    }
    if (str_contains($ua, 'mobile') || str_contains($ua, 'iphone') || str_contains($ua, 'android')) {
        return 'mobile';
    }
    return 'desktop';
}

function browserFromUa(string $ua): string
{
    $ua = strtolower($ua);
    return match (true) {
        $ua === '' => 'unknown',
        str_contains($ua, 'tiktok') || str_contains($ua, 'musical_ly') || str_contains($ua, 'bytedance') => 'tiktok-webview',
        str_contains($ua, 'edg/') => 'edge',
        str_contains($ua, 'chrome/') || str_contains($ua, 'crios/') => 'chrome',
        str_contains($ua, 'safari/') && !str_contains($ua, 'chrome/') => 'safari',
        str_contains($ua, 'firefox/') || str_contains($ua, 'fxios/') => 'firefox',
        default => 'other',
    };
}

function percentile(array $values, float $p): float
{
    $count = count($values);
    if ($count === 0) {
        return 0.0;
    }
    $index = (int)ceil($p * $count) - 1;
    $index = max(0, min($count - 1, $index));
    return round((float)$values[$index], 4);
}

function printTop(string $title, array $rows): void
{
    echo "== {$title} ==\n";
    if (!$rows) {
        echo "(none)\n\n";
        return;
    }
    foreach ($rows as $key => $count) {
        echo str_pad((string)$count, 8, ' ', STR_PAD_LEFT) . "  {$key}\n";
    }
    echo "\n";
}
