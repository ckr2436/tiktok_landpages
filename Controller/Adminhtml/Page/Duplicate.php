<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Controller\Adminhtml\Page;

use Pynarae\TiktokLandingPages\Api\LandingPageRepositoryInterface;
use Pynarae\TiktokLandingPages\Model\LandingPageFactory;
use Pynarae\TiktokLandingPages\Model\Media\AssetCopyService;

class Duplicate extends AbstractPage implements PostActionInterface
{
    use PostOnlyTrait;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        private readonly LandingPageRepositoryInterface $landingPageRepository,
        private readonly LandingPageFactory $landingPageFactory,
        private readonly AssetCopyService $assetCopyService
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

            $copy->setData(
                'hero_image_url',
                $this->assetCopyService->duplicateIfManaged((string)$source->getData('hero_image_url'), 'hero')
            );
            $copy->setData(
                'cta_bg_image_url',
                $this->assetCopyService->duplicateIfManaged((string)$source->getData('cta_bg_image_url'), 'cta')
            );
            $copy->setData(
                'promo_image_url',
                $this->assetCopyService->duplicateIfManaged((string)$source->getData('promo_image_url'), 'promo')
            );

            $this->landingPageRepository->save($copy);
            $this->messageManager->addSuccessMessage(__('Landing page duplicated.'));
            return $this->_redirect('*/*/edit', ['id' => $copy->getId()]);
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $this->_redirect('*/*/index');
        }
    }
}
