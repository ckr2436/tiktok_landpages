<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Controller\Adminhtml\Page;

use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Page;

abstract class AbstractPage extends Action
{
    public const ADMIN_RESOURCE = 'Pynarae_TiktokLandingPages::pages';

    protected function initPage(Page $resultPage): Page
    {
        $resultPage->setActiveMenu('Pynarae_TiktokLandingPages::pages');
        $resultPage->addBreadcrumb(__('TikTok Landing Pages'), __('TikTok Landing Pages'));
        return $resultPage;
    }
}
