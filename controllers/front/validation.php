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

class ZruValidationModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    /**
     * Server-to-server notification (IPN) sent by Zru.
     *
     * The notification is authenticated by its signature (checkSignature) and,
     * on top of that, status, amount and cart id are always read back from
     * the Zru API using the merchant credentials.
     *
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        if (!$this->module->isPayment()) {
            $this->exitWithResponse(503, 'Module inactive or incomplete configuration.');
        }

        $json = (array) json_decode(Tools::file_get_contents('php://input'));
        if (empty($json['id']) || !is_string($json['id'])) {
            $this->exitWithResponse(400, 'Invalid notification payload.');
        }

        $zru = new ZRU\ZRUClient(Configuration::get('ZRU_KEY'), Configuration::get('ZRU_SECRET_KEY'));
        $notificationData = $zru->NotificationData($json);

        try {
            $validSignature = $notificationData->checkSignature();
        } catch (\Throwable $e) {
            $validSignature = false;
        }
        if (!$validSignature) {
            $this->exitWithResponse(400, 'Invalid notification signature.');
        }

        if (!$notificationData->isTransaction()) {
            $this->exitWithResponse(200, 'Notification ignored: not a transaction.');
        }

        try {
            $zruTransaction = $notificationData->getTransaction();
        } catch (ZRU\ZRUError $e) {
            $this->exitWithResponse(400, 'Unable to retrieve the transaction from Zru.');
        }

        if (!$zruTransaction) {
            $this->exitWithResponse(400, 'Invalid notification, nothing to do.');
        }

        $cart = new Cart((int) $zruTransaction->order_id);
        if (!Validate::isLoadedObject($cart)) {
            $this->exitWithResponse(400, 'Unable to load the cart of this transaction.');
        }

        $customer = new Customer((int) $cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            $this->exitWithResponse(400, 'Unable to load the customer of this transaction.');
        }

        if ($cart->orderExists()) {
            $this->exitWithResponse(200, 'Order already created for this cart.');
        }

        if ($zruTransaction->status !== ZRU\NotificationData::STATUS_DONE) {
            $this->exitWithResponse(200, 'Transaction not paid: no order created.');
        }

        $this->module->validateOrder(
            (int) $cart->id,
            (int) Configuration::get('PS_OS_PAYMENT'),
            (float) $zruTransaction->total_price,
            $this->module->displayName,
            'Zru transaction ID: ' . $zruTransaction->id,
            array('transaction_id' => $zruTransaction->id),
            (int) $cart->id_currency,
            false,
            $customer->secure_key
        );

        $this->exitWithResponse(200, 'OK');
    }

    /**
     * @param int $httpCode
     * @param string $message
     */
    private function exitWithResponse($httpCode, $message)
    {
        http_response_code($httpCode);
        header('Content-Type: text/plain; charset=utf-8');
        exit($message);
    }
}
