<?php

/**
 *
 * Copyright © 2017 Storeplugins. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Storeplugins\Paychant\Observer;

use Magento\Framework\Event\ObserverInterface;

class SaveConfigPaychant implements ObserverInterface {

    protected $request;
    protected $_storeInterface;
    protected $_scopeConfig;
    protected $_moduleList;
    protected $curl;
    protected $messageManager;
    
    public function __construct($eventManager = null)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->request = $objectManager->get('Magento\Framework\App\Request\Http');
        $this->_storeInterface = $objectManager->get('Magento\Store\Model\StoreManagerInterface');
        $this->_scopeConfig = $objectManager->get('Magento\Framework\Config\Scope');
        $this->_moduleList = $objectManager->get('Magento\Framework\Module\ModuleList');
        $this->curl = $objectManager->get('Magento\Framework\HTTP\Adapter\Curl');
        $this->messageManager = $objectManager->get('Magento\Framework\Message\ManagerInterface');
    }

    public function execute(\Magento\Framework\Event\Observer $observer) {

        $controller = $observer->getControllerAction();
        $groups = $observer->getControllerAction()->getRequest()->getPost('groups');

        //$this->request->getParam('section')

        if (isset($groups['storepluginspaychant'])
                && isset($groups['storepluginspaychant']['fields']) && isset($groups['storepluginspaychant']['fields']['live_api_key'])
                && isset($groups['storepluginspaychant']['fields']['test_api_key']) && isset($groups['storepluginspaychant']['fields']['live_api_key']['inherit'])
                && isset($groups['storepluginspaychant']['fields']['test_api_key']['inherit'])){
            $groups['storepluginspaychant']['fields']['min_amount'] = array('inherit' => '1');
            $groups['storepluginspaychant']['fields']['max_amount'] = array('inherit' => '1');
            $groups['storepluginspaychant']['fields']['currencies'] = array('inherit' => '1');
            //$observer->getControllerAction()->getRequest()->setPost('groups', $groups);
            $observer->getControllerAction()->getRequest()->setPostValue('groups', $groups);
        }
		
        if (isset($groups['storepluginspaychant'])
                && isset($groups['storepluginspaychant']['fields']) && isset($groups['storepluginspaychant']['fields']['live_api_key'])
                && isset($groups['storepluginspaychant']['fields']['test_api_key']) && (isset($groups['storepluginspaychant']['fields']['live_api_key']['inherit'])
                || isset($groups['storepluginspaychant']['fields']['test_api_key']['inherit']))){
            throw new \RuntimeException("Both live and test key must be supplied.");
        }
    }

}
