<?php
/**
 * Copyright © 2017 Storeplugins. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Storeplugins\Paychant\Block;

class Form extends \Magento\Payment\Block\Form
{
    /**
     * Checkmo template
     *
     * @var string
     */
    protected $_supportedInfoLocales = array();
    protected $_defaultInfoLocale = 'en';

    protected $_template = 'Storeplugins_Paychant::form.phtml';

    /**
     * Get instructions text from config
     *
     * @return null|string
     */
    public function getInstructions()
    {
        if ($this->_instructions === null) {
            /** @var \Magento\Payment\Model\Method\AbstractMethod $method */
            $method = $this->getMethod();
            $this->_instructions = $method->getConfigData('instructions');
        }
        return $this->_instructions;
    }
}
