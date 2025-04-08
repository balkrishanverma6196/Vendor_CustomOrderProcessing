<?php
namespace Vendor\CustomOrderProcessing\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class OrderStatusLog extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('vendor_order_status_log', 'log_id'); // Table name, primary key
    }
}
