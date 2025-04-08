<?php
namespace Vendor\CustomOrderProcessing\Api;

interface OrderStatusInterface
{
    /**
     * @param string $incrementId
     * @param string $status
     * @return string
     */
    public function updateStatus($incrementId, $status);
}
