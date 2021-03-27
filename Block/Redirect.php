<?php
/**
 * Copyright © 2017 Storeplugins. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Storeplugins\Paychant\Block;

class Redirect extends \Magento\Framework\View\Element\AbstractBlock {

    protected $Config;

    protected $_formFactory;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Storeplugins\Paychant\Model\PaymentMethod $paymentConfig,
        \Magento\Framework\Data\FormFactory $formFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_formFactory = $formFactory;
        $this->Config = $paymentConfig;
    }

    protected function _toHtml(){
		$payment_data=$this->Config->getPaymentData();
		
		if(!empty($payment_data['error']))return $payment_data['error'];
		else {
			if(!empty($payment_data['redirect_url'])){
				header("Location: {$payment_data['redirect_url']}"); //exit; //tormuto: experimenting
			}
			
			$form = $this->_formFactory->create();
			$form->setAction($payment_data['form_action'])
				->setId('paychant_checkout')
				->setName('paychant_checkout')
				->setMethod($payment_data['form_method'])
				->setUseContainer(true);
			if(!empty($payment_data['fields'])){
				foreach($payment_data['fields'] as $field => $value){
					$form->addField($field, 'hidden', ['name' => $field, 'value' => $value]);
				}
			}
			$html = '<html><body>';
			$html .= __('Please wait... You will be redirected to the payment page in a few seconds.');
			$html .= $form->toHtml();
			if(!empty($payment_data['redirect_url'])){
				$html .= '<script type="text/javascript">window.location.href="'.$payment_data['redirect_url'].'";</script>';
			}
			else $html .= '<script type="text/javascript">document.getElementById("paychant_checkout").submit();</script>';
			$html .= '</body></html>';
		}

        return $html;
    }
}
