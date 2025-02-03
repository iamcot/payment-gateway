<?php namespace Iamcot\PaymentGateway;



class Vnpay implements PaymentInterface
{
    const NAME = 'vnpay';
    const SUCCESS_CODE = "00";
    private $amount;
    private $orderId;
    private $ip;
    private $saveToken;
    private $existsToken;
    private $existsTokenExp;
    private $userId;

    private $cfAccessKey;
    private $cfSecretKey;
    private $cfPartnerCode;
    private $cfPaymentUrl;
    private $cfReturnUrl;
    private $cfNotifyUrl;

    public function __construct($params)
    {
        $this->cfAccessKey = isset($params['accessKey']) ? $params['accessKey'] : '';
        $this->cfSecretKey = isset($params['secretKey']) ? $params['secretKey'] : '';
        $this->cfPartnerCode = isset($params['partnerCode']) ? $params['partnerCode'] : '';
        $this->cfReturnUrl = isset($params['returnUrl']) ? $params['returnUrl'] : '';
        $this->cfPaymentUrl = isset($params['paymentUrl']) ? $params['paymentUrl'] : '';
        $this->cfNotifyUrl = isset($params['notifyUrl']) ? $params['notifyUrl'] : '';
    }

    public function validate($input)
    {
        $amount = isset($input['amount']) ? $input['amount'] : 0;
        $orderId = isset($input['orderid']) ? $input['orderid'] : "";
        $ip = isset($input['ip']) ? $input['ip'] : "";
        if (empty($orderId)) {
            $orderId = time();
        }
        if ($amount <= 0 || empty($orderId) || empty($ip)) {
            return 'REQUIRE_AMOUNT_OR_ORDERID_IP';
        }

        $this->amount = $amount * 100; //special rule of vnpay
        $this->orderId = $orderId;
        $this->ip = $ip;

        $this->saveToken = isset($input['save_card']) ? $input['save_card'] : false;
        $this->existsToken = isset($input['token_num']) ? $input['token_num'] : false;
        $this->existsTokenExp = isset($input['token_exp']) ? $input['token_exp'] : false;

        if ($this->saveToken || $this->existsToken) {
            $this->userId = isset($input['user_id']) ? $input['user_id'] : false;
            if ($this->userId === false) {
                return 'REQUIRED_USER_INFO_TO_USE_CARD';
            }
        }
        return true;
    }

    public function processPayment()
    {
        if (empty($this->amount) || empty($this->orderId) || empty($this->ip)) {
            $response = new ServiceResponse(false, 'NOT_VALID_INPUT');
        }
        $response = $this->getPaymentPage();

        if (!$response instanceof ServiceResponse) {
            $response = new ServiceResponse(false, 'NOT_VALID_RESPONSE');
            return $response;
        }

        if (empty($response->data)) {
            $response->status = false;
            $response->errorCode = 'NOT_VALID_DATA';
        }

        return $response;
    }

    public function processReturnData($str)
    {

        $data = $this->processFeedbackData($str, "return");

        return $this->buildUrl($data);
    }

    public function prepareNotifyResponse($response, $feedbackResult)
    {
        $data = [
            'RspCode' => $feedbackResult['errorCode'],
            'Message' => $feedbackResult['message']
        ];
        return $data;
    }

    public function processFeedbackData($str, $from)
    {
        $response = [];
        foreach (explode('&', $str) as $couple) {
            list($key, $val) = explode('=', $couple);
            $response[$key] = $val;
        }
        $data = [
            'status' => 0,
            'message' => '',
            'errorCode' => '',
        ];

        $data = array_merge($data, [
            'orderId' => isset($response['vnp_OrderInfo']) ? $response['vnp_OrderInfo'] : '',
            'amount' => isset($response['vnp_Amount']) ? $response['vnp_Amount'] / 100 : '',
            'transId' => isset($response['vnp_TxnRef']) ? $response['vnp_TxnRef'] : '',
            'payType' => 'web',
            'payment' => self::NAME,
        ]);

        if (!$this->checkHash($response)) {
            $data['message'] = 'Invalid Checksum';
            $data['errorCode'] = '97';
            return $data;
        }


        // Some special case for order process required from VNPAY should be done from user side
        //
        // $order = Order::find($data['transId']);
        // if (!$order) {
        //     $data['message'] = 'Order Not Found';
        //     $data['errorCode'] = '01';
        //     return $data;
        // }

        // if ($data['amount'] != "" && $order->amount != $data['amount']) {
        //     $data['message'] = 'Invalid amount';
        //     $data['errorCode'] = '04';
        //     return $data;
        // }

        // if ($from == "notify") {
        //     if ($order->status != OrderConstants::STATUS_PAY_PENDING) {
        //         $data['message'] = 'Order already confirmed';
        //         $data['errorCode'] = '02';
        //         return $data;
        //     }
        // }

        if (isset($response['vnp_TransactionStatus']) && $response['vnp_TransactionStatus'] == self::SUCCESS_CODE) {
            $data['status'] = 1;
            $data['errorCode'] = '00';
            $data['message'] = 'Confirm Success';
        } else {
            $data['errorCode'] = '00'; //to ack getting response 
            $data['message'] = $this->getTransactionStatus($response['vnp_TransactionStatus']);
        }
        return $data;
    }

