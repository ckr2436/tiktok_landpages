<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Model\ResourceModel\Visit;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Pynarae\TiktokLandingPages\Model\ResourceModel\Visit as ResourceModel;
use Pynarae\TiktokLandingPages\Model\Visit as Model;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
