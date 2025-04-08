<?php
namespace Vendor\CustomOrderProcessing\Model;

use Vendor\CustomOrderProcessing\Api\OrderStatusInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Sales\Model\Order\StatusFactory;

class OrderStatus implements OrderStatusInterface
{
    protected $orderRepository;
    protected $orderFactory;
    protected $orderCollectionFactory;
    protected $statusFactory;

    public function __construct(OrderRepositoryInterface $orderRepository,\Magento\Sales\Model\OrderFactory $orderFactory,
    CollectionFactory $orderCollectionFactory,StatusFactory $statusFactory)
    {
        $this->orderRepository = $orderRepository;
        $this->orderFactory = $orderFactory;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->statusFactory = $statusFactory;
    }

    public function updateStatus($incrementId, $status)
    {
        $order = $this->orderCollectionFactory->create()
            ->addFieldToFilter('increment_id', $incrementId)
            ->getFirstItem();

        if (!$order || !$order->getId()) {
            throw new \Magento\Framework\Exception\NoSuchEntityException(__('Order not found'));
        }
        $state = $this->getStateFromStatus($status);
        if (!$state) {
            throw new LocalizedException(__('Invalid status or no state mapped.'));
        }
        $order->setState($state)->setStatus($status);
        $order->addStatusHistoryComment("Status updated via API to $status");
        $order->save();
        
        return 'Order updated successfully';
    }
    
    public function getStateFromStatus($status)
    {
        $defaultMap = [
            'pending' => 'new',
            'processing' => 'processing',
            'complete' => 'complete',
            'shipped' => 'complete',
            'canceled' => 'canceled',
            'closed' => 'closed',
            'holded' => 'holded',
            'payment_review' => 'payment_review',
        ];
        return $defaultMap[$status] ?? null;
    }

}
