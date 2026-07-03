<?php
/**
 * zru Module
 *
 * Copyright (c) 2026 Zru
 *
 * @category  Payment
 * @author    Zru, <www.zrupay.com>
 * @copyright 2026, Zru
 * @link      https://www.zrupay.com/
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 *
 * Description:
 *
 * Payment module zru
 *
 * --
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to hola@zrupay.com so we can send you a copy immediately.
 */

require_once dirname(__FILE__) . '/../../lib/ZRU/ZRUClient.php';

class ZruPaymentModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    /**
     * Creates the Zru transaction and redirects the customer to the payment
     * page (or embeds it in an iframe).
     *
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        $this->page_name = 'checkout';

        parent::initContent();

        if (!$this->isTokenValid()) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        if (!$this->module->isPayment()) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $cart = $this->context->cart;
        $customer = new Customer((int) $cart->id_customer);
        $currency = $this->context->currency;
        $language = $this->context->language;

        if (!Validate::isLoadedObject($cart)
            || $cart->nbProducts() <= 0
            || !Validate::isLoadedObject($customer)
            || !Validate::isLoadedObject($currency)
            || !Validate::isLoadedObject($language)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $url = array(
            'notify' => $this->context->link->getModuleLink($this->module->name, 'validation', array(), true),
            'cancel' => $this->context->link->getPageLink('order', true, null, array('step' => '3')),
            'return' => $this->context->link->getPageLink('order-confirmation', true, null, array(
                'id_cart' => (int) $cart->id,
                'id_module' => (int) $this->module->id,
                'key' => $customer->secure_key,
            )),
        );

        $zru = new ZRU\ZRUClient(Configuration::get('ZRU_KEY'), Configuration::get('ZRU_SECRET_KEY'));

        $payload = $this->buildTransactionPayload($cart, $customer, $currency, $language, $url);
        $transaction = $zru->Transaction($payload);

        try {
            $transaction->save();
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                sprintf('Zru: unable to create the transaction (%s)', $e->getMessage()),
                3,
                null,
                'Cart',
                (int) $cart->id
            );
            $this->errors[] = $this->module->l(
                'There was an error while processing the payment. Please try again or choose another payment method.',
                'payment'
            );
            $this->redirectWithNotifications($this->context->link->getPageLink('order', true, null, array('step' => '3')));
        }

        if (Configuration::get('ZRU_IFRAME') == 'active') {
            $this->context->smarty->assign('url', $transaction->getIframeUrl());
            $this->setTemplate('module:zru/views/templates/front/iframe.tpl');
        } else {
            Tools::redirect($transaction->getPayUrl());
        }
    }

    /**
     * Build the Zru transaction payload from the cart: one entry per cart
     * product, plus shipping (and gift wrapping) and cart discounts, so the
     * total computed by Zru matches the cart total exactly.
     *
     * @param Cart $cart
     * @param Customer $customer
     * @param Currency $currency
     * @param Language $language
     * @param array $url return/cancel/notify urls
     * @return array
     */
    private function buildTransactionPayload($cart, $customer, $currency, $language, $url)
    {
        $precision = isset($currency->precision) ? (int) $currency->precision : 2;

        $products = array();
        $itemsTotal = 0.0;
        foreach ($cart->getProducts(true) as $cartProduct) {
            $name = $cartProduct['name'];
            if (!empty($cartProduct['attributes'])) {
                $name .= ' (' . $cartProduct['attributes'] . ')';
            }
            $quantity = (int) $cartProduct['cart_quantity'];
            $unitPrice = Tools::ps_round((float) $cartProduct['price_wt'], $precision);

            $products[] = array(
                'amount' => $quantity,
                'product' => array(
                    'name' => Tools::substr($name, 0, 100),
                    'price' => $unitPrice,
                ),
            );
            $itemsTotal += $unitPrice * $quantity;
        }

        $wrappingValue = Tools::ps_round((float) $cart->getOrderTotal(true, Cart::ONLY_WRAPPING), $precision);
        if ($wrappingValue > 0) {
            $products[] = array(
                'amount' => 1,
                'product' => array(
                    'name' => $this->module->l('Gift wrapping', 'payment'),
                    'price' => $wrappingValue,
                ),
            );
            $itemsTotal += $wrappingValue;
        }

        $shippingValue = Tools::ps_round((float) $cart->getOrderTotal(true, Cart::ONLY_SHIPPING), $precision);
        $couponValue = Tools::ps_round((float) $cart->getOrderTotal(true, Cart::ONLY_DISCOUNTS), $precision);
        $cartTotal = Tools::ps_round((float) $cart->getOrderTotal(), $precision);

        /* Absorb any rounding drift so Zru charges exactly the cart total */
        $residual = Tools::ps_round($cartTotal - ($itemsTotal + $shippingValue - $couponValue), $precision);
        if ($residual > 0) {
            $shippingValue = Tools::ps_round($shippingValue + $residual, $precision);
        } elseif ($residual < 0) {
            $couponValue = Tools::ps_round($couponValue - $residual, $precision);
        }

        $payload = array(
            'order_id' => (string) $cart->id,
            'currency' => $currency->iso_code,
            'return_url' => $url['return'],
            'cancel_url' => $url['cancel'],
            'notify_url' => $url['notify'],
            'language' => $language->iso_code,
            'products' => $products,
            'extra' => array(
                'email' => $customer->email,
                'full_name' => $customer->firstname . ' ' . $customer->lastname,
            ),
        );

        if ($shippingValue > 0) {
            $payload['shipping_value'] = $shippingValue;
            $carrier = new Carrier((int) $cart->id_carrier);
            if (Validate::isLoadedObject($carrier)) {
                $payload['shipping_name'] = $carrier->name;
            }
        }

        if ($couponValue > 0) {
            $payload['coupon_value'] = $couponValue;
            $couponNames = array();
            foreach ($cart->getCartRules() as $cartRule) {
                $couponNames[] = !empty($cartRule['code']) ? $cartRule['code'] : $cartRule['name'];
            }
            if (!empty($couponNames)) {
                $payload['coupon_name'] = Tools::substr(implode(', ', array_unique($couponNames)), 0, 100);
            }
        }

        return $payload;
    }
}
