<?php
namespace Vendor\CustomOrderProcessing\Model;

use Magento\Framework\Model\AbstractModel;

class OrderStatusLog extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(\Vendor\CustomOrderProcessing\Model\ResourceModel\OrderStatusLog::class);
    }
}
