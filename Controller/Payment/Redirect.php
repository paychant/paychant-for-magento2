<?php

namespace Storeplugins\Paychant\Controller\Payment;


use Magento\Framework\Controller\ResultFactory;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\GuestCartManagementInterface;
use Magento\Customer\Model\Group;

class Redirect extends \Magento\Framework\App\Action\Action
{
    /**
     * Customer session model
     *
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;
    protected $_paymentMethod;
    protected $resultRawFactory;
    protected $_checkoutSession;
    protected $checkout;
    protected $cartManagement;
    protected $orderRepository;
    protected $_scopeConfig;
    protected $layoutFactory;
    protected $guestcartManagement;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\LayoutFactory $layoutFactory,
        \Magento\Framework\Controller\Result\RawFactory $resultRawFactory,
        \Magento\Customer\Model\Session $customerSession,
        \Storeplugins\Paychant\Model\PaymentMethod $paymentMethod,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        CartManagementInterface $cartManagement,
        GuestCartManagementInterface $guestcartManagement
    ) {
        $this->_customerSession = $customerSession;
        parent::__construct($context);
        $this->_paymentMethod = $paymentMethod;
        $this->_checkoutSession = $checkoutSession;
        $this->cartManagement = $cartManagement;
        $this->orderRepository = $orderRepository;
        $this->_scopeConfig = $scopeConfig;
        $this->layoutFactory = $layoutFactory;
        $this->resultRawFactory= $resultRawFactory;
        $this->guestcartManagement = $guestcartManagement;
    }


    public function execute(){
		
		if(false){ //todo:: Make this Optional in the Settings. 
			$order = $this->_checkoutSession->getLastRealOrder();
			//$order = $this->orderRepository->get($orderId);

			if ($order){
				$order->setState($this->_scopeConfig->getValue('payment/storepluginspaychant/new_order_status', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
				$order->setStatus($this->_scopeConfig->getValue('payment/storepluginspaychant/new_order_status', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
				$order->addStatusToHistory($order->getStatus(), __('Customer redirected to Paychant gateway'));
				$order->setIsNotified(false);
				$order->save();
			}
		}


      $output = $this->layoutFactory->create()
       ->createBlock('Storeplugins\Paychant\Block\Redirect')
       ->toHtml();
       $resultRaw = $this->resultRawFactory->create();
       return $resultRaw->setContents($output);
    }


    /*public function dispatch(RequestInterface $request)
    {
        $url = $this->_paymentMethod->getPayplugCheckoutRedirection();
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setUrl($url);
        return $resultRedirect;
    }*/
}
