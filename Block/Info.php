<?php
/**
 * Copyright © 2017 Storeplugins. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Storeplugins\Paychant\Block;

class Info extends \Magento\Payment\Block\Info
{
    /**
     * @var string
     */
    protected $_payableTo;

    /**
     * @var string
     */
    protected $_mailingAddress;

    /**
     * @var string
     */
    protected $_template = 'Storeplugins_Paychant::info.phtml';


    public function getMethodCode()
    {
        return $this->getInfo()->getMethodInstance()->getCode();
    }

    /**
     * @return string
     */
    public function toPdf()
    {
        //$this->setTemplate('J2t_Payplug::info/pdf/checkmo.phtml');
        $this->setTemplate('Storeplugins_Paychant::pdf/info.phtml');
        return $this->toHtml();
    }
}
