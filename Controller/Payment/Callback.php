<?php
namespace Storeplugins\Paychant\Controller\Payment;
use Magento\Framework\Controller\ResultFactory;

class CallbackGeneric extends \Magento\Framework\App\Action\Action{
    /**
     * Customer session model
     *
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;
    protected $resultPageFactory;
    protected $_scopeConfig;
    protected $_orderFactory;
    private $invoiceService;
    protected $orderSender;
    protected $_paymentMethod;
    protected $checkoutSession;


    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     */
    public function __construct(
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\App\Action\Context $context,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Sales\Model\Order\Email\Sender\CreditmemoSender $creditmemoSender,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Storeplugins\Paychant\Model\PaymentMethod $paymentMethod,
        \Magento\Checkout\Model\Session $checkoutSession
    ) {
        $this->_customerSession = $customerSession;
        parent::__construct($context);
        $this->_scopeConfig = $scopeConfig;
        $this->_orderFactory = $orderFactory;
        $this->invoiceService = $invoiceService;
        $this->creditmemoSender = $creditmemoSender;
        $this->orderSender = $orderSender;
        $this->_paymentMethod = $paymentMethod;
        $this->_messageManager = $context->getMessageManager();
        $this->checkoutSession = $checkoutSession;
    }


    protected function _createInvoice($order) {
        if (!$order->canInvoice()) {
            return;
        }

        $invoice = $order->prepareInvoice();
        if (!$invoice->getTotalQty()) {
            throw new \RuntimeException("Cannot create an invoice without products.");
        }

        $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
        $invoice->register();
        $order->addRelatedObject($invoice);
    }

