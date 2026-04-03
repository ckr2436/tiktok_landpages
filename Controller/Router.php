<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Controller;

use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;
use Magento\Framework\Url;

class Router implements RouterInterface
{
    public function __construct(private readonly ActionFactory $actionFactory)
    {
    }

    public function match(RequestInterface $request): ?ActionInterface
    {
        $identifier = trim((string)$request->getPathInfo(), '/');
        if ($identifier === '') {
            return null;
        }

        $parts = explode('/', $identifier);
        if (($parts[0] ?? '') !== 'lp') {
            return null;
        }

        $pageIdentifier = trim((string)($parts[1] ?? ''));
        if ($pageIdentifier === '') {
            return null;
        }

        $request->setAlias(Url::REWRITE_REQUEST_PATH_ALIAS, 'lp/' . $pageIdentifier)
            ->setRouteName('pynarae_lp')
            ->setModuleName('pynarae_lp')
            ->setControllerName('page')
            ->setActionName('view')
            ->setControllerModule('Pynarae_TiktokLandingPages')
            ->setParam('identifier', $pageIdentifier);

        return $this->actionFactory->create(Forward::class);
    }
}
