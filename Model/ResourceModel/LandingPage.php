<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class LandingPage extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('pynarae_tiktok_landing_page', 'entity_id');
    }
}
