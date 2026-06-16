<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Model;

use Magento\Framework\Model\AbstractModel;
use Pynarae\TiktokLandingPages\Model\ResourceModel\Conversion as ResourceModel;

class Conversion extends AbstractModel
{
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const EVENT_PURCHASE = 'Purchase';
    public const EVENT_COMPLETE_PAYMENT = 'CompletePayment';

    protected function _construct(): void
    {
        $this->_init(ResourceModel::class);
    }
}
