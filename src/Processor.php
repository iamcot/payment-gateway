<?php

namespace Iamcot\PaymentGateway;

class Processor
{
    static $processor = null;

    /**
     * 
     * @param string $payment 'momo', 'onepay', 'vnpay'
     * @param array $params ['accessKey', 'secretKey', 'paymentUrl', 'returnUrl', 'notifyUrl', 'partnerCode']
     * @return PaymentInterface
     */
    public static function getProcessor($payment, $params = [])
    {
        if (null != self::$processor) {
            return self::$processor;
        }
        switch ($payment) {
            case Momo::NAME:
                self::$processor = new Momo($params);
                break;
            case Onepay::NAME:
                self::$processor =  new Onepay($params);
                break;
            case Vnpay::NAME:
                self::$processor =  new Vnpay($params);
                break;
            default:
                break;
        }
        return self::$processor;
    }

    public static function generatePaymentToken($orderId, $lenght = 20)
    {
        $randomString = str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ');
        return $orderId ? $orderId . 'Al' . substr($randomString, 0, $lenght) : $orderId;
    }

    public static function getOrderIdFromPaymentToken($paymentToken)
    {
        return stristr($paymentToken, 'Al', true);
    }
}
