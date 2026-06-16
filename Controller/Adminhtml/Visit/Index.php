<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Controller\Adminhtml\Visit;

use Magento\Framework\View\Result\PageFactory;

class Index extends AbstractVisit
{
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultPage = $this->initPage($this->resultPageFactory->create());
        $resultPage->getConfig()->getTitle()->prepend(__('TikTok Landing Page Visit Records'));
        return $resultPage;
    }
}
