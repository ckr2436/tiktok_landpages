<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Controller\Adminhtml\Page;

use Pynarae\TiktokLandingPages\Api\LandingPageRepositoryInterface;
use Pynarae\TiktokLandingPages\Model\LandingPageFactory;

class Duplicate extends AbstractPage
{
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        private readonly LandingPageRepositoryInterface $landingPageRepository,
        private readonly LandingPageFactory $landingPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $id = (int)$this->getRequest()->getParam('id');
        if ($id <= 0) {
            $this->messageManager->addErrorMessage(__('Unable to find the landing page to duplicate.'));
            return $this->_redirect('*/*/index');
        }

        try {
            $source = $this->landingPageRepository->getById($id);
            $copy = $this->landingPageFactory->create();
            $copy->setData($source->getData());
            $copy->setId(null);
            $copy->setData('identifier', $source->getData('identifier') . '-copy-' . date('His'));
            $copy->setData('title', $source->getData('title') . ' (Copy)');
            $this->landingPageRepository->save($copy);
            $this->messageManager->addSuccessMessage(__('Landing page duplicated.'));
            return $this->_redirect('*/*/edit', ['id' => $copy->getId()]);
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $this->_redirect('*/*/index');
        }
    }
}