    private function buildUrl($data)
    {
        $flatdata = [];
        foreach ($data as $key => $value) {
            $flatdata[] = urlencode($key) . '=' . urlencode($value);
        }
        return $this->cfReturnUrl . '?' . implode("&", $flatdata);
    }

    public function checkHash($input)
    {
        $hash = isset($input['vnp_SecureHash']) ? $input['vnp_SecureHash'] : '';
        ksort($input);
        $stringHashData = "";

        foreach ($input as $key => $value) {
            if ($key != "vnp_SecureHash" && (strlen($value) > 0) && ((substr($key, 0, 4) == "vnp_") || (substr($key, 0, 5) == "user_"))) {
                $stringHashData .= urlencode($key) . "=" . urlencode($value) . "&";
            }
        }
        $stringHashData = rtrim($stringHashData, "&");
        $checkedHash = hash_hmac('sha512', $stringHashData, $this->cfSecretKey);
        if (hash_equals($hash, $checkedHash)) {
            return true;
        }
    }

    private function getPaymentPage()
    {
        try {
            $result = $this->createPaymentRequest();
            $signature = $result['signature'];
            $url = $result['result'];
            return new ServiceResponse(true, 0, $url);
        } catch (\Exception $e) {
            return new ServiceResponse(false, 'EXCEPTION', $e->getMessage());
        }
        return new ServiceResponse(false, "NOT_VALID_RESPONSE");
    }

    private function createPaymentRequest()
    {
        $data =  [
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'pay',
            'vnp_TmnCode' => $this->cfAccessKey,
            'vnp_Amount' => strval($this->amount),
            'vnp_CreateDate' => date('YmdHis'),
            'vnp_CurrCode' => 'VND',
            'vnp_IpAddr' => $this->ip,
            'vnp_Locale' => 'vn',
            'vnp_OrderInfo' => strval($this->orderId),
            'vnp_OrderType' => 'other',
            'vnp_ReturnUrl' => $this->cfReturnUrl,
            'vnp_ExpireDate' => date('YmdHis', strtotime('+30 minutes')),
            'vnp_TxnRef' => $this->orderId,
        ];

        $flatdata = [];
        $hashRawData = [];
        ksort($data);
        foreach ($data as $key => $value) {
            $flatdata[] = urlencode($key) . '=' . urlencode($value);
            if ((strlen($value) > 0) && ((substr($key, 0, 4) == "vnp_") || (substr($key, 0, 5) == "user_"))) {
                $hashRawData[] = $key . "=" . $value;
            }
        }
        $query = implode("&", $flatdata);
        $hashRaw = implode("&", $hashRawData);

        $signature =  hash_hmac('sha512', $query, $this->cfSecretKey);

        $query = $this->getServer() . '?' . $query . '&vnp_SecureHash=' . $signature;
        return [
            'result' => $query,
            'signature' => $signature,
        ];
    }

    private function getServer()
    {
        return  $this->cfPaymentUrl ?? 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html';
    }

    private function getTransactionStatus($responseCode)
    {

        switch ($responseCode) {
            case "00":
                $result = "Giao dịch thành công - Successful Transaction";
                break;
            case "01":
                $result = "Giao dịch chưa hoàn tất - Transaction Pending";
                break;
            case "02":
                $result = "Giao dịch bị lỗi - Transaction failed";
                break;
            case "04":
                $result = "Khách hàng đã bị trừ tiền tại Ngân hàng nhưng GD chưa thành công ở VNPAY";
                break;
            case "05":
                $result = "VNPAY đang xử lí";
                break;
            case "06":
                $result = "VNPAY đã gửi yêu cầu sang ngân hàng";
                break;
            case "07":
                $result = "Giao dịch bị nghi ngờ gian lận";
                break;
            case "09":
                $result = "Giao dịch hoàn bị từ chối";
                break;
            default:
                $result = "Unknown Error";
        }
        return $result;
    }

    private function getResponseStatus($responseCode)
    {

        switch ($responseCode) {
            case "00":
                $result = "Giao dịch thành công - Successful Transaction";
                break;
            case "24":
                $result = "Giao dịch không thành công - Khách huỷ.";
                break;
            case "99":
                $result = "Giao dịch không thành công - Lỗi khác.";
                break;
            default:
                $result = "Giao dịch chưa hoàn tất";
                break;
        }
        return $result;
    }

    private function getOrderProcessStatus($responseCode)
    {

        switch ($responseCode) {
            case "00":
                $result = "Confirm Success.";
                break;
            case "01":
                $result = "Order Not Found.";
                break;
            case "02":
                $result = "Invalid amount.";
                break;
            case "04":
                $result = "Order already confirmed.";
                break;
            case "97":
                $result = "Invalid Checksum.";
                break;
            default:
                $result = "Unknown Error";
                break;
        }
        return $result;
    }
}
