<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Model;

use Magento\Framework\Model\AbstractModel;
use Pynarae\TiktokLandingPages\Model\ResourceModel\Visit as ResourceModel;

class Visit extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(ResourceModel::class);
    }
}
