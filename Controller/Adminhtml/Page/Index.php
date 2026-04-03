<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Controller\Adminhtml\Page;

use Magento\Framework\View\Result\PageFactory;

class Index extends AbstractPage
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
        $resultPage->getConfig()->getTitle()->prepend(__('TikTok Landing Pages'));
        return $resultPage;
    }
}
