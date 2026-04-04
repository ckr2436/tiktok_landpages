<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Controller\Adminhtml\Page;

use Pynarae\TiktokLandingPages\Api\LandingPageRepositoryInterface;
use Pynarae\TiktokLandingPages\Model\Media\AssetStorage;

class Delete extends AbstractPage implements PostActionInterface
{
    use PostOnlyTrait;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        private readonly LandingPageRepositoryInterface $landingPageRepository,
        private readonly AssetStorage $assetStorage
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $invalid = $this->requirePost($this->getRequest());
        if ($invalid !== null) {
            return $invalid;
        }

        $id = (int)$this->getRequest()->getParam('id');
        if ($id <= 0) {
            $this->messageManager->addErrorMessage(__('Unable to find the landing page to delete.'));
            return $this->_redirect('*/*/index');
        }

        try {
            $model = $this->landingPageRepository->getById($id);

            $this->assetStorage->deleteIfManaged((string)$model->getData('hero_image_url'));
            $this->assetStorage->deleteIfManaged((string)$model->getData('cta_bg_image_url'));
            $this->assetStorage->deleteIfManaged((string)$model->getData('promo_image_url'));

            $this->landingPageRepository->delete($model);
            $this->messageManager->addSuccessMessage(__('Landing page deleted.'));
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->_redirect('*/*/index');
    }
}
