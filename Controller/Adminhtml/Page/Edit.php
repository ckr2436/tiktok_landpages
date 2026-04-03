<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Controller\Adminhtml\Page;

use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use Pynarae\TiktokLandingPages\Api\LandingPageRepositoryInterface;
use Pynarae\TiktokLandingPages\Model\LandingPageFactory;

class Edit extends AbstractPage
{
    public const REGISTRY_KEY = 'pynarae_tiktok_landing_page';

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly LandingPageRepositoryInterface $landingPageRepository,
        private readonly LandingPageFactory $landingPageFactory,
        private readonly Registry $registry
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $id = (int)$this->getRequest()->getParam('id');
        if ($id) {
            try {
                $model = $this->landingPageRepository->getById($id);
            } catch (\Throwable) {
                $this->messageManager->addErrorMessage(__('This landing page no longer exists.'));
                return $this->_redirect('*/*/index');
            }
        } else {
            $model = $this->landingPageFactory->create();
        }

        $this->registry->register(self::REGISTRY_KEY, $model);
        $resultPage = $this->initPage($this->resultPageFactory->create());
        $resultPage->getConfig()->getTitle()->prepend($id ? __('Edit Landing Page') : __('New Landing Page'));
        return $resultPage;
    }
}
