<?php
/**
 * Copyright © 2017 Storeplugins. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Storeplugins\Paychant\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Customer\Helper\Session\CurrentCustomer;
use Magento\Payment\Helper\Data as PaymentHelper;

class PaychantConfigProvider implements ConfigProviderInterface
{
    /**
     * @var ResolverInterface
     */
    protected $localeResolver;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var \Magento\Customer\Helper\Session\CurrentCustomer
     */
    protected $currentCustomer;

    /**
     * @var \Magento\Payment\Model\Method\AbstractMethod[]
     */
    protected $methods = [];

    /**
     * @var PaymentHelper
     */
    protected $paymentHelper;

    protected $checkoutSession;

    /**
     * @param ConfigFactory $configFactory
     * @param ResolverInterface $localeResolver
     * @param CurrentCustomer $currentCustomer
     * @param PaypalHelper $paypalHelper
     * @param PaymentHelper $paymentHelper
     */
    public function __construct(
        //ConfigFactory $configFactory,
        ResolverInterface $localeResolver,
        CurrentCustomer $currentCustomer,
        \Magento\Checkout\Model\Session $checkoutSession,
        //PaypalHelper $paypalHelper,
        PaymentHelper $paymentHelper
    ) {
        $this->localeResolver = $localeResolver;
        //$this->config = $configFactory->create();
        $this->currentCustomer = $currentCustomer;
        //$this->paypalHelper = $paypalHelper;
        $this->paymentHelper = $paymentHelper;
        $this->checkoutSession = $checkoutSession;

        //foreach ($this->methodCodes as $code) {
            //$this->methods[$code] = $this->paymentHelper->getMethodInstance($code);
        //}
        $code = 'storepluginspaychant';
        $this->methods[$code] = $this->paymentHelper->getMethodInstance($code);
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        $code = 'storepluginspaychant';
        $config = [];

        if ($this->methods[$code]->isAvailable($this->checkoutSession->getQuote())) {
            $config = [];
            $config['payment'] = [];
            $config['payment']['paychant']['redirectUrl'] = [];
            $config['payment']['paychant']['redirectUrl'][$code] = $this->getMethodRedirectUrl($code);
            $config['payment']['paychant']['instructions'] = $this->getInstructions($code);
        }

        return $config;
    }

    /**
     * Return redirect URL for method
     *
     * @param string $code
     * @return mixed
     */
    protected function getMethodRedirectUrl($code)
    {
        return $this->methods[$code]->getOrderPlaceRedirectUrl();
    }

    /**
     * Return redirect URL for method
     *
     * @param string $code
     * @return mixed
     */
    protected function getInstructions($code)
    {
        return $this->methods[$code]->getInstructions();
    }


    /**
     * Return billing agreement code for method
     *
     * @param string $code
     * @return null|string
     */
    /*protected function getBillingAgreementCode($code)
    {
        $customerId = $this->currentCustomer->getCustomerId();
        $this->config->setMethod($code);
        return $this->paypalHelper->shouldAskToCreateBillingAgreement($this->config, $customerId)
            ? Express\Checkout::PAYMENT_INFO_TRANSPORT_BILLING_AGREEMENT : null;
    }*/
}
