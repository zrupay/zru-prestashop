<?php

namespace ZRU;

/**
 * Class to manage notifications from ZRU
 */
class NotificationData 
{
    protected $payload;       // Content of request from ZRU         
    protected $zru;           // ZRUClient

    // Notification types
    const TYPE_TRANSACTION = 'P';
    const TYPE_SUBSCRIPTION = 'S';
    const TYPE_AUTHORIZATION = 'A';

    // Notification statuses
    const STATUS_DONE = 'D';
    const STATUS_CANCELLED = 'C';
    const STATUS_EXPIRED = 'E';
    const STATUS_PENDING = 'N';

    // Subscription statuses
    const SUBSCRIPTION_STATUS_WAIT = 'W';
    const SUBSCRIPTION_STATUS_ACTIVE = 'A';
    const SUBSCRIPTION_STATUS_PAUSED = 'P';
    const SUBSCRIPTION_STATUS_STOPPED = 'S';

    // Authorization statuses
    const AUTHORIZATION_STATUS_ACTIVE = 'A';
    const AUTHORIZATION_STATUS_REMOVED = 'R';

    // Sale actions
    const SALE_GET = 'G';
    const SALE_HOLD = 'H';
    const SALE_VOID = 'V';
    const SALE_CAPTURE = 'C';
    const SALE_REFUND = 'R';
    const SALE_SETTLE = 'S';
    const SALE_ESCROW_REJECTED = 'E';
    const SALE_ERROR = 'I';

    // Signature constants
    const NOTIFICATION_SIGNATURE_PARAM = 'signature';
    const NOTIFICATION_SIGNATURE_IGNORE_FIELDS = ['fail', 'signature'];

    public function __construct($payload, $zru) 
    {
        $this->payload = $payload;
        $this->zru = $zru;        
    }

    // Magic method for dynamic access to payload properties
    public function __get($name)
    {
        switch ($name) {
            case 'transaction':
                return $this->getTransaction();
            case 'subscription':
                return $this->getSubscription();
            case 'authorization':
                return $this->getAuthorization();
            case 'sale':
                return $this->getSale();
            default:
                if (array_key_exists($name, $this->payload)) {
                    return $this->payload[$name];
                }

                $trace = debug_backtrace();
                trigger_error(
                    'Undefined property via __get(): ' . $name .
                    ' in ' . $trace[0]['file'] .
                    ' on line ' . $trace[0]['line'],
                    E_USER_NOTICE);
                return null;
        }
    }

    // Check if notification type is transaction
    public function isTransaction()
    {
        return $this->type === self::TYPE_TRANSACTION;
    }

    // Check if notification type is subscription
    public function isSubscription()
    {
        return $this->type === self::TYPE_SUBSCRIPTION;
    }

    // Check if notification type is authorization
    public function isAuthorization()
    {
        return $this->type === self::TYPE_AUTHORIZATION;
    }

    // Check if status is done
    public function isStatusDone()
    {
        return $this->status === self::STATUS_DONE;
    }

    // Check if status is cancelled
    public function isStatusCancelled()
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    // Check if status is expired
    public function isStatusExpired()
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    // Check if status is pending
    public function isStatusPending()
    {
        return $this->status === self::STATUS_PENDING;
    }

    // Check if subscription status is waiting
    public function isSubscriptionWaiting()
    {
        return $this->subscription_status === self::SUBSCRIPTION_STATUS_WAIT;
    }

    // Check if subscription status is active
    public function isSubscriptionActive()
    {
        return $this->subscription_status === self::SUBSCRIPTION_STATUS_ACTIVE;
    }

    // Check if subscription status is paused
    public function isSubscriptionPaused()
    {
        return $this->subscription_status === self::SUBSCRIPTION_STATUS_PAUSED;
    }

    // Check if subscription status is stopped
    public function isSubscriptionStopped()
    {
        return $this->subscription_status === self::SUBSCRIPTION_STATUS_STOPPED;
    }

    // Check if authorization status is active
    public function isAuthorizationActive()
    {
        return $this->authorization_status === self::AUTHORIZATION_STATUS_ACTIVE;
    }

