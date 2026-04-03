<?php
declare(strict_types=1);

namespace Pynarae\TiktokLandingPages\Model\Source;

class YesNo
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 1, 'label' => __('Yes')],
            ['value' => 0, 'label' => __('No')],
        ];
    }
}
