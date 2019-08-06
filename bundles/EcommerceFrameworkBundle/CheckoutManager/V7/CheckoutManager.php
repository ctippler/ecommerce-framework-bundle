<?php
/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Bundle\EcommerceFrameworkBundle\CheckoutManager\V7;


use Pimcore\Bundle\EcommerceFrameworkBundle\CartManager\CartInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\CheckoutManager\CommitOrderProcessorLocatorInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\CheckoutManager\V7\HandlePendingPayments\HandlePendingPaymentsStrategy;
use Pimcore\Bundle\EcommerceFrameworkBundle\EnvironmentInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\Exception\PaymentNotAllowedException;
use Pimcore\Bundle\EcommerceFrameworkBundle\Exception\UnsupportedException;
use Pimcore\Bundle\EcommerceFrameworkBundle\Model\AbstractOrder;
use Pimcore\Bundle\EcommerceFrameworkBundle\OrderManager\OrderManagerLocatorInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\OrderManager\V7\OrderManagerInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\PaymentManager\V7\Payment\PaymentInterface;
use Pimcore\Bundle\EcommerceFrameworkBundle\PaymentManager\V7\Payment\StartPaymentRequest\AbstractRequest;
use Pimcore\Bundle\EcommerceFrameworkBundle\PaymentManager\V7\Payment\StartPaymentResponse\StartPaymentResponseInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CheckoutManager extends \Pimcore\Bundle\EcommerceFrameworkBundle\CheckoutManager\CheckoutManager implements CheckoutManagerInterface
{
    /**
     * @var HandlePendingPaymentsStrategy
     */
    protected $handlePendingPaymentsStrategy = null;

    public function __construct(
        CartInterface $cart,
        EnvironmentInterface $environment,
        OrderManagerLocatorInterface $orderManagers,
        CommitOrderProcessorLocatorInterface $commitOrderProcessors,
        array $checkoutSteps,
        EventDispatcherInterface $eventDispatcher,
        \Pimcore\Bundle\EcommerceFrameworkBundle\PaymentManager\Payment\PaymentInterface $paymentProvider = null
    ) {
        $this->cart = $cart;
        $this->environment = $environment;

        $this->orderManagers = $orderManagers;
        $this->commitOrderProcessors = $commitOrderProcessors;

        $this->payment = $paymentProvider;
        $this->eventDispatcher = $eventDispatcher;

        $this->setCheckoutSteps($checkoutSteps);
    }



    /**
     * @return HandlePendingPaymentsStrategy
     */
    public function getHandlePendingPaymentsStrategy(): HandlePendingPaymentsStrategy
    {
        return $this->handlePendingPaymentsStrategy;
    }

    /**
     * @param HandlePendingPaymentsStrategy $handlePendingPaymentsStrategy
     */
    public function setHandlePendingPaymentsStrategy(HandlePendingPaymentsStrategy $handlePendingPaymentsStrategy): void
    {
        $this->handlePendingPaymentsStrategy = $handlePendingPaymentsStrategy;
    }



    /**
     * @return AbstractOrder
     *
     * @throws UnsupportedException
     */
    protected function checkIfPaymentIsPossible()
    {
        if (!$this->isFinished()) {
            throw new UnsupportedException('Checkout is not finished yet.');
        }

        if (!$this->payment) {
            throw new UnsupportedException('Payment is not activated');
        }

        /**
         * @var $orderManager OrderManagerInterface
         */
        $orderManager = $this->orderManagers->getOrderManager();

        // create order
        $order = $orderManager->getOrderFromCart($this->getCart());

        if($order) {

            $notAllowedOrderStates = [AbstractOrder::ORDER_STATE_ABORTED, AbstractOrder::ORDER_STATE_CANCELLED, AbstractOrder::ORDER_STATE_COMMITTED];
            if(in_array($order->getOrderState(), $notAllowedOrderStates)) {
                throw new PaymentNotAllowedException(
                    "Payment not allowed since orderState set to '" . $order->getOrderState() . "'. Fix orderState or recreate order.",
                    $order,
                    $this->cart,
                    $orderManager->orderNeedsUpdate($this->cart, $order)
                );
            }

            if($orderManager->cartHasPendingPayments($this->cart)) {

                $order = $this->getHandlePendingPaymentsStrategy()->handlePaymentNotAllowed(
                    $order,
                    $this->cart,
                    $orderManager
                );

            }

        }

        $order = $orderManager->getOrCreateOrderFromCart($this->getCart());

        return $order;
    }

    /**
     * @inheritDoc
     */
    public function startOrderPaymentWithPaymentProvider(AbstractRequest $paymentConfig): StartPaymentResponseInterface
    {
        parent::startOrderPayment();

        $cart = $this->getCart();

        /**
         * @var $paymentProvider PaymentInterface
         */
        $paymentProvider = $this->getPayment();

        $orderManager = $this->orderManagers->getOrderManager();
        $orderAgent = $orderManager->createOrderAgent($this->getOrder());

        return $paymentProvider->startPayment($orderAgent, $cart->getPriceCalculator()->getGrandTotal(), $paymentConfig);
    }
}