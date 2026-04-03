<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Model\ResourceModel\LandingPage;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Pynarae\TiktokLandingPages\Model\LandingPage as Model;
use Pynarae\TiktokLandingPages\Model\ResourceModel\LandingPage as ResourceModel;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
