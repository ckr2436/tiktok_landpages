<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Controller\Adminhtml\Visit;

use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;
use Pynarae\TiktokLandingPages\Model\Conversion\TiktokEventSender;
use Pynarae\TiktokLandingPages\Model\ResourceModel\Visit as VisitResource;
use Pynarae\TiktokLandingPages\Model\VisitFactory;

class Report extends AbstractVisit implements HttpPostActionInterface
{
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        private readonly VisitFactory $visitFactory,
        private readonly VisitResource $visitResource,
        private readonly TiktokEventSender $eventSender
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setRefererUrl();

        try {
            $visitId = (int)$this->getRequest()->getParam('visit_id');
            $visit = $this->visitFactory->create();
            $this->visitResource->load($visit, $visitId);
            if (!$visit->getId()) {
                throw new LocalizedException(__('Visit record does not exist.'));
            }

            $orderNumber = trim((string)$this->getRequest()->getParam('order_number', ''));
            $valueParam = trim((string)$this->getRequest()->getParam('order_value', ''));
            $orderValue = $valueParam === '' ? null : (float)$valueParam;
            if ($orderValue !== null && $orderValue < 0) {
                throw new LocalizedException(__('Order value cannot be negative.'));
            }

            $eventName = (string)$this->getRequest()->getParam('event_name', 'Purchase');
            $eventTimezone = $this->normalizeTimezone((string)$this->getRequest()->getParam('event_timezone', 'UTC'));
            $eventTimeLocal = trim((string)$this->getRequest()->getParam('event_time', ''));
            $eventTime = $this->parseEventTime($eventTimeLocal, $eventTimezone);
            $conversion = $this->eventSender->sendOrderEvent(
                $visit,
                $eventName,
                $orderNumber,
                $orderValue,
                (string)$this->getRequest()->getParam('currency', 'USD'),
                $eventTime,
                $eventTimezone,
                $eventTimeLocal
            );

            $this->messageManager->addSuccessMessage(
                __('TikTok %1 server event was sent. Event ID: %2', $conversion->getData('event_name'), $conversion->getData('event_id'))
            );
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Throwable $exception) {
            $this->messageManager->addErrorMessage(__('Unable to send TikTok event: %1', $exception->getMessage()));
        }

        return $resultRedirect;
    }

    private function parseEventTime(string $value, string $timezone): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            $date = new \DateTimeImmutable(str_replace('T', ' ', $value), new \DateTimeZone($timezone));
            return $date->getTimestamp();
        } catch (\Throwable) {
            throw new LocalizedException(__('Order event time is not valid for the selected timezone.'));
        }
    }

    private function normalizeTimezone(string $timezone): string
    {
        $timezone = trim($timezone) ?: 'UTC';
        if (!in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            throw new LocalizedException(__('Selected timezone is not valid.'));
        }
        return $timezone;
    }
}
