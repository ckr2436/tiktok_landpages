<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Controller\Page;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Store\Model\StoreManagerInterface;
use Pynarae\TiktokLandingPages\Api\LandingPageRepositoryInterface;
use Pynarae\TiktokLandingPages\Model\Frontend\HtmlRenderer;
use Pynarae\TiktokLandingPages\Model\VisitRecorder;

class View extends Action
{
    public function __construct(
        Context $context,
        private readonly LandingPageRepositoryInterface $landingPageRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly RawFactory $rawFactory,
        private readonly ForwardFactory $forwardFactory,
        private readonly HtmlRenderer $htmlRenderer,
        private readonly VisitRecorder $visitRecorder
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $identifier = (string)$this->getRequest()->getParam('identifier');
        $websiteId = (int)$this->storeManager->getStore()->getWebsiteId();

        try {
            $page = $this->landingPageRepository->getByIdentifier($identifier, $websiteId, true);
        } catch (\Throwable) {
            return $this->forwardFactory->create()->forward('noroute');
        }

        $this->visitRecorder->record($page, $this->getRequest());

        $result = $this->rawFactory->create();
        $result->setHeader('Content-Type', 'text/html; charset=UTF-8', true);
        $result->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
        $result->setHeader('Pragma', 'no-cache', true);
        $result->setHeader('Expires', '0', true);
        $result->setHeader('X-Robots-Tag', 'noindex, nofollow', true);
        $result->setContents($this->htmlRenderer->render($page, $this->getRequest()));
        return $result;
    }
}