	public function execute() {
        $temp= $this->getRequest()->getPost('token');
		$temp=explode('.',$temp,2);
        $STORE_SCOPE=\Magento\Store\Model\ScopeInterface::SCOPE_STORE;
		
		//$this->_log_stuff("IPN Received. POST\n".json_encode($_POST,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."GET\n".json_encode($_GET,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
		
        if(count($temp)==2){
			$htmlResponse="";
			$orderId=$temp[0];
			$paychant_orderid= $this->getRequest()->getPost('order_id');			
			
            $sandbox=  $this->_scopeConfig->getValue('payment/storepluginspaychant/sandbox',$STORE_SCOPE);
			if($sandbox)$api_key=$this->_scopeConfig->getValue('payment/storepluginspaychant/test_api_key',$STORE_SCOPE);
			else $api_key=$this->_scopeConfig->getValue('payment/storepluginspaychant/live_api_key',$STORE_SCOPE);
            
			$url=$this->_paymentMethod->getPaychantStatusCheckUrl().$paychant_orderid;
			$header=array("Authorization: Token $api_key");
			$response=$this->curl_request($url,null,$header);
		
			if(!empty($response['error'])) {
				$error="Error verifying payment at Paychant: {$response['error']}";
			}
			else {
				$json = @json_decode($response['body'],true);
				if(empty($json))$error="Error interpreting Paychant verification response: {$response['body']}";
				elseif(@$json['status']!='success')$error="Unsuccessful attempt at verifying payment from Paychant: {$response['body']}";
				else {
					$porder=$json['order'];
					$amount_paid=floatval($porder['amount']);
					$paid_currency=$porder['currency'];
					$pstatus=$porder['status'];
				}
			}
			
			try{
				$order = $this->_orderFactory->create()->loadByIncrementId($orderId);
				$order_state = $order->getState();
				$order_total = floatval($order->getBaseGrandTotal());
				$order_currency = $order->getBaseCurrencyCode();
			}
			catch (Exception $e){
				$order=null;
				$error="Order $orderId not found. ".$e->getMessage();
			}

			if(!empty($error)){			
				$this->_log_stuff("$error\n\n$url\n".json_encode($_POST,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
				
				$this->messageManager->addError(__($error),"storepluginspaychant_message");
				return $this->_redirect('checkout/cart');
			}
			elseif(!empty($porder)){
				$status_update_info=''; $failed=false; $critical=false;
				
				if($amount_paid<$order_total){	$failed=true; $critical=true;
					$status_update_info='Amount paid is less than the total order amount.';
				}
				elseif($paid_currency!=$order_currency) { $failed=true; $critical=true;
					$status_update_info='Order currency is different from the payment currency.';
				}
				elseif($pstatus=='expired'){ $failed=true;
					$status_update_info='Payment expired (not paid within the 30 minutes required time.';
				}
				elseif($pstatus=='canceled'){ $failed='cancelled'; $status_update_info='Payment cancelled';	}
				elseif($pstatus=='pending')$status_update_info='Payment method selected, awaiting payment.';
				elseif($pstatus=='new')$status_update_info='New invoice, awaiting payment via Paychant';
				elseif($pstatus=='paid')$status_update_info='Payment has been complete via Paychant'; 
				else $this->_log_stuff("Un-handled order status $pstatus\n".json_encode($porder,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
				
				$complete_order_status= $this->_scopeConfig->getValue('payment/storepluginspaychant/complete_order_status',$STORE_SCOPE);
				
				if(!$failed&&$pstatus=='paid'){
					if($order->getState() == $complete_order_status){
						// Order is already marked as paid - return http 200 OK
					}
					// If order state is payment in progress by paychant
					elseif($order->getState() == $this->_scopeConfig->getValue('payment/storepluginspaychant/new_order_status',$STORE_SCOPE)){
						$order->setState($complete_order_status);
						$order->setStatus($complete_order_status);
						$order->addStatusToHistory($complete_order_status, __('Payment has been processed successfully by Paychant. Transaction ID: %1', $paychant_orderid));  //,true ?? //$order->getStatus() 
						// save transaction ID
						$order->getPayment()->setLastTransId($paychant_orderid);
						//$order->sendNewOrderEmail();
						$this->orderSender->send($order);
						/*if ($this->_scopeConfig->getValue('payment/storepluginspaychant/invoice',$STORE_SCOPE)){
						$this->_createInvoice($order);
						}*/
						$order->save();
					}
					
					$htmlResponse.='<p>Payment Successful</h3>'. "\n";
					$htmlResponse.='<p>Transaction ID: '.$paychant_orderid.'</p>'. "\n";
					$this->messageManager->addSuccess(__($htmlResponse), "storepluginspaychant_message");
					return $this->_redirect('checkout/onepage/success');
				}
				else {
					if($failed){
						if($critical)$this->_log_stuff("$status_update_info\n".json_encode($porder,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
						$cancel_order_status=$this->_scopeConfig->getValue('payment/storepluginspaychant/cancel_order_status',$STORE_SCOPE);
						
						$order->cancel();
						$this->restoreQuote();
						$order->setState($cancel_order_status);
						$order->setStatus($cancel_order_status);
						$order->addStatusToHistory(\Magento\Sales\Model\Order::STATE_CANCELED, $status_update_info,true); //true==notify
						$order->save();
					}
					elseif(!empty($status_update_info)){
						$order->addStatusToHistory($new_order_status, $status_update_info); //$order->getState()
						$order->save();
					}

					$htmlResponse.='<p><strong>Payment Not Completed</strong></p>'. "\n";
					$htmlResponse.='<p>Reason: '.$status_update_info.'.</p>'. "\n";
					$htmlResponse.='<p>Transaction ID: '.$paychant_orderid.'</p>'. "\n";
					$htmlResponse.='<p class="retry-paychant">Please click the "Proceed to Checkout" button below to retry.</p>'. "\n";
					$this->messageManager->addError(__($htmlResponse),"storepluginspaychant_message");
					return $this->_redirect('checkout/cart');
				}
			}
		}
		else{
			echo __('Error: missing or wrong callback parameters.');
			header($_SERVER['SERVER_PROTOCOL'] . ' 400 Missing or wrong parameters', true, 400);
			die;
        }
    }
	  
    private function restoreQuote(){
       return $this->checkoutSession->restoreQuote();
    }
	
	private function curl_request($url,$post_data,$_http_headers=null){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		if($post_data){
			curl_setopt($ch, CURLOPT_POST, TRUE);
			curl_setopt($ch, CURLOPT_POSTFIELDS,$post_data);
		}
		
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		if(!empty($_http_headers))curl_setopt($ch, CURLOPT_HTTPHEADER, $_http_headers);
		
		$response = curl_exec($ch);
		$response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$response_descr=curl_error($ch);
		curl_close($ch);
		
		$resp= array('body'=>$response);
		if($response_code<200||$response_code>=300)$resp['error']="HTTP Error $response_code: $response_descr";
		return $resp;
	}
	
	private function _log_stuff($str){
		$ddate=date('jS M. Y g:ia');
		file_put_contents(__DIR__ .'/../../debug.log',"$ddate\n$str\n---------------\n",FILE_APPEND); 
	}
  }



if (interface_exists("Magento\Framework\App\CsrfAwareActionInterface")){	
	class Callback extends CallbackGeneric implements \Magento\Framework\App\CsrfAwareActionInterface {
		public function createCsrfValidationException(\Magento\Framework\App\RequestInterface $request): ?\Magento\Framework\App\Request\InvalidRequestException{
			return null;
		}

		public function validateForCsrf(\Magento\Framework\App\RequestInterface $request): ?bool{
			return true;
		}
	}
} else {
	class Callback extends CallbackGeneric {}
}

