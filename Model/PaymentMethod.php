<?php
/**
 * Copyright © 2017 Storeplugins. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Storeplugins\Paychant\Model;

use Magento\Quote\Api\Data\CartInterface;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Sales\Model\Order;

class PaymentMethod extends AbstractMethod
{
    const CODE = 'storepluginspaychant';

    protected $_code = self::CODE;

    protected $_isInitializeNeeded  = true;

    protected $_formBlockType = 'Storeplugins\Paychant\Block\Form';
    protected $_infoBlockType = 'Storeplugins\Paychant\Block\Info';

    protected $_isGateway                   = false;
    protected $_canAuthorize                = false;
    protected $_canCapture                  = true;
    protected $_canCapturePartial           = false;
    protected $_canRefund                   = false;
    protected $_canRefundInvoicePartial     = false;
    protected $_canVoid                     = false;
    protected $_canUseInternal              = true;
    protected $_canUseCheckout              = true;
    protected $_canUseForMultishipping      = false;
    protected $_canSaveCc                   = false;

    protected $urlBuilder;
    protected $_moduleList;
    protected $checkoutSession;
    protected $_orderFactory;

    const URL_PAYMENT = 'https://api-live.paychant.com/v1/order';
    const URL_PAYMENT_TEST = 'https://api-sandbox.paychant.com/v1/order';

    const URL_TRANSACTION_STATUS = 'https://api-live.paychant.com/v1/order/';
    const URL_TRANSACTION_STATUS_TEST = 'https://api-sandbox.paychant.com/v1/order/';

    protected $_supportedCurrencyCodes = array('NGN','USD','GBP','EUR','AUD','CAD','JPY','CNY');


    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\Url $urlBuilder,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []){
        $this->urlBuilder = $urlBuilder;
        $this->_moduleList = $moduleList;
        $this->checkoutSession = $checkoutSession;
        $this->_orderFactory = $orderFactory;
        parent::__construct($context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data);
    }

    public function canUseForCurrency($currencyCode){
        return in_array($currencyCode,$this->_supportedCurrencyCodes);
    }

    public function getInstructions(){
        return trim($this->getConfigData('instructions'));
    }

    public function initialize($paymentAction, $stateObject){
        $payment = $this->getInfoInstance();
        //$order = $payment->getOrder();

        $state = $this->getConfigData('new_order_status');

        //$state = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
        $stateObject->setState($state);
        $stateObject->setStatus($state);
        $stateObject->setIsNotified(false);
    }

    public function getPaymentData(){
        $params = array();
        $orderIncrementId = $this->checkoutSession->getLastRealOrderId();
        $order = $this->_orderFactory->create()->loadByIncrementId($orderIncrementId);
        $live_api_key= $this->getConfigData('live_api_key');
        $test_api_key= $this->getConfigData('test_api_key');
        $amount= number_format($order->getBaseGrandTotal(),0,'','');
  	    $noticeUrl= $this->urlBuilder->getUrl('storepluginspaychant/payment/callback');
  	    $cartUrl= $this->urlBuilder->getUrl('checkout/cart');
  	    $orderUrl= $this->urlBuilder->getUrl('sales/order/view', ['order_id' => $orderIncrementId]);
		
		
		$currency_code=$order->getBaseCurrencyCode();
		$order_title="Order #$orderIncrementId";
		$email=$order->getCustomerEmail(); 
		if(empty($email))$email=$order->getShippingAddress()->getEmail();
		
		$ddate=date('YmdHis');
		$txnid="$orderIncrementId.$ddate";
		
		$orderItems = $order->getAllItems();
		foreach($orderItems as $item ){
			$products_item_line = implode(' x ',array($item->getQtyOrdered(),$item->getName()));
			$product_items[] = $products_item_line;
		}
		$order_description=implode(', ',$product_items);
		
		
		if(empty($email)){
			$paychant_cust_id= $order->getCustomerId();
			if($order->getCustomerIsGuest()){
			  $cust_name = '(Guest) Order #'.$orderIncrementId;
			}else{
			  $cust_name = $order->getCustomerFirstname()." ".$order->getCustomerLastname();
			}
		}
		else $cust_name=$email;
		
		
		//-----------------
		$body = array(
			'amount' => $amount,
			'currency' => $currency_code,
			'title' => substr($order_title,0,50),
			'description' => substr($order_description,0,100),
			'payer_info' => $cust_name,
			'cancel_url' => $cartUrl,
			'success_url' => $orderUrl,
			'callback_url' => $noticeUrl,
			'token' => $txnid,
			'plugin' => 'MAGENTO2',
		);
		
		if($this->getConfigData('sandbox'))
			$api_key=$this->getConfigData('test_api_key');
		else $this->getConfigData('live_api_key');
		
		$header=array("Authorization: Token $api_key");
		$error=null;
		
		$url = $this->getPaychantPaymentUrl();
		$response=$this->curl_request($url,http_build_query($body),$header);
		
		if(!empty($response['error'])) {
			$error_message = $response['error'];			
			$this->_log_stuff("$error_message\n\n".json_encode($body,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
			$error="Error initiating payment at Paychant: $error_message";
		}
		else {
			$json = @json_decode($response['body'],true);
			if(empty($json))$error="Error interpreting paychant initiation response: {$response['body']}";
			elseif(empty($json['order']['payment_url'])){
				if(!empty($json['errors']))$error_message=json_encode($json['errors']);
				else $error_message=json_encode($json);

				$error="Paychant transaction initiation error: $error_message";
			}
			else $payment_url=$json['order']['payment_url'];
		}
		//---------
		
		if(!empty($error))$params['error']=$error;
		else {
			//$moduleDetails = $this->_moduleList->getOne('Storeplugins_Paychant');
			$params['form_method'] = 'GET';
			$params['form_action'] = $payment_url;
			$params['redirect_url'] = $payment_url;
			$params['fields']=array();
			$state = $this->getConfigData('new_order_status');
			$order->setState($state);
			$order->setStatus($state);
			$order->addStatusToHistory($order->getStatus(), __('Customer redirected to Paychant gateway with transaction id [%1]', $txnid));
			//  $order->addStatusToHistory($order->getStatus(), __('Transaction ID %1', $txnid));
			$order->setIsNotified(false);
			$order->save();
		}
        return $params;
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
		file_put_contents(__DIR__ .'/../debug.log',"$ddate\n$str\n---------------\n",FILE_APPEND); 
	}

    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        $min = $this->getConfigData('min_amount');
        $max = $this->getConfigData('max_amount');

        if (parent::isAvailable($quote) && $quote && $quote->getGrandTotal() >= $min && $quote->getGrandTotal() <= $max
                /*&& $this->getConfigData('currencies')*/
                        ) {
            return true;
        }
        return false;
    }



    public function getOrderPlaceRedirectUrl()
    {
        return $this->urlBuilder->getUrl('storepluginspaychant/payment/redirect', ['_secure' => true]);
    }

    public function getPaychantPaymentUrl(){
      if($this->getConfigData('sandbox')){
        return self::URL_PAYMENT_TEST;
      }else{
        return self::URL_PAYMENT;
      }
    }

    public function getPaychantStatusCheckUrl(){
      if($this->getConfigData('sandbox')){
        return self::URL_TRANSACTION_STATUS_TEST;
      }else{
        return self::URL_TRANSACTION_STATUS;
      }
    }
}
