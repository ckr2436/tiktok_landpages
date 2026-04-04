<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Controller\Adminhtml\Page;

use Psr\Log\LoggerInterface;
use Pynarae\TiktokLandingPages\Api\LandingPageRepositoryInterface;
use Pynarae\TiktokLandingPages\Model\Media\AssetStorage;

class Delete extends AbstractPage implements PostActionInterface
{
    use PostOnlyTrait;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        private readonly LandingPageRepositoryInterface $landingPageRepository,
        private readonly AssetStorage $assetStorage,
        private readonly LoggerInterface $logger
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

            $heroImage = (string)$model->getData('hero_image_url');
            $ctaBgImage = (string)$model->getData('cta_bg_image_url');
            $promoImage = (string)$model->getData('promo_image_url');
            $pageTitle = (string)$model->getData('title');

            $this->landingPageRepository->delete($model);

            $cleanupErrors = [];
            foreach ([
                'hero_image_url' => $heroImage,
                'cta_bg_image_url' => $ctaBgImage,
                'promo_image_url' => $promoImage,
            ] as $field => $value) {
                try {
                    $this->assetStorage->deleteIfManaged($value);
                } catch (\Throwable $cleanupException) {
                    $cleanupErrors[] = sprintf('%s: %s', $field, $cleanupException->getMessage());
                }
            }

            if (!empty($cleanupErrors)) {
                $this->logger->warning(
                    'Landing page deleted but one or more managed assets could not be removed.',
                    [
                        'landing_page_id' => $id,
                        'landing_page_title' => $pageTitle,
                        'cleanup_errors' => $cleanupErrors,
                    ]
                );

                $this->messageManager->addSuccessMessage(
                    __('Landing page deleted. Some old uploaded assets could not be removed automatically; please check server logs if needed.')
                );
            } else {
                $this->messageManager->addSuccessMessage(__('Landing page deleted.'));
            }
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->_redirect('*/*/index');
    }
}
