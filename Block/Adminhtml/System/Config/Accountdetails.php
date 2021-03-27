<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace J2t\Payplug\Block\Adminhtml\System\Config;

class Accountdetails extends \Magento\Config\Block\System\Config\Form\Field
{
    protected $_jsonEncoder;
    protected $_storeManager;
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Json\EncoderInterface $jsonEncoder,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_jsonEncoder = $jsonEncoder;
        $this->_storeManager = $context->getStoreManager();
        $this->_scopeConfig = $context->getScopeConfig();
    }
    
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        
        $extra = '';
        if ($current_store = $this->getStore()){
            $store = $current_store;
        } else {
            $store = $this->_storeManager->getWebsite(
                                    $this->getWebsite()
                                )->getDefaultStore();

        }
        $storeId = $store->getId();

        $currency = $this->_scopeConfig->getValue('payment/j2tpayplug/currencies', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
        $return_val = [];

        if ($this->_scopeConfig->getValue('payment/j2tpayplug/user', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId) && $this->_scopeConfig->getValue('payment/j2tpayplug/min_amount', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId)
                && $this->_scopeConfig->getValue('payment/j2tpayplug/max_amount', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId) && $currency){
            $return_val[] = __('User: %1', $this->_scopeConfig->getValue('payment/j2tpayplug/user', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId));
            $return_val[] = __('Min allowed amount: %1 %2', $this->_scopeConfig->getValue('payment/j2tpayplug/min_amount', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId), $currency);
            $return_val[] = __('Max allowed amount: %1 %2', $this->_scopeConfig->getValue('payment/j2tpayplug/max_amount', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId), $currency);
            $return_val[] = __('Currency: %1', $currency);
        } else {
            $return_val[] = __('Not configured yet');
        }

        return implode("<br />", $return_val);
    }
    
}

