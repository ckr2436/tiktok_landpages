<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Controller\Adminhtml\Page;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;

trait PostOnlyTrait
{
    protected function requirePost(RequestInterface $request): ?Redirect
    {
        if ($request->isPost()) {
            return null;
        }

        $this->messageManager->addErrorMessage(__('Invalid request method.'));
        /** @var Redirect $redirect */
        $redirect = $this->resultRedirectFactory->create();
        return $redirect->setPath('*/*/index');
    }
}
