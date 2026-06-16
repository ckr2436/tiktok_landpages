<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Controller\Adminhtml\Visit;

use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Page;

abstract class AbstractVisit extends Action
{
    public const ADMIN_RESOURCE = 'Pynarae_TiktokLandingPages::visits';

    protected function initPage(Page $resultPage): Page
    {
        $resultPage->setActiveMenu('Pynarae_TiktokLandingPages::visits');
        $resultPage->addBreadcrumb(__('TikTok Landing Pages'), __('TikTok Landing Pages'));
        $resultPage->addBreadcrumb(__('Visit Records'), __('Visit Records'));
        return $resultPage;
    }
}
