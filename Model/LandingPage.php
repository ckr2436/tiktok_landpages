<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Model;

use Magento\Framework\Model\AbstractModel;
use Pynarae\TiktokLandingPages\Api\Data\LandingPageInterface;
use Pynarae\TiktokLandingPages\Model\ResourceModel\LandingPage as ResourceModel;

class LandingPage extends AbstractModel implements LandingPageInterface
{
    protected function _construct(): void
    {
        $this->_init(ResourceModel::class);
    }
}
