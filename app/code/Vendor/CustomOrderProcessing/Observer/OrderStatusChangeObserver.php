<?php
namespace Vendor\CustomOrderProcessing\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Vendor\CustomOrderProcessing\Model\ResourceModel\OrderStatusLog as OrderStatusLogResource;
use Vendor\CustomOrderProcessing\Model\OrderStatusLogFactory;
use Magento\Sales\Model\Order;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\ShipmentSender;
use Magento\Sales\Model\Service\ShipmentService;
use Magento\Framework\DB\Transaction;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Magento\Sales\Model\Order\ShipmentFactory;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Sales\Model\Order\Shipment\ItemFactory as ShipmentItemFactory;
use Magento\Sales\Api\OrderRepositoryInterface;

class OrderStatusChangeObserver implements ObserverInterface
{
    protected $logFactory;
    protected $logResource;
    protected $transportBuilder;
    protected $scopeConfig;
    protected $storeManager;
    protected $invoiceService;
    protected $invoiceSender;
    protected $shipmentService;
    protected $shipmentSender;
    protected $transaction;
    protected $logger;
    protected $shipmentFactory;
	protected $trackFactory;
	protected $shipmentItemFactory;
	protected $shipmentRepository;
	protected $orderRepository;

    public function __construct(
        OrderStatusLogFactory $logFactory,
        OrderStatusLogResource $logResource,
        TransportBuilder $transportBuilder,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        InvoiceService $invoiceService,
        InvoiceSender $invoiceSender,
        ShipmentService $shipmentService,
        ShipmentSender $shipmentSender,
        Transaction $transaction,
        LoggerInterface $logger,
        ShipmentFactory $shipmentFactory,
    	TrackFactory $trackFactory,
    	ShipmentItemFactory $shipmentItemFactory,
    	\Magento\Sales\Api\ShipmentRepositoryInterface $shipmentRepository,
    	OrderRepositoryInterface $orderRepository
    ) {
        $this->logFactory = $logFactory;
        $this->logResource = $logResource;
        $this->transportBuilder = $transportBuilder;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->invoiceService = $invoiceService;
        $this->invoiceSender = $invoiceSender;
        $this->shipmentService = $shipmentService;
        $this->shipmentSender = $shipmentSender;
        $this->transaction = $transaction;
        $this->logger = $logger;
        $this->shipmentFactory = $shipmentFactory;
    	$this->trackFactory = $trackFactory;
    	$this->shipmentItemFactory = $shipmentItemFactory;
    	$this->shipmentRepository = $shipmentRepository;
    	$this->orderRepository = $orderRepository;
    }

    public function execute(Observer $observer)
    {
        /** @var Order $order */
        $order = $observer->getEvent()->getOrder();
        $originalOrder = $order->getOrigData();
        if (!isset($originalOrder['status'])) {
		    return; // or handle accordingly
		}
        if ($order->getStatus() !== $originalOrder['status']) {
            $log = $this->logFactory->create();
            $log->setData([
                'order_id' => $order->getId(),
                'old_status' => $originalOrder['status'],
                'new_status' => $order->getStatus(),
                'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);
            $this->logResource->save($log);
            $status = $order->getStatus();
            if ($status === 'processing') {
                try {
			        if (!$order->hasInvoices()) {
			            $this->createInvoice($order);
			        }
			    } catch (\Exception $e) {
			        $this->logger->error('Error creating invoice for order ' . $order->getIncrementId() . ': ' . $e->getMessage());
			    }
            }
            if ($status === 'complete') {
                try {
			        if (!$order->hasInvoices()) {
			            $this->createInvoice($order);
			        }
			        $this->createShipment($order);
			    } catch (\Exception $e) {
			        $this->logger->error('Error creating shipment for order ' . $order->getIncrementId() . ': ' . $e->getMessage());
			    }
            }
        }
    }

    public function createInvoice($order)
    {
        /*if (!$order->canInvoice()) {
            throw new LocalizedException(__('Cannot create invoice for this order.'));
        }*/
        $invoice = $this->invoiceService->prepareInvoice($order);
        $invoice->register();
        $invoice->getOrder()->setIsInProcess(true);
        $this->transaction->addObject($invoice)->addObject($invoice->getOrder())->save();
    }

    public function createShipment($order)
    {
        $shipment = $this->shipmentFactory->create($order);
		$hasItems = false;
		foreach ($order->getAllItems() as $orderItem) {
		    if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
		        continue;
		    }
		    $qtyShipped = $orderItem->getQtyToShip();
		    $shipmentItem = $this->shipmentItemFactory->create();
		    $shipmentItem->setOrderItem($orderItem);
		    $shipmentItem->setQty($qtyShipped);
		    $shipment->addItem($shipmentItem);
		    $hasItems = true;
		}
		if (!$hasItems) {
		    return; // or throw exception if you want to bubble it up
		}
		$shipment->register();
		$shipment->getOrder()->setIsInProcess(true);
		try {
			$this->shipmentSender->send($shipment);
		    $shipment->save();
		    $order->save();
		} catch (\Exception $e) {
		    throw new \Magento\Framework\Exception\LocalizedException(
		        __('Error processing order: %1', $e->getMessage())
		    );
		}
	}
}