    // Check if authorization status is removed
    public function isAuthorizationRemoved()
    {
        return $this->authorization_status === self::AUTHORIZATION_STATUS_REMOVED;
    }

    // Check if sale action is get
    public function isSaleGet()
    {
        return $this->sale_action === self::SALE_GET;
    }

    // Check if sale action is hold
    public function isSaleHold()
    {
        return $this->sale_action === self::SALE_HOLD;
    }

    // Check if sale action is void
    public function isSaleVoid()
    {
        return $this->sale_action === self::SALE_VOID;
    }

    // Check if sale action is capture
    public function isSaleCapture()
    {
        return $this->sale_action === self::SALE_CAPTURE;
    }

    // Check if sale action is refund
    public function isSaleRefund()
    {
        return $this->sale_action === self::SALE_REFUND;
    }

    // Check if sale action is settle
    public function isSaleSettle()
    {
        return $this->sale_action === self::SALE_SETTLE;
    }

    // Check if sale action is escrow rejected
    public function isSaleEscrowRejected()
    {
        return $this->sale_action === self::SALE_ESCROW_REJECTED;
    }

    // Check if sale action is error
    public function isSaleError()
    {
        return $this->sale_action === self::SALE_ERROR;
    }


    /**
     * Returns transaction generated when payment was created
     * @return object|null
     */
    public function getTransaction() 
    {
        if ($this->isTransaction()) {
            $transaction = $this->zru->Transaction(['id' => $this->id]);
            $transaction->retrieve();
            return $transaction;
        }
        return null;
    }

    /**
     * Returns subscription generated when payment was created
     * @return object|null
     */
    public function getSubscription() 
    {
        if ($this->isSubscription()) {
            $subscription = $this->zru->Subscription(['id' => $this->id]);
            $subscription->retrieve();
            return $subscription;
        }
        return null;
    }

    /**
     * Returns authorization generated when payment was created
     * @return object|null
     */
    public function getAuthorization() 
    {
        if ($this->isAuthorization()) {
            $authorization = $this->zru->Authorization(['id' => $this->id]);
            $authorization->retrieve();
            return $authorization;
        }
        return null;
    }

    /**
     * Returns sale generated when payment was paid
     * @return object|null
     */
    public function getSale() 
    {
        if (isset($this->payload['sale_id'])) {
            $sale = $this->zru->Sale(['id' => $this->payload['sale_id']]);
            $sale->retrieve();
            return $sale;
        }
        return null;
    }

    // Verifies the signature of the notification
    public function checkSignature()
    {
        $dict_obj = $this->payload;
        $text_to_sign = '';

        $sorted_keys = $this->_getSortedKeys($dict_obj);

        foreach ($sorted_keys as $key) {
            if (in_array($key, self::NOTIFICATION_SIGNATURE_IGNORE_FIELDS)
                || strpos($key, '_') === 0
                || $dict_obj[$key] === null
                || !is_scalar($dict_obj[$key])) {
                continue;
            }
            $text_to_sign .= $this->_cleanValue($dict_obj[$key]);
        }

        if (!isset($dict_obj[self::NOTIFICATION_SIGNATURE_PARAM]) || !is_string($dict_obj[self::NOTIFICATION_SIGNATURE_PARAM])) {
            return false;
        }

        $text_to_sign .= $this->zru->getApiRequest()->getSecret();
        $signature = hash('sha256', $text_to_sign);

        return hash_equals($signature, $dict_obj[self::NOTIFICATION_SIGNATURE_PARAM]);
    }

    private function _getSortedKeys($dict_obj)
    {
        $keys = array_keys($dict_obj);
        sort($keys);
        return $keys;
    }

    private function _cleanValue($value)
    {
        if (is_bool($value)) {
            // Match Python's str(bool).lower()
            $value = $value ? 'true' : 'false';
        } elseif (is_float($value) && floor($value) === $value && abs($value) < PHP_INT_MAX) {
            // Match Python's str(float): whole floats keep one decimal (100.0)
            $value = number_format($value, 1, '.', '');
        }
        $value = (string)$value;
        $chars_to_replace = ['<', '>', '"', "'", '(', ')', '\\'];
        return trim(str_replace($chars_to_replace, ' ', $value));
    }
}