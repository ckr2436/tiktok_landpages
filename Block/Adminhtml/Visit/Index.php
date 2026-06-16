<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Block\Adminhtml\Visit;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\DataObject;
use Pynarae\TiktokLandingPages\Model\Conversion;
use Pynarae\TiktokLandingPages\Model\ResourceModel\Conversion\CollectionFactory as ConversionCollectionFactory;
use Pynarae\TiktokLandingPages\Model\ResourceModel\LandingPage\CollectionFactory as LandingPageCollectionFactory;
use Pynarae\TiktokLandingPages\Model\ResourceModel\Visit\CollectionFactory as VisitCollectionFactory;

class Index extends Template
{
    protected $_template = 'Pynarae_TiktokLandingPages::visit/index.phtml';

    public function __construct(
        Context $context,
        private readonly VisitCollectionFactory $visitCollectionFactory,
        private readonly ConversionCollectionFactory $conversionCollectionFactory,
        private readonly LandingPageCollectionFactory $landingPageCollectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getVisits(): array
    {
        $filters = $this->getFilters();
        $collection = $this->visitCollectionFactory->create();
        $collection->setOrder('captured_at', 'DESC');
        $collection->setPageSize($this->getLimit());

        if ($filters['landing'] !== '') {
            $like = '%' . $filters['landing'] . '%';
            $collection->addFieldToFilter(
                ['landing_identifier', 'landing_url', 'request_uri'],
                [['like' => $like], ['like' => $like], ['like' => $like]]
            );
        }
        if ($filters['from'] !== '') {
            $collection->addFieldToFilter('captured_at', ['gteq' => $this->normalizeDateTime($filters['from'])]);
        }
        if ($filters['to'] !== '') {
            $collection->addFieldToFilter('captured_at', ['lteq' => $this->normalizeDateTime($filters['to'])]);
        }
        foreach ([
            'ttclid',
            'campaign_id',
            'campaign_name',
            'adgroup_id',
            'adgroup_name',
            'ad_id',
            'ad_name',
            'creative_id',
            'creative_name',
            'ip',
            'cf_ip',
            'country',
            'region',
            'city',
        ] as $field) {
            if ($filters[$field] !== '') {
                $collection->addFieldToFilter($field, ['like' => '%' . $filters[$field] . '%']);
            }
        }
        if ($filters['keyword'] !== '') {
            $like = '%' . $filters['keyword'] . '%';
            $fields = [
                'landing_url',
                'request_uri',
                'query_string',
                'host',
                'uri',
                'referrer',
                'ttclid',
                'campaign_id',
                'campaign_name',
                'adgroup_id',
                'adgroup_name',
                'ad_id',
                'ad_name',
                'creative_id',
                'creative_name',
                'placement',
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'utm_content',
                'utm_term',
                'utm_id',
                'ip',
                'cf_ip',
                'country',
                'region',
                'city',
            ];
            $conditions = array_fill(0, count($fields), ['like' => $like]);
            $collection->addFieldToFilter($fields, $conditions);
        }

        return $collection->getItems();
    }

    public function getLatestConversions(array $visits): array
    {
        $visitIds = [];
        foreach ($visits as $visit) {
            $visitIds[] = (int)$visit->getId();
        }
        if (!$visitIds) {
            return [];
        }

        $collection = $this->conversionCollectionFactory->create();
        $collection->addFieldToFilter('visit_id', ['in' => $visitIds]);
        $collection->setOrder('created_at', 'DESC');

        $latest = [];
        foreach ($collection as $conversion) {
            $visitId = (int)$conversion->getData('visit_id');
            if (!isset($latest[$visitId])) {
                $latest[$visitId] = $conversion;
            }
        }

        return $latest;
    }

    public function getLandingPageOptions(): array
    {
        $collection = $this->landingPageCollectionFactory->create();
        $collection->setOrder('title', 'ASC');
        $options = [];
        foreach ($collection as $page) {
            $identifier = (string)$page->getData('identifier');
            $options[] = [
                'value' => $identifier,
                'label' => sprintf('%s (%s)', (string)$page->getData('title'), $identifier),
            ];
        }
        return $options;
    }

    public function getFilters(): array
    {
        return [
            'landing' => $this->cleanParam('landing'),
            'tz' => $this->getSelectedTimezone(),
            'from' => $this->cleanParam('from'),
            'to' => $this->cleanParam('to'),
            'ttclid' => $this->cleanParam('ttclid'),
            'campaign_id' => $this->cleanParam('campaign_id'),
            'campaign_name' => $this->cleanParam('campaign_name'),
            'adgroup_id' => $this->cleanParam('adgroup_id'),
            'adgroup_name' => $this->cleanParam('adgroup_name'),
            'ad_id' => $this->cleanParam('ad_id'),
            'ad_name' => $this->cleanParam('ad_name'),
            'creative_id' => $this->cleanParam('creative_id'),
            'creative_name' => $this->cleanParam('creative_name'),
            'ip' => $this->cleanParam('ip'),
            'cf_ip' => $this->cleanParam('cf_ip'),
            'country' => $this->cleanParam('country'),
            'region' => $this->cleanParam('region'),
            'city' => $this->cleanParam('city'),
            'keyword' => $this->cleanParam('keyword'),
        ];
    }

    public function getLimit(): int
    {
        $limit = (int)$this->getRequest()->getParam('limit', 100);
        return max(20, min(500, $limit));
    }

    public function getFormActionUrl(): string
    {
        return $this->getUrl('*/*/index');
    }

    public function getResetUrl(): string
    {
        return $this->getUrl('*/*/index');
    }

    public function getReportUrl(): string
    {
        return $this->getUrl('*/*/report');
    }

    public function getDefaultEventTime(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone($this->getSelectedTimezone())))->format('Y-m-d\TH:i');
    }

    public function formatDateTime(?string $value): string
    {
        if (!$value) {
            return '';
        }
        $timestamp = strtotime($value);
        return $timestamp ? date('Y-m-d H:i:s', $timestamp) . ' UTC' : $value;
    }

    public function formatDateTimeInSelectedTimezone(?string $value): string
    {
        return $this->formatDateTimeInTimezone($value, $this->getSelectedTimezone());
    }

    public function formatDateTimeInTimezone(?string $value, string $timezone): string
    {
        if (!$value) {
            return '';
        }

        try {
            $date = new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
            $zone = new \DateTimeZone($this->normalizeTimezone($timezone));
            return $date->setTimezone($zone)->format('Y-m-d H:i:s T');
        } catch (\Throwable) {
            return (string)$value;
        }
    }

    public function formatUnixTime($value, ?string $timezone = null): string
    {
        if (!$value) {
            return '-';
        }
        try {
            $zone = new \DateTimeZone($this->normalizeTimezone($timezone ?: $this->getSelectedTimezone()));
            return (new \DateTimeImmutable('@' . (int)$value))->setTimezone($zone)->format('Y-m-d H:i:s T');
        } catch (\Throwable) {
            return (string)$value;
        }
    }

    public function getSelectedTimezone(): string
    {
        return $this->normalizeTimezone($this->cleanParam('tz') ?: 'America/New_York');
    }

    public function getTimezoneOptions(): array
    {
        return [
            'America/New_York' => 'US Eastern (New York)',
            'America/Chicago' => 'US Central (Chicago)',
            'America/Denver' => 'US Mountain (Denver)',
            'America/Los_Angeles' => 'US Pacific (Los Angeles)',
            'Asia/Shanghai' => 'China (Shanghai)',
            'UTC' => 'UTC',
        ];
    }

    public function getEventNameOptions(): array
    {
        return [
            Conversion::EVENT_PURCHASE => 'Purchase - current TikTok purchase event',
            Conversion::EVENT_COMPLETE_PAYMENT => 'CompletePayment - legacy compatibility',
        ];
    }

    public function short(?string $value, int $length = 56): string
    {
        $value = trim((string)$value);
        if (mb_strlen($value) <= $length) {
            return $value;
        }
        return mb_substr($value, 0, $length - 1) . '...';
    }

    public function display(?string $value): string
    {
        $value = trim((string)$value);
        return $value === '' ? '-' : $value;
    }

    public function formatRequestTime($value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }
        return rtrim(rtrim(number_format((float)$value, 3), '0'), '.') . 's';
    }

    public function money($value, string $currency): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        return strtoupper($currency ?: 'USD') . ' ' . number_format((float)$value, 2);
    }

    public function statusClass(?DataObject $conversion): string
    {
        if (!$conversion || !$conversion->getId()) {
            return 'no-event';
        }
        return (string)$conversion->getData('status');
    }

    private function cleanParam(string $key): string
    {
        return trim((string)$this->getRequest()->getParam($key, ''));
    }

    private function normalizeTimezone(string $timezone): string
    {
        $timezone = trim($timezone) ?: 'America/New_York';
        return array_key_exists($timezone, $this->getTimezoneOptions()) ? $timezone : 'America/New_York';
    }

    private function normalizeDateTime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return $value;
        }

        try {
            $date = new \DateTimeImmutable(str_replace('T', ' ', $value), new \DateTimeZone($this->getSelectedTimezone()));
            return gmdate('Y-m-d H:i:s', $date->getTimestamp());
        } catch (\Throwable) {
            return $value;
        }
    }
}
