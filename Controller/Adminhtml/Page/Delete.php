<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Controller\Adminhtml\Page;

use Pynarae\TiktokLandingPages\Api\LandingPageRepositoryInterface;

class Delete extends AbstractPage
{
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        private readonly LandingPageRepositoryInterface $landingPageRepository
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $id = (int)$this->getRequest()->getParam('id');
        if ($id <= 0) {
            $this->messageManager->addErrorMessage(__('Unable to find the landing page to delete.'));
            return $this->_redirect('*/*/index');
        }

        try {
            $this->landingPageRepository->deleteById($id);
            $this->messageManager->addSuccessMessage(__('Landing page deleted.'));
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->_redirect('*/*/index');
    }
}
