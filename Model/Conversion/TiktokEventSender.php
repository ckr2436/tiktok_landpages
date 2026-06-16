<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Model\Conversion;

use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Pynarae\TiktokLandingPages\Model\Conversion;
use Pynarae\TiktokLandingPages\Model\ConversionFactory;
use Pynarae\TiktokLandingPages\Model\ResourceModel\Conversion as ConversionResource;
use Pynarae\TiktokLandingPages\Model\ResourceModel\Conversion\CollectionFactory as ConversionCollectionFactory;
use Pynarae\TiktokLandingPages\Model\Visit;
use Tiktok\Tiktok\Model\Api\TiktokApiClientBuilder;

class TiktokEventSender
{
    public function __construct(
        private readonly TiktokApiClientBuilder $apiClientBuilder,
        private readonly ConversionFactory $conversionFactory,
        private readonly ConversionResource $conversionResource,
        private readonly ConversionCollectionFactory $conversionCollectionFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    public function sendCompletePayment(
        Visit $visit,
        string $orderNumber,
        ?float $orderValue,
        string $currency,
        ?int $eventTime = null
    ): Conversion {
        return $this->sendOrderEvent(
            $visit,
            Conversion::EVENT_COMPLETE_PAYMENT,
            $orderNumber,
            $orderValue,
            $currency,
            $eventTime
        );
    }

    public function sendOrderEvent(
        Visit $visit,
        string $eventName,
        string $orderNumber,
        ?float $orderValue,
        string $currency,
        ?int $eventTime = null,
        string $eventTimezone = 'UTC',
        string $eventTimeLocal = ''
    ): Conversion {
        $orderNumber = trim($orderNumber);
        if ($orderNumber === '') {
            throw new LocalizedException(__('Order number is required.'));
        }

        $eventName = $this->normalizeEventName($eventName);
        $currency = strtoupper(trim($currency ?: 'USD'));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new LocalizedException(__('Currency must be a 3-letter ISO code, such as USD.'));
        }

        $eventTime = $eventTime ?: time();
        $eventTimezone = $this->normalizeTimezone($eventTimezone);
        $eventTimeLocal = trim($eventTimeLocal);
        $eventId = $this->buildEventId((int)$visit->getId(), $orderNumber, $eventName);
        $conversion = $this->loadExistingConversion($eventId) ?: $this->conversionFactory->create();

        if ($conversion->getId() && $conversion->getData('status') === Conversion::STATUS_SENT) {
            throw new LocalizedException(__('This visit/order pair has already been reported to TikTok Ads.'));
        }

        $payload = $this->buildPayload($visit, $eventName, $eventId, $eventTime, $orderNumber, $orderValue, $currency);
        $conversion->addData([
            'visit_id' => (int)$visit->getId(),
            'website_id' => (int)$visit->getData('website_id'),
            'event_name' => $eventName,
            'event_id' => $eventId,
            'event_time' => $eventTime,
            'event_timezone' => $eventTimezone,
            'event_time_local' => $eventTimeLocal,
            'order_number' => $orderNumber,
            'order_value' => $orderValue,
            'currency' => $currency,
            'status' => 'pending',
            'payload_json' => $this->encode($payload),
            'message' => null,
        ]);
        $this->conversionResource->save($conversion);

        try {
            $client = $this->apiClientBuilder->create((int)$visit->getData('website_id'));
            $scopeManager = $client->getScopeManager();
            if (!$scopeManager->isEnabled()) {
                throw new LocalizedException(__('The official TikTok module is not connected for this website.'));
            }
            if (!$scopeManager->getPixelCode()) {
                throw new LocalizedException(__('TikTok pixel code is missing for this website.'));
            }

            $response = $client->sendEvents([$payload]);
            $success = isset($response['code']) && (int)$response['code'] === 0;
            $conversion->addData([
                'status' => $success ? Conversion::STATUS_SENT : Conversion::STATUS_FAILED,
                'message' => $success ? 'Sent to TikTok Ads.' : (string)($response['message'] ?? 'TikTok API returned an error.'),
                'response_json' => $this->encode($response),
            ]);
            $this->conversionResource->save($conversion);

            if (!$success) {
                throw new LocalizedException(__('TikTok API returned an error: %1', $conversion->getData('message')));
            }

            return $conversion;
        } catch (\Throwable $exception) {
            $conversion->addData([
                'status' => Conversion::STATUS_FAILED,
                'message' => $exception->getMessage(),
            ]);
            $this->conversionResource->save($conversion);
            $this->logger->error('Unable to send TikTok landing page conversion.', [
                'visit_id' => (int)$visit->getId(),
                'order_number' => $orderNumber,
                'message' => $exception->getMessage(),
            ]);
            throw $exception instanceof LocalizedException
                ? $exception
                : new LocalizedException(__('Unable to send TikTok event: %1', $exception->getMessage()));
        }
    }

    private function buildPayload(
        Visit $visit,
        string $eventName,
        string $eventId,
        int $eventTime,
        string $orderNumber,
        ?float $orderValue,
        string $currency
    ): array {
        $user = array_filter([
            'ip' => (string)$visit->getData('ip'),
            'user_agent' => (string)$visit->getData('user_agent'),
            'ttclid' => (string)$visit->getData('ttclid'),
            'ttp' => (string)$visit->getData('ttp'),
        ], static fn($value) => $value !== '');

        $contentId = (string)($visit->getData('product_id') ?: $visit->getData('landing_identifier'));
        $contentName = (string)($visit->getData('content_name') ?: $visit->getData('landing_identifier'));
        $properties = array_filter([
            'order_id' => $orderNumber,
            'currency' => $currency,
            'value' => $orderValue !== null ? round($orderValue, 4) : null,
            'content_type' => 'product',
            'content_ids' => [$contentId],
            'quantity' => 1,
            'contents' => [[
                'content_id' => $contentId,
                'content_name' => $contentName,
                'content_type' => 'product',
                'quantity' => 1,
            ]],
            'description' => 'TikTok Shop order matched from MYUPONA landing page visit.',
        ], static fn($value) => $value !== null && $value !== '');

        $page = array_filter([
            'url' => (string)($visit->getData('landing_url') ?: $visit->getData('request_uri')),
            'referrer' => (string)$visit->getData('referrer'),
        ], static fn($value) => $value !== '');

        return array_filter([
            'event' => $eventName,
            'event_time' => $eventTime,
            'event_id' => $eventId,
            'user' => $user,
            'properties' => $properties,
            'page' => $page,
        ], static fn($value) => $value !== []);
    }

    private function normalizeEventName(string $eventName): string
    {
        $eventName = trim($eventName);
        $allowed = [Conversion::EVENT_PURCHASE, Conversion::EVENT_COMPLETE_PAYMENT];
        if (!in_array($eventName, $allowed, true)) {
            throw new LocalizedException(__('Unsupported TikTok event name.'));
        }
        return $eventName;
    }

    private function normalizeTimezone(string $timezone): string
    {
        $timezone = trim($timezone) ?: 'UTC';
        if (!in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            return 'UTC';
        }
        return $timezone;
    }

    private function buildEventId(int $visitId, string $orderNumber, string $eventName): string
    {
        return 'myupona_lp_' . $visitId . '_' . substr(hash('sha256', $eventName . '|' . $orderNumber), 0, 40);
    }

    private function loadExistingConversion(string $eventId): ?Conversion
    {
        $collection = $this->conversionCollectionFactory->create();
        $collection->addFieldToFilter('event_id', $eventId);
        $collection->setPageSize(1);
        $item = $collection->getFirstItem();
        return $item->getId() ? $item : null;
    }

    private function encode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }
}
