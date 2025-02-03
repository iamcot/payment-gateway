<?php

namespace Iamcot\PaymentGateway;

interface PaymentInterface
{

    /**
     * #1. Validate and prepare data for payment instance, require orderId & totalAmount
     * @param array $input
     * @return string|bool
     */
    public function validate($input);

    /**
     * #2. Process to prepare payment page URL 
     * @return ServiceResponse
     */
    public function processPayment();

    /**
     * #3. Process RETURN data from payment gateway
     * @param $response
     * @return ServiceResponse
     */
    public function processReturnData($response);

    /**
     * #4. Process REAL RETURN data of RETURN OR NOTIFY from payment gateway
     * @param $response
     * @param string $from
     * @return array
     */
    public function processFeedbackData($response, $from);

    /**
     * #5. Prepare data for NOTIFY response if required by payment gateway, sometime it justs need to return 'OK'
     * @param array $data
     * @param bool $feedbackResult
     * @return array
     */
    public function prepareNotifyResponse($data, $feedbackResult);
}
